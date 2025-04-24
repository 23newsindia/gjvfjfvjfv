<?php
/*
Plugin Name: Advanced Banner Carousel
Plugin URI: https://mellmon.in
Description: Create beautiful banner carousels like The Souled Store with multiple slides
Version: 1.0.0
Requires at least: 5.0
Requires PHP: 7.2
Author: Your Name
Author URI: https://mellmon.in
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: banner-carousel
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('ABC_VERSION', '1.0.0');
define('ABC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ABC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ABC_AJAX_CACHE_TIME', 15 * MINUTE_IN_SECONDS); // Add this line

// Include necessary files
require_once ABC_PLUGIN_DIR . 'includes/class-abc-db.php';
require_once ABC_PLUGIN_DIR . 'includes/class-abc-admin.php';
require_once ABC_PLUGIN_DIR . 'includes/class-abc-frontend.php';
require_once ABC_PLUGIN_DIR . 'includes/class-abc-cache.php';

// Initialize the plugin
// Initialize the plugin
function abc_init() {
    // Initialize admin interface
    if (is_admin()) {
        new ABC_Admin();
    }
    
    // Initialize frontend
    new ABC_Frontend();
    
    // Add cache preloading on init (non-AJAX, non-cron requests)
    add_action('init', function() {
        if (!wp_doing_ajax() && !wp_doing_cron()) {
            ABC_Cache::preload_banners();
        }
    });
}

// Register activation hook
register_activation_hook(__FILE__, array('ABC_DB', 'create_tables'));

// Add uninstall hook
register_uninstall_hook(__FILE__, array('ABC_DB', 'delete_tables'));

// Hook into WordPress init to ensure database is ready
function abc_check_db() {
    ABC_DB::check_tables();
}
add_action('init', 'abc_check_db');

// Initialize plugin after all dependencies are loaded
add_action('plugins_loaded', 'abc_init');