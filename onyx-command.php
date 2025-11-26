<?php
/**
 * Plugin Name: Onyx Command
 * Plugin URI: https://www.callumcreed.com/onyx-command
 * Description: Advanced modular plugin management system with script checking, optimization tools, and comprehensive security
 * Version: 1.0.0
 * Author: Callum Creed
 * Author URI: https://www.callumcreed.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: onyx-command
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OC_VERSION', '1.0.0');
define('OC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('OC_MODULES_DIR', OC_PLUGIN_DIR . 'modules/');
define('OC_INCLUDES_DIR', OC_PLUGIN_DIR . 'includes/');
define('OC_ASSETS_DIR', OC_PLUGIN_URL . 'assets/');

/**
 * Plugin activation
 */
function onyx_command_activate() {
    global $wpdb;
    
    $modules_dir = OC_PLUGIN_DIR . 'modules/';
    if (!file_exists($modules_dir)) {
        wp_mkdir_p($modules_dir);
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $modules_table = $wpdb->prefix . 'mm_modules';
    $logs_table = $wpdb->prefix . 'mm_logs';
    $stats_table = $wpdb->prefix . 'mm_statistics';
    
    $sql_modules = "CREATE TABLE IF NOT EXISTS {$modules_table} (
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
    
    $sql_logs = "CREATE TABLE IF NOT EXISTS {$logs_table} (
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
    
    $sql_stats = "CREATE TABLE IF NOT EXISTS {$stats_table} (
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
    
    add_option('mm_version', '1.0.0');
    add_option('mm_installed', current_time('mysql'));
}

register_activation_hook(__FILE__, 'onyx_command_activate');
register_deactivation_hook(__FILE__, 'onyx_command_deactivate');

function onyx_command_deactivate() {
    wp_clear_scheduled_hook('mm_daily_cleanup');
}

/**
 * Add custom deletion dialog
 */
function onyx_command_deletion_dialog() {
    global $pagenow;
    
    if ($pagenow !== 'plugins.php') {
        return;
    }
    
    ?>
    <style>
    .oc-delete-modal {
        display: none;
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.7);
    }
    .oc-delete-modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .oc-delete-modal-header {
        padding: 20px 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 8px 8px 0 0;
    }
    .oc-delete-modal-header h2 {
        margin: 0;
        font-size: 24px;
        color: white;
    }
    .oc-delete-modal-body {
        padding: 30px;
    }
    .oc-delete-modal-body p {
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 20px;
    }
    .oc-delete-options {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    .oc-delete-option {
        flex: 1;
        padding: 20px;
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
    }
    .oc-delete-option:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }
    .oc-delete-option.danger:hover {
        border-color: #dc3545;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
    }
    .oc-delete-option h3 {
        margin: 0 0 10px 0;
        font-size: 18px;
    }
    .oc-delete-option p {
        margin: 0;
        font-size: 14px;
        color: #666;
    }
    .oc-delete-option .icon {
        font-size: 48px;
        margin-bottom: 10px;
    }
    .oc-modal-footer {
        padding: 20px 30px;
        background: #f8f9fa;
        border-radius: 0 0 8px 8px;
        text-align: right;
    }
    .oc-modal-footer button {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    .oc-btn-cancel {
        background: #6c757d;
        color: white;
    }
    .oc-btn-cancel:hover {
        background: #5a6268;
    }
    </style>
    
    <div id="ocDeleteModal" class="oc-delete-modal">
        <div class="oc-delete-modal-content">
            <div class="oc-delete-modal-header">
                <h2>⚠️ Delete Onyx Command</h2>
            </div>
            <div class="oc-delete-modal-body">
                <p><strong>Before deleting Onyx Command, please choose how you want to proceed:</strong></p>
                
                <div class="oc-delete-options">
                    <div class="oc-delete-option" id="ocKeepSettings">
                        <div class="icon">💾</div>
                        <h3>Keep My Settings</h3>
                        <p>Delete plugin files but preserve all settings, API keys, and database records for future reinstall</p>
                    </div>
                    
                    <div class="oc-delete-option danger" id="ocRemoveEverything">
                        <div class="icon">🗑️</div>
                        <h3>Remove Everything</h3>
                        <p>Completely remove all plugin files, settings, API keys, modules, and database records</p>
                    </div>
                </div>
            </div>
            <div class="oc-modal-footer">
                <button class="oc-btn-cancel" id="ocCancelDelete">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var deleteUrl = '';
        var modal = $('#ocDeleteModal');
        
        // Intercept delete link
        $('tr[data-plugin="<?php echo OC_PLUGIN_BASENAME; ?>"] .delete a').on('click', function(e) {
            e.preventDefault();
            deleteUrl = $(this).attr('href');
            modal.fadeIn(200);
        });
        
        // Keep settings option
        $('#ocKeepSettings').on('click', function() {
            $.post(ajaxurl, {
                action: 'oc_set_uninstall_preference',
                keep_settings: true,
                nonce: '<?php echo wp_create_nonce('oc_uninstall_pref'); ?>'
            }, function() {
                window.location.href = deleteUrl;
            });
        });
        
        // Remove everything option
        $('#ocRemoveEverything').on('click', function() {
            if (confirm('⚠️ WARNING: This will permanently delete ALL Onyx Command data!\n\nThis includes:\n• All settings and API keys\n• All modules and their data\n• All database records\n• All log files\n\nThis action CANNOT be undone!\n\nAre you absolutely sure?')) {
                $.post(ajaxurl, {
                    action: 'oc_set_uninstall_preference',
                    keep_settings: false,
                    nonce: '<?php echo wp_create_nonce('oc_uninstall_pref'); ?>'
                }, function() {
                    window.location.href = deleteUrl;
                });
            }
        });
        
        // Cancel button
        $('#ocCancelDelete').on('click', function() {
            modal.fadeOut(200);
        });
        
        // Close on outside click
        modal.on('click', function(e) {
            if (e.target.id === 'ocDeleteModal') {
                modal.fadeOut(200);
            }
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'onyx_command_deletion_dialog');

/**
 * AJAX handler for setting uninstall preference
 */
function onyx_command_set_uninstall_preference() {
    check_ajax_referer('oc_uninstall_pref', 'nonce');
    
    if (!current_user_can('activate_plugins')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $keep_settings = isset($_POST['keep_settings']) && $_POST['keep_settings'] === 'true';
    update_option('oc_keep_settings_on_uninstall', $keep_settings);
    
    wp_send_json_success();
}
add_action('wp_ajax_oc_set_uninstall_preference', 'onyx_command_set_uninstall_preference');

/**
 * Include required files
 */
function onyx_command_include_files() {
    $files = array(
        'class-security.php',
        'class-database.php',
        'class-module-loader.php',
        'class-script-checker.php',
        'class-optimizer.php',
        'class-error-logger.php',
        'class-ajax-handler.php',
        'class-statistics.php',
        'class-settings.php',
        'class-admin-bar.php'
    );
    
    foreach ($files as $file) {
        $file_path = OC_INCLUDES_DIR . $file;
        if (file_exists($file_path)) {
            try {
                require_once $file_path;
            } catch (Throwable $e) {
                error_log("Onyx Command: FAILED to load {$file} - " . $e->getMessage());
            }
        }
    }
}

/**
 * Initialize plugin components
 */
function onyx_command_init() {
    onyx_command_include_files();
    
    try { if (class_exists('OC_Security')) OC_Security::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Database')) OC_Database::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Module_Loader')) OC_Module_Loader::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Script_Checker')) OC_Script_Checker::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Optimizer')) OC_Optimizer::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Error_Logger')) OC_Error_Logger::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Statistics')) OC_Statistics::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Settings')) OC_Settings::get_instance(); } catch (Throwable $e) {}
    try { if (class_exists('OC_Admin_Bar')) OC_Admin_Bar::get_instance(); } catch (Throwable $e) {}
    try { if (is_admin() && class_exists('OC_Ajax_Handler')) OC_Ajax_Handler::get_instance(); } catch (Throwable $e) {}
}
add_action('plugins_loaded', 'onyx_command_init', 1);

