<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to access this page.', 'throwaway-lookup'));
}

global $wpdb;
$table = $wpdb->prefix . 'throwaway_logs';

$log_filter_context = isset($_GET['log_filter_context']) ? sanitize_text_field(wp_unslash($_GET['log_filter_context'])) : '';
$log_filter_email = isset($_GET['log_filter_email']) ? sanitize_text_field(wp_unslash($_GET['log_filter_email'])) : '';

$deleted_count = isset($_GET['throwaway_deleted']) ? (int) $_GET['throwaway_deleted'] : null;
$error_code = isset($_GET['throwaway_error']) ? sanitize_text_field(wp_unslash($_GET['throwaway_error'])) : '';
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    if ($deleted_count !== null) {
        echo '<div class="updated notice notice-success is-dismissible"><p>' . esc_html(sprintf(
            /* translators: %d: number of deleted log rows */
            _n('Deleted %d log row.', 'Deleted %d log rows.', $deleted_count, 'throwaway-lookup'),
            $deleted_count
        )) . '</p></div>';
    }

    if ($error_code === 'no-subject') {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please enter an email or domain.', 'throwaway-lookup') . '</p></div>';
    } elseif ($error_code === 'no-logs') {
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('No logs found for that subject.', 'throwaway-lookup') . '</p></div>';
    }
    ?>

    <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
        <?php
        settings_fields('throwaway_lookup_settings');
        do_settings_sections('throwaway_lookup_settings');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Logging Level', 'throwaway-lookup'); ?></th>
                <td>
                    <select name="throwaway_lookup_log_level">
                        <?php
                        $log_levels = [
                            'none' => __('None', 'throwaway-lookup'),
                            'domain' => __('Domain Only', 'throwaway-lookup'),
                            'full' => __('Full Email Address', 'throwaway-lookup'),
                        ];
                        foreach ($log_levels as $value => $label) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr($value),
                                selected(get_option('throwaway_lookup_log_level'), $value, false),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Choose what gets stored in the log. Domain only is a common GDPR-friendly option.', 'throwaway-lookup'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php echo esc_html__('Allowed List', 'throwaway-lookup'); ?></th>
                <td>
                    <textarea name="throwaway_lookup_allowed_list" rows="5" cols="50"><?php echo esc_textarea(get_option('throwaway_lookup_allowed_list')); ?></textarea>
                    <p class="description"><?php echo esc_html__('Enter one domain or email per line to always allow.', 'throwaway-lookup'); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>

    <hr>
    <h2><?php echo esc_html__('GDPR Tools', 'throwaway-lookup'); ?></h2>

    <h3><?php echo esc_html__('Export Logs (CSV)', 'throwaway-lookup'); ?></h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="throwaway_lookup_export_csv" />
        <?php wp_nonce_field('throwaway_lookup_gdpr_action', 'throwaway_lookup_nonce'); ?>
        <input type="text" name="gdpr_subject" placeholder="<?php echo esc_attr__('Enter email or domain', 'throwaway-lookup'); ?>" required />
        <?php submit_button(__('Export Logs (CSV)', 'throwaway-lookup'), 'secondary', 'export_subject_logs'); ?>
    </form>

    <h3><?php echo esc_html__('Delete Logs', 'throwaway-lookup'); ?></h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="throwaway_lookup_delete_logs" />
        <?php wp_nonce_field('throwaway_lookup_gdpr_action', 'throwaway_lookup_nonce'); ?>
        <input type="text" name="gdpr_subject" placeholder="<?php echo esc_attr__('Enter email or domain', 'throwaway-lookup'); ?>" required />
        <input type="submit" name="delete_subject_logs" class="button button-danger" value="<?php echo esc_attr__('Delete Logs', 'throwaway-lookup'); ?>" />
    </form>

    <hr>
    <h2><?php echo esc_html__('Log Viewer', 'throwaway-lookup'); ?></h2>
    <form method="get" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="throwaway-lookup" />
        <label for="log_filter_context"><?php echo esc_html__('Context:', 'throwaway-lookup'); ?></label>
        <input type="text" name="log_filter_context" id="log_filter_context" value="<?php echo esc_attr($log_filter_context); ?>" />

        <label for="log_filter_email"><?php echo esc_html__('Email/Domain:', 'throwaway-lookup'); ?></label>
        <input type="text" name="log_filter_email" id="log_filter_email" value="<?php echo esc_attr($log_filter_email); ?>" />

        <?php submit_button(__('Filter Logs', 'throwaway-lookup'), 'primary', 'filter_logs', false); ?>
    </form>

    <table class="widefat striped" style="width: 100%;">
        <thead>
            <tr>
                <th><?php echo esc_html__('Timestamp', 'throwaway-lookup'); ?></th>
                <th><?php echo esc_html__('Context', 'throwaway-lookup'); ?></th>
                <th><?php echo esc_html__('Email/Domain', 'throwaway-lookup'); ?></th>
                <th><?php echo esc_html__('Result', 'throwaway-lookup'); ?></th>
                <th><?php echo esc_html__('Source', 'throwaway-lookup'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql_parts = ["SELECT * FROM {$table}"];
            $params = [];

            if (!empty($log_filter_context)) {
                $sql_parts[] = "context = %s";
                $params[] = $log_filter_context;
            }

            if (!empty($log_filter_email)) {
                $sql_parts[] = "email LIKE %s";
                $params[] = '%' . $wpdb->esc_like($log_filter_email) . '%';
            }

            if ($params) {
                $sql = $wpdb->prepare(implode(' WHERE ', $sql_parts) . " ORDER BY timestamp DESC LIMIT 50", ...$params);
            } else {
                $sql = implode(' ', $sql_parts) . " ORDER BY timestamp DESC LIMIT 50";
            }

            $logs = $wpdb->get_results($sql);
            if ($logs) {
                foreach ($logs as $row) {
                    echo '<tr>';
                    echo '<td>' . esc_html($row->timestamp) . '</td>';
                    echo '<td>' . esc_html($row->context) . '</td>';
                    echo '<td>' . esc_html($row->email) . '</td>';
                    echo '<td>' . ($row->result ? '<span style="color: red;">' . esc_html__('Disposable', 'throwaway-lookup') . '</span>' : '<span style="color: green;">' . esc_html__('Valid', 'throwaway-lookup') . '</span>') . '</td>';
                    echo '<td>' . esc_html($row->source) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5">' . esc_html__('No logs found.', 'throwaway-lookup') . '</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
