<?php

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-data.php';
require_once __DIR__ . '/../../includes/player-management.php';
require_once __DIR__ . '/../../includes/medical-access.php';
require_once __DIR__ . '/../../includes/ajax-handlers.php';

class MedicalGatingAjaxTest extends InterSoccer_Test_Case
{
    private static bool $privacy_booted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('intersoccer_clear_user_players_cache')) {
            intersoccer_clear_user_players_cache(null);
        }
        wp_cache_flush();
        $_POST = [];
        unset($GLOBALS['wp_stub_last_json_response']);
    }

    private function bootPrivacyPluginIfAvailable(): void
    {
        if (self::$privacy_booted) {
            return;
        }
        $privacy = dirname(__DIR__, 2) . '/includes/privacy-settings.php';
        if (!is_readable($privacy)) {
            return;
        }
        $stubs = dirname(__DIR__) . '/helpers/privacy-settings-stubs.php';
        if (is_readable($stubs)) {
            require_once $stubs;
        }
        require_once $privacy;
        self::$privacy_booted = true;
    }

    private function basePlayer(): array
    {
        return [
            'player_id' => 'pid-1',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'dob' => '2015-05-15',
            'gender' => 'female',
            'avs_number' => '0000',
            'medical_conditions' => 'Peanuts',
            'creation_timestamp' => 1,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function invokeAjaxSuccess(callable $callback): array
    {
        try {
            $callback();
            $this->fail('Expected wp_send_json_success to throw WPSendJsonExit');
        } catch (WPSendJsonExit $e) {
            $this->assertSame('success', $e->getMessage());
            $this->assertIsArray($e->payload);

            return $e->payload;
        }
    }

    private function mockParentAjaxAuth(int $user_id = 1): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        intersoccer_test_set_user_caps(['customer' => true, 'edit_users' => false, 'edit_user' => true], $user_id);
    }

    private function mockCoachAjaxAuth(): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        intersoccer_test_set_user_caps(['coach' => true, 'read' => true, 'edit_users' => true], 99);
    }

    private function mockPrivacyGrantedStaffAuth(): void
    {
        $GLOBALS['wp_stub_ajax_nonce_valid'] = true;
        $GLOBALS['wp_stub_user_can'] = true;
        intersoccer_test_set_user_caps(['manage_woocommerce' => true, 'edit_users' => true], 50);
        if (!function_exists('get_userdata')) {
            function get_userdata($user_id) {
                $user_id = (int) $user_id;
                if (isset($GLOBALS['wp_stub_user_objects'][$user_id])) {
                    return $GLOBALS['wp_stub_user_objects'][$user_id];
                }

                return null;
            }
        }
        if (!function_exists('user_can')) {
            function user_can($user, $capability) {
                $user_id = is_object($user) ? (int) ($user->ID ?? 0) : (int) $user;
                if ($user_id === (int) get_current_user_id() && isset($GLOBALS['wp_stub_user_caps'][$capability])) {
                    return (bool) $GLOBALS['wp_stub_user_caps'][$capability];
                }

                return !empty($GLOBALS['wp_stub_user_can']);
            }
        }
        $GLOBALS['wp_stub_user_objects'][50] = (object) [
            'ID' => 50,
            'roles' => ['shop_manager'],
        ];
    }

    public function test_parent_add_player_stores_medical_text(): void
    {
        $this->mockParentAjaxAuth(1);

        $this->setUserMeta(1, ['intersoccer_players' => []]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'Sam',
            'player_last_name' => 'Kid',
            'player_dob' => '2015-08-20',
            'player_gender' => 'male',
            'player_avs_number' => '0000',
            'player_medical' => 'Nut allergy',
        ];

        $payload = $this->invokeAjaxSuccess(static function () {
            intersoccer_add_player();
        });

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('Nut allergy', $stored['medical_conditions']);
        $this->assertSame('Nut allergy', $payload['player']['medical_conditions'] ?? null);
    }

    public function test_parent_edit_own_child_updates_dob_and_medical(): void
    {
        $this->mockParentAjaxAuth(1);

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => 'Jane',
            'player_last_name' => 'Doe',
            'player_dob' => '2014-08-20',
            'player_gender' => 'female',
            'player_avs_number' => '0000',
            'player_medical' => 'None',
        ];

        $this->invokeAjaxSuccess(static function () {
            intersoccer_edit_player();
        });

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('2014-08-20', $stored['dob']);
        $this->assertSame('None', $stored['medical_conditions']);
    }

    public function test_parent_get_player_returns_unredacted_sensitive_fields(): void
    {
        $this->mockParentAjaxAuth(1);

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
        ];

        $payload = $this->invokeAjaxSuccess(static function () {
            intersoccer_get_player();
        });

        $this->assertSame('2015-05-15', $payload['player']['dob'] ?? null);
        $this->assertSame('Peanuts', $payload['player']['medical_conditions'] ?? null);
    }

    public function test_coach_edit_other_users_child_preserves_medical_and_dob(): void
    {
        $this->mockCoachAjaxAuth();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => 'Janet',
            'player_last_name' => 'Doe',
            'player_dob' => '2010-01-01',
            'player_gender' => 'female',
            'player_avs_number' => '0000',
            'player_medical' => 'Hacked',
        ];

        $this->invokeAjaxSuccess(static function () {
            intersoccer_edit_player();
        });

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('Janet', $stored['first_name']);
        $this->assertSame('2015-05-15', $stored['dob']);
        $this->assertSame('Peanuts', $stored['medical_conditions']);
    }

    public function test_coach_get_other_users_player_redacts_sensitive_fields(): void
    {
        $this->mockCoachAjaxAuth();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
        ];

        $payload = $this->invokeAjaxSuccess(static function () {
            intersoccer_get_player();
        });

        $this->assertSame('Restricted', $payload['player']['dob'] ?? null);
        $this->assertSame('Restricted', $payload['player']['medical_conditions'] ?? null);
    }

    public function test_coach_add_other_users_child_stores_dob_but_not_medical(): void
    {
        $this->mockCoachAjaxAuth();

        $this->setUserMeta(1, ['intersoccer_players' => []]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_first_name' => 'Sam',
            'player_last_name' => 'Kid',
            'player_dob' => '2015-08-20',
            'player_gender' => 'male',
            'player_avs_number' => '0000',
            'player_medical' => 'Nut allergy',
        ];

        $payload = $this->invokeAjaxSuccess(static function () {
            intersoccer_add_player();
        });
        $players = get_user_meta(1, 'intersoccer_players', true);
        $this->assertCount(1, $players);
        $this->assertSame('2015-08-20', $players[0]['dob']);
        $this->assertArrayHasKey('medical_conditions', $players[0]);
        $this->assertSame('', $players[0]['medical_conditions']);
        $this->assertSame('Restricted', $payload['player']['medical_conditions'] ?? null);
        $this->assertSame('Restricted', $payload['player']['dob'] ?? null);
    }

    public function test_denied_staff_cannot_inject_medical_on_legacy_row_missing_key(): void
    {
        $this->mockCoachAjaxAuth();

        $legacy = $this->basePlayer();
        unset($legacy['medical_conditions']);
        $this->setUserMeta(1, ['intersoccer_players' => [$legacy]]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => 'Jane',
            'player_last_name' => 'Doe',
            'player_dob' => '2015-05-15',
            'player_gender' => 'female',
            'player_avs_number' => '0000',
            'player_medical' => 'Injected',
        ];

        $this->invokeAjaxSuccess(static function () {
            intersoccer_edit_player();
        });

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('', $stored['medical_conditions'] ?? 'missing');
    }

    public function test_privacy_granted_staff_edit_saves_dob_and_medical(): void
    {
        $this->bootPrivacyPluginIfAvailable();
        if (!function_exists('intersoccer_user_can_view_medical') || !function_exists('intersoccer_user_can_view_dob')) {
            $this->markTestSkipped('Privacy helpers are not loaded in this worktree.');
        }

        $this->mockPrivacyGrantedStaffAuth();

        $this->setUserMeta(1, [
            'intersoccer_players' => [$this->basePlayer()],
        ]);

        $_POST = [
            'nonce' => 'valid_nonce',
            'user_id' => 1,
            'player_index' => 0,
            'player_first_name' => 'Jane',
            'player_last_name' => 'Doe',
            'player_dob' => '2014-03-03',
            'player_gender' => 'female',
            'player_avs_number' => '0000',
            'player_medical' => 'Updated note',
        ];

        $this->invokeAjaxSuccess(static function () {
            intersoccer_edit_player();
        });

        $stored = get_user_meta(1, 'intersoccer_players', true)[0];
        $this->assertSame('2014-03-03', $stored['dob']);
        $this->assertSame('Updated note', $stored['medical_conditions']);
    }
}
