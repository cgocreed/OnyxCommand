<?php
/**
 * Module ID: plugin-deletion-manager
 * Module Name: Plugin Deletion Manager
 * Description: Comprehensive deletion archive with cloud backup, configurable retention, complete WordPress deletion interception
 * Version: 3.1.0
 * Author: Callum Creed
 */

if (!defined('ABSPATH')) exit;

if (!defined('OC_PLUGIN_DIR')) {
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Plugin Deletion Manager:</strong> Requires Onyx Command plugin.</p></div>';
        });
    }
    return;
}

class Plugin_Deletion_Manager {
    
    private static $instance = null;
    private $mu_plugin_file = 'onyx-plugin-deletion-manager.php';
    private $mu_plugins_dir;
    private $archive_dir;
    private $table_name;
    private $settings_key = 'oc_deletion_manager_settings';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        
        $this->mu_plugins_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : (WP_CONTENT_DIR . '/mu-plugins');
        $this->archive_dir = WP_CONTENT_DIR . '/oc-deletion-archive';
        $this->table_name = $wpdb->prefix . 'oc_deletion_archive';
        
        $this->create_table();
        $this->install_mu_plugin();
        $this->ensure_archive_dir();
        $this->register_deletion_hooks();
        
        // Intercept plugin deletion at WordPress level
        add_action('admin_init', array($this, 'intercept_plugin_deletion'), 1);
        
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_filter('oc_settings_tabs', array($this, 'add_settings_tab'));
        add_action('oc_settings_tab_deletion_manager', array($this, 'render_settings_tab'));
        
        // AJAX handlers
        add_action('wp_ajax_pdm_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_pdm_get_plugin_info', array($this, 'ajax_get_plugin_info'));
        add_action('wp_ajax_pdm_delete_plugin', array($this, 'ajax_delete_plugin'));
        add_action('wp_ajax_pdm_restore_item', array($this, 'ajax_restore_item'));
        add_action('wp_ajax_pdm_permanent_delete', array($this, 'ajax_permanent_delete'));
        add_action('wp_ajax_pdm_empty_archive', array($this, 'ajax_empty_archive'));
        add_action('wp_ajax_pdm_download_backup', array($this, 'ajax_download_backup'));
        add_action('wp_ajax_pdm_backup_to_cloud', array($this, 'ajax_backup_to_cloud'));
        add_action('wp_ajax_pdm_connect_dropbox', array($this, 'ajax_connect_dropbox'));
        add_action('wp_ajax_pdm_connect_gdrive', array($this, 'ajax_connect_gdrive'));
        
        add_action('pdm_daily_cleanup', array($this, 'cleanup_expired_archives'));
        if (!wp_next_scheduled('pdm_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pdm_daily_cleanup');
        }
        
