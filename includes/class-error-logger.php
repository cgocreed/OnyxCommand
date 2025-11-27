<?php
/**
 * Error Logger — DB-backed logger with migration from option-based storage.
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Error_Logger {
    private static $instance = null;
    private $option_key = 'oc_error_logs'; // legacy
    private $table_name;
    private $max_entries = 10000;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'onyx_error_logs';
    }

    public function init() {
        // register handlers
        $this->register_handlers();
        // ensure table exists and migrate legacy logs
        add_action('init', array($this, 'maybe_create_table'));
        add_action('admin_init', array($this, 'maybe_migrate_option_logs'));
    }

    private function register_handlers() {
        set_error_handler(array($this, 'handle_error'));
        set_exception_handler(array($this, 'handle_exception'));
        register_shutdown_function(array($this, 'handle_shutdown'));
    }

    public function handle_error($errno, $errstr, $errfile, $errline) {
        $this->insert(array(
            'type' => 'php_error',
            'severity' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'trace' => '',
            'context' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'created_at' => current_time('mysql')
        ));
        // return false to let PHP internal handler run as well
        return false;
    }

    public function handle_exception($exception) {
        $this->insert(array(
            'type' => 'exception',
            'severity' => 0,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'created_at' => current_time('mysql')
        ));
    }

    public function handle_shutdown() {
        $err = error_get_last();
        if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR))) {
            $this->insert(array(
                'type' => 'fatal',
                'severity' => $err['type'],
                'message' => $err['message'],
                'file' => $err['file'],
                'line' => $err['line'],
                'trace' => '',
                'context' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                'created_at' => current_time('mysql')
            ));
        }
    }

    private function insert($data) {
        global $wpdb;
        $table = $this->table_name;
        $wpdb->insert($table, array(
            'type' => isset($data['type']) ? $data['type'] : '',
            'severity' => isset($data['severity']) ? intval($data['severity']) : 0,
            'message' => isset($data['message']) ? $data['message'] : '',
            'file' => isset($data['file']) ? $data['file'] : '',
            'line' => isset($data['line']) ? intval($data['line']) : 0,
            'trace' => isset($data['trace']) ? $data['trace'] : '',
            'context' => isset($data['context']) ? $data['context'] : '',
            'created_at' => isset($data['created_at']) ? $data['created_at'] : current_time('mysql'),
        ));

        // Optionally trim old entries
        if ($this->max_entries > 0) {
            $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
            if ($count > $this->max_entries) {
                $offset = $count - $this->max_entries;
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN (SELECT id FROM (SELECT id FROM {$table} ORDER BY id ASC LIMIT %d) x)", $offset));
            }
        }
    }

    public function get_all_logs($args = array()) {
        global $wpdb;
        $table = $this->table_name;
        $limit = isset($args['limit']) ? intval($args['limit']) : 200;
        $type = isset($args['type']) ? sanitize_text_field($args['type']) : '';

        $sql = "SELECT * FROM {$table} ";
        $where = array();
        if ($type) {
            $where[] = $wpdb->prepare('type = %s', $type);
        }
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC ';
        if ($limit) $sql .= ' LIMIT ' . $limit;

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function get_statistics($days = 30) {
        global $wpdb;
        $table = $this->table_name;
        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $threshold)));
        $total = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));
        return array('errors_last_days' => $count, 'total_errors' => $total);
    }

    public function maybe_create_table() {
        global $wpdb;
        $table_name = $this->table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            severity int(11) NOT NULL DEFAULT 0,
            message longtext NOT NULL,
            file varchar(255) DEFAULT NULL,
            line int(11) DEFAULT NULL,
            trace longtext DEFAULT NULL,
            context varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY type (type)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function maybe_migrate_option_logs() {
        // migrate legacy option-based logs into DB table once
        $migrated_flag = 'oc_error_logs_migrated';
        if (get_option($migrated_flag)) return;

        $legacy = get_option($this->option_key, array());
        if (!empty($legacy) && is_array($legacy)) {
            foreach ($legacy as $entry) {
                $this->insert(array(
                    'type' => isset($entry['type']) ? $entry['type'] : 'php_error',
                    'severity' => isset($entry['level']) ? intval($entry['level']) : 0,
                    'message' => isset($entry['message']) ? $entry['message'] : (isset($entry['msg']) ? $entry['msg'] : ''),
                    'file' => isset($entry['file']) ? $entry['file'] : (isset($entry['file']) ? $entry['file'] : ''),
                    'line' => isset($entry['line']) ? intval($entry['line']) : 0,
                    'trace' => isset($entry['trace']) ? $entry['trace'] : '',
                    'context' => isset($entry['context']) ? $entry['context'] : '',
                    'created_at' => isset($entry['time']) ? $entry['time'] : current_time('mysql'),
                ));
            }
            // delete legacy option so migration is one-time
            delete_option($this->option_key);
        }
        update_option($migrated_flag, 1);
    }
}

// Initialize logger
OC_Error_Logger::get_instance();