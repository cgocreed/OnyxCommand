<?php
/**
 * Script Checker Class
 * Checks modules for syntax errors and conflicts with plugins/themes
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Script_Checker {
    
    private static $instance = null;
    
    // WordPress core functions that should be excluded from conflict detection
    private $wp_core_functions = array(
        'add_action', 'remove_action', 'do_action', 'has_action',
        'add_filter', 'remove_filter', 'apply_filters', 'has_filter',
        'add_menu_page', 'add_submenu_page', 'add_options_page', 'add_theme_page',
        'add_plugins_page', 'add_users_page', 'add_management_page', 'add_media_page',
        'register_post_type', 'register_taxonomy', 'register_widget', 'register_sidebar',
        'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style',
        'get_option', 'update_option', 'delete_option', 'add_option',
        'get_post_meta', 'update_post_meta', 'delete_post_meta', 'add_post_meta',
        'wp_insert_post', 'wp_update_post', 'wp_delete_post', 'get_posts',
        'wp_send_json', 'wp_send_json_success', 'wp_send_json_error',
        'wp_nonce_field', 'wp_verify_nonce', 'check_ajax_referer',
        'current_user_can', 'is_admin', 'is_user_logged_in',
        'esc_html', 'esc_attr', 'esc_url', 'sanitize_text_field',
        'wp_die', 'wp_redirect', 'admin_url', 'home_url', 'site_url',
        'get_template_directory', 'get_stylesheet_directory',
        'wp_remote_get', 'wp_remote_post', 'wp_remote_request',
        'wp_mkdir_p', 'wp_upload_dir', 'wp_get_attachment_url'
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Check PHP syntax
     */
    public function check_syntax($file_path) {
        $result = array(
            'errors' => array(),
            'warnings' => array(),
            'suggestions' => array()
        );
        
        if (!file_exists($file_path)) {
            $result['errors'][] = array(
                'type' => 'file_not_found',
                'message' => __('Module file not found.', 'onyx-command'),
                'suggestion' => __('Make sure the file exists and is readable.', 'onyx-command'),
                'auto_fix_available' => false
            );
            return $result;
        }
        
        // Try basic token parsing
        $content = file_get_contents($file_path);
        
        $old_error_level = error_reporting(0);
        $tokens = @token_get_all($content);
        error_reporting($old_error_level);
        
        if ($tokens === false) {
            $result['warnings'][] = array(
                'type' => 'syntax_check_limited',
                'message' => __('Syntax check limited. Module will be loaded anyway.', 'onyx-command'),
                'suggestion' => __('Monitor for errors during use.', 'onyx-command'),
                'auto_fix_available' => false
            );
        }
        
        // Check for short PHP tags
        if (preg_match('/<\?[^p]/i', $content)) {
            $result['warnings'][] = array(
                'type' => 'short_tags',
                'message' => __('Short PHP tags detected. These may not work on all servers.', 'onyx-command'),
                'suggestion' => __('Replace short tags (<?) with full PHP tags (<?php).', 'onyx-command'),
                'auto_fix_available' => true,
                'auto_fix_action' => 'fix_short_tags'
            );
        }
        
        return $result;
    }
    
    /**
     * Check for conflicts with existing plugins and themes
     */
    public function check_conflicts($file_path, $module_id) {
        $result = array(
            'errors' => array(),
            'warnings' => array(),
            'suggestions' => array()
        );
        
        $content = file_get_contents($file_path);
        
        // Extract function names from the module (excluding WordPress core functions)
        preg_match_all('/function\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\(/i', $content, $matches);
        $module_functions = $matches[1];
        
        // Extract class names
        preg_match_all('/class\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/i', $content, $class_matches);
        $module_classes = $class_matches[1];
        
        // Check for function conflicts (excluding WordPress core functions)
        foreach ($module_functions as $func_name) {
            // Skip WordPress core functions
            if (in_array($func_name, $this->wp_core_functions)) {
                continue;
            }
            
            // Skip if it's a method (will be checked with class)
            if (preg_match('/class\s+\w+.*?function\s+' . preg_quote($func_name, '/') . '\s*\(/is', $content)) {
                continue;
            }
            
            if (function_exists($func_name)) {
                $result['errors'][] = array(
                    'type' => 'function_conflict',
                    'message' => sprintf(__('Function "%s" already exists in WordPress or another plugin.', 'onyx-command'), $func_name),
                    'suggestion' => sprintf(__('Rename the function to something unique, like "%s_%s".', 'onyx-command'), $module_id, $func_name),
                    'auto_fix_available' => false
                );
            }
        }
        
        // Check for class conflicts
        foreach ($module_classes as $class_name) {
            if (class_exists($class_name)) {
                $result['errors'][] = array(
                    'type' => 'class_conflict',
                    'message' => sprintf(__('Class "%s" already exists in WordPress or another plugin.', 'onyx-command'), $class_name),
                    'suggestion' => sprintf(__('Rename the class to something unique, like "%s_%s".', 'onyx-command'), $module_id, $class_name),
                    'auto_fix_available' => false
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Comprehensive module scan
     */
    public function scan_module($module_id) {
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module) {
            return new WP_Error('module_not_found', __('Module not found.', 'onyx-command'));
        }
        
        $file_path = OC_MODULES_DIR . $module['file_path'];
        
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Module file not found.', 'onyx-command'));
        }
        
        $results = array(
            'syntax' => $this->check_syntax($file_path),
            'conflicts' => $this->check_conflicts($file_path, $module_id),
            'security' => OC_Security::scan_file_content($file_path)
        );
        
        // Log any errors found
        $total_errors = count($results['syntax']['errors']) + count($results['conflicts']['errors']);
        
        if ($total_errors > 0) {
            OC_Error_Logger::log(
                'error',
                'Module scan found issues',
                $module['name'],
                array('module_id' => $module_id, 'error_count' => $total_errors)
            );
        }
        
        return $results;
    }
}