/**
 * Add admin bar menu
 */
function onyx_command_admin_bar_menu($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node(array('id' => 'onyx-command', 'title' => '⚡ Onyx Command', 'href' => admin_url('admin.php?page=onyx-command')));
    $wp_admin_bar->add_node(array('id' => 'oc-dashboard', 'parent' => 'onyx-command', 'title' => '📊 Dashboard', 'href' => admin_url('admin.php?page=onyx-command')));
    $wp_admin_bar->add_node(array('id' => 'oc-modules', 'parent' => 'onyx-command', 'title' => '🧩 Modules', 'href' => admin_url('admin.php?page=onyx-command-modules')));
    $wp_admin_bar->add_node(array('id' => 'oc-upload', 'parent' => 'onyx-command', 'title' => '📤 Upload', 'href' => admin_url('admin.php?page=onyx-command-upload')));
    $wp_admin_bar->add_node(array('id' => 'oc-logs', 'parent' => 'onyx-command', 'title' => '📋 Error Logs', 'href' => admin_url('admin.php?page=onyx-command-logs')));
    $wp_admin_bar->add_node(array('id' => 'oc-optimizer', 'parent' => 'onyx-command', 'title' => '⚡ Optimizer', 'href' => admin_url('admin.php?page=onyx-command-optimizer')));
    $wp_admin_bar->add_node(array('id' => 'oc-stats', 'parent' => 'onyx-command', 'title' => '📈 Statistics', 'href' => admin_url('admin.php?page=onyx-command-stats')));
    $wp_admin_bar->add_node(array('id' => 'oc-settings', 'parent' => 'onyx-command', 'title' => '⚙️ Settings', 'href' => admin_url('admin.php?page=onyx-command-settings')));
}
add_action('admin_bar_menu', 'onyx_command_admin_bar_menu', 100);

/**
 * Add admin menu
 */
