<?php
/**
 * Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Build media counts grouped by mime-type families
$media_counts = array(
    'total' => 0,
    'image' => 0,
    'video' => 0,
    'audio' => 0,
    'pdf' => 0,
    'zip' => 0,
    'other' => 0,
);

$attachment_ids = get_posts(array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'numberposts'    => -1,
    'fields'         => 'ids',
));

if (!empty($attachment_ids)) {
    $media_counts['total'] = count($attachment_ids);
    foreach ($attachment_ids as $aid) {
        $mime = get_post_mime_type($aid);
        if (strpos($mime, 'image/') === 0) {
            $media_counts['image']++;
        } elseif (strpos($mime, 'video/') === 0) {
            $media_counts['video']++;
        } elseif (strpos($mime, 'audio/') === 0) {
            $media_counts['audio']++;
        } elseif ($mime === 'application/pdf') {
            $media_counts['pdf']++;
        } elseif ($mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
            $media_counts['zip']++;
        } else {
            $media_counts['other']++;
        }
    }
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
        
        <!-- Replaced Free Disk Space with Errors Caught -->
        <div class="oc-stat-card oc-stat-info">
            <div class="oc-stat-icon">⚠️</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html(intval($stats['total_errors'])); ?></h3>
                <p><?php _e('Errors Caught', 'onyx-command'); ?></p>
            </div>
        </div>
        
        <!-- Media Files block with breakdown -->
        <div class="oc-stat-card oc-stat-primary">
            <div class="oc-stat-icon">🖼️</div>
            <div class="oc-stat-content">
                <h3><?php echo esc_html($media_counts['total']); ?></h3>
                <p><?php _e('Media Files', 'onyx-command'); ?></p>
                <p class="oc-media-breakdown" style="font-size:12px; margin-top:6px; color: #666;">
                    <?php
                        $parts = array();
                        $parts[] = sprintf(__('IMAGE FILES (%d)', 'onyx-command'), $media_counts['image']);
                        $parts[] = sprintf(__('VIDEO FILES (%d)', 'onyx-command'), $media_counts['video']);
                        $parts[] = sprintf(__('PDFs (%d)', 'onyx-command'), $media_counts['pdf']);
                        $parts[] = sprintf(__('ZIPs (%d)', 'onyx-command'), $media_counts['zip']);
                        $parts[] = sprintf(__('AUDIO (%d)', 'onyx-command'), $media_counts['audio']);
                        if ($media_counts['other'] > 0) {
                            $parts[] = sprintf(__('OTHER (%d)', 'onyx-command'), $media_counts['other']);
                        }
                        echo implode(', ', $parts);
                    ?>
                </p>
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
                    <?php echo esc_html($suggestion['message']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>