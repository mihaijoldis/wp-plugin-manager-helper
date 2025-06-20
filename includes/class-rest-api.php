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
    }
    
    /**
     * Check permissions
     */
    public function check_permissions() {
        return current_user_can('install_plugins');
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
}
