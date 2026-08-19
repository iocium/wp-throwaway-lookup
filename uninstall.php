<?php
/**
 * Fired when the plugin is uninstalled.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options.
delete_option('throwaway_lookup_allowed_list');
delete_option('throwaway_lookup_log_level');

// Drop the logs table.
global $wpdb;
$table = $wpdb->prefix . 'throwaway_logs';
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));

// Remove plugin transients.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_throwaway_%'
     OR option_name LIKE '_transient_timeout_throwaway_%'"
);
