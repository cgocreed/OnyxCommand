<?php
/**
 * Module ID: ai-alt-tag-manager
 * Module Name: AI Alt Tag Manager
 * Description: Automatically generates SEO-friendly alt tags for images using Claude AI
 * Version: 1.0.0
 * Author: Callum Creed
 * 
 * IMPORTANT: This is a MODULE for Onyx Command plugin.
 * It CANNOT function as a standalone WordPress plugin.
 * It MUST be loaded through Onyx Command's module system.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

// Ensure this is loaded as a module
if (!defined('OC_PLUGIN_DIR')) {
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>AI Alt Tag Manager Error:</strong> This is a module for Onyx Command plugin. Please install and activate Onyx Command first, then add this as a module.</p></div>';
        });
    }
    return;
}

/**
 * AI Alt Tag Manager Class
 */
class AI_Alt_Tag_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Admin hooks
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add "Missing Alt Tags" page under Media menu
        add_action('admin_menu', array($this, 'add_media_page'));
        
        // Media hooks
        add_action('add_attachment', array($this, 'auto_generate_alt_tag'));
        add_filter('attachment_fields_to_edit', array($this, 'add_ai_button_to_media'), 10, 2);
        
        // Media library filters
        add_filter('restrict_manage_posts', array($this, 'add_media_filter_dropdown'));
        add_filter('pre_get_posts', array($this, 'filter_media_by_alt_tag'));
        
        // Media library columns
        add_filter('manage_media_columns', array($this, 'add_alt_tag_column'));
        add_action('manage_media_custom_column', array($this, 'display_alt_tag_column'), 10, 2);
        
        // Bulk actions
        add_filter('bulk_actions-upload', array($this, 'add_bulk_action'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_action'), 10, 3);
        
        // AJAX hooks
        add_action('wp_ajax_generate_ai_alt_tag', array($this, 'ajax_generate_alt_tag'));
        add_action('wp_ajax_bulk_generate_alt_tags', array($this, 'ajax_bulk_generate'));
        add_action('wp_ajax_save_alt_tag', array($this, 'ajax_save_alt_tag'));
        add_action('wp_ajax_clear_alt_tag', array($this, 'ajax_clear_alt_tag'));
        
        // Show admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if API key is configured
        $api_key = OC_Settings::get_api_key('claude');
        
        if (empty($api_key) && isset($_GET['page']) && strpos($_GET['page'], 'missing-alt-tags') !== false) {
            $settings_url = admin_url('admin.php?page=onyx-command-settings#api-keys');
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>AI Alt Tag Manager:</strong> Please configure your Claude API key in Onyx Command settings to use this module.</p>
                <p><a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">Configure API Key</a></p>
            </div>
            <?php
        }
        
        // Show bulk action result
        if (isset($_GET['bulk_ai_alt_tags_generated'])) {
            $count = intval($_GET['bulk_ai_alt_tags_generated']);
            echo '<div class="notice notice-success is-dismissible"><p><strong>AI Alt Tag Manager:</strong> Generated and saved ' . $count . ' alt tag(s) successfully!</p></div>';
        }
    }
    
    /**
     * Add media page
     */
    public function add_media_page() {
        add_media_page(
            'Missing Alt Tags',
            'Missing Alt Tags',
            'upload_files',
            'missing-alt-tags',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        wp_enqueue_style(
            'ai-alt-tag-manager-css',
            OC_PLUGIN_URL . 'modules/ai-alt-tag-manager/assets/style.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'ai-alt-tag-manager-js',
            OC_PLUGIN_URL . 'modules/ai-alt-tag-manager/assets/script.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('ai-alt-tag-manager-js', 'aiAltTagManager', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_alt_tag_nonce'),
            'strings' => array(
                'generating' => 'Generating...',
                'success' => 'Alt tag generated successfully!',
                'error' => 'Error generating alt tag. Please try again.'
            )
        ));
    }
    
    /**
     * Auto-generate alt tag on upload
     */
    public function auto_generate_alt_tag($attachment_id) {
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if (!empty($existing_alt)) {
            return;
        }
        
        $alt_tag = $this->generate_alt_tag($attachment_id);
        
        if ($alt_tag) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_tag);
        }
    }
    
    /**
     * Generate alt tag using Claude AI
     */
    private function generate_alt_tag($attachment_id) {
        // Get API key from centralized settings
        $api_key = OC_Settings::get_api_key('claude');
        
        if (empty($api_key)) {
            $GLOBALS['ai_alt_last_error'] = 'Claude API key not configured. Please set it in Onyx Command Settings > API Keys.';
            return false;
        }
        
        $image_path = get_attached_file($attachment_id);
        $image_path = str_replace('/', DIRECTORY_SEPARATOR, $image_path);
        
        if (!file_exists($image_path)) {
            $GLOBALS['ai_alt_last_error'] = 'Image file not found: ' . $image_path;
            return false;
        }
        
        $image_data = file_get_contents($image_path);
        $base64_image = base64_encode($image_data);
        $mime_type = get_post_mime_type($attachment_id);
        
        $api_url = 'https://api.anthropic.com/v1/messages';
        
        $request_body = array(
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 150,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'image',
                            'source' => array(
                                'type' => 'base64',
                                'media_type' => $mime_type,
                                'data' => $base64_image
                            )
                        ),
                        array(
                            'type' => 'text',
                            'text' => 'Generate a concise, SEO-friendly alt tag for this image. The alt tag should be descriptive, accurate, and between 5-15 words. Focus on the main subject and key details. Only respond with the alt tag text, nothing else.'
                        )
                    )
                )
            )
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($request_body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $GLOBALS['ai_alt_last_error'] = 'API request failed: ' . $response->get_error_message();
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_detail = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            $GLOBALS['ai_alt_last_error'] = 'API error (' . $response_code . '): ' . $error_detail;
            return false;
        }
        
        if (isset($body['content'][0]['text'])) {
            return sanitize_text_field(trim($body['content'][0]['text']));
        }
        
        return false;
    }
    
    /**
     * Add AI button to media edit page
     */
    public function add_ai_button_to_media($fields, $post) {
        if (!wp_attachment_is_image($post->ID)) {
            return $fields;
        }
        
        $current_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $has_alt = !empty($current_alt);
        $button_text = $has_alt ? 'Regenerate AI Alt Tag' : 'Generate AI Alt Tag';
        
        $fields['ai_alt_suggestion'] = array(
            'label' => 'AI Alt Tag Generator',
            'input' => 'html',
            'html' => '<button type="button" class="button button-primary ai-generate-alt-button" data-attachment-id="' . $post->ID . '">
                ' . $button_text . '
            </button>
            <span class="ai-alt-loading" style="display:none; margin-left: 10px;">⏳ Generating...</span>
            <div class="ai-alt-suggestion-result" style="margin-top: 10px; padding: 10px; background: #f0f0f0; border-left: 4px solid #2271b1; display: none;"></div>',
            'helps' => 'Click to generate an AI-powered alt tag that will be automatically applied to this image'
        );
        
        return $fields;
    }
    
    /**
     * AJAX handler for generating alt tag
     */
    public function ajax_generate_alt_tag() {
        check_ajax_referer('ai_alt_tag_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $GLOBALS['ai_alt_last_error'] = '';
        
        $alt_tag = $this->generate_alt_tag($attachment_id);
        
        if ($alt_tag) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_tag);
            wp_send_json_success(array(
                'alt_tag' => $alt_tag,
                'message' => 'Alt tag generated and saved successfully!'
            ));
        } else {
            $error_msg = !empty($GLOBALS['ai_alt_last_error']) ? $GLOBALS['ai_alt_last_error'] : 'Failed to generate alt tag';
            wp_send_json_error($error_msg);
        }
    }
    
    /**
     * AJAX handler for bulk generation
     */
    public function ajax_bulk_generate() {
        check_ajax_referer('ai_alt_tag_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('intval', $_POST['attachment_ids']) : array();
        $results = array();
        
        foreach ($attachment_ids as $attachment_id) {
            $alt_tag = $this->generate_alt_tag($attachment_id);
            
            if ($alt_tag) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_tag);
                $results[] = array('id' => $attachment_id, 'success' => true, 'alt_tag' => $alt_tag);
            } else {
                $results[] = array('id' => $attachment_id, 'success' => false);
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attachment_image_alt',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_wp_attachment_image_alt',
                    'value' => '',
                    'compare' => '='
                )
            )
        );
        
        $images = get_posts($args);
        
        ?>
        <div class="wrap ai-alt-tag-manager">
            <h1>Missing Alt Tags</h1>
            <p>These images are missing alt tags. You can generate them automatically or edit them manually.</p>
            
            <?php if (empty($images)): ?>
                <div class="notice notice-success">
                    <p><strong>Great!</strong> All your images have alt tags.</p>
                </div>
            <?php else: ?>
                <div class="ai-alt-actions">
                    <button type="button" class="button button-primary ai-bulk-generate">
                        Generate All Alt Tags Automatically
                    </button>
                    <span class="ai-progress" style="display: none;">
                        Processing: <span class="ai-progress-count">0</span> / <?php echo count($images); ?>
                    </span>
                </div>
                
                <div class="ai-images-grid">
                    <?php foreach ($images as $image): 
                        $file_path = get_attached_file($image->ID);
                        $file_size = $file_path ? size_format(filesize($file_path)) : 'Unknown';
                        $dimensions = wp_get_attachment_metadata($image->ID);
                        $width = isset($dimensions['width']) ? $dimensions['width'] : 0;
                        $height = isset($dimensions['height']) ? $dimensions['height'] : 0;
                        $mime_type = get_post_mime_type($image->ID);
                    ?>
                        <div class="ai-image-card" data-attachment-id="<?php echo $image->ID; ?>">
                            <div class="ai-image-preview">
                                <?php echo wp_get_attachment_image($image->ID, 'medium'); ?>
                            </div>
                            <div class="ai-image-details">
                                <h3><?php echo esc_html($image->post_title); ?></h3>
                                <div class="ai-image-meta">
                                    <span><?php echo $width . 'x' . $height; ?>px</span>
                                    <span><?php echo $file_size; ?></span>
                                    <span><?php echo strtoupper(str_replace('image/', '', $mime_type)); ?></span>
                                </div>
                                <input type="text" class="ai-alt-input" 
                                       placeholder="Enter alt tag or click Generate..." 
                                       data-attachment-id="<?php echo $image->ID; ?>">
                                <div class="ai-image-actions">
                                    <button type="button" class="button ai-generate-single" 
                                            data-attachment-id="<?php echo $image->ID; ?>">
                                        🤖 Generate AI Alt Tag
                                    </button>
                                    <button type="button" class="button button-primary ai-save-alt" 
                                            data-attachment-id="<?php echo $image->ID; ?>">
                                        💾 Save Alt Tag
                                    </button>
                                </div>
                                <div class="ai-result-message"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add filter dropdown to media library
     */
    public function add_media_filter_dropdown() {
        $scr = get_current_screen();
        if ($scr->base !== 'upload') return;
        
        $filter_value = isset($_GET['ai_alt_filter']) ? $_GET['ai_alt_filter'] : '';
        ?>
        <select name="ai_alt_filter">
            <option value="">All Images (Alt Tag Filter)</option>
            <option value="with_alt" <?php selected($filter_value, 'with_alt'); ?>>With Alt Tags</option>
            <option value="without_alt" <?php selected($filter_value, 'without_alt'); ?>>Without Alt Tags</option>
        </select>
        <?php
    }
    
    /**
     * Filter media library by alt tag presence
     */
    public function filter_media_by_alt_tag($query) {
        global $pagenow, $wpdb;
        
        if ($pagenow === 'upload.php' && isset($_GET['ai_alt_filter']) && $_GET['ai_alt_filter'] !== '') {
            $filter = $_GET['ai_alt_filter'];
            
            if ($filter === 'without_alt') {
                $ids_without_alt = $wpdb->get_col("
                    SELECT p.ID 
                    FROM {$wpdb->posts} p
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image%'
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->postmeta} pm
                        WHERE pm.post_id = p.ID
                        AND pm.meta_key = '_wp_attachment_image_alt'
                        AND pm.meta_value != ''
                    )
                ");
                
                if (!empty($ids_without_alt)) {
                    $query->set('post__in', $ids_without_alt);
                } else {
                    $query->set('post__in', array(0));
                }
            } elseif ($filter === 'with_alt') {
                $ids_with_alt = $wpdb->get_col("
                    SELECT DISTINCT pm.post_id
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_wp_attachment_image_alt'
                    AND pm.meta_value != ''
                    AND p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image%'
                ");
                
                if (!empty($ids_with_alt)) {
                    $query->set('post__in', $ids_with_alt);
                } else {
                    $query->set('post__in', array(0));
                }
            }
        }
        
        return $query;
    }
    
    /**
     * Add bulk action to media library
     */
    public function add_bulk_action($actions) {
        $actions['generate_ai_alt_tags'] = 'Generate AI Alt Tags';
        return $actions;
    }
    
    /**
     * Handle bulk action
     */
    public function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== 'generate_ai_alt_tags') {
            return $redirect_to;
        }
        
        $generated = 0;
        
        foreach ($post_ids as $post_id) {
            if (!wp_attachment_is_image($post_id)) {
                continue;
            }
            
            $alt_tag = $this->generate_alt_tag($post_id);
            
            if ($alt_tag) {
                update_post_meta($post_id, '_wp_attachment_image_alt', $alt_tag);
                $generated++;
            }
            
            sleep(2);
        }
        
        $redirect_to = add_query_arg('bulk_ai_alt_tags_generated', $generated, $redirect_to);
        return $redirect_to;
    }
    
    /**
     * Add Alt Tag column to media library
     */
    public function add_alt_tag_column($columns) {
        $columns['ai_alt_tag'] = 'Alt Tag';
        return $columns;
    }
    
    /**
     * Display Alt Tag column content
     */
    public function display_alt_tag_column($column_name, $post_id) {
        if ($column_name !== 'ai_alt_tag') {
            return;
        }
        
        if (!wp_attachment_is_image($post_id)) {
            echo '<span style="color: #999;">N/A</span>';
            return;
        }
        
        $alt_tag = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        
        ?>
        <div class="ai-alt-column-content" data-attachment-id="<?php echo $post_id; ?>">
            <div class="ai-alt-display" style="margin-bottom: 8px;">
                <?php if ($alt_tag): ?>
                    <strong style="color: #00a32a;">✓</strong> 
                    <span class="ai-alt-text"><?php echo esc_html($alt_tag); ?></span>
                <?php else: ?>
                    <strong style="color: #d63638;">✗</strong> 
                    <span class="ai-alt-text" style="color: #999;">No alt tag</span>
                <?php endif; ?>
            </div>
            <div class="ai-alt-actions">
                <button type="button" class="button button-small ai-generate-alt-inline" data-attachment-id="<?php echo $post_id; ?>">
                    <?php echo $alt_tag ? 'Regenerate' : 'Generate'; ?>
                </button>
                <?php if ($alt_tag): ?>
                    <button type="button" class="button button-small ai-clear-alt-inline" data-attachment-id="<?php echo $post_id; ?>" style="color: #d63638;">
                        Clear
                    </button>
                <?php endif; ?>
            </div>
            <div class="ai-alt-status" style="margin-top: 5px; font-size: 11px;"></div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for saving alt tag
     */
    public function ajax_save_alt_tag() {
        check_ajax_referer('ai_alt_tag_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $alt_tag = sanitize_text_field($_POST['alt_tag']);
        
        if (empty($alt_tag)) {
            wp_send_json_error('Alt tag cannot be empty');
        }
        
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_tag);
        wp_send_json_success(array('message' => 'Alt tag saved successfully!'));
    }
    
    /**
     * AJAX handler for clearing alt tag
     */
    public function ajax_clear_alt_tag() {
        check_ajax_referer('ai_alt_tag_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        delete_post_meta($attachment_id, '_wp_attachment_image_alt');
        
        wp_send_json_success(array('message' => 'Alt tag cleared successfully'));
    }
}

// Initialize the module
AI_Alt_Tag_Manager::get_instance();
