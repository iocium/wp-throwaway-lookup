<?php

class ThrowawayLookupLogicTest extends WP_UnitTestCase {

    protected $plugin;

    public function setUp(): void {
        parent::setUp();
        $this->plugin = new ThrowawayEmailLookup();
        update_option('throwaway_lookup_allowed_list', "trusted.com\nadmin@trusted.com");
        update_option('throwaway_lookup_log_level', 'domain');
    }

    public function test_allow_list_blocks_check() {
        $this->assertTrue($this->plugin->is_allowed('admin@trusted.com'));
        $this->assertTrue($this->plugin->is_allowed('user@trusted.com'));
        $this->assertFalse($this->plugin->is_allowed('user@temp.com'));
    }

    public function test_obfuscation_none() {
        update_option('throwaway_lookup_log_level', 'none');
        $this->assertSame('[REDACTED]', $this->plugin->obfuscate_subject('foo@bar.com'));
    }

    public function test_obfuscation_domain() {
        update_option('throwaway_lookup_log_level', 'domain');
        $this->assertSame('bar.com', $this->plugin->obfuscate_subject('foo@bar.com'));
    }

    public function test_obfuscation_full() {
        update_option('throwaway_lookup_log_level', 'full');
        $this->assertSame('foo@bar.com', $this->plugin->obfuscate_subject('foo@bar.com'));
    }

    public function test_lookup_and_log_inserts() {
        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $wpdb->query("DELETE FROM $table");

        $this->plugin->log_attempt('comment', 'test@example.com', true);
        $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        $this->assertCount(1, $rows);
        $this->assertSame('example.com', $rows[0]['email']);
        $this->assertEquals(1, $rows[0]['result']);
    }

    public function test_external_export_and_deletion() {
        global $wpdb;
        $table = $wpdb->prefix . 'throwaway_logs';
        $wpdb->query("DELETE FROM $table");

        $this->plugin->log_attempt('comment', 'user@example.com', true);
        $results = $this->plugin->handle_external_export('example.com');
        $this->assertNotEmpty($results);

        $this->plugin->handle_external_deletion('example.com');
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $this->assertEquals(0, $count);
    }
}
