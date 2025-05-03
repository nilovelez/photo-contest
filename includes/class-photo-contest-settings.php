<?php
/**
 * The settings class for the plugin.
 *
 * @since      1.0.0
 * @package    Photo_Contest
 * @subpackage Photo_Contest/includes
 */

class Photo_Contest_Settings {
    private $plugin_name;
    private $version;
    private $option_name = 'photo_contest_settings';
    private $api_url = 'https://wordpress.org/photos/wp-json/wp/v2';

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function register_settings() {
        register_setting(
            'photo_contest_options',
            $this->option_name,
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'photo_contest_main_section',
            __('Photo Contest Settings', 'photo-contest'),
            array($this, 'render_section_info'),
            'photo-contest-settings'
        );

        add_settings_field(
            'hashtag',
            __('Contest Hashtag', 'photo-contest'),
            array($this, 'render_hashtag_field'),
            'photo-contest-settings',
            'photo_contest_main_section'
        );

        add_settings_section(
            'photo_contest_cron',
            'Cron Configuration',
            array($this, 'render_cron_section'),
            'photo-contest-settings'
        );
    }

    public function add_settings_page() {
        add_options_page(
            __('Photo Contest Settings', 'photo-contest'),
            __('Photo Contest', 'photo-contest'),
            'manage_options',
            'photo-contest-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('photo_contest_options');
                do_settings_sections('photo-contest-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_section_info() {
        echo '<p>' . __('Configure the settings for the Photo Contest plugin.', 'photo-contest') . '</p>';
    }

    public function render_cron_section() {
        $site_url = get_site_url();
        $cron_url = add_query_arg('action', 'update-contest-photos', $site_url);
        
        echo '<p>To automatically update contest photos, set up a cron job to call the following URL:</p>';
        echo '<code>' . esc_url($cron_url) . '</code>';
        echo '<p class="description">This URL should be called periodically to keep the photos updated.</p>';
    }

    public function render_hashtag_field() {
        $options = get_option($this->option_name);
        $hashtag = isset($options['photo_contest_tag']) ? $options['photo_contest_tag'] : '';
        ?>
        <input type="text" 
               name="<?php echo esc_attr($this->option_name); ?>[photo_contest_tag]" 
               value="<?php echo esc_attr($hashtag); ?>"
               class="regular-text"
               placeholder="shareyourpride2024">
        <p class="description">
            <?php _e('Enter the hashtag that will be used to identify contest photos. Do not include the # symbol.', 'photo-contest'); ?>
        </p>
        <?php
        if (!empty($hashtag)) {
            $directory_url = 'https://wordpress.org/photos/t/' . urlencode($hashtag) . '/';
            ?>
            <div style="margin-top: 1em;">
                <p class="description">
                    <?php 
                    printf(
                        __('Photos tagged as %s in the WordPress Photo Directory:', 'photo-contest'),
                        '<strong>' . esc_html($hashtag) . '</strong>'
                    );
                    ?>
                </p>
                <p>
                    <a href="<?php echo esc_url($directory_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_url($directory_url); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    private function validate_hashtag($hashtag) {
        // Remove # if present and convert to lowercase
        $hashtag = strtolower(ltrim($hashtag, '#'));

        // First, get the tag ID from the photo-tags taxonomy
        $tag_response = wp_remote_get($this->api_url . '/photo-tags?search=' . urlencode($hashtag));
        
        if (is_wp_error($tag_response)) {
            return new WP_Error('api_error', __('Error connecting to WordPress Photo Directory API.', 'photo-contest'));
        }

        $tag_body = wp_remote_retrieve_body($tag_response);
        $tag_data = json_decode($tag_body, true);

        if (!is_array($tag_data) || empty($tag_data)) {
            return new WP_Error('invalid_hashtag', __('This hashtag does not exist in the WordPress Photo Directory.', 'photo-contest'));
        }

        // Find the exact tag match
        $tag_id = null;
        foreach ($tag_data as $tag) {
            if (is_array($tag) && isset($tag['name']) && strtolower($tag['name']) === $hashtag) {
                $tag_id = $tag['id'];
                break;
            }
        }

        if (!$tag_id) {
            return new WP_Error('invalid_hashtag', __('This hashtag does not exist in the WordPress Photo Directory.', 'photo-contest'));
        }

        // Now check if there are photos with this tag
        $photos_response = wp_remote_get($this->api_url . '/photos?photo-tags=' . $tag_id);
        
        if (is_wp_error($photos_response)) {
            return new WP_Error('api_error', __('Error connecting to WordPress Photo Directory API.', 'photo-contest'));
        }

        $photos_body = wp_remote_retrieve_body($photos_response);
        $photos_data = json_decode($photos_body, true);

        if (!is_array($photos_data) || empty($photos_data)) {
            return new WP_Error('invalid_hashtag', __('No photos found with this hashtag in the WordPress Photo Directory.', 'photo-contest'));
        }

        return array(
            'photo_contest_tag' => $hashtag,
            'photo_contest_tag_id' => $tag_id
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['photo_contest_tag'])) {
            $validation_result = $this->validate_hashtag($input['photo_contest_tag']);
            
            if (is_wp_error($validation_result)) {
                add_settings_error(
                    $this->option_name,
                    'invalid_hashtag',
                    $validation_result->get_error_message(),
                    'error'
                );
                return get_option($this->option_name);
            }
            
            $sanitized['photo_contest_tag'] = $validation_result['photo_contest_tag'];
            $sanitized['photo_contest_tag_id'] = $validation_result['photo_contest_tag_id'];
        }

        return $sanitized;
    }

    public function get_hashtag() {
        $options = get_option($this->option_name);
        return isset($options['photo_contest_tag']) ? $options['photo_contest_tag'] : '';
    }

    public function get_tag_id() {
        $options = get_option($this->option_name);
        return isset($options['photo_contest_tag_id']) ? $options['photo_contest_tag_id'] : '';
    }
} 