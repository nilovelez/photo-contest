<?php
/**
 * The updater class for the plugin.
 *
 * @since      1.0.0
 * @package    Photo_Contest
 * @subpackage Photo_Contest/includes
 */

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

class Photo_Contest_Updater {

    /**
     * The settings object.
     *
     * @since    1.0.0
     * @access   private
     * @var      Photo_Contest_Settings    $settings    The settings object.
     */
    private $settings;

    /**
     * The API URL for the WordPress Photo Directory.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $api_url    The API URL.
     */
    private $api_url = 'https://wordpress.org/photos/wp-json/wp/v2';

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    Photo_Contest_Settings    $settings    The settings object.
     */
    public function __construct($settings) {
        $this->settings = $settings;
    }

    /**
     * Process a photo from the REST API and return an array with the required fields.
     *
     * @since    1.0.0
     * @param    array    $photo    The photo data from REST API.
     * @return   array              The processed data.
     */
    private function process_api_photo($photo) {
        // Get the post ID
        $post_id = $photo['id'];

        // Get the slug
        $slug = $photo['slug'];

        // Get the URL
        $url = $photo['link'];

        // Get the image URL from featured media (prefer smaller thumbnail for performance)
        $image_url = '';
        if (isset($photo['_embedded']['wp:featuredmedia'][0])) {
            $media = $photo['_embedded']['wp:featuredmedia'][0];
            
            // First try to get the 1536x1536 thumbnail (much smaller than original)
            if (isset($media['media_details']['sizes']['1536x1536']['source_url'])) {
                $image_url = $media['media_details']['sizes']['1536x1536']['source_url'];
            }
            // Fallback to original size if thumbnail not available
            elseif (isset($media['source_url'])) {
                $image_url = $media['source_url'];
            }
        }

        // Get the author
        $author = '';
        if (isset($photo['_embedded']['author'][0]['name'])) {
            $author = $photo['_embedded']['author'][0]['name'];
        }

        // Get the publication date
        $date = strtotime($photo['date']);

        // Get the modified date
        $modified_date = strtotime($photo['modified']);

        // Get the description/content
        $description = wp_strip_all_tags($photo['content']['rendered']);

        return array(
            'id' => $post_id,
            'slug' => $slug,
            'url' => $url,
            'image_url' => $image_url,
            'author' => $author,
            'date' => $date,
            'feed_date' => $modified_date,
            'description' => $description
        );
    }

    /**
     * Get all photos from the REST API for a specific tag ID.
     *
     * @since    1.0.0
     * @param    int    $tag_id    The tag ID to get photos for.
     * @return   array|WP_Error    Array with photos and total count, or WP_Error on failure.
     */
    private function get_photos_from_api($tag_id) {
        $photos = array();
        $page = 1;
        $per_page = 100; // Maximum allowed by WordPress REST API
        $total_photos = 0;

        do {
            $api_url = add_query_arg(array(
                'photo-tags' => $tag_id,
                'page' => $page,
                'per_page' => $per_page,
                '_embed' => 1 // Include featured media and author data
            ), $this->api_url . '/photos');

            //echo "Calling API URL: " . $api_url . "<br>";

            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'user-agent' => 'WordPress/Photo-Contest-Plugin',
                'headers' => array(
                    'Accept' => 'application/json'
                )
            ));
            
            if (is_wp_error($response)) {
                echo "WP Error: " . $response->get_error_message() . "<br>";
                return new WP_Error('api_error', 'Error connecting to WordPress Photo Directory API: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            //echo "Response code: " . $response_code . "<br>";
            
            if ($response_code !== 200) {
                echo "HTTP Error: " . $response_code . "<br>";
                return new WP_Error('api_error', 'HTTP Error ' . $response_code . ' from WordPress Photo Directory API.');
            }

            $body = wp_remote_retrieve_body($response);
            //echo "Response body length: " . strlen($body) . " characters<br>";
            
            $data = json_decode($body, true);

            if (!is_array($data)) {
                echo "JSON decode error: " . json_last_error_msg() . "<br>";
                echo "First 500 chars of response: " . substr($body, 0, 500) . "<br>";
                return new WP_Error('api_error', 'Invalid response from WordPress Photo Directory API. JSON decode failed.');
            }

            echo "Found " . count($data) . " photos on page " . $page . "<br>";

            // Get total photos count from headers
            if ($page === 1) {
                $total_photos = wp_remote_retrieve_header($response, 'X-WP-Total');
                echo "Total photos available: " . $total_photos . "<br>";
            }

            // Add all photos from this page
            foreach ($data as $photo) {
                $photos[] = $this->process_api_photo($photo);
            }

            // Check if there are more pages
            $total_pages = wp_remote_retrieve_header($response, 'X-WP-TotalPages');
            echo "Total pages: " . $total_pages . "<br>";
            
            $page++;
        } while ($page <= $total_pages);

        return array(
            'photos' => $photos,
            'total_photos' => $total_photos
        );
    }

