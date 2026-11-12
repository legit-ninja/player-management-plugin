<?php
/**
 * Assigned Attendee must appear once per order-item surface (Woo formatted meta only).
 */

require_once __DIR__ . '/../helpers/TestCase.php';

class OrderItemAssignedAttendeeDisplayTest extends InterSoccer_Test_Case
{
    private const META_KEY = 'Assigned Attendee';
    private const ATTENDEE_VALUE = 'Mia Example';

    /**
     * @param array<string, mixed> $context
     */
    private function renderOrderItemMetaSurfaces(array $context, string $displayLabel): string
    {
        $itemId = 42;
        $item = Mockery::mock('WC_Order_Item_Product');
        $item->shouldReceive('get_meta')->with(self::META_KEY)->andReturn(self::ATTENDEE_VALUE);
        $order = Mockery::mock('WC_Order');

        $plainText = !empty($context['plain_text']);
        $wcMeta = $plainText
            ? $displayLabel . ': ' . self::ATTENDEE_VALUE . "\n"
            : '<table class="wc-item-meta"><tr><th>' . $displayLabel . '</th><td>' . self::ATTENDEE_VALUE . '</td></tr></table>';

        ob_start();
        foreach ($GLOBALS['wp_stub_actions']['woocommerce_order_item_meta_end'] ?? [] as $entry) {
            $args = [$itemId, $item, $order, $plainText];
            call_user_func_array(
                $entry['callback'],
                array_slice($args, 0, (int) $entry['accepted_args'])
            );
        }
        $hookOutput = (string) ob_get_clean();

        return $wcMeta . $hookOutput;
    }

    private function countLabelOccurrences(string $output, string $label): int
    {
        if ($label === '') {
            return 0;
        }

        return substr_count($output, $label);
    }

    public function test_assigned_attendee_hook_not_registered_in_player_management(): void
    {
        $source = file_get_contents(PLAYER_MANAGEMENT_PATH . 'includes/player-management.php');
        $this->assertIsString($source);
        $this->assertStringNotContainsString('intersoccer_display_order_item_attendee', $source);
        $this->assertStringNotContainsString(
            "add_action( 'woocommerce_order_item_meta_end', 'intersoccer_display_order_item_attendee'",
            $source
        );
        $this->assertFalse(function_exists('intersoccer_display_order_item_attendee'));
    }

    public function test_assigned_attendee_label_once_customer_email_html(): void
    {
        $output = $this->renderOrderItemMetaSurfaces(
            ['context' => 'email_html', 'plain_text' => false],
            self::META_KEY
        );

        $this->assertSame(1, $this->countLabelOccurrences($output, self::META_KEY));
        $this->assertSame(1, $this->countLabelOccurrences($output, self::ATTENDEE_VALUE));
    }

    public function test_assigned_attendee_label_once_customer_email_plain_text(): void
    {
        $output = $this->renderOrderItemMetaSurfaces(
            ['context' => 'email_plain', 'plain_text' => true],
            self::META_KEY
        );

        $this->assertSame(1, $this->countLabelOccurrences($output, self::META_KEY));
        $this->assertStringNotContainsString('<p>', $output);
    }

    public function test_assigned_attendee_label_once_my_account_order_view(): void
    {
        $GLOBALS['wp_stub_is_admin'] = false;
        $output = $this->renderOrderItemMetaSurfaces(
            ['context' => 'my_account', 'plain_text' => false],
            self::META_KEY
        );

        $this->assertSame(1, $this->countLabelOccurrences($output, self::META_KEY));
    }

    public function test_assigned_attendee_label_once_wp_admin_order_screen(): void
    {
        $GLOBALS['wp_stub_is_admin'] = true;
        $output = $this->renderOrderItemMetaSurfaces(
            ['context' => 'admin', 'plain_text' => false],
            self::META_KEY
        );

        $this->assertSame(1, $this->countLabelOccurrences($output, self::META_KEY));
    }

    public function test_assigned_attendee_french_customer_label_single_row(): void
    {
        $frenchLabel = 'Participant assigné';
        $output = $this->renderOrderItemMetaSurfaces(
            ['context' => 'email_html', 'plain_text' => false],
            $frenchLabel
        );

        $this->assertSame(1, $this->countLabelOccurrences($output, $frenchLabel));
        $this->assertSame(0, $this->countLabelOccurrences($output, self::META_KEY));
    }

    public function test_assigned_attendee_german_customer_label_single_row(): void
    {
        $germanLabel = 'Assigned Attendee';
        $output = $this->renderOrderItemMetaSurfaces(
            ['context' => 'my_account', 'plain_text' => false],
            $germanLabel
        );

        $this->assertSame(1, $this->countLabelOccurrences($output, $germanLabel));
    }
}
