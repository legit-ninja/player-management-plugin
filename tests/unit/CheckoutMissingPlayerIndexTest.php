<?php
/**
 * Cart/checkout player picker removed; ATC cart keys still resolve for order meta.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-data.php';
require_once __DIR__ . '/../../includes/checkout-player-assign.php';

class CheckoutMissingPlayerIndexTest extends InterSoccer_Test_Case
{
    public function test_cart_item_player_index_reads_assigned_player_from_atc()
    {
        $this->assertSame(
            0,
            intersoccer_get_cart_item_player_index(['assigned_player' => 0, 'product_id' => 1])
        );
        $this->assertSame(
            '2',
            (string) intersoccer_get_cart_item_player_index(['intersoccer_player_index' => '2'])
        );
        $this->assertSame(
            '',
            intersoccer_get_cart_item_player_index(['product_id' => 1])
        );
    }

    public function test_source_has_no_cart_checkout_player_picker()
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/includes/checkout-player-assign.php');
        $this->assertStringNotContainsString('intersoccer-player-assign', $contents);
        $this->assertStringNotContainsString('Please assign a player to this item.', $contents);
        $this->assertStringNotContainsString('intersoccer_cart_item_player_field', $contents);
        $this->assertStringNotContainsString('intersoccer_validate_checkout_player_assignment', $contents);
        $this->assertStringNotContainsString('intersoccer_ajax_update_cart_player', $contents);
        $this->assertStringNotContainsString('intersoccer_enqueue_checkout_assets', $contents);
        $this->assertStringNotContainsString("add_filter('woocommerce_cart_item_name'", $contents);
        $this->assertStringContainsString('intersoccer_save_order_item_player', $contents);
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/js/checkout-player.js');
        $this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/css/checkout-player.css');
    }
}
