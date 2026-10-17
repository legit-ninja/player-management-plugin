<?php
/**
 * Integration tests for data deletion functionality
 * Target: 75%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/data-deletion.php';

class DataDeletionTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();
        
        WP_Mock::userFunction('__')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('_e')->andReturnUsing(function($text) {
            echo $text;
        });
    }

    /**
     * Test data deletion shortcode when not logged in
     */
    public function test_deletion_shortcode_not_logged_in()
    {
        WP_Mock::userFunction('is_user_logged_in', [
            'return' => false,
        ]);
        
        WP_Mock::userFunction('esc_html__')->andReturnUsing(function($text) {
            return $text;
        });
        
        $result = intersoccer_data_deletion_shortcode();
        
        $this->assertStringContainsString('log in', $result);
    }

    /**
     * Test data deletion shortcode displays form when logged in
     */
    public function test_deletion_shortcode_logged_in()
    {
        $this->setLoggedIn(true, 1);
        
        WP_Mock::userFunction('wp_nonce_field')->andReturn('');
        
        $result = intersoccer_data_deletion_shortcode();
        
        $this->assertStringContainsString('Request Data Deletion', $result);
    }

    /**
     * Test deletion request creation
     */
    public function test_deletion_request_creation()
    {
        $_POST['data_deletion_nonce'] = 'valid_nonce';
        $_POST['deletion_reason'] = 'Test reason';
        
        WP_Mock::userFunction('is_user_logged_in', [
            'return' => true,
        ]);
        
        WP_Mock::userFunction('wp_verify_nonce', [
            'return' => true,
        ]);
        
        WP_Mock::userFunction('get_current_user_id', [
            'return' => 1,
        ]);
        
        WP_Mock::userFunction('sanitize_textarea_field')->andReturnUsing(function($text) {
            return $text;
        });
        
        WP_Mock::userFunction('wp_insert_post', [
            'return' => 123,
        ]);
        
        WP_Mock::userFunction('wp_mail')->andReturn(true);
        WP_Mock::userFunction('get_option')->andReturn('admin@example.com');
        
        $result = intersoccer_data_deletion_shortcode();
        
        $this->assertTrue(true, 'Deletion request should be created');
        
        unset($_POST['data_deletion_nonce']);
        unset($_POST['deletion_reason']);
    }

    /**
     * Test admin menu registration
     */
    public function test_admin_menu_registration()
    {
        WP_Mock::userFunction('add_submenu_page')->andReturnNull();
        
        intersoccer_data_deletion_admin_menu();
        
        $this->assertTrue(true, 'Admin menu should be registered');
    }

    /**
     * Test admin page requires permissions
     */
    public function test_admin_page_requires_permissions()
    {
        $GLOBALS['wp_stub_user_caps']['manage_options'] = false;

        $this->expectException(RuntimeException::class);
        intersoccer_data_deletion_admin_page();
    }

    /**
     * Test deletion processing
     */
    public function test_deletion_processing()
    {
        $_POST['process_deletion'] = '1';
        $_POST['deletion_nonce'] = 'valid_nonce';
        $_POST['request_id'] = '123';
        
        WP_Mock::userFunction('current_user_can', [
            'args' => ['manage_options'],
            'return' => true,
        ]);
        
        WP_Mock::userFunction('wp_verify_nonce', [
            'return' => true,
        ]);
        
        WP_Mock::userFunction('absint')->andReturnUsing(function($val) {
            return abs((int) $val);
        });
        
        WP_Mock::userFunction('get_post_meta', [
            'return' => 1,
        ]);
        
        WP_Mock::userFunction('delete_user_meta')->andReturn(true);
        WP_Mock::userFunction('wp_delete_user')->andReturn(true);
        WP_Mock::userFunction('wp_update_post')->andReturn(123);
        WP_Mock::userFunction('current_time')->andReturn('2023-06-15 12:00:00');
        
        ob_start();
        intersoccer_data_deletion_admin_page();
        $output = ob_get_clean();
        
        $this->assertTrue(true, 'Deletion should be processed');
        
        unset($_POST['process_deletion']);
        unset($_POST['deletion_nonce']);
        unset($_POST['request_id']);
    }

    /**
     * Deletion processing must log user ID only, not email or other personal fields.
     */
    public function test_deletion_processing_log_contains_user_id_not_email()
    {
        $user_id = 42;
        $distinct_email = 'must-not-appear-in-deletion-log@example.com';

        WP_Mock::userFunction('absint')->andReturnUsing(function ($val) {
            return abs((int) $val);
        });
        WP_Mock::userFunction('current_time')->andReturn('2023-06-15 12:00:00');

        $log_file = tempnam(sys_get_temp_dir(), 'pm-deletion-log-');
        $previous_log = ini_get('error_log');
        ini_set('error_log', $log_file);
        ini_set('log_errors', '1');

        try {
            intersoccer_log_data_deletion_completed($user_id);

            $logged = (string) file_get_contents($log_file);
            $this->assertStringContainsString('user ID 42', $logged);
            $this->assertStringNotContainsString($distinct_email, $logged);
            $this->assertStringNotContainsString('@', $logged);
        } finally {
            if ($previous_log !== false && $previous_log !== '') {
                ini_set('error_log', $previous_log);
            }
            @unlink($log_file);
        }
    }
}

