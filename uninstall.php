<?php
/**
 * Onyx Command Uninstall Script
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It gives the user a choice to keep or delete all plugin data.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user wants to keep settings
$keep_settings = get_option('oc_keep_settings_on_uninstall', false);

if ($keep_settings) {
    // User chose to KEEP settings - only delete plugin files, keep database
    // WordPress will delete the plugin files automatically
    // We just need to NOT delete the database records
    error_log('Onyx Command: Plugin files deleted but settings were preserved per user preference');
    return;
}

// User chose to DELETE everything - proceed with complete cleanup
global $wpdb;

// Delete all plugin options
$options_to_delete = array(
    'oc_version',
    'oc_installed',
    'oc_button_bg_color',
    'oc_button_text_color',
    'oc_button_hover_bg_color',
    'oc_button_hover_text_color',
    'oc_button_border_radius',
    'oc_button_padding_vertical',
    'oc_button_padding_horizontal',
    'oc_button_font_size',
    'oc_button_font_weight',
    'oc_button_text_transform',
    'oc_button_font_family',
    'oc_ignore_plugin_styling',
    'oc_api_keys',
    'oc_keep_settings_on_uninstall',
    'mm_version',
    'mm_installed'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Delete all module-specific options
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'oc_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mm_%'");

// Drop plugin tables
$tables_to_drop = array(
    $wpdb->prefix . 'mm_modules',
    $wpdb->prefix . 'mm_logs',
    $wpdb->prefix . 'mm_statistics',
    $wpdb->prefix . 'oc_checklists',
    $wpdb->prefix . 'oc_checklist_progress'
);

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Clear any scheduled hooks
wp_clear_scheduled_hook('mm_daily_cleanup');
wp_clear_scheduled_hook('oc_daily_cleanup');

// Log complete uninstall
error_log('Onyx Command: Plugin completely uninstalled - all data removed');
