<?php
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load Composer autoloader if available (brings in PHPUnit + Polyfills).
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// If the autoloader didn't load the Polyfills, point WP to them.
if (!class_exists('Yoast\\PHPUnitPolyfills\\Autoload') && !defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    $polyfills_path = dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills';
    if (is_dir($polyfills_path)) {
        define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills_path);
    }
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Manually load the plugin.
function _manually_load_plugin() {
    require dirname(__DIR__) . '/throwaway-lookup.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Ensure the plugin's log table exists once WordPress is loaded.
function _throwaway_lookup_create_test_table() {
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
}
tests_add_filter('muplugins_loaded', '_throwaway_lookup_create_test_table', 20);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';