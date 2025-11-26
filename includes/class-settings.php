<?php
/**
 * Settings Management Class
 * 
 * Handles plugin-wide settings, API keys, and module settings with tab interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class OC_Settings {
    
    private static $instance = null;
    private $module_settings_tabs = array();
    
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
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_assets'));
        add_action('wp_ajax_oc_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_oc_save_api_keys', array($this, 'ajax_save_api_keys'));
        add_action('wp_ajax_oc_save_module_settings', array($this, 'ajax_save_module_settings'));
        add_action('wp_ajax_oc_reset_settings', array($this, 'ajax_reset_settings'));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('oc_settings_group', 'oc_ignore_plugin_styling');
        
        // Button styling settings
        register_setting('oc_settings_group', 'oc_button_bg_color');
        register_setting('oc_settings_group', 'oc_button_text_color');
        register_setting('oc_settings_group', 'oc_button_hover_bg_color');
        register_setting('oc_settings_group', 'oc_button_hover_text_color');
        register_setting('oc_settings_group', 'oc_button_border_radius');
        register_setting('oc_settings_group', 'oc_button_padding_vertical');
        register_setting('oc_settings_group', 'oc_button_padding_horizontal');
        register_setting('oc_settings_group', 'oc_button_font_size');
        register_setting('oc_settings_group', 'oc_button_font_weight');
        register_setting('oc_settings_group', 'oc_button_text_transform');
        register_setting('oc_settings_group', 'oc_button_font_family');
        
        // API Keys
        register_setting('oc_api_keys_group', 'oc_api_keys');
    }
    
    /**
     * Register a module settings tab
     */
    public function register_module_tab($tab_id, $tab_name, $render_callback) {
        $this->module_settings_tabs[$tab_id] = array(
            'name' => $tab_name,
            'callback' => $render_callback
        );
    }
    
    /**
     * Get registered module tabs
     */
    public function get_module_tabs() {
        return apply_filters('oc_settings_module_tabs', $this->module_settings_tabs);
    }
    
    /**
     * Get default settings
     */
    public static function get_defaults() {
        return array(
            'oc_ignore_plugin_styling' => false,
            'oc_button_bg_color' => '#ECAE3D',
            'oc_button_text_color' => '#000000',
            'oc_button_hover_bg_color' => '#000000',
            'oc_button_hover_text_color' => '#ffffff',
            'oc_button_border_radius' => '0',
            'oc_button_padding_vertical' => '5',
            'oc_button_padding_horizontal' => '10',
            'oc_button_font_size' => '12',
            'oc_button_font_weight' => 'normal',
            'oc_button_text_transform' => 'uppercase',
            'oc_button_font_family' => 'system'
        );
    }
    
    /**
     * Get current settings (with defaults as fallback)
     */
    public static function get_settings() {
        $defaults = self::get_defaults();
        $settings = array();
        
        foreach ($defaults as $key => $default) {
            $settings[$key] = get_option($key, $default);
        }
        
        return $settings;
    }
    
    /**
     * Get API key by name
     */
    public static function get_api_key($key_name) {
        $api_keys = get_option('oc_api_keys', array());
        return isset($api_keys[$key_name]) ? $api_keys[$key_name] : false;
    }
    
    /**
     * Set API key
     */
    public static function set_api_key($key_name, $key_value) {
        $api_keys = get_option('oc_api_keys', array());
        $api_keys[$key_name] = sanitize_text_field($key_value);
        update_option('oc_api_keys', $api_keys);
    }
    
    /**
     * Get all API keys
     */
    public static function get_all_api_keys() {
        return get_option('oc_api_keys', array());
    }
    
    /**
     * Enqueue settings page assets
     */
    public function enqueue_settings_assets($hook) {
        if ($hook !== 'onyx-command_page_onyx-command-settings') {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }
    
    /**
     * AJAX: Save general settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('oc_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $settings = array(
            'oc_ignore_plugin_styling' => isset($_POST['ignore_plugin_styling']) ? (bool)$_POST['ignore_plugin_styling'] : false,
            'oc_button_bg_color' => sanitize_hex_color($_POST['button_bg_color']),
            'oc_button_text_color' => sanitize_hex_color($_POST['button_text_color']),
            'oc_button_hover_bg_color' => sanitize_hex_color($_POST['button_hover_bg_color']),
            'oc_button_hover_text_color' => sanitize_hex_color($_POST['button_hover_text_color']),
            'oc_button_border_radius' => intval($_POST['button_border_radius']),
            'oc_button_padding_vertical' => intval($_POST['button_padding_vertical']),
            'oc_button_padding_horizontal' => intval($_POST['button_padding_horizontal']),
            'oc_button_font_size' => intval($_POST['button_font_size']),
            'oc_button_font_weight' => sanitize_text_field($_POST['button_font_weight']),
            'oc_button_text_transform' => sanitize_text_field($_POST['button_text_transform']),
            'oc_button_font_family' => sanitize_text_field($_POST['button_font_family'])
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        wp_send_json_success(array('message' => 'Settings saved successfully!'));
    }
    
    /**
     * AJAX: Save API keys
     */
    public function ajax_save_api_keys() {
        check_ajax_referer('oc_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $api_keys = array();
        
        // Core API keys
        if (isset($_POST['claude_api_key'])) {
            $api_keys['claude'] = sanitize_text_field($_POST['claude_api_key']);
        }
        if (isset($_POST['openai_api_key'])) {
            $api_keys['openai'] = sanitize_text_field($_POST['openai_api_key']);
        }
        if (isset($_POST['google_api_key'])) {
            $api_keys['google'] = sanitize_text_field($_POST['google_api_key']);
        }
        if (isset($_POST['google_secret_key'])) {
            $api_keys['google_secret'] = sanitize_text_field($_POST['google_secret_key']);
        }
        if (isset($_POST['bing_api_key'])) {
            $api_keys['bing'] = sanitize_text_field($_POST['bing_api_key']);
        }
        if (isset($_POST['facebook_api_key'])) {
            $api_keys['facebook'] = sanitize_text_field($_POST['facebook_api_key']);
        }
        if (isset($_POST['facebook_secret_key'])) {
            $api_keys['facebook_secret'] = sanitize_text_field($_POST['facebook_secret_key']);
        }
        
        // Allow modules to add their own API keys
        $api_keys = apply_filters('oc_save_api_keys', $api_keys, $_POST);
        
        update_option('oc_api_keys', $api_keys);
        
        wp_send_json_success(array('message' => 'API keys saved successfully!'));
    }
    
    /**
     * AJAX: Save module-specific settings
     */
    public function ajax_save_module_settings() {
        check_ajax_referer('oc_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $module_id = sanitize_text_field($_POST['module_id']);
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        do_action('oc_save_module_settings_' . $module_id, $settings);
        
        wp_send_json_success(array('message' => 'Module settings saved successfully!'));
    }
    
    /**
     * AJAX: Reset settings to defaults
     */
    public function ajax_reset_settings() {
        check_ajax_referer('oc_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $defaults = self::get_defaults();
        
        foreach ($defaults as $key => $value) {
            update_option($key, $value);
        }
        
        wp_send_json_success(array(
            'message' => 'Settings reset to defaults!',
            'settings' => $defaults
        ));
    }
    
    /**
     * Generate dynamic CSS based on settings
     */
    public static function generate_dynamic_css() {
        $settings = self::get_settings();
        
        // If ignore plugin styling is enabled, return empty CSS
        if ($settings['oc_ignore_plugin_styling']) {
            return '';
        }
        
        $font_families = array(
            'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'arial' => 'Arial, Helvetica, sans-serif',
            'helvetica' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
            'georgia' => 'Georgia, "Times New Roman", Times, serif',
            'times' => '"Times New Roman", Times, serif',
            'courier' => '"Courier New", Courier, monospace',
            'verdana' => 'Verdana, Geneva, sans-serif',
            'tahoma' => 'Tahoma, Geneva, sans-serif',
            'trebuchet' => '"Trebuchet MS", Helvetica, sans-serif',
            'impact' => 'Impact, Charcoal, sans-serif'
        );
        
        $font_family = isset($font_families[$settings['oc_button_font_family']]) 
            ? $font_families[$settings['oc_button_font_family']] 
            : $font_families['system'];
        
        $css = "
/* Onyx Command Dynamic Button Styling */
.wrap.onyx-command .button,
.wrap.onyx-command .button-primary,
.wrap.onyx-command .button-secondary,
.wrap.onyx-command .button-hero,
.oc-section .button,
.oc-actions .button,
.oc-quick-actions .button,
.oc-quick-actions .button-hero,
.oc-view-stats,
.oc-delete-module,
.oc-configure-module,
.oc-scan-module,
button.oc-upload-btn,
.oc-optimizer-actions .button,
.oc-fix-button,
.oc-clear-cache,
.ai-alt-actions .button,
.ai-alt-actions .button-primary,
.ai-image-actions .button,
.ai-image-actions .ai-generate-single,
.ai-image-actions .ai-save-alt,
.ai-generate-alt-button,
.ai-generate-alt-inline,
.ai-clear-alt-inline,
.ai-save-alt,
.ai-generate-single,
.ai-bulk-generate,
.checklist-action-button {
    background: {$settings['oc_button_bg_color']} !important;
    color: {$settings['oc_button_text_color']} !important;
    border: none !important;
    border-radius: {$settings['oc_button_border_radius']}px !important;
    padding: {$settings['oc_button_padding_vertical']}px {$settings['oc_button_padding_horizontal']}px !important;
    font-size: {$settings['oc_button_font_size']}pt !important;
    font-weight: {$settings['oc_button_font_weight']} !important;
    text-transform: {$settings['oc_button_text_transform']} !important;
    font-family: {$font_family} !important;
    transition: all 0.3s ease !important;
    box-shadow: none !important;
    text-shadow: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    line-height: 1.4 !important;
    max-width: 200px !important;
    width: auto !important;
    min-width: 120px !important;
    white-space: normal !important;
    word-wrap: break-word !important;
    text-align: center !important;
    text-decoration: none !important;
    vertical-align: middle !important;
}

.wrap.onyx-command .button:hover,
.wrap.onyx-command .button-primary:hover,
.wrap.onyx-command .button-secondary:hover,
.wrap.onyx-command .button-hero:hover,
.oc-section .button:hover,
.oc-actions .button:hover,
.oc-quick-actions .button:hover,
.oc-quick-actions .button-hero:hover,
.oc-view-stats:hover,
.oc-delete-module:hover,
.oc-configure-module:hover,
.oc-scan-module:hover,
button.oc-upload-btn:hover,
.oc-optimizer-actions .button:hover,
.oc-fix-button:hover,
.oc-clear-cache:hover,
.ai-alt-actions .button:hover,
.ai-alt-actions .button-primary:hover,
.ai-image-actions .button:hover,
.ai-image-actions .ai-generate-single:hover,
.ai-image-actions .ai-save-alt:hover,
.ai-generate-alt-button:hover,
.ai-generate-alt-inline:hover,
.ai-clear-alt-inline:hover,
.ai-save-alt:hover,
.ai-generate-single:hover,
.ai-bulk-generate:hover,
.checklist-action-button:hover {
    background: {$settings['oc_button_hover_bg_color']} !important;
    color: {$settings['oc_button_hover_text_color']} !important;
    transform: none !important;
    box-shadow: none !important;
}

/* Exclude settings page buttons from custom styling */
.oc-settings-page .oc-save-settings,
.oc-settings-page .oc-reset-settings,
.oc-settings-page .button-primary {
    background: #2271b1 !important;
    color: #fff !important;
    text-transform: none !important;
    padding: 8px 16px !important;
    font-size: 13px !important;
    border-radius: 3px !important;
    max-width: none !important;
    min-width: auto !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif !important;
}

.oc-settings-page .oc-save-settings:hover,
.oc-settings-page .button-primary:hover {
    background: #135e96 !important;
}

.oc-settings-page .oc-reset-settings {
    background: #dcdcde !important;
    color: #2c3338 !important;
}

.oc-settings-page .oc-reset-settings:hover {
    background: #c3c4c7 !important;
}
";
        
        return $css;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        require_once OC_PLUGIN_DIR . 'templates/settings.php';
    }
}
