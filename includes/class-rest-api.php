<?php
/**
 * REST API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMH_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'wp-plugin-manager/v1';
        
        // Install endpoint
        register_rest_route($namespace, '/install', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'install_plugins'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'string'
                    )
                ),
                'activate' => array(
                    'type' => 'boolean',
                    'default' => false
                )
            )
        ));
        
        // Status endpoint
        register_rest_route($namespace, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_status'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Plugin status endpoint
        register_rest_route($namespace, '/plugins-status', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'get_plugins_status'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array'
                )
            )
        ));
    
        // Installed plugins endpoint
        register_rest_route($namespace, '/installed-plugins', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_installed_plugins'),
            'permission_callback' => array($this, 'check_permissions_simple')
        ));
        
        // Deactivate and delete plugins endpoint
        register_rest_route($namespace, '/deactivate-delete', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'deactivate_delete_plugins'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'string'
                    )
                )
            )
        ));
        
    }
    
    /**
     * Check permissions
     */
    public function check_permissions($request) {
        // Debug logging
        error_log('[WPM Helper] Permission check - User: ' . get_current_user_id());
        error_log('[WPM Helper] Permission check - Can install plugins: ' . (current_user_can('install_plugins') ? 'yes' : 'no'));
        error_log('[WPM Helper] Permission check - Method: ' . $request->get_method());
        
        // Check if user has the required capability
        if (!current_user_can('install_plugins')) {
            error_log('[WPM Helper] Permission denied: User cannot install plugins');
            return false;
        }
        
        // For POST requests, verify nonce using WordPress REST API method
        if ($request->get_method() === 'POST') {
            $nonce = $request->get_header('X-WP-Nonce');
            error_log('[WPM Helper] Permission check - Nonce received: ' . ($nonce ? 'yes' : 'no'));
            
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                error_log('[WPM Helper] Permission denied: Invalid nonce');
                return false;
            }
            error_log('[WPM Helper] Permission check - Nonce valid: yes');
        }
        
        error_log('[WPM Helper] Permission granted');
        return true;
    }
    
    /**
     * Simple permission check for test endpoint
     */
    public function check_permissions_simple() {
        // Debug logging
        error_log('[WPM Helper] Simple permission check - User ID: ' . get_current_user_id());
        error_log('[WPM Helper] Simple permission check - User logged in: ' . (is_user_logged_in() ? 'yes' : 'no'));
        error_log('[WPM Helper] Simple permission check - Can install plugins: ' . (current_user_can('install_plugins') ? 'yes' : 'no'));
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            error_log('[WPM Helper] Simple permission denied: User not logged in');
            return false;
        }
        
        // Check capability
        if (!current_user_can('install_plugins')) {
            error_log('[WPM Helper] Simple permission denied: User cannot install plugins');
            return false;
        }
        
        error_log('[WPM Helper] Simple permission granted');
        return true;
    }
    
    /**
     * Install plugins endpoint
     */
    public function install_plugins($request) {
        $plugins = $request->get_param('plugins');
        $activate = $request->get_param('activate');
        
        $results = WPMH_Plugin_Installer::install_plugins($plugins, $activate);
        
        // Process results for better response format
        $installed = array();
        $errors = array();
        $summary = array(
            'total' => count($plugins),
            'installed' => 0,
            'activated' => 0,
            'already_installed' => 0,
            'already_active' => 0,
            'errors' => 0
        );
        
        foreach ($results as $result) {
            switch ($result['status']) {
                case 'installed':
                case 'installed_activated':
                    $installed[] = $result['slug'];
                    $summary['installed']++;
                    if ($result['status'] === 'installed_activated') {
                        $summary['activated']++;
                    }
                    break;
                case 'activated':
                    $summary['activated']++;
                    break;
                case 'already_installed':
                    $summary['already_installed']++;
                    break;
                case 'already_active':
                    $summary['already_active']++;
                    break;
                default:
                    $errors[] = array(
                        'slug' => $result['slug'],
                        'message' => $result['message']
                    );
                    $summary['errors']++;
                    break;
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'installed' => $installed,
            'errors' => $errors,
            'summary' => $summary,
            'results' => $results,
            'timestamp' => current_time('mysql')
        ), 200);
    }
    
    /**
     * Get status endpoint
     */
    public function get_status() {
        return new WP_REST_Response(array(
            'success' => true,
            'version' => WPMH_VERSION,
            'rest_enabled' => true,
            'capabilities' => array(
                'install_plugins' => current_user_can('install_plugins'),
                'activate_plugins' => current_user_can('activate_plugins'),
                'update_plugins' => current_user_can('update_plugins')
            ),
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ), 200);
    }
    
    /**
     * Get plugins status
     */
    public function get_plugins_status($request) {
        $slugs = $request->get_param('plugins');
        $results = WPMH_Plugin_Installer::get_plugins_status($slugs);
        
        return new WP_REST_Response(array(
            'success' => true,
            'plugins' => $results
        ), 200);
    }
    
    /**
     * Get installed plugins
     */
    public function get_installed_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();
        
        foreach ($installed_plugins as $plugin_file => $plugin_data) {
            // Extract slug from plugin file path
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }
            
            $plugin_list[] = array(
                'slug' => $slug,
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'url' => $plugin_data['PluginURI'],
                'isActive' => in_array($plugin_file, $active_plugins),
                'isDeactivated' => !in_array($plugin_file, $active_plugins)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'plugins' => $plugin_list
        ), 200);
    }
    
    /**
     * Deactivate and delete plugins
     */
    public function deactivate_delete_plugins($request) {
        $plugins = $request->get_param('plugins');
        
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $results = array();
        $summary = array(
            'total' => count($plugins),
            'deactivated' => 0,
            'deleted' => 0,
            'errors' => 0
        );
        
        foreach ($plugins as $plugin_slug) {
            $result = array(
                'slug' => $plugin_slug,
                'status' => 'unknown',
                'message' => ''
            );
            
            try {
                // Find the plugin file
                $plugin_file = null;
                $installed_plugins = get_plugins();
                
                foreach ($installed_plugins as $file => $plugin_data) {
                    $slug = dirname($file);
                    if ($slug === '.') {
                        $slug = basename($file, '.php');
                    }
                    
                    if ($slug === $plugin_slug) {
                        $plugin_file = $file;
                        break;
                    }
                }
                
                if (!$plugin_file) {
                    $result['status'] = 'not_found';
                    $result['message'] = 'Plugin not found';
                    $summary['errors']++;
                    $results[] = $result;
                    continue;
                }
                
                // Check if plugin is active
                $is_active = is_plugin_active($plugin_file);
                
                // Deactivate if active
                if ($is_active) {
                    deactivate_plugins($plugin_file);
                    $summary['deactivated']++;
                    $result['message'] .= 'Deactivated. ';
                }
                
                // Delete plugin files manually
                $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
                $deleted = $this->delete_plugin_directory($plugin_dir);
                
                if ($deleted) {
                    $result['status'] = 'deleted';
                    $result['message'] .= 'Deleted successfully.';
                    $summary['deleted']++;
                } else {
                    $result['status'] = 'delete_failed';
                    $result['message'] .= 'Failed to delete plugin files.';
                    $summary['errors']++;
                }
                
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['message'] = 'Error: ' . $e->getMessage();
                $summary['errors']++;
            }
            
            $results[] = $result;
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'results' => $results,
            'summary' => $summary
        ), 200);
    }
    
    /**
     * Recursively delete a directory and its contents
     */
    private function delete_plugin_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->delete_plugin_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}
