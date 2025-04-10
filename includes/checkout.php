<?php
/**
 * Checkout Modifications for InterSoccer Player Management
 */

// Add new player form to checkout
add_action('woocommerce_before_checkout_form', 'add_new_player_form_to_checkout');
// Fallback for Elementor Pro or custom checkout pages
add_action('woocommerce_checkout_before_customer_details', 'add_new_player_form_to_checkout');
function add_new_player_form_to_checkout() {
    // Check if WooCommerce is active and the cart is available
    if (!function_exists('WC') || !isset(WC()->cart)) {
        error_log('InterSoccer: WooCommerce or cart not available on checkout page.');
        return;
    }

    // Check if there are items in the cart
    if (WC()->cart->is_empty()) {
        error_log('InterSoccer: Cart is empty on checkout page.');
        return;
    }

    // Get cart items
    $cart_items = WC()->cart->get_cart();
    $requires_player_info = false;

    foreach ($cart_items as $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
        error_log('InterSoccer: Product ID ' . $product_id . ' categories: ' . print_r($categories, true));

        // Check if the product requires player info based on category
        if (has_term('summer-camps', 'product_cat', $product_id)) {
            $requires_player_info = true;
            error_log('InterSoccer: Product ID ' . $product_id . ' requires player info (summer-camps category).');
            break;
        }

        // Fallback: Check if the product has pa_booking-type set to 'week' or 'full_term'
        $booking_type = $product->get_attribute('pa_booking-type');
        error_log('InterSoccer: Product ID ' . $product_id . ' booking type: ' . $booking_type);
        if (in_array($booking_type, array('week', 'full_term'))) {
            $requires_player_info = true;
            error_log('InterSoccer: Product ID ' . $product_id . ' requires player info (booking type: ' . $booking_type . ').');
            break;
        }
    }

    if (!$requires_player_info) {
        error_log('InterSoccer: No products in cart require player info.');
        return;
    }

    // Load the player form template from the child theme
    $template_path = get_stylesheet_directory() . '/templates/intersoccer-player-form.php';
    if (file_exists($template_path)) {
        error_log('InterSoccer: Loading player form template from child theme.');
        include $template_path;
    } else {
        error_log('InterSoccer: Player form template not found in child theme at ' . $template_path);
    }
}

// Save player data on checkout
add_action('woocommerce_checkout_update_user_meta', 'save_player_data_on_checkout', 10, 2);
function save_player_data_on_checkout($user_id, $data) {
    if (isset($_POST['intersoccer_players']) && is_array($_POST['intersoccer_players'])) {
        $players = array();
        foreach ($_POST['intersoccer_players'] as $index => $player_data) {
            // Validate required fields
            if (empty($player_data['name']) || empty($player_data['dob'])) {
                wc_add_notice(__('Please fill in all required player fields.', 'intersoccer-player-management'), 'error');
                continue;
            }

            $player = array(
                'name' => sanitize_text_field($player_data['name']),
                'dob' => sanitize_text_field($player_data['dob']),
                'medical_conditions' => sanitize_textarea_field($player_data['medical_conditions']),
                'consent_file' => '',
            );

            // Handle file upload
            if (!empty($_FILES['intersoccer_players']['name'][$index]['consent_file'])) {
                $file = array(
                    'name' => $_FILES['intersoccer_players']['name'][$index]['consent_file'],
                    'type' => $_FILES['intersoccer_players']['type'][$index]['consent_file'],
                    'tmp_name' => $_FILES['intersoccer_players']['tmp_name'][$index]['consent_file'],
                    'error' => $_FILES['intersoccer_players']['error'][$index]['consent_file'],
                    'size' => $_FILES['intersoccer_players']['size'][$index]['consent_file'],
                );

                // Log file upload details for debugging
                error_log('File Upload Attempt: ' . print_r($file, true));

                // Check for upload errors
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_message = __('File upload failed for player consent form.', 'intersoccer-player-management');
                    switch ($file['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = __('File size exceeds the maximum limit.', 'intersoccer-player-management');
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = __('File was only partially uploaded.', 'intersoccer-player-management');
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = __('No file was uploaded.', 'intersoccer-player-management');
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message = __('Missing a temporary folder for file upload.', 'intersoccer-player-management');
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message = __('Failed to write file to disk.', 'intersoccer-player-management');
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = __('A PHP extension stopped the file upload.', 'intersoccer-player-management');
                            break;
                    }
                    wc_add_notice($error_message, 'error');
                    continue;
                }

                // Upload the file
                $upload = wp_upload_bits($file['name'], null, file_get_contents($file['tmp_name']));
                if (!$upload['error']) {
                    $player['consent_file'] = $upload['url'];
                } else {
                    wc_add_notice(__('Failed to upload consent form: ', 'intersoccer-player-management') . $upload['error'], 'error');
                    continue;
                }
            }

            $players[] = $player;
        }

        // Log the players data for debugging
        error_log('Saving Players Data for User ' . $user_id . ': ' . print_r($players, true));

        // Save the players to user meta
        update_user_meta($user_id, 'intersoccer_players', $players);
    }
}

// Save player data as order item meta
add_action('woocommerce_checkout_create_order_line_item', 'save_player_data_to_order_item', 20, 4);
function save_player_data_to_order_item($item, $cart_item_key, $values, $order) {
    // Get the user ID
    $user_id = $order->get_user_id();
    if (!$user_id) {
        return;
    }

    // Get the players from user meta
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
    if (empty($players)) {
        return;
    }

    // Add player data as order item meta
    foreach ($players as $index => $player) {
        $item->add_meta_data('Player ' . ($index + 1), $player['name'], true);
        if (!empty($player['medical_conditions'])) {
            $item->add_meta_data('Medical Conditions ' . ($index + 1), $player['medical_conditions'], true);
        }
        if (!empty($player['consent_file'])) {
            $item->add_meta_data('Consent Form ' . ($index + 1), $player['consent_file'], true);
        }
    }
}
?>
