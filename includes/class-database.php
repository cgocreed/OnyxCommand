<?php
/**
 * Database Class
 * Handles all database operations for the Onyx Command plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Database {
    
    private static $instance = null;
    private $modules_table;
    private $logs_table;
    private $stats_table;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->modules_table = $wpdb->prefix . 'mm_modules';
        $this->logs_table = $wpdb->prefix . 'mm_logs';
        $this->stats_table = $wpdb->prefix . 'mm_statistics';
    }
    
    /**
     * Check if tables exist
     */
    public function tables_exist() {
        global $wpdb;
        $table = $wpdb->get_var("SHOW TABLES LIKE '{$this->modules_table}'");
        return $table === $this->modules_table;
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Modules table
        $sql_modules = "CREATE TABLE IF NOT EXISTS {$this->modules_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            module_id varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            version varchar(50),
            author varchar(255),
            file_path varchar(500) NOT NULL,
            status enum('active','inactive') DEFAULT 'inactive',
            config longtext,
            installed_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_executed datetime,
            execution_count bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY module_id (module_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Error logs table
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$this->logs_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_type enum('error','warning','info','security') DEFAULT 'info',
            module_id varchar(255),
            message text NOT NULL,
            details longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY log_type (log_type),
            KEY module_id (module_id),
            KEY created_at (created_at),
            KEY resolved (resolved)
        ) $charset_collate;";
        
        // Statistics table
        $sql_stats = "CREATE TABLE IF NOT EXISTS {$this->stats_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            module_id varchar(255) NOT NULL,
            stat_key varchar(255) NOT NULL,
            stat_value longtext,
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY module_id (module_id),
            KEY stat_key (stat_key),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_modules);
        dbDelta($sql_logs);
        dbDelta($sql_stats);
    }
    
    /**
     * Insert module
     */
    public function insert_module($data) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return false;
        }
        
        $defaults = array(
            'module_id' => '',
            'name' => '',
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'file_path' => '',
            'status' => 'inactive',
            'config' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (is_array($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }
        
        $result = $wpdb->insert(
            $this->modules_table,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update module
     */
    public function update_module($module_id, $data) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return false;
        }
        
        if (isset($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config']);
        }
        
        return $wpdb->update(
            $this->modules_table,
            $data,
            array('module_id' => $module_id),
            null,
            array('%s')
        );
    }
    
    /**
     * Get module
     */
    public function get_module($module_id) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return null;
        }
        
        $module = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->modules_table} WHERE module_id = %s",
                $module_id
            ),
            ARRAY_A
        );
        
        if ($module && !empty($module['config'])) {
            $module['config'] = json_decode($module['config'], true);
        }
        
        return $module;
    }
    
    /**
     * Get all modules
     */
    public function get_modules($status = null) {
        global $wpdb;
        
        // Return empty array if tables don't exist yet
        if (!$this->tables_exist()) {
            return array();
        }
        
        if ($status) {
            $modules = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->modules_table} WHERE status = %s ORDER BY name ASC",
                    $status
                ),
                ARRAY_A
            );
        } else {
            $modules = $wpdb->get_results(
                "SELECT * FROM {$this->modules_table} ORDER BY name ASC",
                ARRAY_A
            );
        }
        
        if (!$modules) {
            return array();
        }
        
        foreach ($modules as &$module) {
            if (!empty($module['config'])) {
                $module['config'] = json_decode($module['config'], true);
            }
            
            // Get execution count from stats table
            $execution_count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->stats_table} 
                     WHERE module_id = %s AND stat_key = 'execution'",
                    $module['module_id']
                )
            );
            $module['execution_count'] = intval($execution_count);
        }
        
        return $modules;
    }
    
    /**
     * Delete module
     */
    public function delete_module($module_id) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return false;
        }
        
        return $wpdb->delete(
            $this->modules_table,
            array('module_id' => $module_id),
            array('%s')
        );
    }
    
    /**
     * Insert log entry
     */
    public function insert_log($data) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return false;
        }
        
        $defaults = array(
            'log_type' => 'info',
            'module_id' => null,
            'message' => '',
            'details' => '',
            'metadata' => '',
            'resolved' => 0
        );
        
        $data = wp_parse_args($data, $defaults);
        
        if (is_array($data['details'])) {
            $data['details'] = json_encode($data['details']);
        }
        
        if (is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        return $wpdb->insert(
            $this->logs_table,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Get logs
     */
    public function get_logs($filters = array()) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return array();
        }
        
        $where = array('1=1');
        $params = array();
        
        if (!empty($filters['log_type'])) {
            $where[] = 'log_type = %s';
            $params[] = $filters['log_type'];
        }
        
        if (!empty($filters['module_id'])) {
            $where[] = 'module_id = %s';
            $params[] = $filters['module_id'];
        }
        
        if (isset($filters['resolved'])) {
            $where[] = 'resolved = %d';
            $params[] = (int) $filters['resolved'];
        }
        
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        
        $sql = "SELECT * FROM {$this->logs_table} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $logs = $wpdb->get_results($sql, ARRAY_A);
        
        if (!$logs) {
            return array();
        }
        
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
            if (!empty($log['metadata'])) {
                $log['metadata'] = json_decode($log['metadata'], true);
            }
        }
        
        return $logs;
    }
    
    /**
     * Mark log as resolved
     */
    public function resolve_log($log_id) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return false;
        }
        
        return $wpdb->update(
            $this->logs_table,
            array('resolved' => 1),
            array('id' => $log_id),
            array('%d'),
            array('%d')
        );
    }
    
    /**
     * Delete old logs
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return 0;
        }
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->logs_table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
    
    /**
     * Record statistic
     */
    public function record_stat($module_id, $key, $value) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return false;
        }
        
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        return $wpdb->insert(
            $this->stats_table,
            array(
                'module_id' => $module_id,
                'stat_key' => $key,
                'stat_value' => $value
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Get statistics
     */
    public function get_stats($module_id, $key = null, $limit = 100) {
        global $wpdb;
        
        if (!$this->tables_exist()) {
            return array();
        }
        
        if ($key) {
            $stats = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->stats_table} WHERE module_id = %s AND stat_key = %s ORDER BY recorded_at DESC LIMIT %d",
                    $module_id,
                    $key,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $stats = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->stats_table} WHERE module_id = %s ORDER BY recorded_at DESC LIMIT %d",
                    $module_id,
                    $limit
                ),
                ARRAY_A
            );
        }
        
        if (!$stats) {
            return array();
        }
        
        foreach ($stats as &$stat) {
            $decoded = json_decode($stat['stat_value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $stat['stat_value'] = $decoded;
            }
        }
        
        return $stats;
    }
}
