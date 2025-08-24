<?php
/**
 * Class Photo_Contest_Voting
 *
 * Handles all voting functionality for the photo contest
 */
class Photo_Contest_Voting {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize voting functionality
     */
    public function init() {
        // Add AJAX handlers
        add_action('wp_ajax_submit_vote', array($this, 'handle_vote_submission'));
        add_action('wp_ajax_nopriv_submit_vote', array($this, 'handle_vote_submission'));
        add_action('wp_ajax_handle_photo_tagging', array($this, 'handle_photo_tagging'));
        add_action('wp_ajax_nopriv_handle_photo_tagging', array($this, 'handle_photo_tagging'));
        
        // Add scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register shortcodes
        add_shortcode('vote_photos', array($this, 'render_voting_interface'));
        add_shortcode('vote_results', array($this, 'render_results_table'));
        add_shortcode('authors_report', array($this, 'render_authors_report'));
    }

    /**
     * Render voting interface
     */
    public function render_voting_interface() {
        if (!is_user_logged_in()) {
            ob_start();
            $this->show_login_message();
            return ob_get_clean();
        }

        // Get random photo that user hasn't voted for
        $photo = $this->get_random_unvoted_photo();
        if (!$photo) {
            ob_start();
            $this->show_no_more_photos();
            return ob_get_clean();
        }

        ob_start();
        $this->show_voting_interface($photo);
        return ob_get_clean();
    }

    /**
     * Show login required message
     */
    private function show_login_message() {
        ?>
        <div class="photo-contest-login-message">
            <h2><?php _e('Login Required', 'photo-contest'); ?></h2>
            <p><?php _e('You need to be logged in to vote for photos.', 'photo-contest'); ?></p>
            <a href="<?php echo wp_login_url(home_url('/vote/')); ?>" class="button">
                <?php _e('Login', 'photo-contest'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Show no more photos message
     */
    private function show_no_more_photos() {
        ?>
        <div class="photo-contest-no-more-photos">
            <h2><?php _e('No More Photos to Vote', 'photo-contest'); ?></h2>
            <p><?php _e('You have voted for all available photos. Thank you for participating!', 'photo-contest'); ?></p>
        </div>
        <?php
    }

    /**
     * Show voting interface
     */
    private function show_voting_interface($photo) {
        ?>
        <div class="photo-contest-voting" data-photo-id="<?php echo esc_attr($photo->ID); ?>">
            <div class="photo-contest-voting-header">   
                <h2><?php _e('Vote for this Photo', 'photo-contest'); ?></h2>
                <button class="disqualify-button" data-photo-id="<?php echo esc_attr($photo->ID); ?>">
                    <?php _e('Disqualify this photo', 'photo-contest'); ?>
                </button>
            </div>
            <?php 
            $image_url = get_post_meta($photo->ID, 'photo_image_url', true);
            if (!empty($image_url)) {
                // Get the excerpt or generate one from content
                $excerpt = get_the_excerpt($photo->ID);
                if (empty($excerpt)) {
                    $excerpt = wp_trim_words($photo->post_content, 20, '...');
                }
                
                echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($photo->post_title) . '" title="' . esc_attr($excerpt) . '" style="max-width: 100%; height: auto;">';
            }
            ?>
            <div class="voting-buttons">
                <button class="vote-button" data-value="1">
                    <img src="<?php echo PHOTO_CONTEST_PLUGIN_URL; ?>assets/img/face_1.png" alt="1">
                </button>
                <button class="vote-button" data-value="2">
                    <img src="<?php echo PHOTO_CONTEST_PLUGIN_URL; ?>assets/img/face_2.png" alt="2">
                </button>
                <button class="vote-button" data-value="3">
                    <img src="<?php echo PHOTO_CONTEST_PLUGIN_URL; ?>assets/img/face_3.png" alt="3">
                </button>
                <button class="vote-button" data-value="4">
                    <img src="<?php echo PHOTO_CONTEST_PLUGIN_URL; ?>assets/img/face_4.png" alt="4">
                </button>
                <button class="vote-button" data-value="5">
                    <img src="<?php echo PHOTO_CONTEST_PLUGIN_URL; ?>assets/img/face_5.png" alt="5">
                </button>
            </div>
            <div class="remaining-photos">
                <?php
                $remaining = $this->get_remaining_photos_count();
                printf(
                    __('You have %d photos left to vote', 'photo-contest'),
                    $remaining
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get random unvoted photo
     */
    private function get_random_unvoted_photo() {
        $user_id = get_current_user_id();
        $args = array(
            'post_type' => 'photos',
            'posts_per_page' => 1,
            'orderby' => 'rand',
            'meta_query' => array(
                array(
                    'key' => '_photo_vote_' . $user_id,
                    'compare' => 'NOT EXISTS'
                )
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'photo_tag',
                    'field' => 'slug',
                    'terms' => 'disqualified',
                    'operator' => 'NOT IN'
                )
            )
        );

        $photos = get_posts($args);
        return !empty($photos) ? $photos[0] : false;
    }

    /**
     * Get count of remaining photos to vote
     */
    private function get_remaining_photos_count() {
        $user_id = get_current_user_id();
        $args = array(
            'post_type' => 'photos',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_photo_vote_' . $user_id,
                    'compare' => 'NOT EXISTS'
                )
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'photo_tag',
                    'field' => 'slug',
                    'terms' => 'disqualified',
                    'operator' => 'NOT IN'
                )
            )
        );

        $photos = get_posts($args);
        return count($photos);
    }