        add_action('admin_init', array($this, 'handle_oauth_callback'));
    }
    
    /**
     * Intercept WordPress plugin deletion before it happens
     */
    public function intercept_plugin_deletion() {
        global $pagenow;
        
        if ($pagenow !== 'plugins.php') return;
        
        // Check for delete-selected action
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'delete-selected') {
            // Verify nonce - WordPress uses different nonce names
            $nonce_valid = false;
            if (isset($_REQUEST['_wpnonce'])) {
                $nonce_valid = wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-plugins');
            }
            
            if ($nonce_valid && isset($_REQUEST['checked']) && is_array($_REQUEST['checked'])) {
                // Redirect to our handler instead of letting WordPress delete
                $plugins = array_map('sanitize_text_field', $_REQUEST['checked']);
                
                if (count($plugins) === 1) {
                    // Single plugin - redirect to our AJAX handler page
                    wp_redirect(admin_url('admin.php?page=pdm-handle-delete&plugin=' . urlencode($plugins[0])));
                    exit;
                }
            }
        }
    }
    
    private function get_settings() {
        $defaults = array(
            'retention_days' => 7,
            'dropbox_token' => '',
            'dropbox_connected' => false,
            'gdrive_token' => '',
            'gdrive_connected' => false,
            'gdrive_refresh_token' => '',
            'gdrive_client_id' => '',
            'gdrive_client_secret' => ''
        );
        return wp_parse_args(get_option($this->settings_key, array()), $defaults);
    }
    
    private function get_retention_days() {
        $settings = $this->get_settings();
        $days = intval($settings['retention_days']);
        return $days === 0 ? 0 : max(7, $days);
    }
    
    private function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_type varchar(50) NOT NULL,
            item_id varchar(255) NOT NULL,
            item_name varchar(255) NOT NULL,
            item_slug varchar(255),
            item_version varchar(50),
            item_author varchar(255),
            item_description text,
            archive_path varchar(500),
            original_path varchar(500),
            file_size bigint(20) DEFAULT 0,
            file_count int(11) DEFAULT 0,
            deleted_data longtext,
            delete_type varchar(50) DEFAULT 'files_only',
            deleted_by bigint(20),
            deleted_at datetime NOT NULL,
            expires_at datetime DEFAULT NULL,
            restored_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'archived',
            metadata longtext,
            PRIMARY KEY (id),
            KEY item_type (item_type),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function ensure_archive_dir() {
        if (!file_exists($this->archive_dir)) {
            wp_mkdir_p($this->archive_dir);
        }
        if (!file_exists($this->archive_dir . '/.htaccess')) {
            file_put_contents($this->archive_dir . '/.htaccess', "deny from all\n");
        }
        if (!file_exists($this->archive_dir . '/index.php')) {
            file_put_contents($this->archive_dir . '/index.php', '<?php // Silence');
        }
        foreach (array('plugins', 'themes', 'posts', 'pages', 'media', 'other', 'backups') as $subdir) {
            $path = $this->archive_dir . '/' . $subdir;
            if (!file_exists($path)) wp_mkdir_p($path);
        }
    }
    
    private function register_deletion_hooks() {
        add_action('wp_trash_post', array($this, 'intercept_trash'), 1, 1);
        add_action('before_delete_post', array($this, 'archive_post'), 10, 2);
        add_action('delete_attachment', array($this, 'archive_attachment'), 10, 2);
        add_filter('pre_delete_theme', array($this, 'archive_theme'), 10, 2);
        add_action('delete_comment', array($this, 'archive_comment'), 10, 2);
        add_action('delete_user', array($this, 'archive_user'), 10, 3);
    }
    
    public function intercept_trash($post_id) {
        $post = get_post($post_id);
        if (!$post) return;
        if (in_array($post->post_type, array('revision', 'auto-draft', 'nav_menu_item'))) return;
        if ($post->post_type === 'attachment') return;
        
        remove_action('wp_trash_post', array($this, 'intercept_trash'), 1);
        $this->archive_post($post_id, $post);
        wp_delete_post($post_id, true);
        
        if (wp_doing_ajax()) {
            wp_send_json_success(array('message' => 'Moved to Deletion Archive'));
        } else {
            wp_redirect(admin_url('admin.php?page=pdm-archive&message=archived'));
            exit;
        }
    }
    
    public function archive_post($post_id, $post = null) {
        if (!$post) $post = get_post($post_id);
        if (!$post) return;
        if (in_array($post->post_type, array('revision', 'auto-draft', 'nav_menu_item'))) return;
        if ($post->post_type === 'attachment') return;
        
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE item_type = %s AND item_id = %s AND status = 'archived'",
            $post->post_type, $post_id
        ));
        if ($exists) return;
        
        $meta = get_post_meta($post_id);
        $taxonomies = array();
        foreach (get_object_taxonomies($post->post_type) as $tax_name) {
            $terms = wp_get_object_terms($post_id, $tax_name);
            if (!is_wp_error($terms)) $taxonomies[$tax_name] = $terms;
        }
        
        $data = array(
            'post' => (array) $post,
            'meta' => $meta,
            'taxonomies' => $taxonomies,
            'thumbnail_id' => get_post_thumbnail_id($post_id),
            'post_type' => $post->post_type
        );
        
        $this->create_archive_record(
            $post->post_type === 'page' ? 'page' : 'post',
            $post_id,
            $post->post_title ?: '(No Title)',
            $post->post_name,
            null,
            get_the_author_meta('display_name', $post->post_author),
            wp_trim_words(wp_strip_all_tags($post->post_content), 30),
            null, null, 0, 0, $data, 'complete'
        );
    }
    
    public function archive_attachment($attachment_id, $attachment = null) {
        if (!$attachment) $attachment = get_post($attachment_id);
        if (!$attachment) return;
        
        $meta = wp_get_attachment_metadata($attachment_id);
        $file_path = get_attached_file($attachment_id);
        
        $archive_path = null;
        $file_size = 0;
        $file_count = 1;
        
        if ($file_path && file_exists($file_path)) {
            $archive_subdir = $this->archive_dir . '/media/' . date('Y/m');
            wp_mkdir_p($archive_subdir);
            
            $archive_path = $archive_subdir . '/' . $attachment_id . '_' . basename($file_path);
            copy($file_path, $archive_path);
            $file_size = filesize($file_path);
            
            if (!empty($meta['sizes'])) {
                $upload_dir = dirname($file_path);
                foreach ($meta['sizes'] as $size_data) {
                    $size_file = $upload_dir . '/' . $size_data['file'];
                    if (file_exists($size_file)) {
                        copy($size_file, $archive_subdir . '/' . $attachment_id . '_' . $size_data['file']);
                        $file_size += filesize($size_file);
                        $file_count++;
                    }
                }
            }
        }
        
        $data = array(
            'attachment' => (array) $attachment,
            'meta' => $meta,
            'file_path' => $file_path,
            'post_meta' => get_post_meta($attachment_id)
        );
        
        $this->create_archive_record(
            'media', $attachment_id,
            $attachment->post_title ?: basename($file_path),
            $attachment->post_name, null,
            get_the_author_meta('display_name', $attachment->post_author),
            $attachment->post_mime_type,
            $archive_path, $file_path, $file_size, $file_count, $data, 'complete'
        );
    }
    
    public function archive_theme($delete, $stylesheet) {
        $theme = wp_get_theme($stylesheet);
        if (!$theme->exists()) return $delete;
        
        $theme_dir = $theme->get_stylesheet_directory();
        $archive_path = $this->archive_dir . '/themes/' . $stylesheet . '_' . time();
        
        $this->recursive_copy($theme_dir, $archive_path);
        
        $data = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'stylesheet' => $stylesheet,
            'template' => $theme->get_template()
        );
        
        $this->create_archive_record(
            'theme', $stylesheet, $theme->get('Name'), $stylesheet,
            $theme->get('Version'), $theme->get('Author'),
            $theme->get('Description'), $archive_path, $theme_dir,
            $this->get_directory_size($theme_dir),
            count($this->get_all_files($theme_dir)), $data, 'complete'
        );
        
        return $delete;
    }
    
    public function archive_comment($comment_id, $comment = null) {
        if (!$comment) $comment = get_comment($comment_id);
        if (!$comment) return;
        
        $data = array('comment' => (array) $comment, 'meta' => get_comment_meta($comment_id));
        
        $this->create_archive_record(
            'comment', $comment_id, wp_trim_words($comment->comment_content, 10),
            null, null, $comment->comment_author, $comment->comment_content,
            null, null, 0, 0, $data, 'complete'
        );
    }
    
    public function archive_user($user_id, $reassign, $user = null) {
        if (!$user) $user = get_userdata($user_id);
        if (!$user) return;
        
        $data = array(
            'user' => array(
                'ID' => $user->ID, 'user_login' => $user->user_login,
                'user_email' => $user->user_email, 'display_name' => $user->display_name,
                'roles' => $user->roles
            ),
            'meta' => get_user_meta($user_id)
        );
        
        $this->create_archive_record(
            'user', $user_id, $user->display_name, $user->user_login,
            null, $user->user_email, 'Roles: ' . implode(', ', $user->roles),
            null, null, 0, 0, $data, 'complete'
        );
    }
    
    private function create_archive_record($item_type, $item_id, $item_name, $item_slug, $item_version, $item_author, $item_description, $archive_path, $original_path, $file_size, $file_count, $deleted_data, $delete_type) {
        global $wpdb;
        
        $retention = $this->get_retention_days();
        $expires_at = $retention > 0 ? date('Y-m-d H:i:s', strtotime("+{$retention} days")) : null;
        
        $wpdb->insert($this->table_name, array(
            'item_type' => $item_type,
            'item_id' => $item_id,
            'item_name' => $item_name,
            'item_slug' => $item_slug,
            'item_version' => $item_version,
            'item_author' => $item_author,
            'item_description' => $item_description,
            'archive_path' => $archive_path,
            'original_path' => $original_path,
            'file_size' => $file_size,
            'file_count' => $file_count,
            'deleted_data' => json_encode($deleted_data),
            'delete_type' => $delete_type,
            'deleted_by' => get_current_user_id(),
            'deleted_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'status' => 'archived'
        ));
        
        return $wpdb->insert_id;
    }
    
    public function archive_plugin($plugin_file, $delete_data = false) {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (!file_exists($plugin_path)) {
            return new WP_Error('not_found', 'Plugin file not found: ' . $plugin_path);
        }
        
        $plugin_data = get_plugin_data($plugin_path);
        $plugin_slug = dirname($plugin_file);
        
        // Determine plugin directory
        if ($plugin_slug === '.' || empty($plugin_slug)) {
            // Single file plugin
            $plugin_dir = $plugin_path;
            $is_single_file = true;
        } else {
            $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
            $is_single_file = false;
        }
        
        // Create archive directory
        $archive_subdir = $this->archive_dir . '/plugins/' . sanitize_file_name($plugin_slug ?: basename($plugin_file, '.php')) . '_' . time();
        wp_mkdir_p($archive_subdir);
        
        // Copy plugin files to archive FIRST before any deletion
        if ($is_single_file) {
            copy($plugin_path, $archive_subdir . '/' . basename($plugin_file));
            $file_size = filesize($plugin_path);
            $files = array($plugin_path);
        } else {
            $this->recursive_copy($plugin_dir, $archive_subdir);
            $file_size = $this->get_directory_size($plugin_dir);
            $files = $this->get_all_files($plugin_dir);
        }
        
        $db_data = array();
        $tables_deleted = array();
        $options_deleted = array();
        
        if ($delete_data) {
            $db_data = $this->collect_plugin_db_data($plugin_file);
        }
        
        $was_active = is_plugin_active($plugin_file);
        
        $deleted_data = array(
            'plugin_data' => $plugin_data,
            'plugin_file' => $plugin_file,
            'was_active' => $was_active,
            'is_single_file' => $is_single_file,
            'files' => array_map(function($f) use ($plugin_dir) {
                return str_replace(WP_PLUGIN_DIR, '', $f);
            }, $files),
            'db_data' => $db_data
        );
        
        // Create archive record
        $archive_id = $this->create_archive_record(
            'plugin', $plugin_file, $plugin_data['Name'], $plugin_slug ?: basename($plugin_file, '.php'),
            $plugin_data['Version'], wp_strip_all_tags($plugin_data['Author']),
            $plugin_data['Description'], $archive_subdir, $is_single_file ? $plugin_path : $plugin_dir,
            $file_size, count($files), $deleted_data,
            $delete_data ? 'complete' : 'files_only'
        );
        
        // Deactivate if active
        if ($was_active) {
            deactivate_plugins($plugin_file, true);
        }
        
        // Delete database data if requested
        if ($delete_data && !empty($db_data)) {
            global $wpdb;
            foreach ($db_data['tables'] as $table) {
                $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
                $tables_deleted[] = $table;
            }
            foreach ($db_data['options'] as $option) {
                delete_option($option);
                $options_deleted[] = $option;
            }
        }
        
        // NOW delete the actual plugin files
        if ($is_single_file) {
            if (file_exists($plugin_path)) {
                unlink($plugin_path);
            }
        } else {
            if (is_dir($plugin_dir)) {
                $this->recursive_delete($plugin_dir);
            }
        }
        
        $retention = $this->get_retention_days();
        
        return array(
            'archive_id' => $archive_id,
            'plugin_name' => $plugin_data['Name'],
            'plugin_file' => $plugin_file,
            'plugin_version' => $plugin_data['Version'],
            'delete_type' => $delete_data ? 'complete' : 'files_only',
            'file_size' => $file_size,
            'file_count' => count($files),
            'tables_deleted' => $tables_deleted,
            'options_deleted' => $options_deleted,
            'retention_days' => $retention,
            'expires_at' => $retention > 0 ? date('Y-m-d H:i:s', strtotime("+{$retention} days")) : 'Never',
            'archived_files' => array_slice($files, 0, 50)
        );
    }
    
    private function collect_plugin_db_data($plugin_file) {
        global $wpdb;
        
        $plugin_slug = sanitize_title(dirname($plugin_file));
        if (empty($plugin_slug) || $plugin_slug === '.') {
            $plugin_slug = sanitize_title(basename($plugin_file, '.php'));
        }
        $plugin_slug_underscore = str_replace('-', '_', $plugin_slug);
        
        $data = array('tables' => array(), 'options' => array(), 'options_data' => array());
        
        $all_tables = $wpdb->get_col("SHOW TABLES");
        $core_tables = array(
            $wpdb->prefix . 'commentmeta', $wpdb->prefix . 'comments', $wpdb->prefix . 'links',
            $wpdb->prefix . 'options', $wpdb->prefix . 'postmeta', $wpdb->prefix . 'posts',
            $wpdb->prefix . 'termmeta', $wpdb->prefix . 'terms', $wpdb->prefix . 'term_relationships',
            $wpdb->prefix . 'term_taxonomy', $wpdb->prefix . 'usermeta', $wpdb->prefix . 'users'
        );
        
        foreach ($all_tables as $table) {
            if (in_array($table, $core_tables)) continue;
            $table_name = str_replace($wpdb->prefix, '', $table);
            if (stripos($table_name, $plugin_slug) !== false || stripos($table_name, $plugin_slug_underscore) !== false) {
                $data['tables'][] = $table;
            }
        }
        
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s LIMIT 500",
            '%' . $wpdb->esc_like($plugin_slug) . '%',
            '%' . $wpdb->esc_like($plugin_slug_underscore) . '%'
        ));
        
        foreach ($options as $opt) {
            $data['options'][] = $opt->option_name;
            $data['options_data'][$opt->option_name] = $opt->option_value;
        }
        
        return $data;
    }
    
    public function restore_item($archive_id) {
        global $wpdb;
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND status = 'archived'", $archive_id
        ));
        
        if (!$record) return new WP_Error('not_found', 'Archive not found');
        
        $deleted_data = json_decode($record->deleted_data, true);
        $result = array('success' => false);
        
        switch ($record->item_type) {
            case 'plugin': $result = $this->restore_plugin($record, $deleted_data); break;
            case 'theme': $result = $this->restore_theme($record, $deleted_data); break;
            case 'post': case 'page': $result = $this->restore_post($record, $deleted_data); break;
            case 'media': $result = $this->restore_media($record, $deleted_data); break;
            case 'comment': $result = $this->restore_comment($record, $deleted_data); break;
            case 'user': $result = $this->restore_user($record, $deleted_data); break;
            default: return new WP_Error('unknown_type', 'Unknown type');
        }
        
        if ($result['success']) {
            $wpdb->update($this->table_name,
                array('status' => 'restored', 'restored_at' => current_time('mysql')),
                array('id' => $archive_id)
            );
            if ($record->archive_path && file_exists($record->archive_path)) {
                if (is_dir($record->archive_path)) {
                    $this->recursive_delete($record->archive_path);
                } else {
                    unlink($record->archive_path);
                }
            }
        }
        
        return $result;
    }
    
    private function restore_plugin($record, $deleted_data) {
        if (!file_exists($record->archive_path)) {
            return array('success' => false, 'message' => 'Archive files not found');
        }
        
        $plugin_file = $deleted_data['plugin_file'];
        $is_single_file = !empty($deleted_data['is_single_file']);
        
        if ($is_single_file) {
            // Single file plugin - copy directly to plugins directory
            $dest = WP_PLUGIN_DIR . '/' . basename($plugin_file);
            $archived_file = $record->archive_path . '/' . basename($plugin_file);
            if (file_exists($archived_file)) {
                copy($archived_file, $dest);
            }
        } else {
            // Directory plugin
            $dest = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
            wp_mkdir_p($dest);
            $this->recursive_copy($record->archive_path, $dest);
        }
        
        if ($record->delete_type === 'complete' && !empty($deleted_data['db_data']['options_data'])) {
            foreach ($deleted_data['db_data']['options_data'] as $name => $value) {
                update_option($name, maybe_unserialize($value));
            }
        }
        
        if (!empty($deleted_data['was_active'])) {
            activate_plugin($plugin_file);
        }
        
        return array('success' => true, 'message' => 'Plugin restored to Plugins page');
    }
    
    private function restore_theme($record, $deleted_data) {
        if (!file_exists($record->archive_path)) return array('success' => false, 'message' => 'Archive not found');
        $this->recursive_copy($record->archive_path, get_theme_root() . '/' . $record->item_slug);
        return array('success' => true, 'message' => 'Theme restored');
    }
    
    private function restore_post($record, $deleted_data) {
        if (empty($deleted_data['post'])) return array('success' => false, 'message' => 'Post data not found');
        
        $post_data = $deleted_data['post'];
        $post_data['post_status'] = 'publish';
        unset($post_data['ID']);
        
        $new_id = wp_insert_post($post_data);
        if (is_wp_error($new_id)) return array('success' => false, 'message' => $new_id->get_error_message());
        
        if (!empty($deleted_data['meta'])) {
            foreach ($deleted_data['meta'] as $key => $values) {
                foreach ($values as $value) add_post_meta($new_id, $key, maybe_unserialize($value));
            }
        }
        if (!empty($deleted_data['taxonomies'])) {
            foreach ($deleted_data['taxonomies'] as $tax => $terms) {
                wp_set_object_terms($new_id, wp_list_pluck($terms, 'term_id'), $tax);
            }
        }
        if (!empty($deleted_data['thumbnail_id'])) set_post_thumbnail($new_id, $deleted_data['thumbnail_id']);
        
        return array('success' => true, 'message' => ucfirst($record->item_type) . ' restored', 'new_id' => $new_id);
    }
    
    private function restore_media($record, $deleted_data) {
        if (empty($deleted_data['attachment']) || !$record->archive_path || !file_exists($record->archive_path)) {
            return array('success' => false, 'message' => 'Archive not found');
        }
        $upload_dir = wp_upload_dir();
        $filename = wp_unique_filename($upload_dir['path'], basename($deleted_data['file_path']));
        $dest_path = $upload_dir['path'] . '/' . $filename;
        copy($record->archive_path, $dest_path);
        
        $attach_id = wp_insert_attachment(array(
            'post_mime_type' => $deleted_data['attachment']['post_mime_type'],
            'post_title' => $deleted_data['attachment']['post_title'],
            'post_status' => 'inherit'
        ), $dest_path);
        
        if (is_wp_error($attach_id)) return array('success' => false, 'message' => $attach_id->get_error_message());
        
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $dest_path));
        return array('success' => true, 'message' => 'Media restored to Media Library', 'new_id' => $attach_id);
    }
    
    private function restore_comment($record, $deleted_data) {
        if (empty($deleted_data['comment'])) return array('success' => false, 'message' => 'Comment data not found');
        $data = $deleted_data['comment'];
        unset($data['comment_ID']);
        $new_id = wp_insert_comment($data);
        return $new_id ? array('success' => true, 'message' => 'Comment restored') : array('success' => false, 'message' => 'Failed');
    }
    
    private function restore_user($record, $deleted_data) {
        if (empty($deleted_data['user'])) return array('success' => false, 'message' => 'User data not found');
        $u = $deleted_data['user'];
        if (username_exists($u['user_login']) || email_exists($u['user_email'])) {
            return array('success' => false, 'message' => 'Username or email exists');
        }
        $new_id = wp_insert_user(array(
            'user_login' => $u['user_login'], 'user_email' => $u['user_email'],
            'display_name' => $u['display_name'], 'role' => !empty($u['roles']) ? $u['roles'][0] : 'subscriber'
        ));
        return is_wp_error($new_id) ? array('success' => false, 'message' => $new_id->get_error_message()) : array('success' => true, 'message' => 'User restored');
    }
    
    public function cleanup_expired_archives() {
        global $wpdb;
        $expired = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE status = 'archived' AND expires_at IS NOT NULL AND expires_at < NOW()");
        foreach ($expired as $record) {
            if ($record->archive_path && file_exists($record->archive_path)) {
                is_dir($record->archive_path) ? $this->recursive_delete($record->archive_path) : unlink($record->archive_path);
            }
            $wpdb->update($this->table_name, array('status' => 'expired'), array('id' => $record->id));
        }
        $wpdb->query("DELETE FROM {$this->table_name} WHERE status IN ('expired','restored') AND deleted_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
    }
    
    public function get_archive_stats() {
        global $wpdb;
        $stats = array('total' => 0, 'by_type' => array(), 'total_size' => 0, 'expiring_soon' => 0);
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'archived'");
        $by_type = $wpdb->get_results("SELECT item_type, COUNT(*) as count, SUM(file_size) as size FROM {$this->table_name} WHERE status = 'archived' GROUP BY item_type");
        foreach ($by_type as $row) $stats['by_type'][$row->item_type] = array('count' => $row->count, 'size' => $row->size);
        $stats['total_size'] = $wpdb->get_var("SELECT SUM(file_size) FROM {$this->table_name} WHERE status = 'archived'") ?: 0;
        $stats['expiring_soon'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'archived' AND expires_at IS NOT NULL AND expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY)");
        return $stats;
    }
    
    public function get_archived_items($type = 'all', $page = 1, $per_page = 20) {
        global $wpdb;
        $offset = ($page - 1) * $per_page;
        $where = "WHERE status = 'archived'";
        if ($type !== 'all') $where .= $wpdb->prepare(" AND item_type = %s", $type);
        $items = $wpdb->get_results("SELECT * FROM {$this->table_name} {$where} ORDER BY deleted_at DESC LIMIT {$offset}, {$per_page}");
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} {$where}");
        return array('items' => $items, 'total' => $total, 'pages' => ceil($total / $per_page));
    }
    
    public function ajax_save_settings() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        
        $settings = $this->get_settings();
        if (isset($_POST['retention_days'])) {
            $days = intval($_POST['retention_days']);
            $settings['retention_days'] = in_array($days, array(0, 7, 14, 30)) ? $days : 7;
        }
        if (isset($_POST['gdrive_client_id'])) $settings['gdrive_client_id'] = sanitize_text_field($_POST['gdrive_client_id']);
        if (isset($_POST['gdrive_client_secret'])) $settings['gdrive_client_secret'] = sanitize_text_field($_POST['gdrive_client_secret']);
        if (isset($_POST['dropbox_disconnect'])) {
            $settings['dropbox_token'] = '';
            $settings['dropbox_connected'] = false;
        }
        if (isset($_POST['gdrive_disconnect'])) {
            $settings['gdrive_token'] = '';
            $settings['gdrive_refresh_token'] = '';
            $settings['gdrive_connected'] = false;
        }
        
        update_option($this->settings_key, $settings);
        wp_send_json_success(array('message' => 'Settings saved'));
    }
    
    public function ajax_get_plugin_info() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('delete_plugins')) wp_send_json_error('Permission denied');
        
        $plugin_file = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        if (empty($plugin_file)) wp_send_json_error('No plugin specified');
        
        if (!function_exists('get_plugin_data')) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (!file_exists($plugin_path)) wp_send_json_error('Plugin not found: ' . $plugin_path);
        
        $plugin_data = get_plugin_data($plugin_path);
        $plugin_slug = dirname($plugin_file);
        $plugin_dir = ($plugin_slug === '.' || empty($plugin_slug)) ? $plugin_path : WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        wp_send_json_success(array(
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'author' => wp_strip_all_tags($plugin_data['Author']),
            'size' => size_format($this->get_directory_size($plugin_dir)),
            'tables_count' => count($this->find_plugin_tables($plugin_file)),
            'options_count' => count($this->find_plugin_options($plugin_file)),
            'is_active' => is_plugin_active($plugin_file),
            'retention_days' => $this->get_retention_days()
        ));
    }
    
    public function ajax_delete_plugin() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('delete_plugins')) wp_send_json_error('Permission denied');
        
        $plugin_file = isset($_POST['plugin']) ? sanitize_text_field($_POST['plugin']) : '';
        $delete_data = isset($_POST['delete_data']) && $_POST['delete_data'] === 'true';
        
        if (empty($plugin_file)) wp_send_json_error('No plugin specified');
        
        $result = $this->archive_plugin($plugin_file, $delete_data);
        if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
        
        $key = 'pdm_result_' . md5($plugin_file . time());
        set_transient($key, $result, 3600);
        
        wp_send_json_success(array('redirect' => admin_url('admin.php?page=pdm-results&key=' . $key), 'result' => $result));
    }
    
    public function ajax_restore_item() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('install_plugins')) wp_send_json_error('Permission denied');
        
        $archive_id = isset($_POST['archive_id']) ? intval($_POST['archive_id']) : 0;
        if (!$archive_id) wp_send_json_error('No archive ID');
        
        $result = $this->restore_item($archive_id);
        is_wp_error($result) ? wp_send_json_error($result->get_error_message()) : wp_send_json_success($result);
    }
    
    public function ajax_permanent_delete() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('delete_plugins')) wp_send_json_error('Permission denied');
        
        global $wpdb;
        $archive_id = isset($_POST['archive_id']) ? intval($_POST['archive_id']) : 0;
        if (!$archive_id) wp_send_json_error('No archive ID');
        
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $archive_id));
        if (!$record) wp_send_json_error('Not found');
        
        if ($record->archive_path && file_exists($record->archive_path)) {
            is_dir($record->archive_path) ? $this->recursive_delete($record->archive_path) : unlink($record->archive_path);
        }
        $wpdb->delete($this->table_name, array('id' => $archive_id));
        wp_send_json_success(array('message' => 'Permanently deleted'));
    }
    
    public function ajax_empty_archive() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('delete_plugins')) wp_send_json_error('Permission denied');
        
        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE status = 'archived'");
        foreach ($items as $item) {
            if ($item->archive_path && file_exists($item->archive_path)) {
                is_dir($item->archive_path) ? $this->recursive_delete($item->archive_path) : unlink($item->archive_path);
            }
        }
        $wpdb->query("DELETE FROM {$this->table_name} WHERE status = 'archived'");
        wp_send_json_success(array('message' => 'Archive emptied'));
    }
    
    public function ajax_download_backup() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        
        $archive_id = isset($_POST['archive_id']) ? intval($_POST['archive_id']) : 0;
        
        global $wpdb;
        $where = $archive_id ? $wpdb->prepare("WHERE id = %d AND status = 'archived'", $archive_id) : "WHERE status = 'archived'";
        $items = $wpdb->get_results("SELECT * FROM {$this->table_name} {$where}");
        
        if (empty($items)) wp_send_json_error('No items to backup');
        
        $backup_dir = $this->archive_dir . '/backups';
        $zip_file = $backup_dir . '/backup_' . date('Y-m-d_His') . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) !== true) wp_send_json_error('Could not create ZIP');
        
        foreach ($items as $item) {
            $folder = $item->item_type . '_' . sanitize_file_name($item->item_name);
            if ($item->archive_path && file_exists($item->archive_path)) {
                if (is_dir($item->archive_path)) {
                    $this->add_folder_to_zip($zip, $item->archive_path, $folder);
                } else {
                    $zip->addFile($item->archive_path, $folder . '/' . basename($item->archive_path));
                }
            }
            $zip->addFromString($folder . '/metadata.json', json_encode(array(
                'item_type' => $item->item_type, 'item_name' => $item->item_name,
                'deleted_at' => $item->deleted_at, 'deleted_data' => json_decode($item->deleted_data, true)
            ), JSON_PRETTY_PRINT));
        }
        $zip->close();
        
        wp_send_json_success(array('download_url' => content_url('oc-deletion-archive/backups/' . basename($zip_file)), 'filename' => basename($zip_file)));
    }
    
    private function add_folder_to_zip($zip, $folder, $zip_folder) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $zip->addFile($file->getRealPath(), $zip_folder . '/' . substr($file->getRealPath(), strlen($folder) + 1));
            }
        }
    }
    
    public function ajax_backup_to_cloud() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        
        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : '';
        $settings = $this->get_settings();
        
        if ($service === 'dropbox' && !empty($settings['dropbox_token'])) {
            wp_send_json_success($this->backup_to_dropbox());
        } elseif ($service === 'gdrive' && !empty($settings['gdrive_token'])) {
            wp_send_json_success($this->backup_to_gdrive());
        } else {
            wp_send_json_error('Service not connected');
        }
    }
    
    private function backup_to_dropbox() {
        $settings = $this->get_settings();
        $zip_result = $this->create_full_backup_zip();
        if (!$zip_result['success']) return $zip_result;
        
        $response = wp_remote_post('https://content.dropboxapi.com/2/files/upload', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['dropbox_token'],
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode(array('path' => '/OnyxCommand_Backups/' . basename($zip_result['path']), 'mode' => 'add', 'autorename' => true))
            ),
            'body' => file_get_contents($zip_result['path']),
            'timeout' => 120
        ));
        unlink($zip_result['path']);
        
        if (is_wp_error($response)) return array('success' => false, 'message' => $response->get_error_message());
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['path_display']) ? array('success' => true, 'message' => 'Uploaded to Dropbox') : array('success' => false, 'message' => 'Upload failed');
    }
    
    private function backup_to_gdrive() {
        $this->refresh_gdrive_token();
        $settings = $this->get_settings();
        $zip_result = $this->create_full_backup_zip();
        if (!$zip_result['success']) return $zip_result;
        
        $boundary = wp_generate_uuid4();
        $body = "--{$boundary}\r\nContent-Type: application/json\r\n\r\n" . json_encode(array('name' => basename($zip_result['path']))) . "\r\n--{$boundary}\r\nContent-Type: application/zip\r\n\r\n" . file_get_contents($zip_result['path']) . "\r\n--{$boundary}--";
        
        $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', array(
            'headers' => array('Authorization' => 'Bearer ' . $settings['gdrive_token'], 'Content-Type' => 'multipart/related; boundary=' . $boundary),
            'body' => $body, 'timeout' => 120
        ));
        unlink($zip_result['path']);
        
        if (is_wp_error($response)) return array('success' => false, 'message' => $response->get_error_message());
        $result = json_decode(wp_remote_retrieve_body($response), true);
        return isset($result['id']) ? array('success' => true, 'message' => 'Uploaded to Google Drive') : array('success' => false, 'message' => 'Upload failed');
    }
    
    private function create_full_backup_zip() {
        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE status = 'archived'");
        if (empty($items)) return array('success' => false, 'message' => 'No items');
        
        $zip_file = $this->archive_dir . '/backups/full_backup_' . date('Y-m-d_His') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) !== true) return array('success' => false, 'message' => 'Could not create ZIP');
        
        foreach ($items as $item) {
            $folder = $item->item_type . '_' . sanitize_file_name($item->item_name);
            if ($item->archive_path && file_exists($item->archive_path)) {
                is_dir($item->archive_path) ? $this->add_folder_to_zip($zip, $item->archive_path, $folder) : $zip->addFile($item->archive_path, $folder . '/' . basename($item->archive_path));
            }
            $zip->addFromString($folder . '/metadata.json', json_encode(array('item_type' => $item->item_type, 'item_name' => $item->item_name, 'deleted_data' => json_decode($item->deleted_data, true)), JSON_PRETTY_PRINT));
        }
        $zip->close();
        return array('success' => true, 'path' => $zip_file);
    }
    
    public function ajax_connect_dropbox() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        if (empty($token)) wp_send_json_error('No token');
        
        $response = wp_remote_post('https://api.dropboxapi.com/2/users/get_current_account', array('headers' => array('Authorization' => 'Bearer ' . $token)));
        if (is_wp_error($response)) wp_send_json_error('Connection failed');
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['email'])) {
            $settings = $this->get_settings();
            $settings['dropbox_token'] = $token;
            $settings['dropbox_connected'] = true;
            update_option($this->settings_key, $settings);
            wp_send_json_success(array('message' => 'Connected as ' . $body['email']));
        } else {
            wp_send_json_error('Invalid token');
        }
    }
    
    public function ajax_connect_gdrive() {
        check_ajax_referer('pdm_action', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        
        $settings = $this->get_settings();
        if (empty($settings['gdrive_client_id']) || empty($settings['gdrive_client_secret'])) wp_send_json_error('Save Client ID and Secret first');
        
        $redirect_uri = admin_url('admin.php?page=onyx-command&tab=deletion_manager&gdrive_callback=1');
        wp_send_json_success(array('auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
            'client_id' => $settings['gdrive_client_id'], 'redirect_uri' => $redirect_uri,
            'response_type' => 'code', 'scope' => 'https://www.googleapis.com/auth/drive.file',
            'access_type' => 'offline', 'prompt' => 'consent'
        ))));
    }
    
    public function handle_oauth_callback() {
        if (!isset($_GET['gdrive_callback']) || !isset($_GET['code'])) return;
        if (!current_user_can('manage_options')) return;
        
        $settings = $this->get_settings();
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array('body' => array(
            'code' => sanitize_text_field($_GET['code']),
            'client_id' => $settings['gdrive_client_id'],
            'client_secret' => $settings['gdrive_client_secret'],
            'redirect_uri' => admin_url('admin.php?page=onyx-command&tab=deletion_manager&gdrive_callback=1'),
            'grant_type' => 'authorization_code'
        )));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['access_token'])) {
                $settings['gdrive_token'] = $body['access_token'];
                $settings['gdrive_refresh_token'] = $body['refresh_token'] ?? '';
                $settings['gdrive_connected'] = true;
                update_option($this->settings_key, $settings);
            }
        }
        wp_redirect(admin_url('admin.php?page=onyx-command&tab=deletion_manager'));
        exit;
    }
    
    private function refresh_gdrive_token() {
        $settings = $this->get_settings();
        if (empty($settings['gdrive_refresh_token'])) return;
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array('body' => array(
            'client_id' => $settings['gdrive_client_id'], 'client_secret' => $settings['gdrive_client_secret'],
            'refresh_token' => $settings['gdrive_refresh_token'], 'grant_type' => 'refresh_token'
        )));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['access_token'])) {
                $settings['gdrive_token'] = $body['access_token'];
                update_option($this->settings_key, $settings);
            }
        }
    }
    
    private function find_plugin_tables($plugin_file) {
        global $wpdb;
        $slug = sanitize_title(dirname($plugin_file));
        if (empty($slug) || $slug === '.') $slug = sanitize_title(basename($plugin_file, '.php'));
        $slug_u = str_replace('-', '_', $slug);
        $tables = array();
        $core = array($wpdb->prefix.'commentmeta',$wpdb->prefix.'comments',$wpdb->prefix.'links',$wpdb->prefix.'options',$wpdb->prefix.'postmeta',$wpdb->prefix.'posts',$wpdb->prefix.'termmeta',$wpdb->prefix.'terms',$wpdb->prefix.'term_relationships',$wpdb->prefix.'term_taxonomy',$wpdb->prefix.'usermeta',$wpdb->prefix.'users');
        foreach ($wpdb->get_col("SHOW TABLES") as $t) {
            if (in_array($t, $core)) continue;
            $n = str_replace($wpdb->prefix, '', $t);
            if (stripos($n, $slug) !== false || stripos($n, $slug_u) !== false) $tables[] = $t;
        }
        return $tables;
    }
    
    private function find_plugin_options($plugin_file) {
        global $wpdb;
        $slug = sanitize_title(dirname($plugin_file));
        if (empty($slug) || $slug === '.') $slug = sanitize_title(basename($plugin_file, '.php'));
        $slug_u = str_replace('-', '_', $slug);
        return $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s LIMIT 100", '%'.$wpdb->esc_like($slug).'%', '%'.$wpdb->esc_like($slug_u).'%'));
    }
    
    private function get_directory_size($dir) {
        $size = 0;
        if (!is_dir($dir)) return file_exists($dir) ? filesize($dir) : 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) $size += $file->getSize();
        return $size;
    }
    
    private function get_all_files($dir) {
        $files = array();
        if (!is_dir($dir)) return array($dir);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
            if ($file->isFile()) $files[] = $file->getPathname();
        }
        return $files;
    }
    
    private function recursive_copy($src, $dst) {
        if (!file_exists($dst)) wp_mkdir_p($dst);
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                is_dir($src.'/'.$file) ? $this->recursive_copy($src.'/'.$file, $dst.'/'.$file) : copy($src.'/'.$file, $dst.'/'.$file);
            }
        }
        closedir($dir);
    }
    
    private function recursive_delete($dir) {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $obj) {
                if ($obj != '.' && $obj != '..') {
                    is_dir($dir.'/'.$obj) ? $this->recursive_delete($dir.'/'.$obj) : unlink($dir.'/'.$obj);
                }
            }
            return rmdir($dir);
        }
        return true;
    }
    
    public function install_mu_plugin() {
        if (!file_exists($this->mu_plugins_dir)) wp_mkdir_p($this->mu_plugins_dir);
        $path = $this->mu_plugins_dir . '/' . $this->mu_plugin_file;
        if (file_exists($path) && strpos(file_get_contents($path), '3.1.0') !== false) return true;
        return file_put_contents($path, $this->get_mu_plugin_content()) !== false;
    }
    
    public function add_settings_tab($tabs) {
        $tabs['deletion_manager'] = '🗑️ Deletion Manager';
        return $tabs;
    }
    
    public function render_settings_tab() {
        $settings = $this->get_settings();
        $nonce = wp_create_nonce('pdm_action');
        ?>
        <div class="oc-settings-section">
            <h2>🗑️ Deletion Manager Settings</h2>
            <table class="form-table">
                <tr>
                    <th>Retention Period</th>
                    <td>
                        <select id="pdm_retention">
                            <option value="7" <?php selected($settings['retention_days'], 7); ?>>7 Days</option>
                            <option value="14" <?php selected($settings['retention_days'], 14); ?>>14 Days</option>
                            <option value="30" <?php selected($settings['retention_days'], 30); ?>>30 Days</option>
                            <option value="0" <?php selected($settings['retention_days'], 0); ?>>Indefinitely</option>
                        </select>
                        <p class="description">How long to keep deleted items before permanent removal.</p>
                    </td>
                </tr>
            </table>
            <h3>☁️ Cloud Backup</h3>
            <table class="form-table">
                <tr>
                    <th>Dropbox</th>
                    <td>
                        <?php if ($settings['dropbox_connected']): ?>
                            <span style="color:green">✅ Connected</span>
                            <button type="button" class="button" id="pdm_dropbox_disconnect">Disconnect</button>
                        <?php else: ?>
                            <input type="text" id="pdm_dropbox_token" placeholder="Access Token" style="width:300px">
                            <button type="button" class="button" id="pdm_dropbox_connect">Connect</button>
                            <p class="description"><a href="https://www.dropbox.com/developers/apps" target="_blank">Get token from Dropbox</a></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Google Drive</th>
                    <td>
                        <?php if ($settings['gdrive_connected']): ?>
                            <span style="color:green">✅ Connected</span>
                            <button type="button" class="button" id="pdm_gdrive_disconnect">Disconnect</button>
                        <?php else: ?>
                            <input type="text" id="pdm_gdrive_client_id" value="<?php echo esc_attr($settings['gdrive_client_id']); ?>" placeholder="Client ID" style="width:300px"><br><br>
                            <input type="text" id="pdm_gdrive_client_secret" value="<?php echo esc_attr($settings['gdrive_client_secret']); ?>" placeholder="Client Secret" style="width:300px"><br><br>
                            <button type="button" class="button button-primary" id="pdm_gdrive_connect">Authorize</button>
                            <p class="description">Redirect URI: <code><?php echo admin_url('admin.php?page=onyx-command&tab=deletion_manager&gdrive_callback=1'); ?></code></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p><button type="button" class="button button-primary" id="pdm_save_settings">Save Settings</button></p>
            <hr><p><a href="<?php echo admin_url('admin.php?page=pdm-archive'); ?>" class="button">📦 View Deletion Archive</a></p>
        </div>
        <script>
        jQuery(function($){
            var nonce='<?php echo $nonce; ?>';
            $('#pdm_save_settings').on('click',function(){
                $(this).prop('disabled',true).text('Saving...');
                $.post(ajaxurl,{action:'pdm_save_settings',nonce:nonce,retention_days:$('#pdm_retention').val(),gdrive_client_id:$('#pdm_gdrive_client_id').val(),gdrive_client_secret:$('#pdm_gdrive_client_secret').val()},function(r){
                    $('#pdm_save_settings').prop('disabled',false).text('Save Settings');
                    alert(r.success?'Saved!':r.data);
                });
            });
            $('#pdm_dropbox_connect').on('click',function(){
                var t=$('#pdm_dropbox_token').val();
                if(!t){alert('Enter token');return;}
                $.post(ajaxurl,{action:'pdm_connect_dropbox',nonce:nonce,token:t},function(r){alert(r.success?r.data.message:r.data);if(r.success)location.reload();});
            });
            $('#pdm_dropbox_disconnect').on('click',function(){$.post(ajaxurl,{action:'pdm_save_settings',nonce:nonce,dropbox_disconnect:1},function(){location.reload();});});
            $('#pdm_gdrive_connect').on('click',function(){
                $.post(ajaxurl,{action:'pdm_save_settings',nonce:nonce,gdrive_client_id:$('#pdm_gdrive_client_id').val(),gdrive_client_secret:$('#pdm_gdrive_client_secret').val()},function(){
                    $.post(ajaxurl,{action:'pdm_connect_gdrive',nonce:nonce},function(r){if(r.success)window.location.href=r.data.auth_url;else alert(r.data);});
                });
            });
            $('#pdm_gdrive_disconnect').on('click',function(){$.post(ajaxurl,{action:'pdm_save_settings',nonce:nonce,gdrive_disconnect:1},function(){location.reload();});});
        });
        </script>
        <?php
    }
    
    public function add_admin_menu() {
        add_submenu_page('onyx-command', 'Deletion Archive', '📦 Deletion Archive', 'manage_options', 'pdm-archive', array($this, 'render_archive_page'));
        add_submenu_page(null, 'Results', 'Results', 'activate_plugins', 'pdm-results', array($this, 'render_results_page'));
        add_submenu_page(null, 'Handle Delete', 'Handle Delete', 'delete_plugins', 'pdm-handle-delete', array($this, 'render_handle_delete_page'));
    }
    
    public function render_handle_delete_page() {
        $plugin = isset($_GET['plugin']) ? sanitize_text_field($_GET['plugin']) : '';
        if (empty($plugin)) {
            wp_redirect(admin_url('plugins.php'));
            exit;
        }
        $nonce = wp_create_nonce('pdm_action');
        ?>
        <script>
        jQuery(function($){
            var plugin = <?php echo json_encode($plugin); ?>;
            var nonce = <?php echo json_encode($nonce); ?>;
            
            // Show modal immediately
            $('body').append('<div id="pdmOverlay" style="position:fixed;z-index:999999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center"><div style="background:#fff;border-radius:12px;width:90%;max-width:550px;overflow:hidden"><div style="padding:20px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff"><h2 style="margin:0;color:#fff">⚠️ Delete Plugin</h2></div><div style="padding:25px" id="pdmBody"><p>Loading...</p></div><div style="padding:15px 25px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:right"><button id="pdmCancel" style="padding:10px 20px;border:none;border-radius:6px;cursor:pointer;background:#6b7280;color:#fff">Cancel</button></div></div></div>');
            
            $.post(ajaxurl,{action:'pdm_get_plugin_info',nonce:nonce,plugin:plugin},function(r){
                if(r.success){
                    var d=r.data;
                    $('#pdmBody').html('<div style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px"><p style="font-size:18px;font-weight:600;margin:0 0 5px">'+d.name+'</p><p style="color:#666;margin:0">v'+d.version+' • '+d.size+'</p></div>'+(d.is_active?'<div style="background:#fef3c7;border:1px solid #f59e0b;padding:10px;border-radius:6px;margin-bottom:15px;color:#92400e">⚠️ Active plugin - will be deactivated</div>':'')+'<p><strong>Choose deletion type:</strong></p><div style="display:flex;gap:15px"><div id="pdmKeep" style="flex:1;padding:20px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;text-align:center"><div style="font-size:36px">💾</div><h3 style="margin:10px 0 5px">Keep Data</h3><p style="font-size:12px;color:#666;margin:0">Files only</p></div><div id="pdmDelete" style="flex:1;padding:20px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;text-align:center"><div style="font-size:36px">🗑️</div><h3 style="margin:10px 0 5px">Delete All</h3><p style="font-size:12px;color:#666;margin:0">Files + database</p></div></div><div style="background:#dcfce7;border:1px solid #22c55e;padding:10px;border-radius:6px;margin-top:15px;font-size:13px;color:#166534">📦 Archived '+(d.retention_days>0?d.retention_days+' days':'indefinitely')+'</div>');
                    
                    $('#pdmKeep').hover(function(){$(this).css('border-color','#667eea')},function(){$(this).css('border-color','#e5e7eb')});
                    $('#pdmDelete').hover(function(){$(this).css('border-color','#dc2626')},function(){$(this).css('border-color','#e5e7eb')});
                    
                    $('#pdmKeep').on('click',function(){doDelete(false);});
                    $('#pdmDelete').on('click',function(){if(confirm('Delete ALL data?'))doDelete(true);});
                }else{
                    $('#pdmBody').html('<p style="color:red">Error: '+r.data+'</p>');
                }
            });
            
            function doDelete(deleteData){
                $('#pdmBody').html('<div style="text-align:center;padding:30px"><div style="width:36px;height:36px;border:3px solid #e5e7eb;border-top-color:#667eea;border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 15px"></div><h3>Archiving & Deleting...</h3></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style>');
                $.post(ajaxurl,{action:'pdm_delete_plugin',nonce:nonce,plugin:plugin,delete_data:deleteData?'true':'false'},function(r){
                    if(r.success)window.location.href=r.data.redirect;
                    else{alert('Error: '+r.data);window.location.href='<?php echo admin_url('plugins.php'); ?>';}
                }).fail(function(){alert('Request failed');window.location.href='<?php echo admin_url('plugins.php'); ?>';});
            }
            
            $('#pdmCancel').on('click',function(){window.location.href='<?php echo admin_url('plugins.php'); ?>';});
        });
        </script>
        <?php
    }
    
    public function render_archive_page() {
        $stats = $this->get_archive_stats();
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
        $paged = max(1, intval($_GET['paged'] ?? 1));
        $items = $this->get_archived_items($type, $paged, 20);
        $settings = $this->get_settings();
        $nonce = wp_create_nonce('pdm_action');
        ?>
        <style>.pdm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin:20px 0}.pdm-stat{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px;text-align:center}.pdm-stat-val{font-size:28px;font-weight:bold}.pdm-filter{display:flex;gap:8px;margin:15px 0;flex-wrap:wrap}.pdm-filter a{padding:6px 14px;background:#f0f0f1;border-radius:4px;text-decoration:none;color:#1d2327}.pdm-filter a.active{background:#2271b1;color:#fff}.pdm-table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #ccd0d4}.pdm-table th,.pdm-table td{padding:12px;text-align:left;border-bottom:1px solid #eee}.pdm-table th{background:#f8f9fa}.pdm-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px}.pdm-badge-plugin{background:#dbeafe;color:#1e40af}.pdm-badge-theme{background:#fce7f3;color:#be185d}.pdm-badge-post,.pdm-badge-page{background:#dcfce7;color:#166534}.pdm-badge-media{background:#fef3c7;color:#92400e}.pdm-btn{padding:5px 10px;border:none;border-radius:4px;cursor:pointer;font-size:12px;margin:2px}.pdm-btn-restore{background:#22c55e;color:#fff}.pdm-btn-delete{background:#dc2626;color:#fff}.pdm-btn-backup{background:#3b82f6;color:#fff}</style>
        <div class="wrap" style="max-width:1200px">
            <h1>📦 Deletion Archive</h1>
            <p>Retention: <?php echo $settings['retention_days'] > 0 ? $settings['retention_days'] . ' days' : 'Indefinite'; ?>. <a href="<?php echo admin_url('admin.php?page=onyx-command&tab=deletion_manager'); ?>">Settings</a></p>
            <div class="pdm-stats">
                <div class="pdm-stat"><div class="pdm-stat-val"><?php echo number_format($stats['total']); ?></div><div>Items</div></div>
                <div class="pdm-stat"><div class="pdm-stat-val"><?php echo size_format($stats['total_size']); ?></div><div>Size</div></div>
                <div class="pdm-stat"><div class="pdm-stat-val"><?php echo $stats['expiring_soon']; ?></div><div>Expiring Soon</div></div>
            </div>
            <div class="pdm-filter">
                <a href="?page=pdm-archive&type=all" class="<?php echo $type==='all'?'active':''; ?>">All</a>
                <a href="?page=pdm-archive&type=plugin" class="<?php echo $type==='plugin'?'active':''; ?>">Plugins</a>
                <a href="?page=pdm-archive&type=theme" class="<?php echo $type==='theme'?'active':''; ?>">Themes</a>
                <a href="?page=pdm-archive&type=post" class="<?php echo $type==='post'?'active':''; ?>">Posts</a>
                <a href="?page=pdm-archive&type=page" class="<?php echo $type==='page'?'active':''; ?>">Pages</a>
                <a href="?page=pdm-archive&type=media" class="<?php echo $type==='media'?'active':''; ?>">Media</a>
            </div>
            <?php if (empty($items['items'])): ?>
            <div style="text-align:center;padding:60px;background:#fff;border:1px solid #ccd0d4;border-radius:8px"><div style="font-size:64px">📭</div><h3>Empty</h3></div>
            <?php else: ?>
            <table class="pdm-table"><thead><tr><th>Type</th><th>Name</th><th>Size</th><th>Deleted</th><th>Expires</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($items['items'] as $item): $days = $item->expires_at ? max(0, ceil((strtotime($item->expires_at) - time()) / 86400)) : '∞'; ?>
            <tr data-id="<?php echo $item->id; ?>">
                <td><span class="pdm-badge pdm-badge-<?php echo $item->item_type; ?>"><?php echo ucfirst($item->item_type); ?></span></td>
                <td><strong><?php echo esc_html($item->item_name); ?></strong><?php if($item->item_version): ?> <small>v<?php echo esc_html($item->item_version); ?></small><?php endif; ?></td>
                <td><?php echo $item->file_size ? size_format($item->file_size) : '-'; ?></td>
                <td><?php echo human_time_diff(strtotime($item->deleted_at)); ?> ago</td>
                <td><?php echo is_numeric($days) ? $days . 'd' : $days; ?></td>
                <td>
                    <button class="pdm-btn pdm-btn-restore" data-id="<?php echo $item->id; ?>">↩️</button>
                    <button class="pdm-btn pdm-btn-backup" data-id="<?php echo $item->id; ?>">💾</button>
                    <button class="pdm-btn pdm-btn-delete" data-id="<?php echo $item->id; ?>">🗑️</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
            <p style="text-align:center;margin:20px">
                <button class="button" id="pdmDownloadAll">💾 Download All</button>
                <?php if ($settings['dropbox_connected']): ?><button class="button" id="pdmDropbox">☁️ Dropbox</button><?php endif; ?>
                <?php if ($settings['gdrive_connected']): ?><button class="button" id="pdmGDrive">☁️ Drive</button><?php endif; ?>
                <button class="button" id="pdmEmpty" style="background:#dc2626;color:#fff;border-color:#dc2626">🗑️ Empty</button>
            </p>
            <?php endif; ?>
        </div>
        <script>
        jQuery(function($){
            var nonce='<?php echo $nonce; ?>';
            $('.pdm-btn-restore').on('click',function(){var btn=$(this),id=btn.data('id');if(!confirm('Restore?'))return;btn.prop('disabled',true);$.post(ajaxurl,{action:'pdm_restore_item',nonce:nonce,archive_id:id},function(r){if(r.success){btn.closest('tr').fadeOut();alert(r.data.message);}else{alert(r.data);btn.prop('disabled',false);}});});
            $('.pdm-btn-delete').on('click',function(){var btn=$(this),id=btn.data('id');if(!confirm('PERMANENTLY delete?'))return;btn.prop('disabled',true);$.post(ajaxurl,{action:'pdm_permanent_delete',nonce:nonce,archive_id:id},function(r){if(r.success)btn.closest('tr').fadeOut();else{alert(r.data);btn.prop('disabled',false);}});});
            $('.pdm-btn-backup').on('click',function(){var btn=$(this),id=btn.data('id');btn.prop('disabled',true);$.post(ajaxurl,{action:'pdm_download_backup',nonce:nonce,archive_id:id},function(r){btn.prop('disabled',false);if(r.success)window.open(r.data.download_url);else alert(r.data);});});
            $('#pdmDownloadAll').on('click',function(){var btn=$(this);btn.prop('disabled',true).text('Creating...');$.post(ajaxurl,{action:'pdm_download_backup',nonce:nonce,archive_id:0},function(r){btn.prop('disabled',false).text('💾 Download All');if(r.success)window.open(r.data.download_url);else alert(r.data);});});
            $('#pdmDropbox').on('click',function(){var btn=$(this);btn.prop('disabled',true);$.post(ajaxurl,{action:'pdm_backup_to_cloud',nonce:nonce,service:'dropbox'},function(r){btn.prop('disabled',false);alert(r.success?r.data.message:r.data);});});
            $('#pdmGDrive').on('click',function(){var btn=$(this);btn.prop('disabled',true);$.post(ajaxurl,{action:'pdm_backup_to_cloud',nonce:nonce,service:'gdrive'},function(r){btn.prop('disabled',false);alert(r.success?r.data.message:r.data);});});
            $('#pdmEmpty').on('click',function(){if(!confirm('PERMANENTLY delete ALL?'))return;if(!confirm('Cannot be undone!'))return;$.post(ajaxurl,{action:'pdm_empty_archive',nonce:nonce},function(r){if(r.success)location.reload();else alert(r.data);});});
        });
        </script>
        <?php
    }
    
    public function render_results_page() {
        $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
        $result = get_transient($key);
        if (!$result) { echo '<div class="wrap"><h1>Expired</h1><p><a href="'.admin_url('plugins.php').'">Back</a></p></div>'; return; }
        ?>
        <style>.pdm-card{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px;margin:20px 0;max-width:800px}.pdm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:15px;margin:20px 0}.pdm-stat{background:#f8f9fa;padding:15px;border-radius:6px;text-align:center}.pdm-stat-val{font-size:24px;font-weight:bold}</style>
        <div class="wrap">
            <div class="pdm-card">
                <h1 style="margin-top:0">✅ Deleted</h1>
                <p><strong><?php echo esc_html($result['plugin_name']); ?></strong> v<?php echo esc_html($result['plugin_version']); ?></p>
                <div class="pdm-stats">
                    <div class="pdm-stat"><div class="pdm-stat-val"><?php echo $result['file_count']; ?></div><div>Files</div></div>
                    <div class="pdm-stat"><div class="pdm-stat-val"><?php echo size_format($result['file_size']); ?></div><div>Size</div></div>
                    <?php if($result['delete_type']==='complete'): ?>
                    <div class="pdm-stat"><div class="pdm-stat-val"><?php echo count($result['tables_deleted']); ?></div><div>Tables</div></div>
                    <?php endif; ?>
                </div>
                <div style="background:#dcfce7;border:2px solid #22c55e;padding:15px;border-radius:8px">
                    <strong>📦 Archived <?php echo $result['retention_days'] > 0 ? $result['retention_days'] . ' days' : 'indefinitely'; ?></strong>
                    <p style="margin:10px 0 0"><a href="<?php echo admin_url('admin.php?page=pdm-archive'); ?>" class="button button-primary">View Archive</a> <a href="<?php echo admin_url('plugins.php'); ?>" class="button">Plugins</a></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_mu_plugin_content() {
        return '<?php
/**
 * Plugin Name: Onyx Plugin Deletion Manager
 * Version: 3.1.0
 */
if (!defined("ABSPATH")) exit;
if (!is_admin()) return;

// Completely disable WordPress delete confirmation and redirect to our handler
add_action("admin_init", function() {
    global $pagenow;
    if ($pagenow !== "plugins.php") return;
    
    // Intercept delete-selected before WordPress processes it
    if (isset($_REQUEST["action"]) && $_REQUEST["action"] === "delete-selected" && isset($_REQUEST["checked"])) {
        if (is_array($_REQUEST["checked"]) && count($_REQUEST["checked"]) === 1) {
            $plugin = sanitize_text_field($_REQUEST["checked"][0]);
            wp_redirect(admin_url("admin.php?page=pdm-handle-delete&plugin=" . urlencode($plugin)));
            exit;
        }
    }
}, 1);

// Remove delete links and replace with our own
add_filter("plugin_action_links", function($actions, $plugin_file) {
    if (isset($actions["delete"])) {
        $actions["delete"] = sprintf(
            \'<a href="%s" class="delete" data-plugin="%s">Delete</a>\',
            admin_url("admin.php?page=pdm-handle-delete&plugin=" . urlencode($plugin_file)),
            esc_attr($plugin_file)
        );
    }
    return $actions;
}, 999, 2);

// Add script to intercept any remaining delete actions
add_action("admin_footer-plugins.php", function() {
    ?>
    <script>
    jQuery(function($){
        // Override confirm to always return true for delete messages
        var origConfirm = window.confirm;
        window.confirm = function(msg) {
            if (msg && (msg.toLowerCase().indexOf("delete") !== -1 || msg.toLowerCase().indexOf("you are about to remove") !== -1)) {
                return false; // Block the default behavior
            }
            return origConfirm.apply(this, arguments);
        };
        
        // Intercept all delete link clicks
        $(document).on("click", "a.delete, .row-actions .delete a, a[href*=\\"action=delete\\"]", function(e) {
            var $link = $(this);
            var href = $link.attr("href");
            var plugin = $link.data("plugin") || $link.closest("tr").data("plugin");
            
            if (!plugin && href) {
                var match = href.match(/plugin=([^&]+)/);
                if (match) plugin = decodeURIComponent(match[1]);
                if (!plugin) {
                    match = href.match(/checked%5B0%5D=([^&]+)/);
                    if (match) plugin = decodeURIComponent(match[1]);
                }
            }
            
            if (plugin) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                window.location.href = "' . admin_url('admin.php?page=pdm-handle-delete&plugin=') . '" + encodeURIComponent(plugin);
                return false;
            }
        });
        
        // Also intercept form submissions for bulk delete
        $("form").on("submit", function(e) {
            var action = $(this).find("select[name=action]").val() || $(this).find("select[name=action2]").val();
            if (action === "delete-selected") {
                var checked = $(this).find("input[name=\\"checked[]\\"]:checked");
                if (checked.length === 1) {
                    e.preventDefault();
                    window.location.href = "' . admin_url('admin.php?page=pdm-handle-delete&plugin=') . '" + encodeURIComponent(checked.val());
                    return false;
                }
            }
        });
    });
    </script>
    <?php
});
';
    }
}

Plugin_Deletion_Manager::get_instance();
