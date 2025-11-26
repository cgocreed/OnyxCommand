<?php
/**
 * Optimizer Class
 * Handles site optimization, cleanup, and performance improvements
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Optimizer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('mm_daily_cleanup', array($this, 'scheduled_cleanup'));
    }
    
    /**
     * Clear all caches
     */
    public function clear_all_caches() {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $results = array();
        
        // Clear WordPress object cache
        wp_cache_flush();
        $results['object_cache'] = true;
        
        // Clear transients
        $this->clear_transients();
        $results['transients'] = true;
        
        // Clear popular cache plugins
        $results['plugin_caches'] = $this->clear_plugin_caches();
        
        // Clear rewrite rules
        flush_rewrite_rules();
        $results['rewrite_rules'] = true;
        
        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $results['opcache'] = true;
        }
        
        OC_Error_Logger::log('info', 'All caches cleared', '', array('results' => $results));
        
        return array(
            'success' => true,
            'message' => __('All caches cleared successfully.', 'onyx-command'),
            'details' => $results
        );
    }
    
    /**
     * Clear transients
     */
    private function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
        );
        
        return true;
    }
    
    /**
     * Clear popular cache plugin caches
     */
    private function clear_plugin_caches() {
        $cleared = array();
        
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            $cleared['wp_super_cache'] = true;
        }
        
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            $cleared['w3_total_cache'] = true;
        }
        
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            $cleared['wp_rocket'] = true;
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
            LiteSpeed_Cache_API::purge_all();
            $cleared['litespeed_cache'] = true;
        }
        
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache(true);
            $cleared['wp_fastest_cache'] = true;
        }
        
        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
            $cleared['autoptimize'] = true;
        }
        
        return $cleared;
    }
    
    /**
     * Clean database
     */
    public function clean_database() {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        global $wpdb;
        $results = array();
        
        // Clean post revisions
        $revisions = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
        $results['revisions_deleted'] = $revisions;
        
        // Clean auto-drafts
        $auto_drafts = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
        $results['auto_drafts_deleted'] = $auto_drafts;
        
        // Clean trashed posts
        $trash = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'");
        $results['trash_deleted'] = $trash;
        
        // Clean spam comments
        $spam = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        $results['spam_comments_deleted'] = $spam;
        
        // Clean orphaned post meta
        $orphaned_meta = $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
        );
        $results['orphaned_meta_deleted'] = $orphaned_meta;
        
        // Clean orphaned comment meta
        $orphaned_comment_meta = $wpdb->query(
            "DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL"
        );
        $results['orphaned_comment_meta_deleted'] = $orphaned_comment_meta;
        
        // Optimize tables
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $optimized = 0;
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table[0]}");
            $optimized++;
        }
        
        $results['tables_optimized'] = $optimized;
        
        OC_Error_Logger::log('info', 'Database cleaned', '', array('results' => $results));
        
        return array(
            'success' => true,
            'message' => __('Database cleaned and optimized successfully.', 'onyx-command'),
            'details' => $results
        );
    }
    
    /**
     * Clean old log files
     */
    public function clean_logs($days = 30) {
        if (!OC_Security::is_admin_user()) {
            return new WP_Error('permission_denied', __('Unauthorized access.', 'onyx-command'));
        }
        
        $db = OC_Database::get_instance();
        $deleted = $db->cleanup_old_logs($days);
        
        return array(
            'success' => true,
            'message' => sprintf(__('%d old log entries deleted.', 'onyx-command'), $deleted),
            'deleted_count' => $deleted
        );
    }
    
    /**
     * Get optimization suggestions
     */
    public function get_suggestions() {
        $suggestions = array();
        global $wpdb;
        
        // Check post revisions
        $revision_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'");
        if ($revision_count > 100) {
            $suggestions[] = array(
                'type' => 'revisions',
                'severity' => 'medium',
                'message' => sprintf(__('You have %d post revisions. Consider cleaning them up.', 'onyx-command'), $revision_count),
                'action' => 'clean_database'
            );
        }
        
        // Check auto-drafts
        $autodraft_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
        if ($autodraft_count > 50) {
            $suggestions[] = array(
                'type' => 'auto_drafts',
                'severity' => 'low',
                'message' => sprintf(__('You have %d auto-drafts. Consider cleaning them up.', 'onyx-command'), $autodraft_count),
                'action' => 'clean_database'
            );
        }
        
        // Check transients
        $transient_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"
        );
        if ($transient_count > 1000) {
            $suggestions[] = array(
                'type' => 'transients',
                'severity' => 'medium',
                'message' => sprintf(__('You have %d transients. Consider clearing expired ones.', 'onyx-command'), $transient_count),
                'action' => 'clear_all_caches'
            );
        }
        
        // Check for opcache
        if (!function_exists('opcache_get_status')) {
            $suggestions[] = array(
                'type' => 'opcache',
                'severity' => 'high',
                'message' => __('OPcache is not enabled. Enable it for better PHP performance.', 'onyx-command'),
                'action' => 'manual'
            );
        }
        
        // Check for object cache
        if (!wp_using_ext_object_cache()) {
            $suggestions[] = array(
                'type' => 'object_cache',
                'severity' => 'medium',
                'message' => __('No persistent object cache detected. Consider using Redis or Memcached.', 'onyx-command'),
                'action' => 'manual'
            );
        }
        
        return $suggestions;
    }
    
    /**
     * Get database size
     */
    public function get_database_info() {
        global $wpdb;
        
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $total_size = 0;
        $total_rows = 0;
        $table_info = array();
        
        foreach ($tables as $table) {
            $size = $table['Data_length'] + $table['Index_length'];
            $total_size += $size;
            $total_rows += $table['Rows'];
            
            $table_info[] = array(
                'name' => $table['Name'],
                'rows' => number_format($table['Rows']),
                'size' => size_format($size, 2),
                'size_bytes' => $size
            );
        }
        
        usort($table_info, function($a, $b) {
            return $b['size_bytes'] - $a['size_bytes'];
        });
        
        return array(
            'total_size' => size_format($total_size, 2),
            'total_rows' => number_format($total_rows),
            'table_count' => count($tables),
            'tables' => array_slice($table_info, 0, 10) // Top 10 largest tables
        );
    }
    
    /**
     * Scheduled cleanup
     */
    public function scheduled_cleanup() {
        // Clean old logs
        $this->clean_logs(30);
        
        // Clear expired transients
        delete_expired_transients();
        
        OC_Error_Logger::log('info', 'Scheduled cleanup completed', '');
    }
    
    /**
     * Schedule cleanup
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('mm_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'mm_daily_cleanup');
        }
    }
}