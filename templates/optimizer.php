<?php
/**
 * Optimizer Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap oc-optimizer">
    <h1><?php _e('Site Optimizer', 'onyx-command'); ?></h1>
    
    <!-- Optimization Tools -->
    <div class="oc-optimizer-grid">
        <!-- Cache Management -->
        <div class="oc-optimizer-card">
            <h2>🗄️ <?php _e('Cache Management', 'onyx-command'); ?></h2>
            <p><?php _e('Clear all types of caches to ensure changes are visible.', 'onyx-command'); ?></p>
            <button class="button button-primary button-large oc-clear-cache-btn">
                <?php _e('Clear All Caches', 'onyx-command'); ?>
            </button>
            <div class="oc-cache-info">
                <p><small><?php _e('This will clear WordPress object cache, transients, plugin caches, and OPcache.', 'onyx-command'); ?></small></p>
            </div>
        </div>
        
        <!-- Database Cleanup -->
        <div class="oc-optimizer-card">
            <h2>🗃️ <?php _e('Database Cleanup', 'onyx-command'); ?></h2>
            <p><?php _e('Clean and optimize your WordPress database.', 'onyx-command'); ?></p>
            <button class="button button-primary button-large oc-clean-db-btn">
                <?php _e('Clean Database', 'onyx-command'); ?>
            </button>
            <div class="oc-db-info">
                <p><small><?php _e('Removes revisions, auto-drafts, spam comments, and orphaned data.', 'onyx-command'); ?></small></p>
            </div>
        </div>
        
        <!-- Log Cleanup -->
        <div class="oc-optimizer-card">
            <h2>📋 <?php _e('Log Cleanup', 'onyx-command'); ?></h2>
            <p><?php _e('Remove old log entries to free up space.', 'onyx-command'); ?></p>
            <div class="oc-log-cleanup-form">
                <label>
                    <?php _e('Delete logs older than:', 'onyx-command'); ?>
                    <select id="oc-log-days">
                        <option value="7">7 <?php _e('days', 'onyx-command'); ?></option>
                        <option value="14">14 <?php _e('days', 'onyx-command'); ?></option>
                        <option value="30" selected>30 <?php _e('days', 'onyx-command'); ?></option>
                        <option value="60">60 <?php _e('days', 'onyx-command'); ?></option>
                        <option value="90">90 <?php _e('days', 'onyx-command'); ?></option>
                    </select>
                </label>
                <button class="button button-primary button-large oc-clean-logs-btn">
                    <?php _e('Clean Logs', 'onyx-command'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Database Information -->
    <div class="oc-section">
        <h2><?php _e('Database Information', 'onyx-command'); ?></h2>
        <div class="oc-db-stats">
            <div class="oc-stat-item">
                <strong><?php _e('Total Size:', 'onyx-command'); ?></strong>
                <span><?php echo esc_html($db_info['total_size']); ?></span>
            </div>
            <div class="oc-stat-item">
                <strong><?php _e('Total Rows:', 'onyx-command'); ?></strong>
                <span><?php echo esc_html($db_info['total_rows']); ?></span>
            </div>
            <div class="oc-stat-item">
                <strong><?php _e('Total Tables:', 'onyx-command'); ?></strong>
                <span><?php echo esc_html($db_info['table_count']); ?></span>
            </div>
        </div>
        
        <h3><?php _e('Largest Tables', 'onyx-command'); ?></h3>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Table Name', 'onyx-command'); ?></th>
                    <th><?php _e('Rows', 'onyx-command'); ?></th>
                    <th><?php _e('Size', 'onyx-command'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($db_info['tables'] as $table) : ?>
                    <tr>
                        <td><code><?php echo esc_html($table['name']); ?></code></td>
                        <td><?php echo esc_html($table['rows']); ?></td>
                        <td><?php echo esc_html($table['size']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Optimization Suggestions -->
    <?php if (!empty($suggestions)) : ?>
        <div class="oc-section">
            <h2><?php _e('Optimization Suggestions', 'onyx-command'); ?></h2>
            <div class="oc-suggestions-list">
                <?php foreach ($suggestions as $suggestion) : ?>
                    <div class="oc-suggestion oc-suggestion-<?php echo esc_attr($suggestion['severity']); ?>">
                        <div class="oc-suggestion-icon">
                            <?php 
                            switch ($suggestion['severity']) {
                                case 'high':
                                    echo '🔴';
                                    break;
                                case 'medium':
                                    echo '🟡';
                                    break;
                                default:
                                    echo '🟢';
                            }
                            ?>
                        </div>
                        <div class="oc-suggestion-content">
                            <h4><?php echo esc_html(ucfirst($suggestion['type'])); ?></h4>
                            <p><?php echo esc_html($suggestion['message']); ?></p>
                            <?php if ($suggestion['action'] !== 'manual') : ?>
                                <button class="button oc-apply-suggestion" data-action="<?php echo esc_attr($suggestion['action']); ?>">
                                    <?php _e('Apply Fix', 'onyx-command'); ?>
                                </button>
                            <?php else : ?>
                                <p class="oc-manual-fix"><em><?php _e('Manual configuration required', 'onyx-command'); ?></em></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Performance Tips -->
    <div class="oc-section oc-tips">
        <h2><?php _e('Performance Tips', 'onyx-command'); ?></h2>
        <div class="oc-tips-grid">
            <div class="oc-tip-card">
                <h3>💨 <?php _e('Enable Caching', 'onyx-command'); ?></h3>
                <p><?php _e('Use a caching plugin like WP Rocket, W3 Total Cache, or WP Super Cache for better performance.', 'onyx-command'); ?></p>
            </div>
            <div class="oc-tip-card">
                <h3>🖼️ <?php _e('Optimize Images', 'onyx-command'); ?></h3>
                <p><?php _e('Compress images before uploading and consider using a CDN for faster delivery.', 'onyx-command'); ?></p>
            </div>
            <div class="oc-tip-card">
                <h3>🔧 <?php _e('Update Regularly', 'onyx-command'); ?></h3>
                <p><?php _e('Keep WordPress, themes, and plugins updated for security and performance improvements.', 'onyx-command'); ?></p>
            </div>
            <div class="oc-tip-card">
                <h3>📊 <?php _e('Monitor Performance', 'onyx-command'); ?></h3>
                <p><?php _e('Use tools like Query Monitor or New Relic to identify performance bottlenecks.', 'onyx-command'); ?></p>
            </div>
        </div>
    </div>
</div>

<div id="oc-optimizer-result" class="oc-result-modal" style="display: none;">
    <div class="oc-result-content">
        <span class="oc-result-close">&times;</span>
        <h2><?php _e('Operation Result', 'onyx-command'); ?></h2>
        <div id="oc-optimizer-message"></div>
    </div>
</div>
