<?php
/**
 * Upload Module Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap oc-upload">
    <h1><?php _e('Upload New Module', 'onyx-command'); ?></h1>
    
    <div class="oc-upload-container">
        <div class="oc-upload-section">
            <h2><?php _e('Upload Module File', 'onyx-command'); ?></h2>
            <p><?php _e('Upload a PHP file or ZIP archive containing your module.', 'onyx-command'); ?></p>
            
            <form id="oc-upload-form" enctype="multipart/form-data">
                <?php wp_nonce_field('mm_admin_action', 'nonce'); ?>
                
                <div class="oc-file-upload">
                    <label for="module_file" class="oc-upload-label">
                        <div class="oc-upload-icon">📁</div>
                        <div class="oc-upload-text">
                            <span class="oc-upload-main"><?php _e('Choose a file or drag it here', 'onyx-command'); ?></span>
                            <span class="oc-upload-sub"><?php _e('Supported formats: .php, .zip (Max: 10MB)', 'onyx-command'); ?></span>
                        </div>
                    </label>
                    <input type="file" 
                           id="module_file" 
                           name="module_file" 
                           accept=".php,.zip" 
                           required 
                           style="display: none;">
                </div>
                
                <div id="oc-file-info" class="oc-file-info" style="display: none;">
                    <p><strong><?php _e('Selected file:', 'onyx-command'); ?></strong> <span id="oc-file-name"></span></p>
                    <p><strong><?php _e('Size:', 'onyx-command'); ?></strong> <span id="oc-file-size"></span></p>
                </div>
                
                <div class="oc-upload-actions">
                    <button type="submit" class="button button-primary button-large" id="oc-upload-btn">
                        <?php _e('Upload and Install Module', 'onyx-command'); ?>
                    </button>
                    <button type="button" class="button button-secondary button-large" id="oc-cancel-btn">
                        <?php _e('Cancel', 'onyx-command'); ?>
                    </button>
                </div>
                
                <div id="oc-upload-progress" class="oc-upload-progress" style="display: none;">
                    <div class="oc-progress-bar">
                        <div class="oc-progress-fill"></div>
                    </div>
                    <p class="oc-progress-text"><?php _e('Uploading...', 'onyx-command'); ?></p>
                </div>
                
                <div id="oc-upload-result" class="oc-upload-result" style="display: none;"></div>
            </form>
        </div>
        
        <div class="oc-upload-info">
            <h3><?php _e('Module Requirements', 'onyx-command'); ?></h3>
            <p><?php _e('Your module file must include the following headers:', 'onyx-command'); ?></p>
            <pre class="oc-code-example">
&lt;?php
/*
Module ID: unique-module-id
Module Name: My Awesome Module
Description: What this module does
Version: 1.0.0
Author: Your Name
*/

// Your module code here
?&gt;
            </pre>
            
            <h3><?php _e('Security Notice', 'onyx-command'); ?></h3>
            <ul class="oc-info-list">
                <li>✓ <?php _e('Modules are automatically scanned for syntax errors', 'onyx-command'); ?></li>
                <li>✓ <?php _e('Conflicts with existing plugins are detected', 'onyx-command'); ?></li>
                <li>✓ <?php _e('Security warnings are flagged for review', 'onyx-command'); ?></li>
                <li>✓ <?php _e('Only administrators can upload and manage modules', 'onyx-command'); ?></li>
            </ul>
            
            <h3><?php _e('Best Practices', 'onyx-command'); ?></h3>
            <ul class="oc-info-list">
                <li>Use unique function and class names</li>
                <li>Avoid global variables when possible</li>
                <li>Include proper error handling</li>
                <li>Test modules before activating</li>
                <li>Keep modules focused on single functionality</li>
            </ul>
        </div>
    </div>
</div>
