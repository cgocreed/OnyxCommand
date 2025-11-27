<?php
/**
 * Error Log template — shows recent captured errors with context and suggestions.
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap oc-error-log">
    <h1><?php _e('Onyx Command — Error Log', 'onyx-command'); ?></h1>

    <?php if (empty($logs)) : ?>
        <p><?php _e('No errors have been captured yet.', 'onyx-command'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'onyx-command'); ?></th>
                    <th><?php _e('Type', 'onyx-command'); ?></th>
                    <th><?php _e('Message', 'onyx-command'); ?></th>
                    <th><?php _e('Location', 'onyx-command'); ?></th>
                    <th><?php _e('Context / Page', 'onyx-command'); ?></th>
                    <th><?php _e('Suggested Fix', 'onyx-command'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(isset($log['created_at']) ? $log['created_at'] : (isset($log['time']) ? $log['time'] : '')); ?></td>
                        <td><?php echo esc_html(isset($log['type']) ? $log['type'] : ''); ?></td>
                        <td style="max-width:400px; word-break:break-word;"><?php echo esc_html(isset($log['message']) ? $log['message'] : ''); ?></td>
                        <td><?php echo esc_html((isset($log['file']) ? basename($log['file']) . ':' . $log['line'] : '')); ?></td>
                        <td>
                            <?php
                                $context = isset($log['context']) ? $log['context'] : '';
                                if (!empty($context)) {
                                    // If contains admin edit query, show link
                                    if (false !== strpos($context, 'post.php') || false !== strpos($context, 'post-new.php')) {
                                        echo '<a href="' . esc_url(admin_url($context)) . '">' . esc_html($context) . '</a>';
                                    } else {
                                        echo esc_html($context);
                                    }
                                } else {
                                    echo '-';
                                }
                            ?>
                        </td>
                        <td>
                            <?php
                                if (isset($log['type']) && strpos($log['type'], 'php') !== false) {
                                    echo __('Check the error line in the file, ensure functions exist and required files are included. If this is from a plugin or theme, try disabling that plugin/theme temporarily.', 'onyx-command');
                                } elseif (isset($log['type']) && $log['type'] === 'exception') {
                                    echo __('Inspect the exception stack trace and message to determine which component caused it. Consider contacting the plugin/theme author if it originates from third-party code.', 'onyx-command');
                                } else {
                                    echo __('Review the message and context; search the codebase for the file/line referenced.', 'onyx-command');
                                }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>