<?php
global $wpdb;
$table = $wpdb->prefix . 'throwaway_logs';

$log_filter_context = sanitize_text_field($_GET['log_filter_context'] ?? '');
$log_filter_email = sanitize_text_field($_GET['log_filter_email'] ?? '');
$gdpr_subject = sanitize_text_field($_POST['gdpr_subject'] ?? '');
?>

<div class="wrap">
    <h1>throwaway.cloud E-Mail Check Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('throwaway_lookup_settings');
        do_settings_sections('throwaway_lookup_settings');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Logging Level</th>
                <td>
                    <select name="throwaway_lookup_log_level">
                        <?php
                        $log_levels = [
                            'none' => 'None',
                            'domain' => 'Domain Only',
                            'full' => 'Full Email Address'
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
                    <p class="description">Choose what gets stored in the log. Domain only is a common GDPR-friendly option.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Allowed List</th>
                <td>
                    <textarea name="throwaway_lookup_allowed_list" rows="5" cols="50"><?php echo esc_textarea(get_option('throwaway_lookup_allowed_list')); ?></textarea>
                    <p class="description">Enter one domain or email per line to always allow.</p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>

    <hr>
    <h2>GDPR Tools</h2>
    <form method="post">
        <?php wp_nonce_field('gdpr_tools_nonce', 'gdpr_tools_nonce_field'); ?>
        <input type="text" name="gdpr_subject" placeholder="Enter email or domain" required />
        <input type="submit" name="export_subject_logs" class="button" value="Export Logs (CSV)" />
        <input type="submit" name="delete_subject_logs" class="button button-danger" value="Delete Logs" />
    </form>

    <hr>
    <h2>Log Viewer</h2>
    <form method="get" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="throwaway-lookup" />
        <label for="log_filter_context">Context:</label>
        <input type="text" name="log_filter_context" value="<?php echo esc_attr($log_filter_context); ?>" />
        
        <label for="log_filter_email">Email/Domain:</label>
        <input type="text" name="log_filter_email" value="<?php echo esc_attr($log_filter_email); ?>" />
        
        <input type="submit" class="button button-primary" value="Filter Logs" />
    </form>

    <table class="widefat striped" style="width: 100%;">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Context</th>
                <th>Email/Domain</th>
                <th>Result</th>
                <th>Source</th>
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
                    echo '<td>' . ($row->result ? '<span style="color: red;">Disposable</span>' : '<span style="color: green;">Valid</span>') . '</td>';
                    echo '<td>' . esc_html($row->source) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5">No logs found.</td></tr>';
            }

            if (!empty($gdpr_subject) && check_admin_referer('gdpr_tools_nonce', 'gdpr_tools_nonce_field')) {
                $like = '%' . $wpdb->esc_like($gdpr_subject) . '%';
                if (isset($_POST['delete_subject_logs'])) {
                    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE email LIKE %s", $like));
                    echo '<div class="updated"><p>Logs deleted for: ' . esc_html($gdpr_subject) . '</p></div>';
                } elseif (isset($_POST['export_subject_logs'])) {
                    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE email LIKE %s", $like), ARRAY_A);
                    if ($logs) {
                        header('Content-Type: text/csv');
                        header('Content-Disposition: attachment; filename="subject-logs.csv"');
                        $out = fopen('php://output', 'w');
                        fputcsv($out, array_keys($logs[0]));
                        foreach ($logs as $log) {
                            fputcsv($out, $log);
                        }
                        fclose($out);
                        exit;
                    } else {
                        echo '<div class="notice notice-warning"><p>No logs found for: ' . esc_html($gdpr_subject) . '</p></div>';
                    }
                }
            } elseif (!empty($gdpr_subject)) {
                echo '<div class="notice notice-error"><p>Nonce verification failed.</p></div>';
            }
            ?>
        </tbody>
    </table>
</div>