    /**
     * Handle vote submission
     */
    public function handle_vote_submission() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
        $vote_value = isset($_POST['vote_value']) ? intval($_POST['vote_value']) : 0;

        if (!$photo_id || !$vote_value || $vote_value < 1 || $vote_value > 5) {
            wp_send_json_error('Invalid parameters');
        }

        $user_id = get_current_user_id();
        $vote_key = '_photo_vote_' . $user_id;

        // Check if user has already voted for this photo
        if (get_post_meta($photo_id, $vote_key, true)) {
            wp_send_json_error('Already voted for this photo');
        }

        // Get points based on vote value
        $points = $this->get_points_for_vote($vote_value);

        // Save user's vote
        update_post_meta($photo_id, $vote_key, $points);

        // Update average
        $this->update_photo_average($photo_id);

        // Get next photo
        $next_photo = $this->get_random_unvoted_photo();

        wp_send_json_success(array(
            'next_photo' => $next_photo ? $next_photo->ID : 0,
            'remaining' => $this->get_remaining_photos_count()
        ));
    }

    /**
     * Get points for vote value
     */
    private function get_points_for_vote($value) {
        $points = array(
            1 => 0,
            2 => 3,
            3 => 5,
            4 => 7,
            5 => 10
        );
        return isset($points[$value]) ? $points[$value] : 0;
    }

    /**
     * Update photo average
     */
    private function update_photo_average($photo_id) {
        global $wpdb;

        // Get all votes for this photo
        $votes = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_value FROM $wpdb->postmeta 
            WHERE post_id = %d AND meta_key LIKE '_photo_vote_%'",
            $photo_id
        ));

        if (empty($votes)) {
            return;
        }

        // Calculate average
        $total = array_sum($votes);
        $count = count($votes);
        $average = $total / $count;

        // Update average
        update_post_meta($photo_id, '_photo_vote_average', $average);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        if (has_shortcode(get_post()->post_content, 'vote_photos') || has_shortcode(get_post()->post_content, 'vote_results')) {
            wp_enqueue_script(
                'photo-contest-voting',
                PHOTO_CONTEST_PLUGIN_URL . 'assets/js/voting.js',
                array('jquery'),
                PHOTO_CONTEST_VERSION,
                true
            );

            wp_localize_script('photo-contest-voting', 'photoContestVoting', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('photo_contest_vote'),
                'i18n' => array(
                    'voteSuccess' => __('Vote submitted successfully', 'photo-contest'),
                    'voteError' => __('Error submitting vote', 'photo-contest'),
                    'alreadyVoted' => __('You have already voted for this photo', 'photo-contest'),
                    'noMorePhotos' => __('No More Photos to Vote', 'photo-contest'),
                    'thankYou' => __('Thank you for participating!', 'photo-contest')
                ),
            ));

            wp_enqueue_style(
                'photo-contest-voting',
                PHOTO_CONTEST_PLUGIN_URL . 'assets/css/voting.css',
                array(),
                PHOTO_CONTEST_VERSION
            );
        }
    }

    /**
     * Render results table
     */
    public function render_results_table() {
        $args = array(
            'post_type' => 'photos',
            'posts_per_page' => 10,
            'meta_key' => '_photo_vote_average',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_photo_vote_average',
                    'compare' => 'EXISTS'
                )
            )
        );

        $photos = get_posts($args);
        
        if (empty($photos)) {
            return '<p>' . __('No votes yet', 'photo-contest') . '</p>';
        }

        ob_start();
        ?>
        <div class="photo-contest-results">
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Position', 'photo-contest'); ?></th>
                        <th><?php _e('Photo', 'photo-contest'); ?></th>
                        <th><?php _e('Title', 'photo-contest'); ?></th>
                        <th><?php _e('Author', 'photo-contest'); ?></th>
                        <th><?php _e('Average Score', 'photo-contest'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($photos as $index => $photo): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <?php 
                                $thumbnail_url = get_post_meta($photo->ID, 'photo_thumbnail_url', true);
                                if (!empty($thumbnail_url)) {
                                    echo '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($photo->post_title) . '" style="width: 100px; height: auto;">';
                                }
                                ?>
                            </td>
                            <td><a href="<?php echo esc_url(get_post_meta($photo->ID, 'photo_url', true)); ?>"><?php echo esc_html($photo->post_title); ?></a></td>
                            <td><?php echo esc_html(get_post_meta($photo->ID, 'photo_author', true)); ?></td>
                            <td><?php echo number_format(get_post_meta($photo->ID, '_photo_vote_average', true), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle photo tagging (generic function for adding tags to photos)
     */
    public function handle_photo_tagging() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }

        $photo_id = isset($_POST['photo_id']) ? intval($_POST['photo_id']) : 0;
        $tag_name = isset($_POST['tag_name']) ? sanitize_text_field($_POST['tag_name']) : '';

        if (!$photo_id || !$tag_name) {
            wp_send_json_error('Invalid parameters');
        }

        // Check if photo exists
        $photo = get_post($photo_id);
        if (!$photo || $photo->post_type !== 'photos') {
            wp_send_json_error('Photo not found');
        }

        // Add the tag to the photo
        $result = wp_set_object_terms($photo_id, $tag_name, 'photo_tag', true);

        if (is_wp_error($result)) {
            wp_send_json_error('Error adding tag: ' . $result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => 'Tag added successfully',
            'photo_id' => $photo_id,
            'tag_name' => $tag_name
        ));
    }

    /**
     * Render authors report
     */
    public function render_authors_report() {
        // Get all photos
        $args = array(
            'post_type' => 'photos',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );

        $photos = get_posts($args);
        
        if (empty($photos)) {
            return '<p>' . __('No photos found', 'photo-contest') . '</p>';
        }

        // Group photos by author and count accepted/rejected
        $authors_photos = array();
        $authors_stats = array();
        
        foreach ($photos as $photo) {
            $author = get_post_meta($photo->ID, 'photo_author', true);
            if (!empty($author)) {
                if (!isset($authors_photos[$author])) {
                    $authors_photos[$author] = array();
                    $authors_stats[$author] = array(
                        'total' => 0,
                        'accepted' => 0,
                        'rejected' => 0
                    );
                }
                $authors_photos[$author][] = $photo;
                $authors_stats[$author]['total']++;
                
                // Check if photo is disqualified
                $terms = wp_get_object_terms($photo->ID, 'photo_tag');
                $is_disqualified = false;
                foreach ($terms as $term) {
                    if ($term->slug === 'disqualified') {
                        $is_disqualified = true;
                        break;
                    }
                }
                
                if ($is_disqualified) {
                    $authors_stats[$author]['rejected']++;
                } else {
                    $authors_stats[$author]['accepted']++;
                }
            }
        }

        // Sort authors by number of photos (descending)
        uasort($authors_photos, function($a, $b) {
            return count($b) - count($a);
        });

        if (empty($authors_photos)) {
            return '<p>' . __('No authors found', 'photo-contest') . '</p>';
        }

        ob_start();
        ?>
        <div class="photo-contest-authors-report">
            <?php foreach ($authors_photos as $author => $author_photos): ?>
                <div class="author-section">
                    <h3 class="author-name">
                        <?php echo esc_html($author); ?> 
                        <span class="photo-count">(
                            <?php echo $authors_stats[$author]['total']; ?> <?php echo $authors_stats[$author]['total'] === 1 ? __('photo', 'photo-contest') : __('photos', 'photo-contest'); ?>, 
                            <?php echo $authors_stats[$author]['accepted']; ?> <?php echo __('accepted', 'photo-contest'); ?>, 
                            <?php echo $authors_stats[$author]['rejected']; ?> <?php echo __('rejected', 'photo-contest'); ?>
                        )</span>
                    </h3>
                    <div class="author-photos-grid">
                                                 <?php foreach ($author_photos as $photo): ?>
                             <?php 
                             // Check if photo is disqualified
                             $terms = wp_get_object_terms($photo->ID, 'photo_tag');
                             $is_disqualified = false;
                             foreach ($terms as $term) {
                                 if ($term->slug === 'disqualified') {
                                     $is_disqualified = true;
                                     break;
                                 }
                             }
                             
                             $thumbnail_class = 'photo-thumbnail';
                             if ($is_disqualified) {
                                 $thumbnail_class .= ' rejected-photo';
                             }
                             ?>
                             <div class="<?php echo esc_attr($thumbnail_class); ?>">
                                 <?php 
                                 $thumbnail_url = get_post_meta($photo->ID, 'photo_thumbnail_url', true);
                                 $photo_url = get_post_meta($photo->ID, 'photo_url', true);
                                 
                                 if (!empty($thumbnail_url)) {
                                     if (!empty($photo_url)) {
                                         echo '<a href="' . esc_url($photo_url) . '" target="_blank">';
                                     }
                                     echo '<img src="' . esc_url($thumbnail_url) . '" alt="' . esc_attr($photo->post_title) . '" title="' . esc_attr($photo->post_title) . '">';
                                     if (!empty($photo_url)) {
                                         echo '</a>';
                                     }
                                 } else {
                                     echo '<div class="no-thumbnail">' . esc_html($photo->post_title) . '</div>';
                                 }
                                 ?>
                             </div>
                         <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <style>
        .photo-contest-authors-report {
            max-width: 100%;
        }
        
        .author-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .author-name {
            margin: 0 0 15px 0;
            font-size: 1.5em;
            color: #333;
        }
        
        .photo-count {
            font-size: 0.8em;
            color: #666;
            font-weight: normal;
        }
        
        .author-photos-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-start;
        }
        
        .photo-thumbnail {
            flex: 0 0 auto;
        }
        
        .photo-thumbnail img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .photo-thumbnail img:hover {
            transform: scale(1.05);
        }
        
        .photo-thumbnail a {
            text-decoration: none;
        }
        
                 .no-thumbnail {
             width: 100px;
             height: 100px;
             background: #f0f0f0;
             border: 2px solid #ddd;
             border-radius: 4px;
             display: flex;
             align-items: center;
             justify-content: center;
             text-align: center;
             font-size: 0.8em;
             color: #666;
             word-break: break-word;
         }
         
         .rejected-photo {
             opacity: 0.25;
         }
        </style>
        <?php
        return ob_get_clean();
    }
} 