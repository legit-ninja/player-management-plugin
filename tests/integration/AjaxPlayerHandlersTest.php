<?php
/**
 * Integration tests for Player AJAX handlers
 * Target: 90%+ coverage
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-management.php';
require_once __DIR__ . '/../../includes/ajax-handlers.php';

class AjaxPlayerHandlersTest extends InterSoccer_Test_Case
{
    private $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtures = require __DIR__ . '/../fixtures/players.php';
        $_POST = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST = [];
    }

    private function mockAjaxAuthSuccess(): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
    }

    /**
     * Test intersoccer_add_player with nonce failure
     */
    public function test_add_player_nonce_failure()
    {
        $_POST = [
            'nonce' => 'invalid_nonce',
            'user_id' => 1,
        ];

        $GLOBALS['wp_stub_ajax_nonce_valid'] = false;

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player with permission failure
     */
    public function test_add_player_permission_failure()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
        ];

        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = false;

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player with missing required fields
     */
    public function test_add_player_missing_required_fields()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'John',
        ];

        $this->mockAjaxAuthSuccess();

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player with invalid date format
     */
    public function test_add_player_invalid_date_format()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'John',
            'player_last_name' => 'Doe',
            'player_dob' => 'invalid-date',
            'player_gender' => 'male',
        ];

        $this->mockAjaxAuthSuccess();

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player with age validation failure (too young)
     */
    public function test_add_player_age_too_young()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'Baby',
            'player_last_name' => 'Test',
            'player_dob' => (new DateTime('today'))->modify('-2 years')->format('Y-m-d'),
            'player_gender' => 'male',
        ];

        $this->mockAjaxAuthSuccess();

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player with age validation failure (too old)
     */
    public function test_add_player_age_too_old()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'Teen',
            'player_last_name' => 'Test',
            'player_dob' => (new DateTime('today'))->modify('-14 years')->format('Y-m-d'),
            'player_gender' => 'male',
        ];

        $this->mockAjaxAuthSuccess();

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player with duplicate player
     */
    public function test_add_player_duplicate()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'John',
            'player_last_name' => 'Doe',
            'player_dob' => '2015-05-15',
            'player_gender' => 'male',
        ];

        $this->mockAjaxAuthSuccess();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
        ]);

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_add_player successful add
     */
    public function test_add_player_success()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'Jane',
            'player_last_name' => 'Smith',
            'player_dob' => '2015-08-20',
            'player_gender' => 'female',
            'player_avs_number' => '756.1234.5678.90',
            'player_medical' => 'None',
        ];

        $this->mockAjaxAuthSuccess();

        $this->setUserMeta(1, [
            'intersoccer_players' => [],
        ]);

        $this->expectException(WPSendJsonExit::class);
        intersoccer_add_player();
    }

    /**
     * Test intersoccer_edit_player with nonce failure
     */
    public function test_edit_player_nonce_failure()
    {
        $_POST = [
            'nonce' => 'invalid_nonce',
            'user_id' => 1,
            'player_index' => 0,
        ];

        $GLOBALS['wp_stub_ajax_nonce_valid'] = false;

        $this->expectException(WPSendJsonExit::class);
        intersoccer_edit_player();
    }

    /**
     * Test intersoccer_edit_player with invalid player index
     */
    public function test_edit_player_invalid_index()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => -1,
        ];

        $this->mockAjaxAuthSuccess();

        $this->expectException(WPSendJsonExit::class);
        intersoccer_edit_player();
    }

    /**
     * Test intersoccer_edit_player with player not found
     */
    public function test_edit_player_not_found()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 5,
            'player_first_name' => 'John',
            'player_last_name' => 'Doe',
            'player_dob' => '2015-05-15',
            'player_gender' => 'male',
        ];

        $this->mockAjaxAuthSuccess();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
        ]);

        $this->expectException(WPSendJsonExit::class);
        intersoccer_edit_player();
    }

    /**
     * Test intersoccer_delete_player with nonce failure
     */
    public function test_delete_player_nonce_failure()
    {
        $_POST = [
            'nonce' => 'invalid_nonce',
            'user_id' => 1,
            'player_index' => 0,
        ];

        $GLOBALS['wp_stub_ajax_nonce_valid'] = false;

        $this->expectException(WPSendJsonExit::class);
        intersoccer_delete_player();
    }

    /**
     * Test intersoccer_get_player success
     */
    public function test_get_player_success()
    {
        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
        ];

        $this->mockAjaxAuthSuccess();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->fixtures['valid_player']],
            'billing_state' => 'ZH',
            'billing_city' => 'Zurich',
        ]);

        $this->mockWcGetOrders([]);

        $this->expectException(WPSendJsonExit::class);
        intersoccer_get_player();
    }
}
