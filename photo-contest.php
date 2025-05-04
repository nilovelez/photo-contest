<?php
/**
 * Plugin Name: WordPress Photo Directory Photo Contest
 * Plugin URI: https://www.nilovelez.com/photo-contest/
 * Description: A WordPress plugin to manage photo contests
 * Version: 1.0.0
 * Author: Nilo Vélez
 * Author URI: https://www.nilovelez.com/
 * Text Domain: photo-contest
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('PHOTO_CONTEST_VERSION', '1.0.0');
define('PHOTO_CONTEST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PHOTO_CONTEST_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest.php';
require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest-settings.php';
require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest-post-types.php';
require_once PHOTO_CONTEST_PLUGIN_DIR . 'includes/class-photo-contest-voting.php';

// Initialize the plugin
function run_photo_contest() {
    $plugin = new Photo_Contest();
    $plugin->run();

    $settings = new Photo_Contest_Settings('photo-contest', '1.0.0');
    $hashtag = $settings->get_hashtag();

    // Initialize voting
    $voting = new Photo_Contest_Voting();
}
run_photo_contest();

// Register activation hook
register_activation_hook(__FILE__, array('Photo_Contest', 'activate')); 