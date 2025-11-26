<?php
/**
 * Module Loader Class
 * Handles module installation, activation, deactivation, and execution
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Module_Loader {
    
    private static $instance = null;
    private $loaded_modules = array();
    private $modules_loaded = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Don't load modules in constructor - defer to later hook
        add_action('init', array($this, 'register_module_post_type'));
    }
    
    /**
     * Load all active modules
     * This is called from the main plugin class after all components are initialized
     */
    public function load_active_modules() {
        // Prevent loading modules multiple times
        if ($this->modules_loaded) {
            return;
        }
        
        $this->modules_loaded = true;
        
        $db = OC_Database::get_instance();
        
        // Check if tables exist before querying
        if (!$db->tables_exist()) {
            return;
        }
        
        $active_modules = $db->get_modules('active');
        
        foreach ($active_modules as $module) {
            $module_file = OC_MODULES_DIR . $module['file_path'];
            
            if (file_exists($module_file)) {
                require_once $module_file;
                
                // Track that this module was loaded
                $this->loaded_modules[] = $module['module_id'];
                
                // Try to initialize the module class if it follows naming convention
                $class_name = $this->get_module_class_name($module['module_id']);
                
                if (class_exists($class_name)) {
                    if (method_exists($class_name, 'get_instance')) {
                        call_user_func(array($class_name, 'get_instance'));
                    }
                }
            }
        }
    }
    
    /**
     * Convert module ID to class name
     * e.g., ai-alt-tag-manager -> AI_Alt_Tag_Manager
     */
    private function get_module_class_name($module_id) {
        // Split by hyphen
        $parts = explode('-', $module_id);
        
        // Capitalize each part and join with underscore
        $class_parts = array_map('ucfirst', $parts);
        
        return implode('_', $class_parts);
    }
    
    /**
     * Register custom post type for module documentation (optional)
     */
    public function register_module_post_type() {
        // Reserved for future enhancements
    }
    
    /**
     * Upload and install module
     */
    public function install_module($file) {
        // Verify user permissions
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('You do not have permission to install modules.', 'onyx-command'));
        }
        
        // Sanitize upload
        $sanitize_result = OC_Security::sanitize_upload($file);
        if (is_wp_error($sanitize_result)) {
            return $sanitize_result;
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Handle ZIP files
        if ($file_ext === 'zip') {
            return $this->install_from_zip($file);
        }
        
        // Handle PHP files
        return $this->install_from_php($file);
    }
    
    /**
     * Install module from ZIP file
     */
    private function install_from_zip($file) {
        WP_Filesystem();
        global $wp_filesystem;
        
        // Create temporary directory
        $temp_dir = OC_MODULES_DIR . 'temp_' . uniqid() . '/';
        wp_mkdir_p($temp_dir);
        
        // Unzip file
        $unzip_result = unzip_file($file['tmp_name'], $temp_dir);
        
        if (is_wp_error($unzip_result)) {
            $wp_filesystem->rmdir($temp_dir, true);
            return $unzip_result;
        }
        
        // Find main PHP file
        $main_file = $this->find_main_file($temp_dir);
        
        if (!$main_file) {
            $wp_filesystem->rmdir($temp_dir, true);
            return new WP_Error('no_main_file', __('No valid module file found in ZIP.', 'onyx-command'));
        }
        
        // Get module info
        $module_info = $this->parse_module_info($main_file);
        
        if (is_wp_error($module_info)) {
            $wp_filesystem->rmdir($temp_dir, true);
            return $module_info;
        }
        
        // Check for conflicts
        $conflict_check = OC_Script_Checker::get_instance()->check_conflicts($main_file, $module_info['module_id']);
        
        if (!empty($conflict_check['errors'])) {
            $wp_filesystem->rmdir($temp_dir, true);
            return new WP_Error('conflicts_detected', __('Module conflicts detected.', 'onyx-command'), $conflict_check);
        }
        
        // Move to permanent location
        $module_dir = OC_MODULES_DIR . $module_info['module_id'] . '/';
        
        if (file_exists($module_dir)) {
            $wp_filesystem->rmdir($temp_dir, true);
            return new WP_Error('module_exists', __('Module already exists.', 'onyx-command'));
        }
        
        rename($temp_dir, $module_dir);
        
        // Register in database
        $relative_path = str_replace(OC_MODULES_DIR, '', $main_file);
        return $this->register_module($module_info, $module_info['module_id'] . '/' . basename($main_file));
    }
    
    /**
     * Install module from PHP file
     */
    private function install_from_php($file) {
        // Scan file for malicious code
        $security_warnings = OC_Security::scan_file_content($file['tmp_name']);
        
        if (!empty($security_warnings)) {
            OC_Error_Logger::log('warning', 'Security warnings during upload', implode(', ', $security_warnings));
        }
        
        // Parse module info
        $module_info = $this->parse_module_info($file['tmp_name']);
        
        if (is_wp_error($module_info)) {
            return $module_info;
        }
        
        // Check for syntax errors
        $syntax_check = OC_Script_Checker::get_instance()->check_syntax($file['tmp_name']);
        
        if (!empty($syntax_check['errors'])) {
            return new WP_Error('syntax_error', __('Syntax errors detected in module.', 'onyx-command'), $syntax_check);
        }
        
        // Check for conflicts
        $conflict_check = OC_Script_Checker::get_instance()->check_conflicts($file['tmp_name'], $module_info['module_id']);
        
        if (!empty($conflict_check['errors'])) {
            return new WP_Error('conflicts_detected', __('Module conflicts detected.', 'onyx-command'), $conflict_check);
        }
        
        // Create module directory
        $module_dir = OC_MODULES_DIR . $module_info['module_id'] . '/';
        wp_mkdir_p($module_dir);
        
        // Move file to module directory
        $new_file_path = $module_dir . sanitize_file_name($file['name']);
        
        if (!move_uploaded_file($file['tmp_name'], $new_file_path)) {
            return new WP_Error('upload_failed', __('Failed to move uploaded file.', 'onyx-command'));
        }
        
        // Register in database
        $relative_path = $module_info['module_id'] . '/' . sanitize_file_name($file['name']);
        return $this->register_module($module_info, $relative_path);
    }
    
    /**
     * Find main module file in directory
     */
    private function find_main_file($dir) {
        $files = glob($dir . '*.php');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/Module Name:/i', $content)) {
                return $file;
            }
        }
        
        return false;
    }
    
    /**
     * Parse module information from file headers
     */
    private function parse_module_info($file_path) {
        $content = file_get_contents($file_path);
        
        $headers = array(
            'module_id' => 'Module ID',
            'name' => 'Module Name',
            'description' => 'Description',
            'version' => 'Version',
            'author' => 'Author'
        );
        
        $module_info = array();
        
        foreach ($headers as $key => $header) {
            if (preg_match('/' . preg_quote($header, '/') . ':\s*(.+)/i', $content, $matches)) {
                $module_info[$key] = trim($matches[1]);
            }
        }
        
        if (empty($module_info['module_id']) || empty($module_info['name'])) {
            return new WP_Error('invalid_module', __('Invalid module file. Missing required headers (Module ID and Module Name).', 'onyx-command'));
        }
        
        return $module_info;
    }
    
    /**
     * Register module in database
     */
    private function register_module($module_info, $file_path) {
        $db = OC_Database::get_instance();
        
        $data = array(
            'module_id' => $module_info['module_id'],
            'name' => $module_info['name'],
            'description' => isset($module_info['description']) ? $module_info['description'] : '',
            'version' => isset($module_info['version']) ? $module_info['version'] : '1.0.0',
            'author' => isset($module_info['author']) ? $module_info['author'] : '',
            'file_path' => $file_path,
            'status' => 'inactive'
        );
        
        $result = $db->insert_module($data);
        
        if ($result) {
            OC_Error_Logger::log('info', 'Module installed', $module_info['name'], array('module_id' => $module_info['module_id']));
            return array('success' => true, 'module_id' => $module_info['module_id'], 'message' => __('Module installed successfully.', 'onyx-command'));
        }
        
        return new WP_Error('db_error', __('Failed to register module in database.', 'onyx-command'));
    }
    
    /**
     * Activate module
     */
    public function activate_module($module_id) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module) {
            return new WP_Error('module_not_found', __('Module not found.', 'onyx-command'));
        }
        
        $result = $db->update_module($module_id, array('status' => 'active'));
        
        if ($result !== false) {
            OC_Error_Logger::log('info', 'Module activated', $module['name'], array('module_id' => $module_id));
            return array('success' => true, 'message' => __('Module activated successfully.', 'onyx-command'));
        }
        
        return new WP_Error('activation_failed', __('Failed to activate module.', 'onyx-command'));
    }
    
    /**
     * Deactivate module
     */
    public function deactivate_module($module_id) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $db = OC_Database::get_instance();
        $result = $db->update_module($module_id, array('status' => 'inactive'));
        
        if ($result !== false) {
            OC_Error_Logger::log('info', 'Module deactivated', '', array('module_id' => $module_id));
            return array('success' => true, 'message' => __('Module deactivated successfully.', 'onyx-command'));
        }
        
        return new WP_Error('deactivation_failed', __('Failed to deactivate module.', 'onyx-command'));
    }
    
    /**
     * Delete module
     */
    public function delete_module($module_id) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module) {
            return new WP_Error('module_not_found', __('Module not found.', 'onyx-command'));
        }
        
        // Delete files
        $module_dir = OC_MODULES_DIR . $module_id . '/';
        if (file_exists($module_dir)) {
            WP_Filesystem();
            global $wp_filesystem;
            $wp_filesystem->rmdir($module_dir, true);
        }
        
        // Delete from database
        $db->delete_module($module_id);
        
        OC_Error_Logger::log('info', 'Module deleted', $module['name'], array('module_id' => $module_id));
        
        return array('success' => true, 'message' => __('Module deleted successfully.', 'onyx-command'));
    }
    
    /**
     * Get active modules
     */
    public function get_active_modules() {
        $db = OC_Database::get_instance();
        return $db->get_modules('active');
    }
    
    /**
     * Execute module
     */
    public function execute_module($module_id) {
        if (!OC_Security::is_admin_user()) {
            return;
        }
        
        if (isset($this->loaded_modules[$module_id])) {
            return; // Already loaded
        }
        
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module || $module['status'] !== 'active') {
            return;
        }
        
        $file_path = OC_MODULES_DIR . $module['file_path'];
        
        if (!file_exists($file_path)) {
            OC_Error_Logger::log('error', 'Module file not found', $module['name'], array('file_path' => $file_path));
            return;
        }
        
        try {
            include_once $file_path;
            $this->loaded_modules[$module_id] = true;
            
            // Update execution stats
            $db->update_module($module_id, array(
                'last_executed' => current_time('mysql'),
                'execution_count' => $module['execution_count'] + 1
            ));
            
        } catch (Exception $e) {
            OC_Error_Logger::log('error', 'Module execution failed', $e->getMessage(), array('module_id' => $module_id));
        }
    }
    
    /**
     * Update module configuration
     */
    public function update_config($module_id, $config) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $sanitized_config = OC_Security::sanitize_config($config);
        
        $db = OC_Database::get_instance();
        $result = $db->update_module($module_id, array('config' => $sanitized_config));
        
        if ($result !== false) {
            return array('success' => true, 'message' => __('Configuration updated successfully.', 'onyx-command'));
        }
        
        return new WP_Error('update_failed', __('Failed to update configuration.', 'onyx-command'));
    }
    
    /**
     * Scan modules directory and auto-register unregistered modules
     */
    public function scan_and_register_modules() {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $registered_count = 0;
        $db = OC_Database::get_instance();
        
        // Get all existing module IDs from database
        $existing_modules = $db->get_modules();
        $existing_ids = array();
        foreach ($existing_modules as $module) {
            $existing_ids[] = $module['module_id'];
        }
        
        // Scan modules directory
        $module_dirs = glob(OC_MODULES_DIR . '*', GLOB_ONLYDIR);
        
        foreach ($module_dirs as $module_dir) {
            // Find main PHP file in this directory
            $main_file = $this->find_main_file($module_dir . '/');
            
            if (!$main_file) {
                continue;
            }
            
            // Parse module info
            $module_info = $this->parse_module_info($main_file);
            
            if (is_wp_error($module_info)) {
                continue;
            }
            
            // Check if already registered
            if (in_array($module_info['module_id'], $existing_ids)) {
                continue;
            }
            
            // Register this module
            $dir_name = basename($module_dir);
            $file_name = basename($main_file);
            $relative_path = $dir_name . '/' . $file_name;
            
            $result = $this->register_module($module_info, $relative_path);
            
            if (!is_wp_error($result)) {
                $registered_count++;
            }
        }
        
        return array(
            'success' => true,
            'registered_count' => $registered_count,
            'message' => sprintf(__('Scanned modules directory. Registered %d new module(s).', 'onyx-command'), $registered_count)
        );
    }
}
