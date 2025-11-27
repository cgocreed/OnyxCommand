<?php
if (!defined('ABSPATH')) exit;

define('OC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OC_ASSETS_DIR', plugins_url('assets/', __FILE__));
define('OC_VERSION', '1.0.0');
define('OC_ASSETS_DIR', plugins_url('assets/', __FILE__));
define('OC_VERSION', '1.0.0');

// Require the new modules
if (file_exists(OC_PLUGIN_DIR . 'includes/class-error-logger.php')) {
    require_once OC_PLUGIN_DIR . 'includes/class-error-logger.php';
    // Initialization happens inside file
}

if (file_exists(OC_PLUGIN_DIR . 'includes/class-dynamic-checklists.php')) {
    require_once OC_PLUGIN_DIR . 'includes/class-dynamic-checklists.php';
    // Initialization happens inside file
}

// Admin menu registration (add Error Log and Dynamic Checklists settings link)
add_action('admin_menu', 'onyx_command_add_admin_menu');
// Require the new modules
if (file_exists(OC_PLUGIN_DIR . 'includes/class-error-logger.php')) {
    require_once OC_PLUGIN_DIR . 'includes/class-error-logger.php';
    // Initialization happens inside file
}

if (file_exists(OC_PLUGIN_DIR . 'includes/class-dynamic-checklists.php')) {
    require_once OC_PLUGIN_DIR . 'includes/class-dynamic-checklists.php';
    // Initialization happens inside file
}

// Admin menu registration (add Error Log and Dynamic Checklists settings link)
add_action('admin_menu', 'onyx_command_add_admin_menu');
function onyx_command_add_admin_menu() {
    add_menu_page('Onyx Command', 'Onyx Command', 'manage_options', 'onyx-command', 'onyx_command_render_dashboard', 'dashicons-admin-generic', 61);
    add_menu_page('Onyx Command', 'Onyx Command', 'manage_options', 'onyx-command', 'onyx_command_render_dashboard', 'dashicons-admin-generic', 61);
    add_submenu_page('onyx-command', 'Modules', 'Modules', 'manage_options', 'onyx-command-modules', 'onyx_command_render_modules');
    add_submenu_page('onyx-command', 'Upload Module', 'Upload', 'manage_options', 'onyx-command-upload', 'onyx_command_render_upload');
    add_submenu_page('onyx-command', 'Upload Module', 'Upload', 'manage_options', 'onyx-command-upload', 'onyx_command_render_upload');
    add_submenu_page('onyx-command', 'Statistics', 'Statistics', 'manage_options', 'onyx-command-stats', 'onyx_command_render_statistics');
    add_submenu_page('onyx-command', 'Logs', 'Logs', 'manage_options', 'onyx-command-logs', 'onyx_command_render_logs');
    add_submenu_page('onyx-command', 'Logs', 'Logs', 'manage_options', 'onyx-command-logs', 'onyx_command_render_logs');
    add_submenu_page('onyx-command', 'Settings', 'Settings', 'manage_options', 'onyx-command-settings', 'onyx_command_render_settings');

    // Add Error Log as a submenu item (new tab entry)
    add_submenu_page('onyx-command', 'Error Log', 'Error Log', 'manage_options', 'onyx-command-error-log', 'onyx_command_render_error_log');

    // Add Dynamic Checklists settings as a submenu tab near settings (add it after existing settings)
    add_submenu_page('onyx-command', 'Dynamic Checklists', 'Dynamic Checklists', 'manage_options', 'onyx-command-dynamic-checklists', 'onyx_command_render_dynamic_checklists_settings');
}

// Render error log screen
function onyx_command_render_error_log() {

    // Add Error Log as a submenu item (new tab entry)
    add_submenu_page('onyx-command', 'Error Log', 'Error Log', 'manage_options', 'onyx-command-error-log', 'onyx_command_render_error_log');

    // Add Dynamic Checklists settings as a submenu tab near settings (add it after existing settings)
    add_submenu_page('onyx-command', 'Dynamic Checklists', 'Dynamic Checklists', 'manage_options', 'onyx-command-dynamic-checklists', 'onyx_command_render_dynamic_checklists_settings');
}

// Render error log screen
function onyx_command_render_error_log() {
    $logs = array();
    if (class_exists('OC_Error_Logger')) {
        $logs = OC_Error_Logger::get_instance()->get_all_logs(array('limit' => 200));
        $logs = OC_Error_Logger::get_instance()->get_all_logs(array('limit' => 200));
    }
    include OC_PLUGIN_DIR . 'templates/error-log.php';
    include OC_PLUGIN_DIR . 'templates/error-log.php';
}

// Render dynamic checklists settings screen
function onyx_command_render_dynamic_checklists_settings() {
    include OC_PLUGIN_DIR . 'templates/dynamic-checklists-settings.php';
}