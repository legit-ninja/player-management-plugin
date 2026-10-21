<?php
/**
 * The inline "Assigned Attendee" line must not duplicate the visible order item meta row.
 */

require_once __DIR__ . '/../helpers/TestCase.php';
require_once __DIR__ . '/../../includes/player-management.php';

class OrderItemAttendeeDisplayTest extends InterSoccer_Test_Case
{
    private function item(array $formatted)
    {
        return new class($formatted) {
            private $formatted;
            public function __construct($formatted) { $this->formatted = $formatted; }
            public function get_meta($key) { return $key === 'Assigned Attendee' ? 'Kid A' : ''; }
            public function get_formatted_meta_data() { return $this->formatted; }
        };
    }

    public function test_skips_inline_line_when_meta_row_already_listed(): void
    {
        $item = $this->item([(object) ['key' => 'Assigned Attendee', 'display_key' => 'Participant', 'value' => 'Kid A']]);
        ob_start();
        intersoccer_display_order_item_attendee(1, $item, null);
        $this->assertSame('', ob_get_clean());
    }

    public function test_prints_inline_line_when_meta_row_hidden(): void
    {
        $item = $this->item([]);
        ob_start();
        intersoccer_display_order_item_attendee(1, $item, null);
        $this->assertStringContainsString('Kid A', ob_get_clean());
    }
}
