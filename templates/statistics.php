<?php
/**
 * Statistics Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap oc-statistics">
    <h1><?php _e('Statistics', 'onyx-command'); ?></h1>
    
    <div class="oc-stats-grid">
        <div class="oc-stat-card">
            <div class="oc-stat-icon">📦</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html($overall_stats['total_modules']); ?></h3>
                <p><?php _e('Total Modules', 'onyx-command'); ?></p>
            </div>
        </div>
        
        <div class="oc-stat-card oc-stat-success">
            <div class="oc-stat-icon">✓</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html($overall_stats['active_modules']); ?></h3>
                <p><?php _e('Active Modules', 'onyx-command'); ?></p>
            </div>
        </div>
        
        <!-- Total Executions removed -->
        
        <div class="oc-stat-card oc-stat-warning">
            <div class="oc-stat-icon">⚠️</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html($overall_stats['total_errors']); ?></h3>
                <p><?php _e('Total Errors', 'onyx-command'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Performance Report -->
    <?php if (!empty($performance)) : ?>
        <div class="oc-section">
            <h2><?php _e('Performance Report (Last 30 Days)', 'onyx-command'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Module ID', 'onyx-command'); ?></th>
                        <th><?php _e('Successful', 'onyx-command'); ?></th>
                        <th><?php _e('Failed', 'onyx-command'); ?></th>
                        <th><?php _e('Success Rate', 'onyx-command'); ?></th>
                        <th><?php _e('Avg Duration (ms)', 'onyx-command'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($performance as $module_id => $data) : ?>
                        <tr>
                            <td><code><?php echo esc_html($module_id); ?></code></td>
                            <td class="oc-success-count"><?php echo number_format($data['successful_executions']); ?></td>
                            <td class="oc-error-count"><?php echo number_format($data['failed_executions']); ?></td>
                            <td>
                                <div class="oc-progress-bar">
                                    <div class="oc-progress-fill" style="width: <?php echo esc_attr($data['success_rate']); ?>%"></div>
                                </div>
                                <span><?php echo esc_html($data['success_rate']); ?>%</span>
                            </td>
                            <td><?php echo esc_html(number_format($data['avg_duration'], 2)); ?> ms</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Most Used Modules -->
    <?php if (!empty($overall_stats['most_used_modules'])) : ?>
        <div class="oc-section">
            <h2><?php _e('Most Used Modules', 'onyx-command'); ?></h2>
            <div class="oc-module-usage">
                <?php foreach ($overall_stats['most_used_modules'] as $index => $module) : ?>
                    <div class="oc-usage-item">
                        <div class="oc-usage-rank">#<?php echo ($index + 1); ?></div>
                        <div class="oc-usage-info">
                            <strong><?php echo esc_html($module['name']); ?></strong>
                            <p><code><?php echo esc_html($module['module_id']); ?></code></p>
                        </div>
                        <div class="oc-usage-count">
                            <strong><?php echo number_format($module['execution_count']); ?></strong>
                            <p><?php _e('uses', 'onyx-command'); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>