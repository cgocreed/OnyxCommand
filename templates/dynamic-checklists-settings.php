```php
<?php
/**
 * Dynamic Checklists settings template (basic)
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap oc-dynamic-checklists-settings">
    <h1><?php _e('Dynamic Checklists', 'onyx-command'); ?></h1>

    <p><?php _e('Create and manage checklist categories and checklists here. Each checklist can be set to "Require before publish" which enforces completion on post/page publish.', 'onyx-command'); ?></p>

    <h2><?php _e('Create Checklist Category', 'onyx-command'); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field('oc_create_checklist_category', 'oc_create_checklist_category_nonce'); ?>
        <input type="text" name="oc_category_name" placeholder="<?php _e('Category name', 'onyx-command'); ?>" style="width:300px;">
        <button class="button button-primary" type="submit" name="oc_create_category"><?php _e('Create Category', 'onyx-command'); ?></button>
    </form>

    <h2 style="margin-top:20px;"><?php _e('Create Checklist', 'onyx-command'); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field('oc_create_checklist', 'oc_create_checklist_nonce'); ?>
        <p><label><?php _e('Title', 'onyx-command'); ?>: <input type="text" name="oc_checklist_title" style="width:400px;"></label></p>
        <p><label><?php _e('Category', 'onyx-command'); ?>:
            <select name="oc_checklist_category">
                <option value=""><?php _e('-- Select --', 'onyx-command'); ?></option>
                <?php
                    $cats = get_terms(array('taxonomy' => 'oc_checklist_category','hide_empty'=>false));
                    foreach($cats as $c) {
                        echo '<option value="'.esc_attr($c->term_id).'">'.esc_html($c->name).'</option>';
                    }
                ?>
            </select>
        </label></p>
        <p><label><?php _e('Items (one per line)', 'onyx-command'); ?>:<br>
            <textarea name="oc_checklist_items" rows="6" cols="60"></textarea>
        </label></p>
        <p><label><input type="checkbox" name="oc_checklist_require"> <?php _e('Require checklist to be completed before publishing a page or post', 'onyx-command'); ?></label></p>
        <p><button class="button button-primary" type="submit" name="oc_create_checklist"><?php _e('Create Checklist', 'onyx-command'); ?></button></p>
    </form>

    <?php
    // Handle form submissions (simple)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['oc_create_category']) && check_admin_referer('oc_create_checklist_category','oc_create_checklist_category_nonce')) {
            $name = sanitize_text_field($_POST['oc_category_name']);
            if ($name) wp_insert_term($name, 'oc_checklist_category');
            echo '<div class="updated"><p>' . __('Category created.', 'onyx-command') . '</p></div>';
        }
        if (isset($_POST['oc_create_checklist']) && check_admin_referer('oc_create_checklist','oc_create_checklist_nonce')) {
            $title = sanitize_text_field($_POST['oc_checklist_title']);
            $cat = intval($_POST['oc_checklist_category']);
            $items_raw = sanitize_textarea_field($_POST['oc_checklist_items']);
            $items = array_filter(array_map('trim', explode("\n", $items_raw)));
            $require = isset($_POST['oc_checklist_require']) ? 1 : 0;
            $post_id = wp_insert_post(array('post_title'=>$title,'post_type'=>'oc_checklist','post_status'=>'publish'));
            if ($post_id && $cat) wp_set_object_terms($post_id, array($cat), 'oc_checklist_category');
            if ($post_id) {
                update_post_meta($post_id, 'checklist_items', $items);
                update_post_meta($post_id, '_oc_checklist_require_before_publish', $require);
            }
            echo '<div class="updated"><p>' . __('Checklist created.', 'onyx-command') . '</p></div>';
        }
    }
    ?>
</div>