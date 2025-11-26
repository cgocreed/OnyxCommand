<?php
/**
 * Error Logs Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap oc-logs">
    <h1><?php _e('Error Logs', 'onyx-command'); ?></h1>
    
    <!-- Log Statistics -->
    <div class="oc-log-stats">
        <div class="oc-stat-card">
            <h3><?php echo esc_html($stats['unresolved_count']); ?></h3>
            <p><?php _e('Unresolved Errors', 'onyx-command'); ?></p>
        </div>
        
        <?php if (!empty($stats['by_type'])) : ?>
            <?php foreach ($stats['by_type'] as $type_stat) : ?>
                <div class="oc-stat-card">
                    <h3><?php echo esc_html($type_stat['count']); ?></h3>
                    <p><?php echo esc_html(ucfirst($type_stat['log_type'])); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Filter and Actions -->
    <div class="oc-log-actions">
        <div class="oc-filters">
            <select id="oc-log-filter" class="oc-filter-select">
                <option value="all" <?php selected($filter, 'all'); ?>><?php _e('All Logs', 'onyx-command'); ?></option>
                <option value="error" <?php selected($filter, 'error'); ?>><?php _e('Errors Only', 'onyx-command'); ?></option>
                <option value="warning" <?php selected($filter, 'warning'); ?>><?php _e('Warnings Only', 'onyx-command'); ?></option>
                <option value="info" <?php selected($filter, 'info'); ?>><?php _e('Info Only', 'onyx-command'); ?></option>
                <option value="security" <?php selected($filter, 'security'); ?>><?php _e('Security Only', 'onyx-command'); ?></option>
            </select>
        </div>
        
        <div class="oc-actions-right">
            <button class="button oc-export-logs"><?php _e('Export Logs', 'onyx-command'); ?></button>
            <button class="button oc-clean-old-logs"><?php _e('Clean Old Logs', 'onyx-command'); ?></button>
            <button class="button button-link-delete oc-clear-all-logs"><?php _e('Clear All Logs', 'onyx-command'); ?></button>
        </div>
    </div>
    
    <!-- Logs Table -->
    <?php if (!empty($logs)) : ?>
        <table class="wp-list-table widefat fixed striped oc-logs-table">
            <thead>
                <tr>
                    <th style="width: 80px;"><?php _e('Type', 'onyx-command'); ?></th>
                    <th style="width: 150px;"><?php _e('Module', 'onyx-command'); ?></th>
                    <th><?php _e('Message', 'onyx-command'); ?></th>
                    <th style="width: 180px;"><?php _e('Date', 'onyx-command'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'onyx-command'); ?></th>
                    <th style="width: 120px;"><?php _e('Actions', 'onyx-command'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr class="oc-log-row oc-log-<?php echo esc_attr($log['log_type']); ?><?php echo $log['resolved'] ? ' oc-log-resolved' : ''; ?>" 
                        data-log-id="<?php echo esc_attr($log['id']); ?>">
                        <td>
                            <span class="oc-log-badge oc-log-badge-<?php echo esc_attr($log['log_type']); ?>">
                                <?php 
                                $icons = array(
                                    'error' => '❌',
                                    'warning' => '⚠️',
                                    'info' => 'ℹ️',
                                    'security' => '🔒'
                                );
                                echo $icons[$log['log_type']] ?? '•';
                                echo ' ' . esc_html(ucfirst($log['log_type']));
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['module_id']) : ?>
                                <code><?php echo esc_html($log['module_id']); ?></code>
                            <?php else : ?>
                                <em><?php _e('System', 'onyx-command'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="oc-log-message">
                                <?php echo esc_html($log['message']); ?>
                            </div>
                            <?php if (!empty($log['details'])) : ?>
                                <button class="button-link oc-toggle-details"><?php _e('Show Details', 'onyx-command'); ?></button>
                                <div class="oc-log-details" style="display: none;">
                                    <pre><?php echo esc_html(is_array($log['details']) ? json_encode($log['details'], JSON_PRETTY_PRINT) : $log['details']); ?></pre>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(date('M j, Y g:i A', strtotime($log['created_at']))); ?></td>
                        <td>
                            <?php if ($log['resolved']) : ?>
                                <span class="oc-badge-resolved">✓ <?php _e('Resolved', 'onyx-command'); ?></span>
                            <?php else : ?>
                                <span class="oc-badge-unresolved">○ <?php _e('Unresolved', 'onyx-command'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$log['resolved']) : ?>
                                <button class="button button-small oc-resolve-log" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <?php _e('Resolve', 'onyx-command'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="oc-empty-state">
            <p><?php _e('No logs found.', 'onyx-command'); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Most Problematic Modules -->
    <?php if (!empty($stats['problematic_modules'])) : ?>
        <div class="oc-section">
            <h2><?php _e('Most Problematic Modules', 'onyx-command'); ?></h2>
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th><?php _e('Module ID', 'onyx-command'); ?></th>
                        <th><?php _e('Error Count (Last 30 Days)', 'onyx-command'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['problematic_modules'] as $module) : ?>
                        <tr>
                            <td><code><?php echo esc_html($module['module_id']); ?></code></td>
                            <td><?php echo esc_html($module['error_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
