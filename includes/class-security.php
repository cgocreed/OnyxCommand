<?php
/**
 * Security Class
 * Handles all security measures for the Onyx Command plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Security {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_security'));
    }
    
    /**
     * Initialize security measures
     */
    public function init_security() {
        // Additional security headers
        add_action('send_headers', array($this, 'send_security_headers'));
    }
    
    /**
     * Check if current user is administrator
     */
    public static function is_admin_user() {
        return current_user_can('manage_options') && is_user_logged_in();
    }
    
    /**
     * Verify nonce for AJAX requests
     */
    public static function verify_nonce($action = 'mm_admin_action') {
        if (!isset($_REQUEST['nonce'])) {
            return false;
        }
        return wp_verify_nonce($_REQUEST['nonce'], $action);
    }
    
    /**
     * Check user capability and nonce
     */
    public static function check_permission($nonce_action = 'mm_admin_action') {
        if (!self::is_admin_user()) {
            wp_die(__('Unauthorized access. Administrator privileges required.', 'onyx-command'));
        }
        
        if (!self::verify_nonce($nonce_action)) {
            wp_die(__('Security check failed. Please refresh the page and try again.', 'onyx-command'));
        }
        
        return true;
    }
    
    /**
     * Sanitize module file upload
     */
    public static function sanitize_upload($file) {
        $allowed_extensions = array('php', 'zip');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_extensions)) {
            return new WP_Error('invalid_file', __('Only PHP and ZIP files are allowed.', 'onyx-command'));
        }
        
        // Check file size (max 10MB)
        if ($file['size'] > 10485760) {
            return new WP_Error('file_too_large', __('File size exceeds 10MB limit.', 'onyx-command'));
        }
        
        return true;
    }
    
    /**
     * Scan uploaded file for malicious code
     */
    public static function scan_file_content($file_path) {
        $content = file_get_contents($file_path);
        
        // Patterns to detect potentially malicious code
        $dangerous_patterns = array(
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/proc_open\s*\(/i',
            '/popen\s*\(/i',
            '/curl_exec\s*\(/i',
            '/curl_multi_exec\s*\(/i',
            '/parse_ini_file\s*\(/i',
            '/show_source\s*\(/i',
            '/file_get_contents\s*\(\s*[\'"]http/i',
        );
        
        $warnings = array();
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $warnings[] = sprintf(
                    __('Potentially dangerous function detected: %s', 'onyx-command'),
                    str_replace(array('/', '\\s*', '\\(', 'i'), '', $pattern)
                );
            }
        }
        
        return $warnings;
    }
    
    /**
     * Sanitize module configuration data
     */
    public static function sanitize_config($config) {
        if (!is_array($config)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($config as $key => $value) {
            $sanitized[sanitize_key($key)] = self::sanitize_value($value);
        }
        
        return $sanitized;
    }
    
    /**
     * Recursively sanitize values
     */
    private static function sanitize_value($value) {
        if (is_array($value)) {
            return array_map(array(self::class, 'sanitize_value'), $value);
        }
        
        if (is_numeric($value)) {
            return $value;
        }
        
        if (is_bool($value)) {
            return $value;
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Generate secure random string
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Send security headers
     */
    public function send_security_headers() {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'onyx-command') === 0) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }
    
    /**
     * Log security event
     */
    public static function log_security_event($event, $details = '') {
        OC_Error_Logger::log(
            'security',
            $event,
            $details,
            array(
                'user_id' => get_current_user_id(),
                'ip' => self::get_user_ip()
            )
        );
    }
    
    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}