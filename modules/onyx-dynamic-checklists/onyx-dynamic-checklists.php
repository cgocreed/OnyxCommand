<?php
/**
 * Module ID: onyx-dynamic-checklists
 * Module Name: Dynamic Checklists
 * Description: AI-powered checklist management for posts and pages with sidebar accordions
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
            echo '<div class="notice notice-error"><p><strong>Dynamic Checklists Error:</strong> This is a module for Onyx Command plugin. Please install and activate Onyx Command first, then add this as a module.</p></div>';
        });
    }
    return;
}

/**
 * Dynamic Checklists Class
 */
class Onyx_Dynamic_Checklists {
    
    private static $instance = null;
    private $table_checklists;
    private $table_progress;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_checklists = $wpdb->prefix . 'oc_checklists';
        $this->table_progress = $wpdb->prefix . 'oc_checklist_progress';
        
        // Create tables on first load
        $this->create_tables();
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Meta box for posts and pages
        add_action('add_meta_boxes', array($this, 'add_checklist_meta_box'));
        
        // AJAX handlers
        add_action('wp_ajax_odc_save_checklist', array($this, 'ajax_save_checklist'));
        add_action('wp_ajax_odc_delete_checklist', array($this, 'ajax_delete_checklist'));
        add_action('wp_ajax_odc_simplify_checklist', array($this, 'ajax_simplify_checklist'));
        add_action('wp_ajax_odc_update_progress', array($this, 'ajax_update_progress'));
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql_checklists = "CREATE TABLE IF NOT EXISTS {$this->table_checklists} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            items longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_progress = "CREATE TABLE IF NOT EXISTS {$this->table_progress} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            checklist_id bigint(20) NOT NULL,
            checked_items longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_checklist (post_id, checklist_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_checklists);
        dbDelta($sql_progress);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'onyx-command',
            'Dynamic Checklists',
            'Checklists',
            'manage_options',
            'onyx-checklists',
            array($this, 'render_management_page')
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        // Only load on our pages and post/page edit screens
        if ($hook !== 'onyx-command_page_onyx-checklists' && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        wp_enqueue_script('jquery-ui-accordion');
        
        wp_localize_script('jquery', 'onyxChecklists', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('onyx_checklists_nonce')
        ));
    }
    
    /**
     * Add meta box to posts and pages
     */
    public function add_checklist_meta_box() {
        $screens = array('post', 'page');
        
        foreach ($screens as $screen) {
            add_meta_box(
                'onyx_checklists_meta_box',
                'Checklists',
                array($this, 'render_meta_box'),
                $screen,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        global $wpdb;
        
        // Get all checklists
        $checklists = $wpdb->get_results("SELECT * FROM {$this->table_checklists} ORDER BY name ASC");
        
        if (empty($checklists)) {
            echo '<p>No checklists available. <a href="' . admin_url('admin.php?page=onyx-checklists') . '">Create one</a></p>';
            return;
        }
        
        echo '<div class="onyx-checklists-accordion" id="onyx-checklists-accordion">';
        
        foreach ($checklists as $checklist) {
            // Get progress for this post
            $progress = $wpdb->get_var($wpdb->prepare(
                "SELECT checked_items FROM {$this->table_progress} WHERE post_id = %d AND checklist_id = %d",
                $post->ID,
                $checklist->id
            ));
            
            $checked_items = $progress ? json_decode($progress, true) : array();
            $items = json_decode($checklist->items, true);
            
            echo '<h3>' . esc_html($checklist->name) . '</h3>';
            echo '<div class="checklist-content">';
            
            if (!empty($checklist->description)) {
                echo '<p class="checklist-description">' . esc_html($checklist->description) . '</p>';
            }
            
            echo '<ul class="checklist-items" data-checklist-id="' . $checklist->id . '" data-post-id="' . $post->ID . '">';
            
            foreach ($items as $index => $item) {
                $is_checked = in_array($index, $checked_items);
                echo '<li>';
                echo '<label>';
                echo '<input type="checkbox" class="checklist-item-checkbox" data-item-index="' . $index . '" ' . checked($is_checked, true, false) . '>';
                echo ' ' . esc_html($item);
                echo '</label>';
                echo '</li>';
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add jQuery UI accordion initialization
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#onyx-checklists-accordion').accordion({
                collapsible: true,
                heightStyle: 'content'
            });
            
            // Handle checkbox changes
            $('.checklist-item-checkbox').on('change', function() {
                var $list = $(this).closest('.checklist-items');
                var checklistId = $list.data('checklist-id');
                var postId = $list.data('post-id');
                var checkedItems = [];
                
                $list.find('.checklist-item-checkbox:checked').each(function() {
                    checkedItems.push($(this).data('item-index'));
                });
                
                $.post(onyxChecklists.ajax_url, {
                    action: 'odc_update_progress',
                    nonce: onyxChecklists.nonce,
                    post_id: postId,
                    checklist_id: checklistId,
                    checked_items: checkedItems
                });
            });
        });
        </script>
        <style>
        .onyx-checklists-accordion { margin-top: 10px; }
        .onyx-checklists-accordion h3 { font-size: 13px; padding: 8px; margin: 0; cursor: pointer; background: #f0f0f1; }
        .checklist-content { padding: 10px; }
        .checklist-description { font-style: italic; color: #666; margin-bottom: 10px; }
        .checklist-items { list-style: none; margin: 0; padding: 0; }
        .checklist-items li { margin: 5px 0; }
        .checklist-items label { display: flex; align-items: center; }
        </style>
        <?php
    }
    
    /**
     * Render management page
     */
    public function render_management_page() {
        global $wpdb;
        
        $checklists = $wpdb->get_results("SELECT * FROM {$this->table_checklists} ORDER BY created_at DESC");
        
        ?>
        <div class="wrap onyx-checklists-page">
            <h1>Dynamic Checklists</h1>
            <p>Create and manage checklists that will appear in the sidebar of posts and pages.</p>
            
            <div class="onyx-checklists-layout" style="display: flex; gap: 20px;">
                <div class="checklists-list" style="flex: 1;">
                    <h2>Existing Checklists</h2>
                    
                    <?php if (empty($checklists)): ?>
                        <p>No checklists yet. Create your first one!</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Items</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checklists as $checklist): 
                                    $items = json_decode($checklist->items, true);
                                    $item_count = is_array($items) ? count($items) : 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($checklist->name); ?></strong></td>
                                        <td><?php echo $item_count; ?> items</td>
                                        <td><?php echo date('M j, Y', strtotime($checklist->created_at)); ?></td>
                                        <td>
                                            <button class="button delete-checklist" data-id="<?php echo $checklist->id; ?>">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="checklist-editor" style="flex: 1;">
                    <h2>Create New Checklist</h2>
                    
                    <form id="checklist-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="checklist-name">Checklist Name</label></th>
                                <td><input type="text" id="checklist-name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="checklist-description">Description</label></th>
                                <td><textarea id="checklist-description" rows="2" class="large-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="checklist-items">Items (one per line)</label></th>
                                <td><textarea id="checklist-items" rows="10" class="large-text" required></textarea></td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">Save Checklist</button>
                        </p>
                        
                        <div id="checklist-message" class="notice" style="display:none; margin-top:15px;"></div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#checklist-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post(onyxChecklists.ajax_url, {
                    action: 'odc_save_checklist',
                    nonce: onyxChecklists.nonce,
                    name: $('#checklist-name').val(),
                    description: $('#checklist-description').val(),
                    items: $('#checklist-items').val()
                }, function(response) {
                    if (response.success) {
                        $('#checklist-message').removeClass('notice-error').addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                        $('#checklist-form')[0].reset();
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        $('#checklist-message').removeClass('notice-success').addClass('notice-error').html('<p>' + response.data + '</p>').show();
                    }
                });
            });
            
            $('.delete-checklist').on('click', function() {
                if (!confirm('Are you sure you want to delete this checklist?')) return;
                
                var id = $(this).data('id');
                
                $.post(onyxChecklists.ajax_url, {
                    action: 'odc_delete_checklist',
                    nonce: onyxChecklists.nonce,
                    id: id
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Save checklist
     */
    public function ajax_save_checklist() {
        check_ajax_referer('onyx_checklists_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $items_text = sanitize_textarea_field($_POST['items']);
        
        // Convert items to array
        $items = array_filter(array_map('trim', explode("\n", $items_text)));
        $items_json = json_encode(array_values($items));
        
        // Insert new
        $result = $wpdb->insert(
            $this->table_checklists,
            array(
                'name' => $name,
                'description' => $description,
                'items' => $items_json
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Checklist saved successfully!'));
        } else {
            wp_send_json_error('Failed to save checklist');
        }
    }
    
    /**
     * AJAX: Delete checklist
     */
    public function ajax_delete_checklist() {
        check_ajax_referer('onyx_checklists_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $id = intval($_POST['id']);
        
        // Delete checklist
        $wpdb->delete($this->table_checklists, array('id' => $id), array('%d'));
        
        // Delete all progress records
        $wpdb->delete($this->table_progress, array('checklist_id' => $id), array('%d'));
        
        wp_send_json_success(array('message' => 'Checklist deleted successfully!'));
    }
    
    /**
     * AJAX: Update checklist progress
     */
    public function ajax_update_progress() {
        check_ajax_referer('onyx_checklists_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $post_id = intval($_POST['post_id']);
        $checklist_id = intval($_POST['checklist_id']);
        $checked_items = isset($_POST['checked_items']) ? array_map('intval', $_POST['checked_items']) : array();
        
        $checked_json = json_encode($checked_items);
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_progress} WHERE post_id = %d AND checklist_id = %d",
            $post_id,
            $checklist_id
        ));
        
        if ($exists) {
            $wpdb->update(
                $this->table_progress,
                array('checked_items' => $checked_json),
                array('post_id' => $post_id, 'checklist_id' => $checklist_id),
                array('%s'),
                array('%d', '%d')
            );
        } else {
            $wpdb->insert(
                $this->table_progress,
                array(
                    'post_id' => $post_id,
                    'checklist_id' => $checklist_id,
                    'checked_items' => $checked_json
                ),
                array('%d', '%d', '%s')
            );
        }
        
        wp_send_json_success(array('message' => 'Progress saved'));
    }
}

// Initialize the module
Onyx_Dynamic_Checklists::get_instance();
