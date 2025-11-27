<?php
/**
 * Modules template (trimmed to updated table header/rows)
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<h1 class="wp-heading-inline"><?php _e('Modules', 'onyx-command'); ?></h1>
<a href="<?php echo admin_url('admin.php?page=onyx-command-upload'); ?>" class="page-title-action">
    <?php _e('Upload New Module', 'onyx-command'); ?>
</a>
<button type="button" id="scan-modules-btn" class="page-title-action" style="background: #00a32a; border-color: #00a32a; color: #fff;">
    <?php _e('Scan for Modules', 'onyx-command'); ?>
</button>

<div id="scan-result" style="display:none; margin: 15px 0;"></div>

<?php if (empty($modules)) : ?>
    <div class="oc-empty-state">
        <p><?php _e('No modules installed yet.', 'onyx-command'); ?></p>
        <a href="<?php echo admin_url('admin.php?page=onyx-command-upload'); ?>" class="button button-primary">
            <?php _e('Upload Your First Module', 'onyx-command'); ?>
        </a>
    </div>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" class="oc-select-all"></th>
                <th><?php _e('Module Name', 'onyx-command'); ?></th>
                <th><?php _e('Description', 'onyx-command'); ?></th>
                <th><?php _e('Version', 'onyx-command'); ?></th>
                <th><?php _e('Author', 'onyx-command'); ?></th>
                <th><?php _e('Status', 'onyx-command'); ?></th>
                <th><?php _e('Actions', 'onyx-command'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($modules as $module) : ?>
                <tr data-module-id="<?php echo esc_attr($module['module_id']); ?>">
                    <td><input type="checkbox" class="oc-module-checkbox" value="<?php echo esc_attr($module['module_id']); ?>"></td>
                    <td>
                        <strong><?php echo esc_html($module['name']); ?></strong>
                        <div class="oc-module-id"><?php echo esc_html($module['module_id']); ?></div>
                    </td>
                    <td><?php echo esc_html($module['description']); ?></td>
                    <td><?php echo esc_html($module['version']); ?></td>
                    <td><?php echo esc_html($module['author']); ?></td>
                    <td>
                        <label class="oc-toggle">
                            <input type="checkbox" 
                                   class="oc-module-toggle" 
                                   data-module-id="<?php echo esc_attr($module['module_id']); ?>"
                                   <?php checked($module['status'], 'active'); ?>>
                            <span class="oc-toggle-slider"></span>
                        </label>
                    </td>
                    <td class="oc-actions">
                        <button class="button button-small oc-view-stats" 
                                data-module-id="<?php echo esc_attr($module['module_id']); ?>"
                                title="<?php _e('View Statistics', 'onyx-command'); ?>">
                            📊 <?php _e('Stats', 'onyx-command'); ?>
                        </button>
                        <button class="button button-small button-link-delete oc-delete-module" 
                                data-module-id="<?php echo esc_attr($module['module_id']); ?>">
                            🗑️ <?php _e('Delete', 'onyx-command'); ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>