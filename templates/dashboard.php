<?php
/**
 * Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap oc-dashboard">
    <h1><?php _e('Onyx Command Dashboard', 'onyx-command'); ?></h1>
    
    <!-- Statistics Overview -->
    <div class="oc-stats-grid">
        <div class="oc-stat-card">
            <div class="oc-stat-icon">📦</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html($stats['total_modules']); ?></h3>
                <p><?php _e('Total Modules', 'onyx-command'); ?></p>
            </div>
        </div>
        
        <div class="oc-stat-card oc-stat-success">
            <div class="oc-stat-icon">✓</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html($stats['active_modules']); ?></h3>
                <p><?php _e('Active Modules', 'onyx-command'); ?></p>
            </div>
        </div>
        
        <div class="oc-stat-card oc-stat-info">
            <div class="oc-stat-icon">💾</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html(size_format(disk_free_space(ABSPATH))); ?></h3>
                <p><?php _e('Free Disk Space', 'onyx-command'); ?></p>
            </div>
        </div>
        
        <div class="oc-stat-card oc-stat-primary">
            <div class="oc-stat-icon">⚡</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html(wp_count_posts('attachment')->publish); ?></h3>
                <p><?php _e('Media Files', 'onyx-command'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="oc-section">
        <h2><?php _e('Quick Actions', 'onyx-command'); ?></h2>
        <div class="oc-quick-actions">
            <a href="<?php echo admin_url('admin.php?page=onyx-command-upload'); ?>" class="button button-primary button-hero">
                <?php _e('Upload New Module', 'onyx-command'); ?>
            </a>
            <button class="button button-secondary button-hero oc-clear-cache">
                <?php _e('Clear All Caches', 'onyx-command'); ?>
            </button>
            <a href="<?php echo admin_url('admin.php?page=onyx-command-optimizer'); ?>" class="button button-secondary button-hero">
                <?php _e('Optimize Site', 'onyx-command'); ?>
            </a>
        </div>
    </div>
    
    <!-- Optimization Suggestions -->
    <?php if (!empty($suggestions)) : ?>
    <div class="oc-section">
        <h2><?php _e('Optimization Suggestions', 'onyx-command'); ?></h2>
        <div class="oc-suggestions">
            <?php foreach ($suggestions as $suggestion) : ?>
                <div class="oc-suggestion oc-suggestion-<?php echo esc_attr($suggestion['severity']); ?>">
                    <div class="oc-suggestion-icon">
                        <?php if ($suggestion['severity'] === 'high') : ?>
                            ⚠️
                        <?php elseif ($suggestion['severity'] === 'medium') : ?>
                            ⚠️
                        <?php else : ?>
                            ℹ️
                        <?php endif; ?>
                    </div>
                    <div class="oc-suggestion-content">
                        <p><?php echo esc_html($suggestion['message']); ?></p>
                        <?php if ($suggestion['action'] !== 'manual') : ?>
                            <button class="button button-small oc-apply-suggestion" data-action="<?php echo esc_attr($suggestion['action']); ?>">
                                <?php _e('Apply Fix', 'onyx-command'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Most Used Modules -->
    <?php if (!empty($stats['most_used_modules'])) : ?>
    <div class="oc-section">
        <h2><?php _e('Most Used Modules', 'onyx-command'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Module Name', 'onyx-command'); ?></th>
                    <th><?php _e('Module ID', 'onyx-command'); ?></th>
                    <th><?php _e('Execution Count', 'onyx-command'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['most_used_modules'] as $module) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($module['name']); ?></strong></td>
                        <td><?php echo esc_html($module['module_id']); ?></td>
                        <td><?php echo number_format($module['execution_count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Recent Activity -->
    <div class="oc-section">
        <h2><?php _e('Recent Activity', 'onyx-command'); ?></h2>
        <?php if (!empty($recent_logs)) : ?>
            <table class="wp-list-table widefat fixed striped oc-activity-table">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php _e('Type', 'onyx-command'); ?></th>
                        <th><?php _e('Details', 'onyx-command'); ?></th>
                        <th style="width: 150px;"><?php _e('Date', 'onyx-command'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log) : 
                        $details_data = !empty($log['details']) ? (is_string($log['details']) ? json_decode($log['details'], true) : $log['details']) : array();
                        $metadata = !empty($log['metadata']) ? (is_string($log['metadata']) ? json_decode($log['metadata'], true) : $log['metadata']) : array();
                    ?>
                        <tr class="oc-activity-row oc-activity-<?php echo esc_attr($log['log_type']); ?>">
                            <td>
                                <span class="oc-log-badge oc-log-<?php echo esc_attr($log['log_type']); ?>">
                                    <?php 
                                    $icons = array(
                                        'error' => '❌',
                                        'warning' => '⚠️',
                                        'info' => 'ℹ️',
                                        'security' => '🔒'
                                    );
                                    echo $icons[$log['log_type']] ?? 'ℹ️';
                                    ?>
                                    <?php echo esc_html(ucfirst($log['log_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="oc-activity-details">
                                    <strong class="oc-activity-message"><?php echo esc_html($log['message']); ?></strong>
                                    
                                    <?php if ($log['module_id']) : ?>
                                        <div class="oc-activity-meta">
                                            <span class="oc-meta-item">
                                                📦 Module: <strong><?php echo esc_html($log['module_id']); ?></strong>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($details_data)) : ?>
                                        <div class="oc-activity-extra">
                                            <button type="button" class="oc-toggle-details" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'; this.textContent = this.textContent === '▼ Show Details' ? '▲ Hide Details' : '▼ Show Details';">▼ Show Details</button>
                                            <div class="oc-details-content" style="display: none;">
                                                <?php if ($log['log_type'] === 'error' && !empty($details_data)) : ?>
                                                    <div class="oc-error-details">
                                                        <?php if (!empty($details_data['error'])) : ?>
                                                            <div class="oc-error-message">
                                                                <strong>Error Message:</strong><br>
                                                                <code><?php echo esc_html($details_data['error']); ?></code>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="oc-troubleshooting">
                                                            <strong>🔧 Troubleshooting Steps:</strong>
                                                            <ol>
                                                                <li>Check if the module is compatible with your WordPress version</li>
                                                                <li>Verify all required PHP extensions are installed</li>
                                                                <li>Review the error log file for additional context</li>
                                                                <li>Try deactivating and reactivating the module</li>
                                                                <li>Check for conflicting plugins or themes</li>
                                                            </ol>
                                                        </div>
                                                    </div>
                                                <?php else : ?>
                                                    <pre><?php echo esc_html(json_encode($details_data, JSON_PRETTY_PRINT)); ?></pre>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="oc-activity-time">
                                    <?php 
                                    $time = strtotime($log['created_at']);
                                    $now = current_time('timestamp');
                                    $diff = $now - $time;
                                    
                                    if ($diff < 60) {
                                        echo 'Just now';
                                    } elseif ($diff < 3600) {
                                        echo round($diff / 60) . ' min ago';
                                    } elseif ($diff < 86400) {
                                        echo round($diff / 3600) . ' hours ago';
                                    } else {
                                        echo date('M j, Y', $time);
                                    }
                                    ?>
                                    <br>
                                    <small style="color: #757575;"><?php echo date('g:i A', $time); ?></small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo admin_url('admin.php?page=onyx-command-logs'); ?>" class="button">
                    <?php _e('View All Logs', 'onyx-command'); ?>
                </a>
            </p>
        <?php else : ?>
            <p><?php _e('No recent activity.', 'onyx-command'); ?></p>
        <?php endif; ?>
    </div>
</div>
