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
        add_action('init', array($this, 'register_module_post_type'));
    }
    
    /**
     * Load all active modules with comprehensive error handling
     */
    public function load_active_modules() {
        if ($this->modules_loaded) {
            return;
        }
        
        $this->modules_loaded = true;
        
        try {
            $db = OC_Database::get_instance();
            
            if (!$db->tables_exist()) {
                return;
            }
            
            $active_modules = $db->get_modules('active');
            
            foreach ($active_modules as $module) {
                $module_file = OC_MODULES_DIR . $module['file_path'];
                
                if (!file_exists($module_file)) {
                    error_log("Onyx Command: Module file not found - {$module_file}");
                    $db->update_module($module['module_id'], array('status' => 'inactive'));
                    continue;
                }
                
                // Check for syntax errors before loading
                $syntax_check = $this->check_file_syntax($module_file);
                if ($syntax_check !== true) {
                    error_log("Onyx Command: Module has syntax error - {$module['module_id']}: {$syntax_check}");
                    $db->update_module($module['module_id'], array('status' => 'inactive'));
                    if (class_exists('OC_Error_Logger')) {
                        OC_Error_Logger::log('error', 'Module syntax error', $syntax_check, array('module_id' => $module['module_id'], 'file' => $module_file));
                    }
                    continue;
                }
                
                // Try to load the module
                try {
                    @include_once $module_file;
                    $this->loaded_modules[] = $module['module_id'];
                    
                    $class_name = $this->get_module_class_name($module['module_id']);
                    
                    if (class_exists($class_name)) {
                        if (method_exists($class_name, 'get_instance')) {
                            try {
                                call_user_func(array($class_name, 'get_instance'));
                            } catch (Throwable $e) {
                                error_log("Onyx Command: Module initialization failed - {$module['module_id']}: " . $e->getMessage());
                                if (class_exists('OC_Error_Logger')) {
                                    OC_Error_Logger::log('error', 'Module initialization failed', $e->getMessage(), array('module_id' => $module['module_id']));
                                }
                                $db->update_module($module['module_id'], array('status' => 'inactive'));
                            }
                        }
                    }
                } catch (Throwable $e) {
                    error_log("Onyx Command: Module loading failed - {$module['module_id']}: " . $e->getMessage());
                    if (class_exists('OC_Error_Logger')) {
                        OC_Error_Logger::log('error', 'Module loading failed', $e->getMessage(), array('module_id' => $module['module_id']));
                    }
                    $db->update_module($module['module_id'], array('status' => 'inactive'));
                }
            }
        } catch (Throwable $e) {
            error_log('Onyx Command: Module loading error - ' . $e->getMessage());
        }
    }
    
    /**
     * Check file for syntax errors
     */
    private function check_file_syntax($file) {
        $output = array();
        $return_var = 0;
        
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
        
        if ($return_var !== 0) {
            return implode("\n", $output);
        }
        
        return true;
    }
    
    private function get_module_class_name($module_id) {
        $parts = explode('-', $module_id);
        $class_parts = array_map('ucfirst', $parts);
        return implode('_', $class_parts);
    }
    
    public function register_module_post_type() {
        // Reserved for future enhancements
    }
    
    public function install_module($file) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('You do not have permission to install modules.', 'onyx-command'));
        }
        
        $sanitize_result = OC_Security::sanitize_upload($file);
        if (is_wp_error($sanitize_result)) {
            return $sanitize_result;
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_ext === 'zip') {
            return $this->install_from_zip($file);
        }
        
        return $this->install_from_php($file);
    }
    
    private function install_from_zip($file) {
        WP_Filesystem();
        global $wp_filesystem;
        
        $temp_dir = OC_MODULES_DIR . 'temp_' . uniqid() . '/';
        wp_mkdir_p($temp_dir);
        
        $unzip_result = unzip_file($file['tmp_name'], $temp_dir);
        
        if (is_wp_error($unzip_result)) {
            $wp_filesystem->rmdir($temp_dir, true);
            return $unzip_result;
        }
        
        $main_file = $this->find_main_file($temp_dir);
        
        if (!$main_file) {
            $wp_filesystem->rmdir($temp_dir, true);
            return new WP_Error('no_main_file', __('No valid module file found in ZIP. Make sure your module has "Module Name:" and "Module ID:" headers.', 'onyx-command'));
        }
        
        $module_info = $this->parse_module_info($main_file);
        
        if (is_wp_error($module_info)) {
            $wp_filesystem->rmdir($temp_dir, true);
            return $module_info;
        }
        
        $conflict_check = OC_Script_Checker::get_instance()->check_conflicts($main_file, $module_info['module_id']);
        
        if (!empty($conflict_check['errors'])) {
            $wp_filesystem->rmdir($temp_dir, true);
            return new WP_Error('conflicts_detected', __('Module conflicts detected.', 'onyx-command'), $conflict_check);
        }
        
        $module_dir = OC_MODULES_DIR . $module_info['module_id'] . '/';
        
        if (file_exists($module_dir)) {
            $wp_filesystem->rmdir($temp_dir, true);
            return new WP_Error('module_exists', __('Module already exists.', 'onyx-command'));
        }
        
        // Check if ZIP has a single root folder
        $temp_contents = scandir($temp_dir);
        $temp_contents = array_diff($temp_contents, array('.', '..'));
        
        if (count($temp_contents) === 1 && is_dir($temp_dir . reset($temp_contents))) {
            // ZIP has a single root folder, use that as the module directory
            $source_dir = $temp_dir . reset($temp_contents) . '/';
            rename($source_dir, $module_dir);
            $wp_filesystem->rmdir($temp_dir, true);
        } else {
            // ZIP contents are at root level, rename temp dir to module dir
            rename($temp_dir, $module_dir);
        }
        
        // Get relative path from module directory
        $relative_path = $module_info['module_id'] . '/' . basename($main_file);
        
        return $this->register_module($module_info, $relative_path);
    }
    
    private function install_from_php($file) {
        $security_warnings = OC_Security::scan_file_content($file['tmp_name']);
        
        if (!empty($security_warnings)) {
            OC_Error_Logger::log('warning', 'Security warnings during upload', implode(', ', $security_warnings));
        }
        
        $module_info = $this->parse_module_info($file['tmp_name']);
        
        if (is_wp_error($module_info)) {
            return $module_info;
        }
        
        $syntax_check = OC_Script_Checker::get_instance()->check_syntax($file['tmp_name']);
        
        if (!empty($syntax_check['errors'])) {
            return new WP_Error('syntax_error', __('Syntax errors detected in module.', 'onyx-command'), $syntax_check);
        }
        
        $conflict_check = OC_Script_Checker::get_instance()->check_conflicts($file['tmp_name'], $module_info['module_id']);
        
        if (!empty($conflict_check['errors'])) {
            return new WP_Error('conflicts_detected', __('Module conflicts detected.', 'onyx-command'), $conflict_check);
        }
        
        $module_dir = OC_MODULES_DIR . $module_info['module_id'] . '/';
        wp_mkdir_p($module_dir);
        
        $new_file_path = $module_dir . sanitize_file_name($file['name']);
        
        if (!move_uploaded_file($file['tmp_name'], $new_file_path)) {
            return new WP_Error('upload_failed', __('Failed to move uploaded file.', 'onyx-command'));
        }
        
        $relative_path = $module_info['module_id'] . '/' . sanitize_file_name($file['name']);
        return $this->register_module($module_info, $relative_path);
    }
    
    /**
     * Find main module file recursively
     */
    private function find_main_file($dir) {
        // First try root level
        $files = glob($dir . '*.php');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $content = file_get_contents($file);
                if (preg_match('/Module\s+Name:/i', $content) && preg_match('/Module\s+ID:/i', $content)) {
                    return $file;
                }
            }
        }
        
        // If not found, search one level deep (for modules in subdirectories)
        $subdirs = glob($dir . '*', GLOB_ONLYDIR);
        
        foreach ($subdirs as $subdir) {
            $files = glob($subdir . '/*.php');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $content = file_get_contents($file);
                    if (preg_match('/Module\s+Name:/i', $content) && preg_match('/Module\s+ID:/i', $content)) {
                        return $file;
                    }
                }
            }
        }
        
        return false;
    }
    
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
    
    public function delete_module($module_id) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module) {
            return new WP_Error('module_not_found', __('Module not found.', 'onyx-command'));
        }
        
        $module_dir = OC_MODULES_DIR . $module_id . '/';
        if (file_exists($module_dir)) {
            WP_Filesystem();
            global $wp_filesystem;
            $wp_filesystem->rmdir($module_dir, true);
        }
        
        $db->delete_module($module_id);
        
        OC_Error_Logger::log('info', 'Module deleted', $module['name'], array('module_id' => $module_id));
        
        return array('success' => true, 'message' => __('Module deleted successfully.', 'onyx-command'));
    }
    
    public function get_active_modules() {
        $db = OC_Database::get_instance();
        return $db->get_modules('active');
    }
    
    public function execute_module($module_id) {
        if (!OC_Security::is_admin_user()) {
            return;
        }
        
        if (isset($this->loaded_modules[$module_id])) {
            return;
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
            
            $db->update_module($module_id, array(
                'last_executed' => current_time('mysql'),
                'execution_count' => $module['execution_count'] + 1
            ));
            
        } catch (Throwable $e) {
            OC_Error_Logger::log('error', 'Module execution failed', $e->getMessage(), array('module_id' => $module_id));
        }
    }
    
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
    
    public function scan_and_register_modules() {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $registered_count = 0;
        $db = OC_Database::get_instance();
        
        $existing_modules = $db->get_modules();
        $existing_ids = array();
        foreach ($existing_modules as $module) {
            $existing_ids[] = $module['module_id'];
        }
        
        $module_dirs = glob(OC_MODULES_DIR . '*', GLOB_ONLYDIR);
        
        foreach ($module_dirs as $module_dir) {
            $main_file = $this->find_main_file($module_dir . '/');
            
            if (!$main_file) {
                continue;
            }
            
            $module_info = $this->parse_module_info($main_file);
            
            if (is_wp_error($module_info)) {
                continue;
            }
            
            if (in_array($module_info['module_id'], $existing_ids)) {
                continue;
            }
            
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
