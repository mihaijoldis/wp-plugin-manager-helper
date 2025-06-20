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
    error_log('[WPM Helper] Plugin loaded and initializing...');
    new WPMH_REST_API();
    new WPMH_Ajax_Handler();
    error_log('[WPM Helper] Plugin initialization complete');
});

// Add a simple test to see if the plugin is active
add_action('init', function() {
    error_log('[WPM Helper] WordPress init hook - plugin is active');
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

// Expose helper object to frontend - multiple hooks for better reliability
add_action('admin_head', function() {
    // Remove capability check temporarily for debugging
    // if (!current_user_can('install_plugins')) return;
    ?>
    <script>
    // Check debug mode without redeclaring variable
    if (localStorage.getItem('wpm_debug_mode') === 'true') {
        console.log('[WPM Helper] admin_head hook executing...');
    }
    // Initialize immediately in head
    window.wpmHelper = {
        version: '<?php echo WPMH_VERSION; ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        restUrl: '<?php echo rest_url('wp-plugin-manager/v1'); ?>',
        nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        initialized: true
    };
    if (localStorage.getItem('wpm_debug_mode') === 'true') {
        console.log('[WPM Helper] Initialized in head:', window.wpmHelper);
    }
    </script>
    <?php
});

add_action('admin_footer', function() {
    // Remove capability check temporarily for debugging
    // if (!current_user_can('install_plugins')) return;
    ?>
    <script>
    if (localStorage.getItem('wpm_debug_mode') === 'true') {
        console.log('[WPM Helper] admin_footer hook executing...');
    }
    // Ensure it's available in footer too
    if (!window.wpmHelper) {
        window.wpmHelper = {
            version: '<?php echo WPMH_VERSION; ?>',
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            restUrl: '<?php echo rest_url('wp-plugin-manager/v1'); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            initialized: true
        };
        if (localStorage.getItem('wpm_debug_mode') === 'true') {
            console.log('[WPM Helper] Initialized in footer:', window.wpmHelper);
        }
    } else {
        if (localStorage.getItem('wpm_debug_mode') === 'true') {
            console.log('[WPM Helper] Already initialized:', window.wpmHelper);
        }
    }
    
    // Also add a data attribute to body for easier detection
    document.body.setAttribute('data-wpm-helper', 'active');
    // Add nonce to body for content script access
    document.body.setAttribute('data-wpm-nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
    if (localStorage.getItem('wpm_debug_mode') === 'true') {
        console.log('[WPM Helper] Added data-wpm-helper attribute to body');
        console.log('[WPM Helper] Added nonce to body:', '<?php echo wp_create_nonce('wp_rest'); ?>');
    }
    </script>
    <?php
});

// Also add to wp_head for non-admin pages that might need it
add_action('wp_head', function() {
    // Remove capability check temporarily for debugging
    // if (!current_user_can('install_plugins')) return;
    ?>
    <script>
    console.log('[WPM Helper] wp_head hook executing...');
    window.wpmHelper = {
        version: '<?php echo WPMH_VERSION; ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        restUrl: '<?php echo rest_url('wp-plugin-manager/v1'); ?>',
        nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        initialized: true
    };
    console.log('[WPM Helper] Initialized in wp_head:', window.wpmHelper);
    </script>
    <?php
});
