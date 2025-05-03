<?php
/**
 * The main plugin class.
 *
 * @since      1.0.0
 * @package    Photo_Contest
 * @subpackage Photo_Contest/includes
 */

class Photo_Contest {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Photo_Contest_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * The post types object.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Photo_Contest_Post_Types    $post_types    The post types object.
     */
    protected $post_types;

    /**
     * The settings object.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Photo_Contest_Settings    $settings    The settings object.
     */
    protected $settings;

    /**
     * The updater object.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Photo_Contest_Updater    $updater    The updater object.
     */
    protected $updater;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'photo-contest';
        $this->version = PHOTO_CONTEST_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->register_update_hook();
    }

    /**
     * Register the custom endpoint for updating contest photos
     *
     * @since    1.0.0
     */
    private function register_update_hook() {
        add_action('init', array($this->updater, 'check_update_request'));
    }

    private function load_dependencies() {
        require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest-post-types.php';
        require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest-settings.php';
        require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest-updater.php';
        
        $this->post_types = new Photo_Contest_Post_Types($this->plugin_name, $this->version);
        $this->settings = new Photo_Contest_Settings($this->plugin_name, $this->version);
        $this->updater = new Photo_Contest_Updater($this->settings);
    }

    private function define_admin_hooks() {
        add_action('init', array($this->post_types, 'register_photo_post_type'));
        
        // Settings hooks
        add_action('admin_menu', array($this->settings, 'add_settings_page'));
        add_action('admin_init', array($this->settings, 'register_settings'));
    }

    private function define_public_hooks() {
        // Here we will define the hooks for the public area
    }

    public function run() {
        // Here we will run the plugin
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }

    /**
     * The code that runs during plugin activation.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
} 