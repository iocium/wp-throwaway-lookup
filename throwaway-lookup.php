<?php
/**
 * Plugin Name: throwaway.cloud E-Mail Check
 * Description: Blocks disposable email addresses by performing lookups to the throwaway.cloud API.
 * Version: 1.0.0
 * Author: Iocium
 * License: MIT
 * Text Domain: throwawaycloud-email-lookup
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

class ThrowawayEmailLookup {
    const API_ENDPOINT = 'https://throwaway.cloud/api/v2/';
    const OPTION_ALLOWED = 'throwaway_lookup_allowed_list';
    const OPTION_LOG_LEVEL = 'throwaway_lookup_log_level';

    private $allowed_cached = null;

    public function __construct() {
        add_action('preprocess_comment', [$this, 'filter_comment'], 1);
        add_filter('registration_errors', [$this, 'filter_registration'], 10, 3);
        add_action('throwaway_lookup_delete_subject', [$this, 'handle_external_deletion'], 10, 1);
        add_filter('throwaway_lookup_export_subject', [$this, 'handle_external_export'], 10, 1);
        add_filter('throwaway_lookup_check', [self::class, 'handle_external_check'], 10, 3);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_throwaway_lookup_export_csv', [$this, 'admin_export_csv']);
        add_action('admin_post_throwaway_lookup_delete_logs', [$this, 'admin_delete_logs']);

        // Register data exporters and erasers for GDPR compliance
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_data_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_data_eraser']);
    }

    public static function handle_external_check($default, $email, $_unused = null) {
        $instance = throwaway_email_lookup();

        $source_plugin = 'unknown';
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (!empty($frame['file']) && strpos($frame['file'], WP_PLUGIN_DIR) === 0) {
                $relative_path = str_replace(WP_PLUGIN_DIR . '/', '', $frame['file']);
                $parts = explode('/', $relative_path);
                $source_plugin = $parts[0] ?? 'unknown';
                break;
            }
        }

        return $instance->is_throwaway($email, 'external', $source_plugin);
    }

    public function filter_comment($commentdata) {
        $email = $commentdata['comment_author_email'] ?? '';
        if ($this->is_throwaway($email, 'comment')) {
            wp_die('Please use a permanent email address for commenting.', '', ['response' => 403]);
        }
        return $commentdata;
    }

    public function filter_registration($errors, $login, $email) {
        if ($this->is_throwaway($email, 'registration')) {
            $errors->add('throwaway_email', 'Please use a permanent email address.');
        }
        return $errors;
    }

    public function is_throwaway($email, $context, $source = null) {
        if ($this->is_allowed($email)) return false;
        $is_disposable = $this->query_api($email);
        $is_disposable = apply_filters('throwaway_lookup_result', $is_disposable, $email, $context);
        $this->log_attempt($context, $email, $is_disposable, $source);
        return $is_disposable;
    }

    public function is_allowed($email) {
        if ($this->allowed_cached === null) {
            $this->allowed_cached = array_filter(
                array_map('strtolower', array_map('trim', explode("\n", get_option(self::OPTION_ALLOWED, ''))))
            );
        }

        $email = strtolower($email);
        foreach ($this->allowed_cached as $rule) {
            $normalized_rule = ltrim($rule, '@');
            if ($this->str_ends_with($email, '@' . $normalized_rule) || $email === $rule) {
                return true;
            }
        }
        return false;
    }

    /**
     * PHP 7.2-compatible replacement for str_ends_with().
     */
    private function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        $length = strlen($needle);
        return $length <= strlen($haystack) && substr($haystack, -$length) === $needle;
    }

    public function query_api($subject) {
        if (filter_var($subject, FILTER_VALIDATE_EMAIL)) {
            $domain = substr(strrchr($subject, "@"), 1);
        } else {
            $domain = $subject;
        }

        $cache_key = 'throwaway_api_query_' . md5($domain);

        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }

        $resp = wp_remote_get(self::API_ENDPOINT . urlencode($domain), [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 5,
        ]);

        if (is_wp_error($resp)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $disposable_status = $body['disposable'] ?? false;

        set_transient($cache_key, ['value' => $disposable_status], 3600);

        return $disposable_status;
    }

    public function log_attempt($context, $email, $result, $source = null) {
        if (get_option(self::OPTION_LOG_LEVEL, 'domain') === 'none') {
            return;
        }

        if ($source === null) {
            $source = defined('THROWAWAY_LOOKUP_SOURCE') ? THROWAWAY_LOOKUP_SOURCE : 'core';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $display = $this->obfuscate_subject($email);
        $wpdb->insert($table, [
            'context' => $context,
            'email' => $display,
            'result' => $result ? 1 : 0,
            'source' => $source,
        ]);
    }

    public function obfuscate_subject($subject) {
        $level = get_option(self::OPTION_LOG_LEVEL, 'domain');
        if ($level === 'none') return '[REDACTED]';
        if ($level === 'domain') return substr(strrchr($subject, "@"), 1) ?: $subject;
        return $subject;
    }

    private function log_audit_event($message) {
        if (!current_user_can('manage_options')) return;
        error_log('[ThrowawayEmailLookup Audit] ' . $message);
    }

    /**
     * Registers the export functionality.
     */
    public function register_data_exporter($exporters) {
        $exporters['throwaway_logs'] = [
            'exporter_friendly_name' => 'Throwaway Email Logs',
            'callback' => [$this, 'data_exporter'],
        ];
        return $exporters;
    }

    /**
     * Callback function to export personal data.
     */
    public function data_exporter($email_address, $page = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $items = [];
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE email LIKE %s", $table, '%' . $wpdb->esc_like($email_address) . '%'), ARRAY_A);

        foreach ($logs as $log) {
            $items[] = [
                'group_id' => 'throwaway_logs',
                'group_label' => 'Throwaway Email Logs',
                'item_id' => "throwaway-log-{$log['id']}",
                'data' => [
                    [
                        'name' => 'Context',
                        'value' => $log['context'],
                    ],
                    [
                        'name' => 'Result',
                        'value' => $log['result'] ? 'Disposable' : 'Not Disposable',
                    ],
                    [
                        'name' => 'Source',
                        'value' => $log['source'],
                    ],
                    [
                        'name' => 'Timestamp',
                        'value' => $log['timestamp'],
                    ],
                ],
            ];
        }

        return [
            'data' => $items,
            'done' => true,
        ];
    }

    /**
     * Registers the eraser functionality.
     */
    public function register_data_eraser($erasers) {
        $erasers['throwaway_logs'] = [
            'eraser_friendly_name' => 'Throwaway Email Logs',
            'callback' => [$this, 'data_eraser'],
        ];
        return $erasers;
    }

    /**
     * Callback function to erase personal data.
     */
    public function data_eraser($email_address, $page = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $count = $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE email LIKE %s", $table, '%' . $wpdb->esc_like($email_address) . '%'));
        $this->log_audit_event("Deleted $count logs for email: $email_address");

        return [
            'items_removed' => $count > 0,
            'items_retained' => false,
            'messages' => [],
            'done' => true,
        ];
    }

    public function handle_external_deletion($subject) {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $count = $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE email LIKE %s", $table, '%' . $wpdb->esc_like($subject) . '%'));
        $this->log_audit_event("Deleted $count logs for subject: $subject");
    }

    public function handle_external_export($subject) {
        if (!current_user_can('manage_options')) {
            return [];
        }

        global $wpdb;

        $cache_key = 'throwaway_export_logs_' . md5($subject);

        $cached_logs = get_transient($cache_key);
        if ($cached_logs !== false) {
            $this->log_audit_event("Fetched cached logs for subject: $subject");
            return $cached_logs;
        }

        $table = $wpdb->prefix . 'throwaway_logs';
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE email LIKE %s", $table, '%' . $wpdb->esc_like($subject) . '%'), ARRAY_A);

        set_transient($cache_key, $logs, 3600);
        $this->log_audit_event("Exported logs for subject: $subject");

        return $logs;
    }

    /**
     * Handle CSV export request from the admin tools page.
     */
    public function admin_export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '', ['response' => 403]);
        }
        check_admin_referer('throwaway_lookup_gdpr_action', 'throwaway_lookup_nonce');

        $subject = isset($_POST['gdpr_subject']) ? sanitize_text_field(wp_unslash($_POST['gdpr_subject'])) : '';
        if (empty($subject)) {
            wp_safe_redirect(admin_url('options-general.php?page=throwaway-lookup&throwaway_error=no-subject'));
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $like = '%' . $wpdb->esc_like($subject) . '%';
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE email LIKE %s", $table, $like), ARRAY_A);

        if (empty($logs)) {
            wp_safe_redirect(admin_url('options-general.php?page=throwaway-lookup&throwaway_error=no-logs'));
            exit;
        }

        $this->log_audit_event("Exported logs for subject: $subject");

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subject-logs.csv"');

        $output = $this->format_csv_row(array_keys($logs[0]));
        foreach ($logs as $log) {
            $output .= $this->format_csv_row($log);
        }
        echo wp_kses_post($output);
        exit;
    }

    /**
     * Format an array of fields as a CSV line without direct filesystem calls.
     */
    private function format_csv_row(array $fields) {
        $escaped = array_map(function ($field) {
            $field = str_replace('"', '""', (string) $field);
            if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false || strpos($field, "\r") !== false) {
                $field = '"' . $field . '"';
            }
            return $field;
        }, $fields);
        return implode(',', $escaped) . "\n";
    }

    /**
     * Handle delete request from the admin tools page.
     */
    public function admin_delete_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', '', ['response' => 403]);
        }
        check_admin_referer('throwaway_lookup_gdpr_action', 'throwaway_lookup_nonce');

        $subject = isset($_POST['gdpr_subject']) ? sanitize_text_field(wp_unslash($_POST['gdpr_subject'])) : '';
        if (empty($subject)) {
            wp_safe_redirect(admin_url('options-general.php?page=throwaway-lookup&throwaway_error=no-subject'));
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $count = $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE email LIKE %s", $table, '%' . $wpdb->esc_like($subject) . '%'));
        $this->log_audit_event("Deleted $count logs for subject: $subject");

        wp_safe_redirect(admin_url('options-general.php?page=throwaway-lookup&throwaway_deleted=' . (int) $count));
        exit;
    }

    public function add_admin_menu() {
        add_options_page(
            'throwaway.cloud E-Mail Check Settings',
            'throwaway.cloud E-Mail Check',
            'manage_options',
            'throwaway-lookup',
            [$this, 'settings_page']
        );
    }

    public function register_settings() {
        register_setting('throwaway_lookup_settings', self::OPTION_LOG_LEVEL, ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('throwaway_lookup_settings', self::OPTION_ALLOWED, ['sanitize_callback' => 'sanitize_textarea_field']);
    }

    public function settings_page() {
        include plugin_dir_path(__FILE__) . 'admin/settings-page.php';
    }
}

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table = $wpdb->prefix . 'throwaway_logs';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        context VARCHAR(255),
        email VARCHAR(255),
        result TINYINT(1),
        source VARCHAR(255),
        INDEX (timestamp),
        INDEX (context),
        INDEX (email),
        INDEX (result),
        INDEX (source)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

/**
 * Main plugin instance.
 *
 * @return ThrowawayEmailLookup
 */
function throwaway_email_lookup() {
    static $instance;
    if (!$instance) {
        $instance = new ThrowawayEmailLookup();
    }
    return $instance;
}

throwaway_email_lookup();
