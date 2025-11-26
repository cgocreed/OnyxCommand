<?php
/**
 * Admin Class
 * Handles the admin interface and dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'check_permissions'));
    }
    
    /**
     * Check user permissions for all admin pages
     */
    public function check_permissions() {
        if (isset($_GET['page']) && strpos($_GET['page'], 'onyx-command') === 0) {
            if (!OC_Security::is_admin_user()) {
                wp_die(__('You do not have permission to access this page.', 'onyx-command'));
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Onyx Command', 'onyx-command'),
            __('Onyx Command', 'onyx-command'),
            'manage_options',
            'onyx-command',
            array($this, 'render_dashboard'),
            'dashicons-admin-plugins',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'onyx-command',
            __('Dashboard', 'onyx-command'),
            __('Dashboard', 'onyx-command'),
            'manage_options',
            'onyx-command',
            array($this, 'render_dashboard')
        );
        
        // Modules submenu
        add_submenu_page(
            'onyx-command',
            __('Modules', 'onyx-command'),
            __('Modules', 'onyx-command'),
            'manage_options',
            'onyx-command-modules',
            array($this, 'render_modules_page')
        );
        
        // Upload Module submenu
        add_submenu_page(
            'onyx-command',
            __('Upload Module', 'onyx-command'),
            __('Upload Module', 'onyx-command'),
            'manage_options',
            'onyx-command-upload',
            array($this, 'render_upload_page')
        );
        
        // Error Logs submenu
        add_submenu_page(
            'onyx-command',
            __('Error Logs', 'onyx-command'),
            __('Error Logs', 'onyx-command'),
            'manage_options',
            'onyx-command-logs',
            array($this, 'render_logs_page')
        );
        
        // Optimizer submenu
        add_submenu_page(
            'onyx-command',
            __('Optimizer', 'onyx-command'),
            __('Optimizer', 'onyx-command'),
            'manage_options',
            'onyx-command-optimizer',
            array($this, 'render_optimizer_page')
        );
        
        // Statistics submenu
        add_submenu_page(
            'onyx-command',
            __('Statistics', 'onyx-command'),
            __('Statistics', 'onyx-command'),
            'manage_options',
            'onyx-command-stats',
            array($this, 'render_statistics_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'onyx-command') === false) {
            return;
        }
        
        wp_enqueue_style('oc-admin-style', OC_ASSETS_DIR . 'css/admin.css', array(), OC_VERSION);
        wp_enqueue_script('oc-admin-script', OC_ASSETS_DIR . 'js/admin.js', array('jquery'), OC_VERSION, true);
        
        wp_localize_script('oc-admin-script', 'ocAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mm_admin_action'),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this module?', 'onyx-command'),
                'confirm_clear_logs' => __('Are you sure you want to clear all logs?', 'onyx-command'),
                'confirm_clean_db' => __('Are you sure you want to clean the database?', 'onyx-command'),
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $stats = OC_Statistics::get_instance()->get_overall_stats();
        $recent_logs = OC_Error_Logger::get_instance()->get_all_logs(array('limit' => 10));
        $suggestions = OC_Optimizer::get_instance()->get_suggestions();
        
        include OC_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    /**
     * Render modules page
     */
    public function render_modules_page() {
        $db = OC_Database::get_instance();
        $modules = $db->get_modules();
        
        include OC_PLUGIN_DIR . 'templates/modules.php';
    }
    
    /**
     * Render upload page
     */
    public function render_upload_page() {
        include OC_PLUGIN_DIR . 'templates/upload.php';
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        
        $filters = array('limit' => 100);
        
        if ($filter !== 'all') {
            $filters['log_type'] = $filter;
        }
        
        $logger = OC_Error_Logger::get_instance();
        $logs = $logger->get_all_logs($filters);
        $stats = $logger->get_statistics(30);
        
        include OC_PLUGIN_DIR . 'templates/logs.php';
    }
    
    /**
     * Render optimizer page
     */
    public function render_optimizer_page() {
        $optimizer = OC_Optimizer::get_instance();
        $suggestions = $optimizer->get_suggestions();
        $db_info = $optimizer->get_database_info();
        
        include OC_PLUGIN_DIR . 'templates/optimizer.php';
    }
    
    /**
     * Render statistics page
     */
    public function render_statistics_page() {
        $stats_manager = OC_Statistics::get_instance();
        $overall_stats = $stats_manager->get_overall_stats();
        $performance = $stats_manager->generate_performance_report(30);
        
        include OC_PLUGIN_DIR . 'templates/statistics.php';
    }
}