function onyx_command_add_admin_menu() {
    add_menu_page('Onyx Command', 'Onyx Command', 'manage_options', 'onyx-command', 'onyx_command_render_dashboard', 'dashicons-admin-plugins', 30);
    add_submenu_page('onyx-command', 'Dashboard', 'Dashboard', 'manage_options', 'onyx-command', 'onyx_command_render_dashboard');
    add_submenu_page('onyx-command', 'Modules', 'Modules', 'manage_options', 'onyx-command-modules', 'onyx_command_render_modules');
    add_submenu_page('onyx-command', 'Upload Module', 'Upload Module', 'manage_options', 'onyx-command-upload', 'onyx_command_render_upload');
    add_submenu_page('onyx-command', 'Error Logs', 'Error Logs', 'manage_options', 'onyx-command-logs', 'onyx_command_render_logs');
    add_submenu_page('onyx-command', 'Optimizer', 'Optimizer', 'manage_options', 'onyx-command-optimizer', 'onyx_command_render_optimizer');
    add_submenu_page('onyx-command', 'Statistics', 'Statistics', 'manage_options', 'onyx-command-stats', 'onyx_command_render_statistics');
    add_submenu_page('onyx-command', 'Settings', 'Settings', 'manage_options', 'onyx-command-settings', 'onyx_command_render_settings');
}
add_action('admin_menu', 'onyx_command_add_admin_menu');

/**
 * Enqueue admin assets
 */
function onyx_command_enqueue_assets($hook) {
    if (strpos($hook, 'onyx-command') === false) {
        return;
    }
    
    if (file_exists(OC_PLUGIN_DIR . 'assets/css/admin.css')) {
        wp_enqueue_style('oc-admin-style', OC_ASSETS_DIR . 'css/admin.css', array(), OC_VERSION);
    }
    
    if (file_exists(OC_PLUGIN_DIR . 'assets/js/admin.js')) {
        wp_enqueue_script('oc-admin-script', OC_ASSETS_DIR . 'js/admin.js', array('jquery'), OC_VERSION, true);
        wp_localize_script('oc-admin-script', 'ocAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mm_admin_action'),
            'strings' => array(
                'confirm_delete' => 'Are you sure you want to delete this module?',
                'confirm_clear_logs' => 'Are you sure you want to clear all logs?',
                'confirm_clean_db' => 'Are you sure you want to clean the database?',
            )
        ));
    }
}
add_action('admin_enqueue_scripts', 'onyx_command_enqueue_assets');

/**
 * Render functions
 */
function onyx_command_render_dashboard() {
    $stats = array('total_modules' => 0, 'active_modules' => 0, 'inactive_modules' => 0, 'total_executions' => 0, 'total_errors' => 0, 'unresolved_errors' => 0, 'most_used_modules' => array(), 'recent_activity' => array());
    $recent_logs = array();
    $suggestions = array();
    
    if (class_exists('OC_Statistics')) $stats = OC_Statistics::get_instance()->get_overall_stats();
    if (class_exists('OC_Error_Logger')) $recent_logs = OC_Error_Logger::get_instance()->get_all_logs(array('limit' => 10));
    if (class_exists('OC_Optimizer')) $suggestions = OC_Optimizer::get_instance()->get_suggestions();
    
    include OC_PLUGIN_DIR . 'templates/dashboard.php';
}

function onyx_command_render_modules() {
    $modules = array();
    if (class_exists('OC_Database')) $modules = OC_Database::get_instance()->get_modules();
    include OC_PLUGIN_DIR . 'templates/modules.php';
}

function onyx_command_render_upload() {
    include OC_PLUGIN_DIR . 'templates/upload.php';
}

function onyx_command_render_logs() {
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    $filters = array('limit' => 100);
    if ($filter !== 'all') $filters['log_type'] = $filter;
    
    $logs = array();
    $stats = array();
    if (class_exists('OC_Error_Logger')) {
        $logger = OC_Error_Logger::get_instance();
        $logs = $logger->get_all_logs($filters);
        $stats = $logger->get_statistics(30);
    }
    include OC_PLUGIN_DIR . 'templates/logs.php';
}

function onyx_command_render_optimizer() {
    $suggestions = array();
    $db_info = array();
    if (class_exists('OC_Optimizer')) {
        $optimizer = OC_Optimizer::get_instance();
        $suggestions = $optimizer->get_suggestions();
        $db_info = $optimizer->get_database_info();
    }
    include OC_PLUGIN_DIR . 'templates/optimizer.php';
}

function onyx_command_render_statistics() {
    $overall_stats = array();
    $performance = array();
    if (class_exists('OC_Statistics')) {
        $stats_manager = OC_Statistics::get_instance();
        $overall_stats = $stats_manager->get_overall_stats();
        $performance = $stats_manager->generate_performance_report(30);
    }
    include OC_PLUGIN_DIR . 'templates/statistics.php';
}

function onyx_command_render_settings() {
    include OC_PLUGIN_DIR . 'templates/settings.php';
}

/**
 * Load active modules
 */
function onyx_command_load_modules() {
    if (class_exists('OC_Module_Loader')) {
        OC_Module_Loader::get_instance()->load_active_modules();
    }
}
add_action('init', 'onyx_command_load_modules');
