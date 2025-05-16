<?php
/**
 * Event Tickets Integration
 * Changes (Updated):
 * - Added dynamic ticket type selection in meta box with AJAX loading.
 * - Implemented batch processing for attendee creation using WP_Background_Process (with fallback).
 * - Aligned player meta retrieval with checkout.php (Player X Name).
 * - Optimized event queries with caching.
 * - Added REST API endpoint for real-time roster updates.
 * - Deferred initialization to init hook to prevent early text domain loading.
 * Testing:
 * - Edit a product, select an event and ticket type in the meta box, verify options load via AJAX.
 * - Place an order with multiple players, confirm attendees are created in The Events Calendar.
 * - Check background process logs for attendee creation (if wp-background-processing is installed).
 * - Use REST API to fetch roster for an event, verify player data returns.
 * - Verify event query caching reduces database load.
 * - Ensure no translation loading notices in server logs.
 */

defined('ABSPATH') or die('No script kiddies please!');

add_action('init', function() {
    // Define background process class only if library is available
    if (class_exists('WP_Background_Process')) {
        class InterSoccer_Attendee_Process extends WP_Background_Process {
            protected $action = 'intersoccer_create_attendees';

            protected function task($item) {
                $order_id = $item['order_id'];
                $item_id = $item['item_id'];
                $order = wc_get_order($order_id);
                $user_id = $order->get_user_id();
                $user = get_userdata($user_id);
                $user_email = $user ? $user->user_email : $order->get_billing_email();
                $user_name = $user ? $user->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $item = $order->get_item($item_id);
                $product_id = $item->get_product_id();
                $event_id = get_post_meta($product_id, '_tribe_event_id', true);
                $ticket_type_id = get_post_meta($product_id, '_tribe_ticket_type_id', true);

                if ($event_id && tribe_is_event($event_id) && $ticket_type_id) {
                    $players = [];
                    foreach ($item->get_meta_data() as $meta) {
                        if (preg_match('/^Player (\d+) Name$/', $meta->key, $matches)) {
                            $index = $matches[1];
                            $players[$index] = [
                                'name' => $meta->value,
                                'dob' => $item->get_meta("Player {$index} DOB", true),
                                'gender' => $item->get_meta("Player {$index} Gender", true),
                            ];
                        }
                    }
                    foreach ($players as $player) {
                        $attendee_data = [
                            'full_name' => $player['name'],
                            'email' => $user_email,
                            'order_id' => $order_id,
                            'ticket_id' => $ticket_type_id,
                            'event_id' => $event_id,
                            'user_id' => $user_id,
                            'meta' => [
                                'player_name' => $player['name'],
                                'dob' => $player['dob'],
                                'gender' => $player['gender'],
                            ],
                        ];
                        $attendee_id = tribe_tickets()->create_attendee($attendee_data, 'rsvp');
                        if (is_wp_error($attendee_id)) {
                            error_log('Failed to create attendee for order ' . $order_id . ': ' . $attendee_id->get_error_message());
                        }
                    }
                }
                return false;
            }
        }
    }

    add_action('woocommerce_order_status_completed', 'intersoccer_link_woocommerce_order_to_event_tickets');
    function intersoccer_link_woocommerce_order_to_event_tickets($order_id) {
        $order = wc_get_order($order_id);
        if (class_exists('WP_Background_Process')) {
            $process = new InterSoccer_Attendee_Process();
            foreach ($order->get_items() as $item_id => $item) {
                $process->push_to_queue(['order_id' => $order_id, 'item_id' => $item_id]);
            }
            $process->save()->dispatch();
        } else {
            // Synchronous fallback
            foreach ($order->get_items() as $item_id => $item) {
                $user_id = $order->get_user_id();
                $user = get_userdata($user_id);
                $user_email = $user ? $user->user_email : $order->get_billing_email();
                $user_name = $user ? $user->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $product_id = $item->get_product_id();
                $event_id = get_post_meta($product_id, '_tribe_event_id', true);
                $ticket_type_id = get_post_meta($product_id, '_tribe_ticket_type_id', true);

                if ($event_id && tribe_is_event($event_id) && $ticket_type_id) {
                    $players = [];
                    foreach ($item->get_meta_data() as $meta) {
                        if (preg_match('/^Player (\d+) Name$/', $meta->key, $matches)) {
                            $index = $matches[1];
                            $players[$index] = [
                                'name' => $meta->value,
                                'dob' => $item->get_meta("Player {$index} DOB", true),
                                'gender' => $item->get_meta("Player {$index} Gender", true),
                            ];
                        }
                    }
                    foreach ($players as $player) {
                        $attendee_data = [
                            'full_name' => $player['name'],
                            'email' => $user_email,
                            'order_id' => $order_id,
                            'ticket_id' => $ticket_type_id,
                            'event_id' => $event_id,
                            'user_id' => $user_id,
                            'meta' => [
                                'player_name' => $player['name'],
                                'dob' => $player['dob'],
                                'gender' => $player['gender'],
                            ],
                        ];
                        $attendee_id = tribe_tickets()->create_attendee($attendee_data, 'rsvp');
                        if (is_wp_error($attendee_id)) {
                            error_log('Failed to create attendee for order ' . $order_id . ': ' . $attendee_id->get_error_message());
                        }
                    }
                }
            }
        }
    }

    add_action('add_meta_boxes', function() {
        add_meta_box(
            'intersoccer-event-tickets',
            __('Event Tickets Link', 'intersoccer-player-management'),
            'intersoccer_render_event_tickets_meta_box',
            'product',
            'side',
            'default'
        );
    });

    function intersoccer_render_event_tickets_meta_box($post) {
        $event_id = get_post_meta($post->ID, '_tribe_event_id', true);
        $ticket_type_id = get_post_meta($post->ID, '_tribe_ticket_type_id', true);
        $events = wp_cache_get('intersoccer_events', 'intersoccer');
        if (false === $events) {
            $events = tribe_get_events(['posts_per_page' => 50, 'post_status' => 'publish']);
            wp_cache_set('intersoccer_events', $events, 'intersoccer', 3600);
        }
        ?>
        <label for="tribe_event_id"><?php _e('Select Event', 'intersoccer-player-management'); ?></label>
        <select name="tribe_event_id" id="tribe_event_id">
            <option value=""><?php _e('None', 'intersoccer-player-management'); ?></option>
            <?php foreach ($events as $event): ?>
                <option value="<?php echo esc_attr($event->ID); ?>" <?php selected($event_id, $event->ID); ?>>
                    <?php echo esc_html($event->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="tribe_ticket_type_id"><?php _e('Select Ticket Type', 'intersoccer-player-management'); ?></label>
        <select name="tribe_ticket_type_id" id="tribe_ticket_type_id">
            <option value=""><?php _e('Select Event First', 'intersoccer-player-management'); ?></option>
        </select>
        <script>
            jQuery(document).ready(function($) {
                $('#tribe_event_id').on('change', function() {
                    var eventId = $(this).val();
                    $('#tribe_ticket_type_id').empty().append('<option value="">Loading...</option>');
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'intersoccer_get_ticket_types',
                        event_id: eventId,
                        nonce: '<?php echo wp_create_nonce('intersoccer_ticket_types'); ?>'
                    }, function(response) {
                        var options = '<option value=""><?php _e('Select Ticket Type', 'intersoccer-player-management'); ?></option>';
                        if (response.success) {
                            response.data.forEach(function(ticket) {
                                options += '<option value="' + ticket.id + '">' + ticket.name + '</option>';
                            });
                        }
                        $('#tribe_ticket_type_id').html(options);
                    });
                });
            });
        </script>
        <?php
        wp_nonce_field('save_event_tickets_link', 'event_tickets_nonce');
    }

    add_action('save_post_product', function($post_id) {
        if (!isset($_POST['event_tickets_nonce']) || !wp_verify_nonce($_POST['event_tickets_nonce'], 'save_event_tickets_link')) {
            return;
        }
        if (isset($_POST['tribe_event_id'])) {
            update_post_meta($post_id, '_tribe_event_id', absint($_POST['tribe_event_id']));
        }
        if (isset($_POST['tribe_ticket_type_id'])) {
            update_post_meta($post_id, '_tribe_ticket_type_id', absint($_POST['tribe_ticket_type_id']));
        }
    });

    add_action('wp_ajax_intersoccer_get_ticket_types', 'intersoccer_get_ticket_types');
    function intersoccer_get_ticket_types() {
        check_ajax_referer('intersoccer_ticket_types', 'nonce');
        $event_id = absint($_POST['event_id']);
        $ticket_types = Tribe__Tickets__Tickets::get_event_tickets($event_id);
        $tickets = array_map(function($ticket) {
            return ['id' => $ticket->ID, 'name' => $ticket->name];
        }, $ticket_types);
        wp_send_json_success($tickets);
    }

    add_action('rest_api_init', function() {
        register_rest_route('intersoccer/v1', '/roster/(?P<event_id>\d+)', [
            'methods' => 'GET',
            'callback' => 'intersoccer_get_roster_rest',
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    });

    function intersoccer_get_roster_rest(WP_REST_Request $request) {
        $event_id = $request['event_id'];
        $attendees = tribe_tickets_get_attendees($event_id);
        $roster = array_map(function($attendee) {
            $order_id = $attendee['order_id'];
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            $achievements = get_user_meta($user_id, 'intersoccer_achievements', true) ?: [];
            return [
                'player_name' => get_post_meta($attendee['ID'], 'player_name', true),
                'order_id' => $order_id,
                'achievements' => array_column($achievements, 'title'),
            ];
        }, $attendees);
        return rest_ensure_response($roster);
    }
});
?>
