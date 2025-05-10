<?php

class ThrowawayGDPRTest extends WP_UnitTestCase {

    protected $plugin;
    protected $table;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->plugin = new ThrowawayEmailLookup();
        $this->table = $wpdb->prefix . 'throwaway_logs';

        // Create the table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            context VARCHAR(255),
            email VARCHAR(255),
            result TINYINT(1),
            source VARCHAR(255),
            INDEX (email)
        ) {$wpdb->get_charset_collate()};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Seed data
        $wpdb->insert($this->table, [
            'context' => 'registration',
            'email' => 'subject1@mailinator.com',
            'result' => 1,
            'source' => 'test'
        ]);
        $wpdb->insert($this->table, [
            'context' => 'comment',
            'email' => 'trusted@example.com',
            'result' => 0,
            'source' => 'test'
        ]);
    }

    public function test_export_subject_logs() {
        wp_set_current_user(1); // Simulate admin
        $exported = $this->plugin->handle_external_export('mailinator.com');
        $this->assertNotEmpty($exported);
        $this->assertSame('subject1@mailinator.com', $exported[0]['email']);
    }

    public function test_delete_subject_logs() {
        wp_set_current_user(1); // Simulate admin
        $this->plugin->handle_external_deletion('mailinator.com');

        global $wpdb;
        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE email LIKE '%mailinator.com%'");
        $this->assertEquals(0, $remaining);
    }

    public function test_export_requires_admin() {
        wp_set_current_user(0); // Simulate not logged in
        $this->assertSame([], $this->plugin->handle_external_export('mailinator.com'));
    }

    public function test_delete_requires_admin() {
        wp_set_current_user(0); // Simulate not logged in
        global $wpdb;
        $this->plugin->handle_external_deletion('mailinator.com');
        $still_exists = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE email LIKE '%mailinator.com%'");
        $this->assertGreaterThan(0, $still_exists);
    }

    public function test_log_level_none_obfuscates_all() {
        update_option('throwaway_lookup_log_level', 'none');
        $obfuscated = $this->plugin->obfuscate_subject('test@example.com');
        $this->assertSame('[REDACTED]', $obfuscated);
    }

    public function test_log_level_domain_only_returns_domain() {
        update_option('throwaway_lookup_log_level', 'domain');
        $obfuscated = $this->plugin->obfuscate_subject('test@example.com');
        $this->assertSame('example.com', $obfuscated);
    }

    public function test_log_level_full_returns_email() {
        update_option('throwaway_lookup_log_level', 'full');
        $obfuscated = $this->plugin->obfuscate_subject('test@example.com');
        $this->assertSame('test@example.com', $obfuscated);
    }

}