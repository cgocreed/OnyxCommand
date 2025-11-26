<?php
/**
 * Admin Bar Menu Class
 * 
 * Adds Onyx Command menu to WordPress admin bar
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Admin_Bar {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_action('wp_ajax_oc_quick_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('admin_footer', array($this, 'enqueue_admin_bar_scripts'));
        add_action('wp_footer', array($this, 'enqueue_admin_bar_scripts'));
    }
    
    /**
     * Add Onyx Command menu to admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Main menu item
        $wp_admin_bar->add_node(array(
            'id'    => 'onyx-command',
            'title' => '⚡ Onyx Command',
            'href'  => admin_url('admin.php?page=onyx-command'),
            'meta'  => array(
                'title' => __('Onyx Command Dashboard', 'onyx-command'),
            ),
        ));
        
        // Dashboard
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-dashboard',
            'parent' => 'onyx-command',
            'title'  => '📊 Dashboard',
            'href'   => admin_url('admin.php?page=onyx-command'),
        ));
        
        // Clear Cache (AJAX action)
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-clear-cache',
            'parent' => 'onyx-command',
            'title'  => '🗑️ Clear Cache',
            'href'   => '#',
            'meta'   => array(
                'onclick' => 'return ocQuickClearCache();',
            ),
        ));
        
        // Upload Module
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-upload',
            'parent' => 'onyx-command',
            'title'  => '📤 Upload Module',
            'href'   => admin_url('admin.php?page=onyx-command-upload'),
        ));
        
        // Manage Modules
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-modules',
            'parent' => 'onyx-command',
            'title'  => '🧩 Manage Modules',
            'href'   => admin_url('admin.php?page=onyx-command-modules'),
        ));
        
        // Statistics
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-stats',
            'parent' => 'onyx-command',
            'title'  => '📈 Statistics',
            'href'   => admin_url('admin.php?page=onyx-command-stats'),
        ));
        
        // Settings
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-settings',
            'parent' => 'onyx-command',
            'title'  => '⚙️ Settings',
            'href'   => admin_url('admin.php?page=onyx-command-settings'),
        ));
        
        // Logs
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-logs',
            'parent' => 'onyx-command',
            'title'  => '📋 Logs',
            'href'   => admin_url('admin.php?page=onyx-command-logs'),
        ));
        
        // Optimizer
        $wp_admin_bar->add_node(array(
            'id'     => 'oc-optimizer',
            'parent' => 'onyx-command',
            'title'  => '⚡ Optimizer',
            'href'   => admin_url('admin.php?page=onyx-command-optimizer'),
        ));
    }
    
    /**
     * AJAX: Quick clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('oc_admin_bar_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Clear WordPress caches
        wp_cache_flush();
        
        // Clear object cache if available
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
        
        wp_send_json_success(array('message' => 'Cache cleared successfully!'));
    }
    
    /**
     * Enqueue admin bar scripts
     */
    public function enqueue_admin_bar_scripts() {
        ?>
        <script>
        function ocQuickClearCache() {
            if (!confirm('Clear all caches?')) {
                return false;
            }
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oc_quick_clear_cache',
                    nonce: '<?php echo wp_create_nonce('oc_admin_bar_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('✓ ' + response.data.message);
                    } else {
                        alert('✗ Error: ' + response.data);
                    }
                }
            });
            
            return false;
        }
        </script>
        <?php
    }
}
