<?php
/**
 * AJAX Handler Class
 * Handles all AJAX requests from the admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Ajax_Handler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Module actions
        add_action('wp_ajax_mm_upload_module', array($this, 'upload_module'));
        add_action('wp_ajax_mm_activate_module', array($this, 'activate_module'));
        add_action('wp_ajax_mm_deactivate_module', array($this, 'deactivate_module'));
        add_action('wp_ajax_mm_delete_module', array($this, 'delete_module'));
        add_action('wp_ajax_mm_scan_module', array($this, 'scan_module'));
        add_action('wp_ajax_mm_scan_modules_directory', array($this, 'scan_modules_directory'));
        add_action('wp_ajax_mm_auto_fix', array($this, 'auto_fix'));
        add_action('wp_ajax_mm_update_config', array($this, 'update_config'));
        
        // Optimizer actions
        add_action('wp_ajax_mm_clear_caches', array($this, 'clear_caches'));
        add_action('wp_ajax_mm_clean_database', array($this, 'clean_database'));
        add_action('wp_ajax_mm_clean_logs', array($this, 'clean_logs'));
        
        // Log actions
        add_action('wp_ajax_mm_resolve_log', array($this, 'resolve_log'));
        add_action('wp_ajax_mm_export_logs', array($this, 'export_logs'));
        add_action('wp_ajax_mm_clear_all_logs', array($this, 'clear_all_logs'));
        
        // Statistics actions
        add_action('wp_ajax_mm_get_module_stats', array($this, 'get_module_stats'));
        add_action('wp_ajax_mm_export_stats', array($this, 'export_stats'));
    }
    
    /**
     * Upload module
     */
    public function upload_module() {
        OC_Security::check_permission();
        
        if (empty($_FILES['module_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded.', 'onyx-command')));
        }
        
        $loader = OC_Module_Loader::get_instance();
        $result = $loader->install_module($_FILES['module_file']);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'data' => $result->get_error_data()
            ));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Activate module
     */
    public function activate_module() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        
        if (empty($module_id)) {
            wp_send_json_error(array('message' => __('Module ID is required.', 'onyx-command')));
        }
        
        $loader = OC_Module_Loader::get_instance();
        $result = $loader->activate_module($module_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Deactivate module
     */
    public function deactivate_module() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        
        if (empty($module_id)) {
            wp_send_json_error(array('message' => __('Module ID is required.', 'onyx-command')));
        }
        
        $loader = OC_Module_Loader::get_instance();
        $result = $loader->deactivate_module($module_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Delete module
     */
    public function delete_module() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        
        if (empty($module_id)) {
            wp_send_json_error(array('message' => __('Module ID is required.', 'onyx-command')));
        }
        
        $loader = OC_Module_Loader::get_instance();
        $result = $loader->delete_module($module_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Scan module
     */
    public function scan_module() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        
        if (empty($module_id)) {
            wp_send_json_error(array('message' => __('Module ID is required.', 'onyx-command')));
        }
        
        $checker = OC_Script_Checker::get_instance();
        $result = $checker->scan_module($module_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Auto fix
     */
    public function auto_fix() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        $fix_action = isset($_POST['fix_action']) ? sanitize_text_field($_POST['fix_action']) : '';
        $fix_data = isset($_POST['fix_data']) ? json_decode(stripslashes($_POST['fix_data']), true) : array();
        
        if (empty($module_id) || empty($fix_action)) {
            wp_send_json_error(array('message' => __('Module ID and fix action are required.', 'onyx-command')));
        }
        
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module) {
            wp_send_json_error(array('message' => __('Module not found.', 'onyx-command')));
        }
        
        $file_path = OC_MODULES_DIR . $module['file_path'];
        
        $checker = OC_Script_Checker::get_instance();
        $result = $checker->auto_fix($file_path, $fix_action, $fix_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Update module configuration
     */
    public function update_config() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        $config = isset($_POST['config']) ? json_decode(stripslashes($_POST['config']), true) : array();
        
        if (empty($module_id)) {
            wp_send_json_error(array('message' => __('Module ID is required.', 'onyx-command')));
        }
        
        $loader = OC_Module_Loader::get_instance();
        $result = $loader->update_config($module_id, $config);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Clear caches
     */
    public function clear_caches() {
        OC_Security::check_permission();
        
        $optimizer = OC_Optimizer::get_instance();
        $result = $optimizer->clear_all_caches();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Clean database
     */
    public function clean_database() {
        OC_Security::check_permission();
        
        $optimizer = OC_Optimizer::get_instance();
        $result = $optimizer->clean_database();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Clean logs
     */
    public function clean_logs() {
        OC_Security::check_permission();
        
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        
        $optimizer = OC_Optimizer::get_instance();
        $result = $optimizer->clean_logs($days);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Resolve log
     */
    public function resolve_log() {
        OC_Security::check_permission();
        
        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
        
        if (empty($log_id)) {
            wp_send_json_error(array('message' => __('Log ID is required.', 'onyx-command')));
        }
        
        $logger = OC_Error_Logger::get_instance();
        $result = $logger->resolve($log_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Export logs
     */
    public function export_logs() {
        OC_Security::check_permission();
        
        $logger = OC_Error_Logger::get_instance();
        $csv = $logger->export_logs();
        
        if (is_wp_error($csv)) {
            wp_send_json_error(array('message' => $csv->get_error_message()));
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="oc-logs-' . date('Y-m-d') . '.csv"');
        echo $csv;
        exit;
    }
    
    /**
     * Clear all logs
     */
    public function clear_all_logs() {
        OC_Security::check_permission();
        
        $logger = OC_Error_Logger::get_instance();
        $result = $logger->clear_all_logs();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get module statistics
     */
    public function get_module_stats() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        
        if (empty($module_id)) {
            wp_send_json_error(array('message' => __('Module ID is required.', 'onyx-command')));
        }
        
        $stats = OC_Statistics::get_instance()->get_module_stats($module_id);
        
        if (is_wp_error($stats)) {
            wp_send_json_error(array('message' => $stats->get_error_message()));
        }
        
        wp_send_json_success($stats);
    }
    
    /**
     * Export statistics
     */
    public function export_stats() {
        OC_Security::check_permission();
        
        $module_id = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : null;
        
        $stats = OC_Statistics::get_instance()->export_stats($module_id);
        
        if (is_wp_error($stats)) {
            wp_send_json_error(array('message' => $stats->get_error_message()));
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $stats['filename'] . '"');
        echo $stats['content'];
        exit;
    }
    
    /**
     * Scan modules directory and register new modules
     */
    public function scan_modules_directory() {
        OC_Security::check_permission();
        
        error_log('ONYX: scan_modules_directory called');
        
        try {
            $loader = OC_Module_Loader::get_instance();
            error_log('ONYX: Module loader instance obtained');
            
            $result = $loader->scan_and_register_modules();
            error_log('ONYX: scan_and_register_modules result: ' . print_r($result, true));
            
            if (is_wp_error($result)) {
                error_log('ONYX: WP_Error detected: ' . $result->get_error_message());
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            error_log('ONYX: Sending success response');
            wp_send_json_success($result);
        } catch (Exception $e) {
            error_log('ONYX: Exception caught: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Exception: ' . $e->getMessage()));
        }
    }
}