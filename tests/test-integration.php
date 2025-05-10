<?php

class ThrowawayIntegrationTest extends WP_UnitTestCase {

    protected $plugin;

    public function setUp(): void {
        parent::setUp();
        $this->plugin = new ThrowawayEmailLookup();
    }

    public function test_comment_filter_allows_legit_email() {
        $data = [ 'comment_author_email' => 'user@example.com' ];
        $this->assertEquals($data, $this->plugin->filter_comment($data));
    }

    public function test_comment_filter_blocks_disposable_email() {
        $this->expectExceptionMessage('Please use a permanent email address for commenting.');
        $this->plugin->filter_comment([ 'comment_author_email' => 'user@mailinator.com' ]);
    }

    public function test_registration_allows_legit_email() {
        $errors = new WP_Error();
        $result = $this->plugin->filter_registration($errors, 'user', 'user@example.com');
        $this->assertFalse($result->get_error_code());
    }

    public function test_registration_blocks_disposable_email() {
        $errors = new WP_Error();
        $result = $this->plugin->filter_registration($errors, 'user', 'user@mailinator.com');
        $this->assertTrue($result->get_error_code());
        $this->assertArrayHasKey('throwaway_email', $result->errors);
    }
}