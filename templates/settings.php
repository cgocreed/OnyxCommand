<?php
/**
 * Settings Page Template with Tabs
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = OC_Settings::get_settings();
$api_keys = OC_Settings::get_all_api_keys();
$claude_key = isset($api_keys['claude']) ? $api_keys['claude'] : '';
$openai_key = isset($api_keys['openai']) ? $api_keys['openai'] : '';
$google_key = isset($api_keys['google']) ? $api_keys['google'] : '';
$google_secret = isset($api_keys['google_secret']) ? $api_keys['google_secret'] : '';
$bing_key = isset($api_keys['bing']) ? $api_keys['bing'] : '';
$facebook_key = isset($api_keys['facebook']) ? $api_keys['facebook'] : '';
$facebook_secret = isset($api_keys['facebook_secret']) ? $api_keys['facebook_secret'] : '';
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
$ignore_styling = get_option('oc_ignore_plugin_styling', false);

// Get module tabs
$module_tabs = OC_Settings::get_instance()->get_module_tabs();
?>
<div class="wrap oc-settings-page">
    <h1><?php _e('Onyx Command Settings', 'onyx-command'); ?></h1>
    
    <div class="oc-settings-layout">
        <!-- Left Sidebar Tabs -->
        <div class="oc-settings-tabs">
            <a href="#general" class="oc-tab-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>" data-tab="general">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e('General', 'onyx-command'); ?>
            </a>
            <a href="#api-keys" class="oc-tab-link <?php echo $active_tab === 'api-keys' ? 'active' : ''; ?>" data-tab="api-keys">
                <span class="dashicons dashicons-admin-network"></span>
                <?php _e('API Keys', 'onyx-command'); ?>
            </a>
            <?php foreach ($module_tabs as $tab_id => $tab_data): ?>
                <a href="#<?php echo esc_attr($tab_id); ?>" class="oc-tab-link <?php echo $active_tab === $tab_id ? 'active' : ''; ?>" data-tab="<?php echo esc_attr($tab_id); ?>">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php echo esc_html($tab_data['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Right Content Area -->
        <div class="oc-settings-content">
            <?php wp_nonce_field('oc_settings_nonce', 'oc_settings_nonce'); ?>
            
            <!-- General Tab -->
            <div id="general-tab" class="oc-tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;"><?php _e('General Settings', 'onyx-command'); ?></h2>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="ignore_plugin_styling" name="ignore_plugin_styling" value="1" <?php checked($ignore_styling, true); ?>>
                        <span><?php _e('Ignore Plugin Styling (Use Theme Styles)', 'onyx-command'); ?></span>
                    </label>
                </div>
                <p class="description"><?php _e('Customize the appearance of buttons throughout Onyx Command and all installed modules.', 'onyx-command'); ?></p>
                
                <div class="oc-settings-section">
                    <h3><?php _e('Button Styling', 'onyx-command'); ?></h3>
                    
                    <div class="oc-settings-grid">
                        <!-- Colors -->
                        <div class="oc-settings-group">
                            <h4><?php _e('Colors', 'onyx-command'); ?></h4>
                            
                            <div class="oc-setting-row">
                                <label for="button_bg_color"><?php _e('Background', 'onyx-command'); ?></label>
                                <input type="text" id="button_bg_color" name="button_bg_color" value="<?php echo esc_attr($settings['oc_button_bg_color']); ?>" class="oc-color-picker">
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_text_color"><?php _e('Text', 'onyx-command'); ?></label>
                                <input type="text" id="button_text_color" name="button_text_color" value="<?php echo esc_attr($settings['oc_button_text_color']); ?>" class="oc-color-picker">
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_hover_bg_color"><?php _e('Hover Background', 'onyx-command'); ?></label>
                                <input type="text" id="button_hover_bg_color" name="button_hover_bg_color" value="<?php echo esc_attr($settings['oc_button_hover_bg_color']); ?>" class="oc-color-picker">
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_hover_text_color"><?php _e('Hover Text', 'onyx-command'); ?></label>
                                <input type="text" id="button_hover_text_color" name="button_hover_text_color" value="<?php echo esc_attr($settings['oc_button_hover_text_color']); ?>" class="oc-color-picker">
                            </div>
                        </div>
                        
                        <!-- Dimensions -->
                        <div class="oc-settings-group">
                            <h4><?php _e('Dimensions', 'onyx-command'); ?></h4>
                            
                            <div class="oc-setting-row">
                                <label for="button_border_radius"><?php _e('Border Radius (px)', 'onyx-command'); ?></label>
                                <input type="number" id="button_border_radius" name="button_border_radius" value="<?php echo esc_attr($settings['oc_button_border_radius']); ?>" min="0" max="50">
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_padding_vertical"><?php _e('Vertical Padding (px)', 'onyx-command'); ?></label>
                                <input type="number" id="button_padding_vertical" name="button_padding_vertical" value="<?php echo esc_attr($settings['oc_button_padding_vertical']); ?>'" min="0" max="50">
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_padding_horizontal"><?php _e('Horizontal Padding (px)', 'onyx-command'); ?></label>
                                <input type="number" id="button_padding_horizontal" name="button_padding_horizontal" value="<?php echo esc_attr($settings['oc_button_padding_horizontal']); ?>" min="0" max="100">
                            </div>
                        </div>
                        
                        <!-- Typography -->
                        <div class="oc-settings-group">
                            <h4><?php _e('Typography', 'onyx-command'); ?></h4>
                            
                            <div class="oc-setting-row">
                                <label for="button_font_size"><?php _e('Font Size (pt)', 'onyx-command'); ?></label>
                                <input type="number" id="button_font_size" name="button_font_size" value="<?php echo esc_attr($settings['oc_button_font_size']); ?>" min="8" max="24">
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_font_weight"><?php _e('Font Weight', 'onyx-command'); ?></label>
                                <select id="button_font_weight" name="button_font_weight">
                                    <option value="normal" <?php selected($settings['oc_button_font_weight'], 'normal'); ?>>Normal</option>
                                    <option value="bold" <?php selected($settings['oc_button_font_weight'], 'bold'); ?>>Bold</option>
                                    <option value="600" <?php selected($settings['oc_button_font_weight'], '600'); ?>>Semi-Bold (600)</option>
                                    <option value="700" <?php selected($settings['oc_button_font_weight'], '700'); ?>>Bold (700)</option>
                                </select>
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_text_transform"><?php _e('Text Transform', 'onyx-command'); ?></label>
                                <select id="button_text_transform" name="button_text_transform">
                                    <option value="none" <?php selected($settings['oc_button_text_transform'], 'none'); ?>>None</option>
                                    <option value="uppercase" <?php selected($settings['oc_button_text_transform'], 'uppercase'); ?>>UPPERCASE</option>
                                    <option value="lowercase" <?php selected($settings['oc_button_text_transform'], 'lowercase'); ?>>lowercase</option>
                                    <option value="capitalize" <?php selected($settings['oc_button_text_transform'], 'capitalize'); ?>>Capitalize</option>
                                </select>
                            </div>
                            
                            <div class="oc-setting-row">
                                <label for="button_font_family"><?php _e('Font Family', 'onyx-command'); ?></label>
                                <select id="button_font_family" name="button_font_family">
                                    <option value="system" <?php selected($settings['oc_button_font_family'], 'system'); ?>>System Default</option>
                                    <option value="arial" <?php selected($settings['oc_button_font_family'], 'arial'); ?>>Arial</option>
                                    <option value="helvetica" <?php selected($settings['oc_button_font_family'], 'helvetica'); ?>>Helvetica</option>
                                    <option value="georgia" <?php selected($settings['oc_button_font_family'], 'georgia'); ?>>Georgia</option>
                                    <option value="times" <?php selected($settings['oc_button_font_family'], 'times'); ?>>Times New Roman</option>
                                    <option value="courier" <?php selected($settings['oc_button_font_family'], 'courier'); ?>>Courier</option>
                                    <option value="verdana" <?php selected($settings['oc_button_font_family'], 'verdana'); ?>>Verdana</option>
                                    <option value="tahoma" <?php selected($settings['oc_button_font_family'], 'tahoma'); ?>>Tahoma</option>
                                    <option value="trebuchet" <?php selected($settings['oc_button_font_family'], 'trebuchet'); ?>>Trebuchet MS</option>
                                    <option value="impact" <?php selected($settings['oc_button_font_family'], 'impact'); ?>>Impact</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="oc-settings-actions">
                    <button type="button" class="button button-primary oc-save-tab" data-tab="general">
                        <?php _e('Save Settings', 'onyx-command'); ?>
                    </button>
                    <button type="button" class="button oc-reset-settings">
                        <?php _e('Reset to Defaults', 'onyx-command'); ?>
                    </button>
                </div>
            </div>
            
            <!-- API Keys Tab -->
            <div id="api-keys-tab" class="oc-tab-content <?php echo $active_tab === 'api-keys' ? 'active' : ''; ?>">
                <h2><?php _e('API Keys', 'onyx-command'); ?></h2>
                <p class="description"><?php _e('Configure API keys that will be used across all modules. Set them once here, and all modules can access them.', 'onyx-command'); ?></p>
                
                <div class="oc-settings-section">
                    <h3>AI Services</h3>
                    
                    <div class="oc-setting-row">
                        <label for="claude_api_key">
                            <strong><?php _e('Claude API Key', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('Used by AI-powered modules for text generation and analysis', 'onyx-command'); ?></span>
                        </label>
                        <input type="text" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr($claude_key); ?>" class="regular-text" placeholder="sk-ant-api03-...">
                        <?php if (!empty($claude_key)): ?>
                            <p class="description"><?php _e('Current key: ', 'onyx-command'); echo esc_html(substr($claude_key, 0, 12) . '...' . substr($claude_key, -4)); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="oc-setting-row">
                        <label for="openai_api_key">
                            <strong><?php _e('OpenAI API Key', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('Used by modules that integrate with OpenAI services', 'onyx-command'); ?></span>
                        </label>
                        <input type="text" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr($openai_key); ?>" class="regular-text" placeholder="sk-...">
                        <?php if (!empty($openai_key)): ?>
                            <p class="description"><?php _e('Current key: ', 'onyx-command'); echo esc_html(substr($openai_key, 0, 12) . '...' . substr($openai_key, -4)); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="oc-settings-section">
                    <h3>Google Services</h3>
                    
                    <div class="oc-setting-row">
                        <label for="google_api_key">
                            <strong><?php _e('Google API Key', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('Used for Google services integration (Maps, Analytics, etc.)', 'onyx-command'); ?></span>
                        </label>
                        <input type="text" id="google_api_key" name="google_api_key" value="<?php echo esc_attr($google_key); ?>" class="regular-text" placeholder="AIza...">
                        <?php if (!empty($google_key)): ?>
                            <p class="description"><?php _e('Current key: ', 'onyx-command'); echo esc_html(substr($google_key, 0, 12) . '...' . substr($google_key, -4)); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="oc-setting-row">
                        <label for="google_secret_key">
                            <strong><?php _e('Google Secret Key', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('OAuth client secret for Google services', 'onyx-command'); ?></span>
                        </label>
                        <input type="password" id="google_secret_key" name="google_secret_key" value="<?php echo esc_attr($google_secret); ?>" class="regular-text" placeholder="GOCSPX-...">
                        <?php if (!empty($google_secret)): ?>
                            <p class="description"><?php _e('Secret key is set', 'onyx-command'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="oc-settings-section">
                    <h3>Search & Social Services</h3>
                    
                    <div class="oc-setting-row">
                        <label for="bing_api_key">
                            <strong><?php _e('Bing API Key', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('Used for Bing Search API and related services', 'onyx-command'); ?></span>
                        </label>
                        <input type="text" id="bing_api_key" name="bing_api_key" value="<?php echo esc_attr($bing_key); ?>" class="regular-text">
                        <?php if (!empty($bing_key)): ?>
                            <p class="description"><?php _e('Current key: ', 'onyx-command'); echo esc_html(substr($bing_key, 0, 12) . '...' . substr($bing_key, -4)); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="oc-setting-row">
                        <label for="facebook_api_key">
                            <strong><?php _e('Facebook App ID', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('Facebook App ID for social integration', 'onyx-command'); ?></span>
                        </label>
                        <input type="text" id="facebook_api_key" name="facebook_api_key" value="<?php echo esc_attr($facebook_key); ?>" class="regular-text">
                        <?php if (!empty($facebook_key)): ?>
                            <p class="description"><?php _e('App ID is set', 'onyx-command'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="oc-setting-row">
                        <label for="facebook_secret_key">
                            <strong><?php _e('Facebook App Secret', 'onyx-command'); ?></strong>
                            <span class="description"><?php _e('Facebook App Secret for authentication', 'onyx-command'); ?></span>
                        </label>
                        <input type="password" id="facebook_secret_key" name="facebook_secret_key" value="<?php echo esc_attr($facebook_secret); ?>" class="regular-text">
                        <?php if (!empty($facebook_secret)): ?>
                            <p class="description"><?php _e('App Secret is set', 'onyx-command'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="oc-api-keys-info">
                    <h3><?php _e('How to Get API Keys', 'onyx-command'); ?></h3>
                    <div class="oc-info-grid">
                        <div class="oc-info-card">
                            <h4>Claude (Anthropic)</h4>
                            <ol>
                                <li>Visit <a href="https://console.anthropic.com" target="_blank">Anthropic Console</a></li>
                                <li>Sign in or create an account</li>
                                <li>Navigate to API Keys section</li>
                                <li>Create a new API key</li>
                                <li>Copy and paste it above</li>
                            </ol>
                        </div>
                        <div class="oc-info-card">
                            <h4>OpenAI</h4>
                            <ol>
                                <li>Visit <a href="https://platform.openai.com" target="_blank">OpenAI Platform</a></li>
                                <li>Sign in or create an account</li>
                                <li>Go to API Keys section</li>
                                <li>Create a new secret key</li>
                                <li>Copy and paste it above</li>
                            </ol>
                        </div>
                        <div class="oc-info-card">
                            <h4>Google</h4>
                            <ol>
                                <li>Visit <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                                <li>Create or select a project</li>
                                <li>Enable required APIs</li>
                                <li>Create credentials (API Key & OAuth)</li>
                                <li>Copy and paste them above</li>
                            </ol>
                        </div>
                        <div class="oc-info-card">
                            <h4>Facebook</h4>
                            <ol>
                                <li>Visit <a href="https://developers.facebook.com" target="_blank">Facebook Developers</a></li>
                                <li>Create an app</li>
                                <li>Get App ID and App Secret</li>
                                <li>Configure app settings</li>
                                <li>Copy and paste credentials above</li>
                            </ol>
                        </div>
                    </div>
                    <p class="oc-security-note"><strong><?php _e('Security Note:', 'onyx-command'); ?></strong> <?php _e('Your API keys are stored securely in your WordPress database and are never shared with third parties.', 'onyx-command'); ?></p>
                </div>
                
                <div class="oc-settings-actions">
                    <button type="button" class="button button-primary oc-save-tab" data-tab="api-keys">
                        <?php _e('Save API Keys', 'onyx-command'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Module Tabs -->
            <?php foreach ($module_tabs as $tab_id => $tab_data): ?>
                <div id="<?php echo esc_attr($tab_id); ?>-tab" class="oc-tab-content <?php echo $active_tab === $tab_id ? 'active' : ''; ?>">
                    <?php 
                    if (is_callable($tab_data['callback'])) {
                        call_user_func($tab_data['callback']);
                    }
                    ?>
                    <div class="oc-settings-actions">
                        <button type="button" class="button button-primary oc-save-tab" data-tab="<?php echo esc_attr($tab_id); ?>">
                            <?php _e('Save Settings', 'onyx-command'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="oc-settings-message"></div>
        </div>
    </div>
</div>

<style>
.oc-settings-layout {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.oc-settings-tabs {
    flex: 0 0 200px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 10px 0;
}

.oc-tab-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    color: #2c3338;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.oc-tab-link:hover {
    background: #f6f7f7;
    color: #2271b1;
}

.oc-tab-link.active {
    background: #f0f6fc;
    border-left-color: #2271b1;
    color: #2271b1;
    font-weight: 600;
}

.oc-tab-link .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.oc-settings-content {
    flex: 1;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.oc-tab-content {
    display: none;
}

.oc-tab-content.active {
    display: block;
}

.oc-settings-section {
    margin-bottom: 30px;
}

.oc-settings-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
}

.oc-settings-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 15px;
}

.oc-settings-group {
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
}

.oc-settings-group h4 {
    margin-top: 0;
    margin-bottom: 12px;
    font-size: 14px;
    color: #1d2327;
    border-bottom: 1px solid #dcdcde;
    padding-bottom: 8px;
}

.oc-setting-row {
    margin-bottom: 15px;
}

.oc-setting-row:last-child {
    margin-bottom: 0;
}

.oc-setting-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 13px;
    color: #1d2327;
}

.oc-setting-row label .description {
    display: block;
    font-weight: normal;
    font-size: 12px;
    color: #646970;
    margin-top: 3px;
}

.oc-setting-row input[type="text"],
.oc-setting-row input[type="password"],
.oc-setting-row input[type="number"],
.oc-setting-row select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
}

.oc-setting-row input[type="text"].regular-text,
.oc-setting-row input[type="password"].regular-text {
    max-width: 100%;
}

.oc-settings-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #dcdcde;
    display: flex;
    gap: 10px;
}

.oc-settings-message {
    margin-top: 15px;
    padding: 12px;
    border-radius: 4px;
    font-size: 13px;
    display: none;
}

.oc-settings-message.success {
    background: #d7f0db;
    border: 1px solid #00a32a;
    color: #00a32a;
    display: block;
}

.oc-settings-message.error {
    background: #fcf0f1;
    border: 1px solid #d63638;
    color: #d63638;
    display: block;
}

.oc-api-keys-info {
    margin-top: 30px;
    padding: 20px;
    background: #f0f6fc;
    border-radius: 4px;
}

.oc-api-keys-info h3 {
    margin-top: 0;
    margin-bottom: 15px;
}

.oc-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 15px;
}

.oc-info-card {
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #c3c4c7;
}

.oc-info-card h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #2271b1;
}

.oc-info-card ol {
    margin: 0;
    padding-left: 20px;
}

.oc-info-card li {
    margin-bottom: 5px;
    font-size: 13px;
}

.oc-security-note {
    margin: 0;
    padding: 12px;
    background: #fff;
    border-left: 4px solid #2271b1;
    font-size: 13px;
}

@media screen and (max-width: 1200px) {
    .oc-settings-grid {
        grid-template-columns: 1fr;
    }
    
    .oc-info-grid {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 782px) {
    .oc-settings-layout {
        flex-direction: column;
    }
    
    .oc-settings-tabs {
        flex: 1;
        display: flex;
        overflow-x: auto;
        padding: 0;
    }
    
    .oc-tab-link {
        flex: 0 0 auto;
        border-left: none;
        border-bottom: 3px solid transparent;
    }
    
    .oc-tab-link.active {
        border-left: none;
        border-bottom-color: #2271b1;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize color pickers
    $('.oc-color-picker').wpColorPicker();
    
    // Tab switching
    $('.oc-tab-link').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        switchTab(tabId);
    });
    
    function switchTab(tabId) {
        $('.oc-tab-link').removeClass('active');
        $('.oc-tab-link[data-tab="' + tabId + '"]').addClass('active');
        $('.oc-tab-content').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');
        window.location.hash = tabId;
    }
    
    // Save general settings
    $('.oc-save-tab[data-tab="general"]').on('click', function() {
        var $message = $('.oc-settings-message');
        $message.removeClass('success error').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oc_save_settings',
                nonce: $('#oc_settings_nonce').val(),
                ignore_plugin_styling: $('#ignore_plugin_styling').is(':checked') ? 1 : 0,
                button_bg_color: $('#button_bg_color').val(),
                button_text_color: $('#button_text_color').val(),
                button_hover_bg_color: $('#button_hover_bg_color').val(),
                button_hover_text_color: $('#button_hover_text_color').val(),
                button_border_radius: $('#button_border_radius').val(),
                button_padding_vertical: $('#button_padding_vertical').val(),
                button_padding_horizontal: $('#button_padding_horizontal').val(),
                button_font_size: $('#button_font_size').val(),
                button_font_weight: $('#button_font_weight').val(),
                button_text_transform: $('#button_text_transform').val(),
                button_font_family: $('#button_font_family').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').html(response.data.message).show();
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $message.addClass('error').html(response.data).show();
                }
            }
        });
    });
    
    // Save API keys
    $('.oc-save-tab[data-tab="api-keys"]').on('click', function() {
        var $message = $('.oc-settings-message');
        $message.removeClass('success error').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oc_save_api_keys',
                nonce: $('#oc_settings_nonce').val(),
                claude_api_key: $('#claude_api_key').val(),
                openai_api_key: $('#openai_api_key').val(),
                google_api_key: $('#google_api_key').val(),
                google_secret_key: $('#google_secret_key').val(),
                bing_api_key: $('#bing_api_key').val(),
                facebook_api_key: $('#facebook_api_key').val(),
                facebook_secret_key: $('#facebook_secret_key').val()
            },
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').html(response.data.message).show();
                    setTimeout(function() { $message.fadeOut(); }, 3000);
                } else {
                    $message.addClass('error').html(response.data).show();
                }
            }
        });
    });
    
    // Reset settings
    $('.oc-reset-settings').on('click', function() {
        if (!confirm('Are you sure you want to reset all settings to defaults?')) {
            return;
        }
        
        var $message = $('.oc-settings-message');
        $message.removeClass('success error').hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oc_reset_settings',
                nonce: $('#oc_settings_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    $message.addClass('error').html(response.data).show();
                }
            }
        });
    });
    
    // Load tab from URL hash
    if (window.location.hash) {
        var hash = window.location.hash.substring(1);
        if ($('.oc-tab-link[data-tab="' + hash + '"]').length) {
            switchTab(hash);
        }
    }
});
</script>
