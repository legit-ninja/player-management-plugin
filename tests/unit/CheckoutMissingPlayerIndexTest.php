<?php
/**
 * Deleted cart indexes must not book another child or pass with no attendee.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-data.php';
require_once __DIR__ . '/../../includes/checkout-player-assign.php';

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return $text;
    }
}

if (!function_exists('wc_add_notice')) {
    function wc_add_notice($message, $type = 'notice') {
        $GLOBALS['wp_stub_wc_notices'][] = [
            'message' => $message,
            'type' => $type,
        ];
    }
}

if (!function_exists('WC')) {
    function WC() {
        return $GLOBALS['wp_stub_wc'] ?? null;
    }
}

class Checkout_Stub_Attendee_Product {
    public function get_attribute($name) {
        return 'yes';
    }

    public function get_name() {
        return 'Summer Camp';
    }

    public function get_id() {
        return 5;
    }

    public function get_slug() {
        return 'summer-camp';
    }
}

class Checkout_Stub_Cart {
    public $items = [];

    public function get_cart() {
        return $this->items;
    }
}

class Checkout_Stub_WC {
    public $cart;

    public function __construct($cart) {
        $this->cart = $cart;
    }
}

class CheckoutMissingPlayerIndexTest extends InterSoccer_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_stub_wc_notices'] = [];
        $GLOBALS['wp_stub_wc'] = null;
    }

    public function test_deleted_index_does_not_resolve_to_another_child_or_pass_checkout()
    {
        $user_id = 21;
        $this->setLoggedIn(true, $user_id);
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => 'alice',
                    'first_name' => 'Alice',
                    'last_name' => 'A',
                    'dob' => '2015-01-01',
                    'medical_conditions' => 'none',
                ],
                2 => [
                    'player_id' => 'carol',
                    'first_name' => 'Carol',
                    'last_name' => 'C',
                    'dob' => '2013-03-03',
                    'medical_conditions' => 'asthma',
                ],
            ],
        ]);

        $this->assertTrue(intersoccer_checkout_player_index_is_unresolved(1));
        $this->assertTrue(intersoccer_checkout_player_index_is_unresolved('1'));
        $this->assertFalse(intersoccer_checkout_player_index_is_unresolved(0));
        $this->assertFalse(intersoccer_checkout_player_index_is_unresolved(2));

        $cart = new Checkout_Stub_Cart();
        $cart->items = [
            'line-1' => [
                'data' => new Checkout_Stub_Attendee_Product(),
                'intersoccer_player_index' => 1,
            ],
        ];
        $GLOBALS['wp_stub_wc'] = new Checkout_Stub_WC($cart);

        intersoccer_validate_checkout_player_assignment();

        $this->assertNotEmpty($GLOBALS['wp_stub_wc_notices']);
        $this->assertSame('error', $GLOBALS['wp_stub_wc_notices'][0]['type']);
        $this->assertStringContainsString('Summer Camp', $GLOBALS['wp_stub_wc_notices'][0]['message']);

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_customer_id')->andReturn($user_id);
        $item = Mockery::mock('WC_Order_Item_Product');
        $item->shouldReceive('add_meta_data')->never();

        intersoccer_save_order_item_player($item, 'line-1', [
            'intersoccer_player_index' => 1,
        ], $order);
    }

    public function test_only_remaining_player_does_not_fill_a_deleted_index()
    {
        $user_id = 22;
        $this->setLoggedIn(true, $user_id);
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => 'alice',
                    'first_name' => 'Alice',
                    'last_name' => 'A',
                    'dob' => '2015-01-01',
                    'medical_conditions' => 'none',
                ],
            ],
        ]);

        $this->assertTrue(intersoccer_checkout_player_index_is_unresolved(1));
        $this->assertNull(intersoccer_get_player_by_index($user_id, 1));

        $cart = new Checkout_Stub_Cart();
        $cart->items = [
            'line-1' => [
                'data' => new Checkout_Stub_Attendee_Product(),
                'assigned_player' => '1',
            ],
        ];
        $GLOBALS['wp_stub_wc'] = new Checkout_Stub_WC($cart);

        intersoccer_validate_checkout_player_assignment();
        $this->assertNotEmpty($GLOBALS['wp_stub_wc_notices']);

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_customer_id')->andReturn($user_id);
        $item = Mockery::mock('WC_Order_Item_Product');
        $item->shouldReceive('add_meta_data')->never();

        intersoccer_save_order_item_player($item, 'line-1', [
            'assigned_player' => '1',
        ], $order);
    }

    public function test_valid_existing_index_still_saves_that_child()
    {
        $user_id = 23;
        $this->setLoggedIn(true, $user_id);
        $this->setUserMeta($user_id, [
            'intersoccer_players' => [
                0 => [
                    'player_id' => 'alice',
                    'first_name' => 'Alice',
                    'last_name' => 'A',
                    'dob' => '2015-01-01',
                    'medical_conditions' => 'none',
                ],
                2 => [
                    'player_id' => 'carol',
                    'first_name' => 'Carol',
                    'last_name' => 'C',
                    'dob' => '2013-03-03',
                    'medical_conditions' => 'asthma',
                ],
            ],
        ]);

        $saved = [];
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_customer_id')->andReturn($user_id);
        $order->shouldReceive('get_id')->andReturn(90);
        $item = Mockery::mock('WC_Order_Item_Product');
        $item->shouldReceive('get_id')->andReturn(7);
        $item->shouldReceive('add_meta_data')->andReturnUsing(function ($key, $value) use (&$saved) {
            $saved[$key] = $value;
        });

        intersoccer_save_order_item_player($item, 'line-1', [
            'intersoccer_player_index' => 2,
        ], $order);

        $this->assertSame('Carol C', $saved['Assigned Attendee']);
        $this->assertSame('2013-03-03', $saved['Player DOB']);
        $this->assertSame('asthma', $saved['Player Medical']);

        $cart = new Checkout_Stub_Cart();
        $cart->items = [
            'line-1' => [
                'data' => new Checkout_Stub_Attendee_Product(),
                'intersoccer_player_index' => 2,
            ],
        ];
        $GLOBALS['wp_stub_wc'] = new Checkout_Stub_WC($cart);
        intersoccer_validate_checkout_player_assignment();
        $this->assertSame([], $GLOBALS['wp_stub_wc_notices']);
    }
}
