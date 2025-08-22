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
        
        // Add scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register shortcodes
        add_shortcode('vote_photos', array($this, 'render_voting_interface'));
        add_shortcode('vote_results', array($this, 'render_results_table'));
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
            <h2><?php _e('Vote for this Photo', 'photo-contest'); ?></h2>
            <?php echo get_the_post_thumbnail($photo->ID, 'large'); ?>
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
                            <td><?php echo get_the_post_thumbnail($photo->ID, 'thumbnail'); ?></td>
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
} 