<?php
/**
 * Statistics Class
 * Tracks and reports module usage statistics
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Statistics {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Record module execution
     */
    public function record_execution($module_id, $duration = 0, $success = true) {
        $db = OC_Database::get_instance();
        
        $db->record_stat($module_id, 'execution', array(
            'duration' => $duration,
            'success' => $success,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Record custom metric
     */
    public function record_metric($module_id, $metric_name, $value) {
        $db = OC_Database::get_instance();
        return $db->record_stat($module_id, $metric_name, $value);
    }
    
    /**
     * Get module statistics
     */
    public function get_module_stats($module_id) {
        $db = OC_Database::get_instance();
        $module = $db->get_module($module_id);
        
        if (!$module) {
            return new WP_Error('module_not_found', __('Module not found.', 'onyx-command'));
        }
        
        $stats = array(
            'module_info' => array(
                'name' => $module['name'],
                'version' => $module['version'],
                'status' => $module['status'],
                'installed_at' => $module['installed_at'],
                'last_executed' => $module['last_executed'],
                'execution_count' => $module['execution_count']
            ),
            'recent_executions' => $db->get_stats($module_id, 'execution', 50),
            'error_count' => $this->get_error_count($module_id),
            'uptime' => $this->calculate_uptime($module)
        );
        
        return $stats;
    }
    
    /**
     * Get error count for module
     */
    private function get_error_count($module_id) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'mm_logs';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$logs_table} WHERE module_id = %s AND log_type = 'error'",
                $module_id
            )
        );
    }
    
    /**
     * Calculate module uptime percentage
     */
    private function calculate_uptime($module) {
        if (!$module['last_executed']) {
            return 0;
        }
        
        $installed = strtotime($module['installed_at']);
        $now = current_time('timestamp');
        $total_time = $now - $installed;
        
        // This is a simplified calculation
        // In a real scenario, you'd track actual uptime/downtime
        $error_count = $this->get_error_count($module['module_id']);
        $execution_count = $module['execution_count'];
        
        if ($execution_count == 0) {
            return 0;
        }
        
        $uptime_percentage = (($execution_count - $error_count) / $execution_count) * 100;
        
        return round($uptime_percentage, 2);
    }
    
    /**
     * Get overall statistics
     */
    public function get_overall_stats() {
        $db = OC_Database::get_instance();
        global $wpdb;
        
        $modules_table = $wpdb->prefix . 'mm_modules';
        $stats_table = $wpdb->prefix . 'mm_statistics';
        $logs_table = $wpdb->prefix . 'mm_logs';
        
        // Calculate total executions from stats table
        $total_executions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$stats_table} WHERE stat_key = 'execution'"
        );
        
        // Get most used modules with execution counts from stats table
        $most_used_modules = $wpdb->get_results(
            "SELECT m.module_id, m.name, COUNT(s.id) as execution_count 
             FROM {$modules_table} m 
             LEFT JOIN {$stats_table} s ON m.module_id = s.module_id AND s.stat_key = 'execution'
             GROUP BY m.module_id, m.name
             ORDER BY execution_count DESC 
             LIMIT 5",
            ARRAY_A
        );
        
        $stats = array(
            'total_modules' => $wpdb->get_var("SELECT COUNT(*) FROM {$modules_table}"),
            'active_modules' => $wpdb->get_var("SELECT COUNT(*) FROM {$modules_table} WHERE status = 'active'"),
            'inactive_modules' => $wpdb->get_var("SELECT COUNT(*) FROM {$modules_table} WHERE status = 'inactive'"),
            'total_executions' => intval($total_executions),
            'total_errors' => $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE log_type = 'error'"),
            'unresolved_errors' => $wpdb->get_var("SELECT COUNT(*) FROM {$logs_table} WHERE log_type = 'error' AND resolved = 0"),
            'most_used_modules' => $wpdb->get_results(
                "SELECT module_id, name, execution_count FROM {$modules_table} ORDER BY execution_count DESC LIMIT 5",
                ARRAY_A
            ),
            'recent_activity' => $wpdb->get_results(
                "SELECT log_type, COUNT(*) as count FROM {$logs_table} 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY log_type",
                ARRAY_A
            )
        );
        
        return $stats;
    }
    
    /**
     * Generate performance report
     */
    public function generate_performance_report($days = 30) {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'mm_statistics';
        
        $execution_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT module_id, stat_value FROM {$stats_table} 
                WHERE stat_key = 'execution' 
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL %d DAY) 
                ORDER BY recorded_at DESC",
                $days
            ),
            ARRAY_A
        );
        
        $performance = array();
        
        foreach ($execution_stats as $stat) {
            $value = json_decode($stat['stat_value'], true);
            
            if (!isset($performance[$stat['module_id']])) {
                $performance[$stat['module_id']] = array(
                    'total_executions' => 0,
                    'successful_executions' => 0,
                    'failed_executions' => 0,
                    'total_duration' => 0,
                    'avg_duration' => 0
                );
            }
            
            $performance[$stat['module_id']]['total_executions']++;
            
            if ($value['success']) {
                $performance[$stat['module_id']]['successful_executions']++;
            } else {
                $performance[$stat['module_id']]['failed_executions']++;
            }
            
            $performance[$stat['module_id']]['total_duration'] += $value['duration'];
        }
        
        // Calculate averages
        foreach ($performance as $module_id => &$data) {
            if ($data['total_executions'] > 0) {
                $data['avg_duration'] = round($data['total_duration'] / $data['total_executions'], 3);
                $data['success_rate'] = round(($data['successful_executions'] / $data['total_executions']) * 100, 2);
            }
        }
        
        return $performance;
    }
    
    /**
     * Export statistics to CSV
     */
    public function export_stats($module_id = null) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        if ($module_id) {
            $stats = $this->get_module_stats($module_id);
            $filename = 'module-stats-' . $module_id . '-' . date('Y-m-d') . '.csv';
        } else {
            $stats = $this->get_overall_stats();
            $filename = 'overall-stats-' . date('Y-m-d') . '.csv';
        }
        
        // Generate CSV content based on stats
        $csv_content = $this->generate_csv_from_stats($stats);
        
        return array(
            'filename' => $filename,
            'content' => $csv_content
        );
    }
    
    /**
     * Generate CSV from stats array
     */
    private function generate_csv_from_stats($stats) {
        $csv = "Metric,Value\n";
        
        foreach ($stats as $key => $value) {
            if (is_array($value)) {
                $csv .= "$key," . json_encode($value) . "\n";
            } else {
                $csv .= "$key,$value\n";
            }
        }
        
        return $csv;
    }
}