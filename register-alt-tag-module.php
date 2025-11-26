<?php
/**
 * One-Time Module Registration Script
 * Run this once to register the AI Alt Tag Manager module
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied');
}

// Get the module loader
$loader = OC_Module_Loader::get_instance();

// Module file path
$module_file = OC_MODULES_DIR . 'ai-alt-tag-manager/ai-alt-tag-manager.php';

if (!file_exists($module_file)) {
    die('Module file not found');
}

// Parse module info
$content = file_get_contents($module_file);

$headers = array(
    'module_id' => 'Module ID',
    'name' => 'Module Name',
    'description' => 'Description',
    'version' => 'Version',
    'author' => 'Author'
);

$module_info = array();

foreach ($headers as $key => $header) {
    if (preg_match('/' . preg_quote($header, '/') . ':\s*(.+)/i', $content, $matches)) {
        $module_info[$key] = trim($matches[1]);
    }
}

// Verify required fields
if (empty($module_info['module_id']) || empty($module_info['name'])) {
    die('Module missing required headers');
}

// Register module in database
$db = OC_Database::get_instance();

$data = array(
    'module_id' => $module_info['module_id'],
    'name' => $module_info['name'],
    'description' => isset($module_info['description']) ? $module_info['description'] : '',
    'version' => isset($module_info['version']) ? $module_info['version'] : '1.0.0',
    'author' => isset($module_info['author']) ? $module_info['author'] : '',
    'file_path' => 'ai-alt-tag-manager/ai-alt-tag-manager.php',
    'status' => 'inactive',
    'config' => json_encode(array())
);

$result = $db->insert_module($data);

if ($result) {
    echo '<h1>Success!</h1>';
    echo '<p>AI Alt Tag Manager module has been registered.</p>';
    echo '<p>Go to <a href="' . admin_url('admin.php?page=onyx-command-modules') . '">Onyx Command â†’ Manage Modules</a> to activate it.</p>';
} else {
    echo '<h1>Error</h1>';
    echo '<p>Failed to register module. It may already be registered.</p>';
}

// Delete this file after running
@unlink(__FILE__);
echo '<p><em>This registration script has been automatically deleted.</em></p>';
?>