<?php
/**
 * Error Logger Class
 * Handles all error logging and reporting
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Error_Logger {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Log an event
     */
    public static function log($type, $message, $details = '', $metadata = array()) {
        $db = OC_Database::get_instance();
        
        $data = array(
            'log_type' => $type,
            'message' => $message,
            'details' => $details,
            'metadata' => $metadata
        );
        
        if (isset($metadata['module_id'])) {
            $data['module_id'] = $metadata['module_id'];
            unset($metadata['module_id']);
            $data['metadata'] = $metadata;
        }
        
        return $db->insert_log($data);
    }
    
    /**
     * Get all logs
     */
    public function get_all_logs($filters = array()) {
        $db = OC_Database::get_instance();
        return $db->get_logs($filters);
    }
    
    /**
     * Get logs by type
     */
    public function get_logs_by_type($type, $limit = 100) {
        return $this->get_all_logs(array('log_type' => $type, 'limit' => $limit));
    }
    
    /**
     * Get logs by module
     */
    public function get_logs_by_module($module_id, $limit = 100) {
        return $this->get_all_logs(array('module_id' => $module_id, 'limit' => $limit));
    }
    
    /**
     * Get unresolved errors
     */
    public function get_unresolved_errors() {
        return $this->get_all_logs(array('log_type' => 'error', 'resolved' => 0));
    }
    
    /**
     * Mark log as resolved
     */
    public function resolve($log_id) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $db = OC_Database::get_instance();
        $result = $db->resolve_log($log_id);
        
        if ($result) {
            return array('success' => true, 'message' => __('Log marked as resolved.', 'onyx-command'));
        }
        
        return new WP_Error('resolve_failed', __('Failed to resolve log.', 'onyx-command'));
    }
    
    /**
     * Get log statistics
     */
    public function get_statistics($days = 7) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'mm_logs';
        
        $stats = array();
        
        // Total logs by type
        $stats['by_type'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT log_type, COUNT(*) as count FROM {$logs_table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
                GROUP BY log_type",
                $days
            ),
            ARRAY_A
        );
        
        // Logs over time
        $stats['over_time'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, log_type, COUNT(*) as count 
                FROM {$logs_table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
                GROUP BY DATE(created_at), log_type 
                ORDER BY date DESC",
                $days
            ),
            ARRAY_A
        );
        
        // Most problematic modules
        $stats['problematic_modules'] = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT module_id, COUNT(*) as error_count 
                FROM {$logs_table} 
                WHERE log_type IN ('error', 'warning') 
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
                AND module_id IS NOT NULL 
                GROUP BY module_id 
                ORDER BY error_count DESC 
                LIMIT 10",
                $days
            ),
            ARRAY_A
        );
        
        // Unresolved errors
        $stats['unresolved_count'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$logs_table} WHERE log_type = 'error' AND resolved = 0"
        );
        
        return $stats;
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs($filters = array()) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $logs = $this->get_all_logs($filters);
        
        $csv_content = "ID,Type,Module,Message,Details,Created At,Resolved\n";
        
        foreach ($logs as $log) {
            $csv_content .= sprintf(
                "%d,%s,%s,\"%s\",\"%s\",%s,%s\n",
                $log['id'],
                $log['log_type'],
                $log['module_id'] ?: 'N/A',
                str_replace('"', '""', $log['message']),
                str_replace('"', '""', is_array($log['details']) ? json_encode($log['details']) : $log['details']),
                $log['created_at'],
                $log['resolved'] ? 'Yes' : 'No'
            );
        }
        
        return $csv_content;
    }
    
    /**
     * Clear all logs
     */
    public function clear_all_logs() {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        global $wpdb;
        $logs_table = $wpdb->prefix . 'mm_logs';
        
        $result = $wpdb->query("TRUNCATE TABLE {$logs_table}");
        
        if ($result !== false) {
            return array('success' => true, 'message' => __('All logs cleared successfully.', 'onyx-command'));
        }
        
        return new WP_Error('clear_failed', __('Failed to clear logs.', 'onyx-command'));
    }
}