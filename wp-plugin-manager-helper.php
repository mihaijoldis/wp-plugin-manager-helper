<?php
/**
 * Plugin Name: WP Plugin Manager Helper
 * Plugin URI: https://github.com/yourusername/wp-plugin-manager-helper
 * Description: Enables bulk plugin installation for the WP Plugin Manager Chrome Extension
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wp-plugin-manager-helper
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPMH_VERSION', '1.0.0');
define('WPMH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPMH_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WPMH_PLUGIN_DIR . 'includes/class-plugin-installer.php';
require_once WPMH_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once WPMH_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    new WPMH_REST_API();
    new WPMH_Ajax_Handler();
});

// Add admin notice for successful activation
add_action('admin_notices', function() {
    if (get_transient('wpmh_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('WP Plugin Manager Helper is now active! Your Chrome extension can now perform bulk plugin installations.', 'wp-plugin-manager-helper'); ?></p>
        </div>
        <?php
        delete_transient('wpmh_activated');
    }
});

// Set transient on activation
register_activation_hook(__FILE__, function() {
    set_transient('wpmh_activated', true, 5);
});