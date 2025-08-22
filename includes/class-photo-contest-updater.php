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
     * Process an RSS item and return an array with the required fields.
     *
     * @since    1.0.0
     * @param    SimpleXMLElement    $item    The RSS item to process.
     * @return   array                        The processed data.
     */
    private function process_rss_item($item) {
        // Get the post ID directly
        $post_id = (string)$item->{'post-id'};

        // Get the URL and extract the slug
        $url = (string)$item->link;
        $slug = basename(parse_url($url, PHP_URL_PATH));

        // Get the image URL from the enclosure
        $enclosure = $item->enclosure;
        $image_url = (string)$enclosure['url'];

        // Get the author from CDATA
        $creator = $item->children('dc', true)->creator;
        $author = (string)$creator;

        // Get the publication date and convert to Unix timestamp
        $pub_date = (string)$item->pubDate;
        $date = strtotime($pub_date);

        // Get the feed modification date and convert to Unix timestamp
        $feed_date = strtotime((string)$item->children('dc', true)->date);

        // Get the description from CDATA
        $description = (string)$item->description;

        return array(
            'id' => $post_id,
            'slug' => $slug,
            'url' => $url,
            'image_url' => $image_url,
            'author' => $author,
            'date' => $date,
            'feed_date' => $feed_date,
            'description' => $description
        );
    }

    /**
     * Check if this is an update request and handle it.
     *
     * @since    1.0.0
     */
    public function check_update_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'update-contest-photos') {
            $hashtag = $this->settings->get_hashtag();
            
            if (empty($hashtag)) {
                echo 'hastag not defined';
                exit;
            }

            // Get the RSS feed for the tag
            $feed_url = 'https://wordpress.org/photos/t/' . urlencode($hashtag) . '/feed/';
            $response = wp_remote_get($feed_url);
            
            if (is_wp_error($response)) {
                echo 'Error getting RSS feed';
                exit;
            }

            $body = wp_remote_retrieve_body($response);
            
            // Parse the RSS feed
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                echo 'Error parsing RSS feed';
                exit;
            }

            // Process all items
            $photos_data = array();
            foreach ($xml->channel->item as $item) {
                $photos_data[] = $this->process_rss_item($item);
            }

            if (empty($photos_data)) {
                echo 'No photos found in feed';
                exit;
            }

            // Process each photo
            foreach ($photos_data as $photo_data) {
                $result = $this->create_or_update_photos_post($photo_data);
                
                if (is_wp_error($result)) {
                    echo 'Photo ' . $photo_data['slug'] . '. Error: ' . $result->get_error_message() . '<br>';
                    continue;
                }

                echo 'Photo ' . $photo_data['slug'] . '. ' . ucfirst($result['status']) . ' (post ID ' . $result['post_id'] . ')<br>';
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