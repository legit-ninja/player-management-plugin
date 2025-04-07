<?php
/**
 * Event Tickets Integration: Link WooCommerce Products to Event Tickets
 */

// Check if Event Tickets is active
if (!class_exists('Tribe__Tickets__Main')) {
    return;
}

// Create an attendee record in Event Tickets after a WooCommerce order is placed
add_action('woocommerce_order_status_completed', 'link_woocommerce_order_to_event_tickets', 10, 1);
function link_woocommerce_order_to_event_tickets($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    $user = get_userdata($user_id);
    $user_email = $user ? $user->user_email : $order->get_billing_email();
    $user_name = $user ? $user->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $player_name = $item->get_meta('Player', true);

        // Check if the product is linked to an Event Tickets event
        $event_id = get_post_meta($product_id, '_tribe_event_id', true);
        if (!$event_id) {
            continue;
        }

        // Check if the event exists and is a valid Event Tickets event
        if (!tribe_is_event($event_id)) {
            continue;
        }

        // Get the ticket type (assuming one ticket type per event for simplicity)
        $ticket_types = Tribe__Tickets__Tickets::get_event_tickets($event_id);
        $ticket_type_id = 0;
        foreach ($ticket_types as $ticket) {
            if ($ticket->provider_class === 'Tribe__Tickets__RSVP') {
                $ticket_type_id = $ticket->ID;
                break;
            }
        }

        if (!$ticket_type_id) {
            continue;
        }

        // Create an attendee record
        $attendee_data = array(
            'full_name' => $user_name,
            'email' => $user_email,
            'order_id' => $order_id,
            'ticket_id' => $ticket_type_id,
            'event_id' => $event_id,
            'user_id' => $user_id,
            'meta' => array(
                'player_name' => $player_name,
            ),
        );

        $attendee_id = tribe_tickets()->create_attendee($attendee_data, 'rsvp');
        if (is_wp_error($attendee_id)) {
            error_log('Failed to create Event Tickets attendee: ' . $attendee_id->get_error_message());
        }
    }
}

// Add a meta box to WooCommerce products to link to an Event Tickets event
add_action('add_meta_boxes', 'add_event_tickets_meta_box');
function add_event_tickets_meta_box() {
    add_meta_box(
        'event-tickets-link',
        __('Event Tickets Link', 'intersoccer-player-management'),
        'render_event_tickets_meta_box',
        'product',
        'side',
        'default'
    );
}

function render_event_tickets_meta_box($post) {
    $event_id = get_post_meta($post->ID, '_tribe_event_id', true);
    $events = tribe_get_events(array(
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));

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
    <?php
    wp_nonce_field('save_event_tickets_link', 'event_tickets_nonce');
}

add_action('save_post_product', 'save_event_tickets_link');
function save_event_tickets_link($post_id) {
    if (!isset($_POST['event_tickets_nonce']) || !wp_verify_nonce($_POST['event_tickets_nonce'], 'save_event_tickets_link')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $event_id = isset($_POST['tribe_event_id']) ? absint($_POST['tribe_event_id']) : 0;
    update_post_meta($post_id, '_tribe_event_id', $event_id);
}
?>