    /**
     * Check if this is an update request and handle it.
     *
     * @since    1.0.0
     */
    public function check_update_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'update-contest-photos') {
            $tag_id = $this->settings->get_tag_id();
            
            if (empty($tag_id)) {
                echo 'Tag ID not defined. Please configure the hashtag in settings.';
                exit;
            }

            // Get all photos from the REST API
            $api_result = $this->get_photos_from_api($tag_id);
            
            if (is_wp_error($api_result)) {
                echo 'Error: ' . $api_result->get_error_message();
                exit;
            }

            $photos_data = $api_result['photos'];
            $total_photos = $api_result['total_photos'];

            if (empty($photos_data)) {
                echo 'No photos found with this hashtag.';
                exit;
            }

            echo 'Found ' . count($photos_data) . ' photos total. Processing up to 50 new/updated photos...<br><br>';

            $processed_count = 0;
            $limit = 50;

            // Process each photo until we reach the limit or process all
            foreach ($photos_data as $photo_data) {
                $result = $this->create_or_update_photos_post($photo_data);
                
                if (is_wp_error($result)) {
                    echo 'Photo ' . $photo_data['slug'] . '. Error: ' . $result->get_error_message() . '<br>';
                    continue;
                }

                echo 'Photo ' . $photo_data['slug'] . '. ' . ucfirst($result['status']) . ' (post ID ' . $result['post_id'] . ')<br>';
                
                // Count only created or updated photos (not skipped)
                if ($result['status'] === 'created' || $result['status'] === 'updated') {
                    $processed_count++;
                    
                    // Stop when we reach the limit
                    if ($processed_count >= $limit) {
                        echo '<br><strong>⚠️ LIMIT REACHED:</strong> Processed ' . $limit . ' new/updated photos. ';
                        echo 'Run the update again to continue with remaining photos.<br>';
                        break;
                    }
                }
            }

            // Show completion message
            if ($processed_count < $limit) {
                echo '<br><strong>✅ COMPLETED:</strong> All photos have been processed. ';
                echo 'Total new/updated: ' . $processed_count . '<br>';
            }
            exit;
        }
    }

    /**
     * Create or update a photo post
     *
     * @param array $photo_data The photo data to create/update
     * @return array|WP_Error The result of the operation
     */
    private function create_or_update_photos_post($photo_data) {
        // Check if post exists by slug
        $existing_post = get_page_by_path($photo_data['slug'], OBJECT, 'photos');
        
        // If post exists, check if it needs to be updated
        if ($existing_post) {
            $last_feed_update = get_post_meta($existing_post->ID, 'photo_feed_date', true);
            
            // If the feed date is the same, skip the update
            if ($last_feed_update == $photo_data['feed_date']) {
                return array('status' => 'skipped', 'post_id' => $existing_post->ID);
            }
        }
        
        $post_data = array(
            'post_title'    => $photo_data['slug'],
            'post_content'  => $photo_data['description'],
            'post_status'   => 'publish',
            'post_type'     => 'photos',
            'post_name'     => $photo_data['slug'],
            'post_date'     => date('Y-m-d H:i:s', $photo_data['date'])
        );

        if ($existing_post) {
            // Update existing post
            $post_data['ID'] = $existing_post->ID;
            $post_id = wp_update_post($post_data, true);

            // Check if post has featured image
            if (!has_post_thumbnail($post_id)) {
                // Download the image and add it to the media library
                $image_id = media_sideload_image($photo_data['image_url'], $post_id, null, 'id');

                if (!is_wp_error($image_id)) {
                    // Assign the image as the featured image of the post
                    set_post_thumbnail($post_id, $image_id);
                }
            }

            if (!is_wp_error($post_id)) {
                // Update custom fields
                update_post_meta($post_id, 'photo_id', $photo_data['id']);
                update_post_meta($post_id, 'photo_url', $photo_data['url']);
                update_post_meta($post_id, 'photo_image_url', $photo_data['image_url']);
                update_post_meta($post_id, 'photo_author', $photo_data['author']);
                update_post_meta($post_id, 'photo_date', $photo_data['date']);
                update_post_meta($post_id, 'photo_feed_date', $photo_data['feed_date']);
                return array('status' => 'updated', 'post_id' => $post_id);
            }
        } else {
            // Create new post
            $post_id = wp_insert_post($post_data, true);

            // Download the image and add it to the media library
            $image_id = media_sideload_image($photo_data['image_url'], $post_id, null, 'id');

            if (!is_wp_error($image_id)) {
                // Assign the image as the featured image of the post
                set_post_thumbnail($post_id, $image_id);
            }

            if (!is_wp_error($post_id)) {
                // Update custom fields
                update_post_meta($post_id, 'photo_id', $photo_data['id']);
                update_post_meta($post_id, 'photo_url', $photo_data['url']);
                update_post_meta($post_id, 'photo_image_url', $photo_data['image_url']);
                update_post_meta($post_id, 'photo_author', $photo_data['author']);
                update_post_meta($post_id, 'photo_date', $photo_data['date']);
                update_post_meta($post_id, 'photo_feed_date', $photo_data['feed_date']);
                return array('status' => 'created', 'post_id' => $post_id);
            }
        }

        return new WP_Error('update_failed', 'Failed to create or update photo');
    }
}