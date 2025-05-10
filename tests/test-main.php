<?php

class ThrowawayMainTest extends WP_UnitTestCase {

    protected $table;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table = $wpdb->prefix . 'throwaway_logs';
    }

    public function test_plugin_class_exists() {
        $this->assertTrue(class_exists('ThrowawayEmailLookup'));
    }

    public function test_plugin_activation_creates_log_table() {
        global $wpdb;

        // Simulate plugin activation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        do_action('activate_throwaway-lookup/throwaway-lookup.php');

        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table
        ));

        $this->assertEquals($this->table, $exists);
    }
}