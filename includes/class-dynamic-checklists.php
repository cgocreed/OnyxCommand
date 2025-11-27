<?php
/**
 * Dynamic Checklists manager
 * - Registers 'oc_checklist' CPT to store checklists as WP posts
 * - Registers 'oc_checklist_category' taxonomy for categories
 * - Adds a metabox on Post/Page edit screens to choose a Checklist Category and display checklists (accordion)
 * - Adds a basic settings screen template (settings tab will call the template)
 * - Enforces 'require before publish' on save_post (skips quickedit/bulk)
 */

if (!defined('ABSPATH')) exit;

class OC_Dynamic_Checklists {
    private static $instance = null;
    private $checklist_post_type = 'oc_checklist';
    private $category_tax = 'oc_checklist_category';
    private $meta_key_require = '_oc_checklist_require_before_publish';
    private $meta_key_assignments = '_oc_checklist_assigned';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action('init', array($this, 'register_post_type_and_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_editor_metabox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_oc_get_checklists_for_category', array($this, 'ajax_get_checklists_for_category'));
        add_action('save_post', array($this, 'enforce_checklists_on_publish'), 10, 3);
        // Settings page hook will include template via onyx-command settings; see onyx-command.php for include
    }

    public function register_post_type_and_taxonomy() {
        // Checklist post type
        register_post_type($this->checklist_post_type, array(
            'labels' => array(
                'name' => __('Checklists', 'onyx-command'),
                'singular_name' => __('Checklist', 'onyx-command'),
            ),
            'public' => false,
            'show_ui' => true,
            'supports' => array('title','editor','custom-fields'),
            'capability_type' => 'post',
            'capabilities' => array('create_posts' => 'manage_options'),
            'map_meta_cap' => true,
        ));

        // Category taxonomy
        register_taxonomy($this->category_tax, $this->checklist_post_type, array(
            'labels' => array(
                'name' => __('Checklist Categories', 'onyx-command'),
                'singular_name' => __('Checklist Category', 'onyx-command'),
            ),
            'public' => false,
            'show_ui' => true,
            'hierarchical' => false,
        ));
    }

    public function add_editor_metabox() {
        add_meta_box('oc_checklists_mb', __('Dynamic Checklists', 'onyx-command'), array($this, 'render_editor_metabox'), 'post', 'side', 'default');
        add_meta_box('oc_checklists_mb', __('Dynamic Checklists', 'onyx-command'), array($this, 'render_editor_metabox'), 'page', 'side', 'default');
    }

    public function render_editor_metabox($post) {
        wp_nonce_field('oc_checklists_nonce', 'oc_checklists_nonce_field');
        // Category dropdown
        $terms = get_terms(array('taxonomy' => $this->category_tax, 'hide_empty' => false));
        echo '<label for="oc_checklist_category_select">' . __('Checklist Category', 'onyx-command') . '</label><br/>';
        echo '<select id="oc_checklist_category_select" name="oc_checklist_category_select" style="width:100%">';
        echo '<option value="">' . __('-- Select Category --', 'onyx-command') . '</option>';
        foreach ($terms as $t) {
            echo '<option value="' . esc_attr($t->term_id) . '">' . esc_html($t->name) . '</option>';
        }
        echo '</select>';

        // Container for accordion checklists (populated via JS/AJAX)
        echo '<div id="oc_checklists_accordion" style="margin-top:10px;"></div>';

        // Hidden field to store checklist completion status (json)
        echo '<input type="hidden" id="oc_checklist_state" name="oc_checklist_state" value="">';
        echo '<p class="description">' . __('Select a category to display available checklists. Open a checklist to mark items as done. If a checklist is set as required before publishing, the post will not publish until it is complete.', 'onyx-command') . '</p>';
    }

    public function enqueue_admin_assets($hook) {
        // Only enqueue on post.php / post-new.php
        if (in_array($hook, array('post.php','post-new.php'))) {
            wp_enqueue_script('oc-checklists-js', plugins_url('../assets/js/checklists.js', __FILE__), array('jquery'), OC_VERSION, true);
            wp_localize_script('oc-checklists-js', 'ocChecklists', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oc_checklists'),
            ));
            wp_enqueue_style('oc-checklists-css', plugins_url('../assets/css/admin.css', __FILE__), array(), OC_VERSION);
        }
    }

    public function ajax_get_checklists_for_category() {
        check_ajax_referer('oc_checklists', 'nonce');
        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        if (!$term_id) {
            wp_send_json_error(array('message' => 'No category specified'));
        }

        $checklist_posts = get_posts(array(
            'post_type' => $this->checklist_post_type,
            'numberposts' => -1,
            'tax_query' => array(
                array('taxonomy' => $this->category_tax, 'field' => 'term_id', 'terms' => $term_id)
            )
        ));

        $out = array();
        foreach ($checklist_posts as $p) {
            $items_raw = get_post_meta($p->ID, 'checklist_items', true);
            $items = is_array($items_raw) ? $items_raw : (is_string($items_raw) && $items_raw ? maybe_unserialize($items_raw) : array());
            $required = get_post_meta($p->ID, $this->meta_key_require, true);
            $out[] = array(
                'id' => $p->ID,
                'title' => $p->post_title,
                'items' => $items,
                'required' => $required ? 1 : 0,
            );
        }

        wp_send_json_success($out);
    }

    public function enforce_checklists_on_publish($post_id, $post, $update) {
        // Only enforce when transitioning to publish (and when this save is trying to publish)
        // Check post_status of submitted request
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Skip if this is a quick-edit or bulk edit (these send 'bulk_edit' or different request params)
        if (isset($_POST['bulk_edit']) || isset($_REQUEST['bulk_edit'])) return;
        if (isset($_POST['action']) && in_array($_POST['action'], array('inline-save', 'inline-save-tax'))) return; // quick edit

        // Only run when post is being published or updated to publish
        $new_status = isset($_POST['post_status']) ? $_POST['post_status'] : $post->post_status;
        if ($new_status !== 'publish') return;

        // Determine assigned checklists for this post (we will check categories selected by meta box)
        $selected_cat = isset($_POST['oc_checklist_category_select']) ? intval($_POST['oc_checklist_category_select']) : 0;
        if (!$selected_cat) return;

        // Get all checklists in this category and see if any have require flag
        $checklist_posts = get_posts(array(
            'post_type' => $this->checklist_post_type,
            'numberposts' => -1,
            'tax_query' => array(
                array('taxonomy' => $this->category_tax, 'field' => 'term_id', 'terms' => $selected_cat)
            )
        ));

        foreach ($checklist_posts as $p) {
            $required = get_post_meta($p->ID, $this->meta_key_require, true);
            if ($required) {
                // check completion data sent from editor (JSON of completion)
                $state_json = isset($_POST['oc_checklist_state']) ? wp_unslash($_POST['oc_checklist_state']) : '';
                $state = $state_json ? json_decode($state_json, true) : array();
                $complete = isset($state[$p->ID]) ? boolval($state[$p->ID]['complete']) : false;
                if (!$complete) {
                    // Prevent publish: set post status back to draft and add admin notice
                    // Save as draft and add redirect back with message
                    // Revert status (this is a best-effort prevention)
                    remove_action('save_post', array($this, 'enforce_checklists_on_publish'), 10);
                    wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
                    add_filter('redirect_post_location', function($location) {
                        return add_query_arg('oc_checklist_publish_failed', 1, $location);
                    });
                    return;
                }
            }
        }
    }
}

// Initialize
OC_Dynamic_Checklists::get_instance();
