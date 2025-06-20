<?php
/**
 * AJAX Handler for when REST API is disabled
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMH_Ajax_Handler {
    
    public function __construct() {
        add_action('wp_ajax_wpmh_install_plugins', array($this, 'ajax_install_plugins'));
        add_action('wp_ajax_wpmh_get_status', array($this, 'ajax_get_status'));
        add_action('admin_footer', array($this, 'add_ajax_data'));
    }
    
    /**
     * AJAX install handler
     */
    public function ajax_install_plugins() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpmh_ajax_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check permissions
        if (!current_user_can('install_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $plugins = json_decode(stripslashes($_POST['plugins']), true);
        $activate = isset($_POST['activate']) && $_POST['activate'] === '1';
        
        if (!is_array($plugins)) {
            wp_send_json_error('Invalid plugin data');
        }
        
        $results = WPMH_Plugin_Installer::install_plugins($plugins, $activate);
        
        wp_send_json_success(array(
            'results' => $results,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * AJAX status handler
     */
    public function ajax_get_status() {
        if (!current_user_can('install_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        wp_send_json_success(array(
            'version' => WPMH_VERSION,
            'ajax_enabled' => true,
            'nonce' => wp_create_nonce('wpmh_ajax_nonce')
        ));
    }
    
    /**
     * Add AJAX data to admin pages
     */
    public function add_ajax_data() {
        if (!current_user_can('install_plugins')) {
            return;
        }
        ?>
        <script>
        window.wpmHelper = {
            version: '<?php echo WPMH_VERSION; ?>',
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('wpmh_ajax_nonce'); ?>',
            restUrl: '<?php echo rest_url('wp-plugin-manager/v1'); ?>',
            restNonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
        };
        </script>
        <?php
    }
}
