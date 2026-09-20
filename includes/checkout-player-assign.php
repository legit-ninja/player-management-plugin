<?php
/**
 * Checkout Player Assignment — AC C8–C10
 *
 * Adds per-line player assignment dropdown at checkout for products requiring attendee.
 * Persists assignment to order meta for reports-rosters interop.
 * Blocks place order when required assignment is missing.
 *
 * @package PlayerManagement
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Check if a product requires an attendee assignment.
 *
 * Products are considered to require attendee if:
 * - They have the 'intersoccer-requires-attendee' attribute set to 'yes'
 * - OR they are in a product category containing 'camp', 'course', or 'birthday'
 *
 * @param WC_Product|int $product Product object or ID.
 * @return bool
 */
function intersoccer_product_requires_attendee($product) {
    if (is_numeric($product)) {
        $product = wc_get_product($product);
    }
    
    if (!$product) {
        return false;
    }

    // Check explicit attribute
    $requires_attendee = $product->get_attribute('intersoccer-requires-attendee');
    if (strtolower($requires_attendee) === 'yes') {
        return true;
    }

    // Check product categories for camp/course/birthday keywords
    $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
    if (is_array($categories)) {
        foreach ($categories as $cat_name) {
            $cat_lower = strtolower($cat_name);
            if (strpos($cat_lower, 'camp') !== false ||
                strpos($cat_lower, 'course') !== false ||
                strpos($cat_lower, 'birthday') !== false) {
                return true;
            }
        }
    }

    // Check product name/slug for keywords (fallback)
    $name_lower = strtolower($product->get_name());
    $slug_lower = strtolower($product->get_slug());
    if (strpos($name_lower, 'camp') !== false ||
        strpos($name_lower, 'course') !== false ||
        strpos($slug_lower, 'camp') !== false ||
        strpos($slug_lower, 'course') !== false) {
        return true;
    }

    return false;
}

/**
 * Render player assignment dropdown for a cart item.
 *
 * @param array           $cart_item     Cart item data.
 * @param string          $cart_item_key Cart item key.
 * @param WC_Product|null $product       Product object.
 * @return string HTML for player dropdown.
 */
function intersoccer_render_player_dropdown($cart_item, $cart_item_key, $product = null) {
    if (!is_user_logged_in()) {
        return '<p class="intersoccer-login-notice" data-field="login-notice">' . 
               sprintf(
                   __('Please <a href="%s">log in</a> to assign a player.', 'player-management'),
                   esc_url(wp_login_url(wc_get_checkout_url()))
               ) . '</p>';
    }

    $user_id = get_current_user_id();
    $players = function_exists('intersoccer_get_user_players') 
        ? intersoccer_get_user_players($user_id) 
        : (get_user_meta($user_id, 'intersoccer_players', true) ?: []);

    $selected_index = isset($cart_item['intersoccer_player_index']) 
        ? $cart_item['intersoccer_player_index'] 
        : '';

    ob_start();
    ?>
    <div class="intersoccer-player-assign" data-field="player-assign" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
        <label for="intersoccer_player_<?php echo esc_attr($cart_item_key); ?>" class="intersoccer-player-label">
            <strong><?php esc_html_e('Player', 'player-management'); ?></strong>
            <span class="required">*</span>
        </label>
        <?php if (empty($players)) : ?>
            <p class="intersoccer-no-players" data-field="no-players-notice">
                <?php 
                printf(
                    __('No players found. <a href="%s">Add a player</a> first.', 'player-management'),
                    esc_url(wc_get_account_endpoint_url('dashboard'))
                );
                ?>
            </p>
            <input type="hidden" name="intersoccer_player[<?php echo esc_attr($cart_item_key); ?>]" value="" data-field="player-input">
        <?php else : ?>
            <select name="intersoccer_player[<?php echo esc_attr($cart_item_key); ?>]" 
                    id="intersoccer_player_<?php echo esc_attr($cart_item_key); ?>"
                    class="intersoccer-player-select"
                    data-field="player-select"
                    data-cart-key="<?php echo esc_attr($cart_item_key); ?>"
                    required>
                <option value=""><?php esc_html_e('— Select Player —', 'player-management'); ?></option>
                <?php foreach ($players as $index => $player) : 
                    $first_name = $player['first_name'] ?? '';
                    $last_name = $player['last_name'] ?? '';
                    $dob = $player['dob'] ?? '';
                    $display_name = trim($first_name . ' ' . $last_name);
                    if ($dob) {
                        $age = intersoccer_calculate_player_age($dob);
                        if ($age >= 0) {
                            $display_name .= ' (' . $age . ' ' . __('years', 'player-management') . ')';
                        }
                    }
                ?>
                    <option value="<?php echo esc_attr($index); ?>" <?php selected($selected_index, (string)$index); ?>>
                        <?php echo esc_html($display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="intersoccer-player-error" style="display: none;" data-field="player-error">
                <?php esc_html_e('Please assign a player to this item.', 'player-management'); ?>
            </span>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Add player assignment field to cart item (cart table).
 *
 * @param string $product_name Product name HTML.
 * @param array  $cart_item    Cart item data.
 * @param string $cart_item_key Cart item key.
 * @return string Modified product name with player dropdown.
 */
function intersoccer_cart_item_player_field($product_name, $cart_item, $cart_item_key) {
    $product = $cart_item['data'] ?? null;
    
    if (!$product || !intersoccer_product_requires_attendee($product)) {
        return $product_name;
    }

    $dropdown = intersoccer_render_player_dropdown($cart_item, $cart_item_key, $product);
    
    return $product_name . $dropdown;
}
add_filter('woocommerce_cart_item_name', 'intersoccer_cart_item_player_field', 10, 3);

/**
 * Add player assignment field to checkout order review.
 *
 * @param string $product_name Product name HTML.
 * @param array  $cart_item    Cart item data.
 * @param string $cart_item_key Cart item key.
 * @return string Modified product name with player info.
 */
function intersoccer_checkout_item_player_field($product_name, $cart_item, $cart_item_key) {
    // On checkout, show selected player name instead of dropdown
    if (is_checkout() && !is_cart()) {
        $product = $cart_item['data'] ?? null;
        
        if (!$product || !intersoccer_product_requires_attendee($product)) {
            return $product_name;
        }

        $player_index = isset($cart_item['intersoccer_player_index']) ? $cart_item['intersoccer_player_index'] : '';
        
        if ($player_index !== '' && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $player = function_exists('intersoccer_get_player_by_index')
                ? intersoccer_get_player_by_index($user_id, $player_index)
                : null;
            
            if ($player) {
                $player_name = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
                return $product_name . '<br><small class="intersoccer-assigned-player" data-field="assigned-player">' . 
                       '<strong>' . esc_html__('Player:', 'player-management') . '</strong> ' . 
                       esc_html($player_name) . '</small>';
            }
        }

        // Show dropdown on checkout too (for editing)
        return intersoccer_cart_item_player_field($product_name, $cart_item, $cart_item_key);
    }

    return $product_name;
}
add_filter('woocommerce_checkout_cart_item_name', 'intersoccer_checkout_item_player_field', 10, 3);

/**
 * Save player selection to cart item session.
 */
function intersoccer_save_cart_player_selection() {
    if (!isset($_POST['intersoccer_player']) || !is_array($_POST['intersoccer_player'])) {
        return;
    }

    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    foreach ($_POST['intersoccer_player'] as $cart_item_key => $player_index) {
        $cart_item_key = sanitize_text_field($cart_item_key);
        $player_index = sanitize_text_field($player_index);

        if (isset($cart->cart_contents[$cart_item_key])) {
            $cart->cart_contents[$cart_item_key]['intersoccer_player_index'] = $player_index;
        }
    }

    // Force cart session update
    $cart->set_session();
}
add_action('woocommerce_cart_updated', 'intersoccer_save_cart_player_selection');
add_action('woocommerce_checkout_update_order_review', 'intersoccer_save_cart_player_selection_from_post');

/**
 * Parse posted player selections during checkout update.
 *
 * @param string $posted_data URL-encoded posted data.
 */
function intersoccer_save_cart_player_selection_from_post($posted_data) {
    parse_str($posted_data, $data);
    
    if (!isset($data['intersoccer_player']) || !is_array($data['intersoccer_player'])) {
        return;
    }

    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    foreach ($data['intersoccer_player'] as $cart_item_key => $player_index) {
        $cart_item_key = sanitize_text_field($cart_item_key);
        $player_index = sanitize_text_field($player_index);

        if (isset($cart->cart_contents[$cart_item_key])) {
            $cart->cart_contents[$cart_item_key]['intersoccer_player_index'] = $player_index;
        }
    }
}

/**
 * AC C9: Validate player assignment before checkout.
 * Block place order when required assignment is missing.
 */
function intersoccer_validate_checkout_player_assignment() {
    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    $missing_assignments = [];

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'] ?? null;
        
        if (!$product || !intersoccer_product_requires_attendee($product)) {
            continue;
        }

        $player_index = isset($cart_item['intersoccer_player_index']) ? $cart_item['intersoccer_player_index'] : '';
        
        if ($player_index === '' || $player_index === null) {
            $missing_assignments[] = $product->get_name();
        }
    }

    if (!empty($missing_assignments)) {
        $product_list = implode(', ', array_map('esc_html', $missing_assignments));
        wc_add_notice(
            sprintf(
                /* translators: %s: product name(s) */
                __('Please assign a player to: %s', 'player-management'),
                $product_list
            ),
            'error'
        );
    }
}
add_action('woocommerce_checkout_process', 'intersoccer_validate_checkout_player_assignment');

/**
 * AC C9: Save player assignment to order item meta.
 * Persist for reports-rosters interop.
 *
 * @param WC_Order_Item_Product $item          Order item.
 * @param string                $cart_item_key Cart item key.
 * @param array                 $values        Cart item values.
 * @param WC_Order              $order         Order object.
 */
function intersoccer_save_order_item_player($item, $cart_item_key, $values, $order) {
    $player_index = isset($values['intersoccer_player_index']) ? $values['intersoccer_player_index'] : '';
    
    if ($player_index === '' || $player_index === null) {
        return;
    }

    $user_id = $order->get_customer_id();
    $player = null;
    
    if ($user_id && function_exists('intersoccer_get_player_by_index')) {
        $player = intersoccer_get_player_by_index($user_id, $player_index);
    } elseif ($user_id) {
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        $player = isset($players[$player_index]) ? $players[$player_index] : null;
    }

    if (!$player) {
        return;
    }

    $player_name = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
    
    // Save multiple meta keys for reports-rosters interop
    $item->add_meta_data('Assigned Attendee', $player_name, true);
    $item->add_meta_data('intersoccer_player_index', $player_index, true);
    $item->add_meta_data('assigned_player', $player_index, true);
    $item->add_meta_data('Player Index', $player_index, true);
    
    // Also save DOB and medical info for roster reports
    if (!empty($player['dob'])) {
        $item->add_meta_data('Player DOB', $player['dob'], true);
    }
    if (!empty($player['medical_conditions'])) {
        $item->add_meta_data('Player Medical', $player['medical_conditions'], true);
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            'InterSoccer: Saved player assignment order=%d item=%d player_index=%s name=%s',
            $order->get_id(),
            $item->get_id(),
            $player_index,
            $player_name
        ));
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'intersoccer_save_order_item_player', 10, 4);

/**
 * AC C10: Allow editing player assignment before payment (cart updates).
 * This is handled by the cart session persistence above.
 * After payment: out of scope unless already supported.
 */

/**
 * Enqueue checkout player assignment styles and scripts.
 */
function intersoccer_enqueue_checkout_assets() {
    if (!is_cart() && !is_checkout()) {
        return;
    }

    wp_enqueue_style(
        'intersoccer-checkout',
        PLAYER_MANAGEMENT_URL . 'css/checkout-player.css',
        [],
        PLAYER_MANAGEMENT_VERSION
    );

    wp_enqueue_script(
        'intersoccer-checkout',
        PLAYER_MANAGEMENT_URL . 'js/checkout-player.js',
        ['jquery'],
        PLAYER_MANAGEMENT_VERSION,
        true
    );

    wp_localize_script('intersoccer-checkout', 'intersoccerCheckout', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('intersoccer_checkout_nonce'),
        'i18n' => [
            'selectPlayer' => __('— Select Player —', 'player-management'),
            'assignError' => __('Please assign a player to this item.', 'player-management'),
        ],
    ]);
}
add_action('wp_enqueue_scripts', 'intersoccer_enqueue_checkout_assets');

/**
 * AJAX handler for updating player assignment in cart.
 */
function intersoccer_ajax_update_cart_player() {
    check_ajax_referer('intersoccer_checkout_nonce', 'nonce');

    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $player_index = isset($_POST['player_index']) ? sanitize_text_field($_POST['player_index']) : '';

    if (empty($cart_item_key)) {
        wp_send_json_error(['message' => __('Invalid cart item.', 'player-management')]);
    }

    $cart = WC()->cart;
    if (!$cart || !isset($cart->cart_contents[$cart_item_key])) {
        wp_send_json_error(['message' => __('Cart item not found.', 'player-management')]);
    }

    $cart->cart_contents[$cart_item_key]['intersoccer_player_index'] = $player_index;
    $cart->set_session();

    // Get player name for response
    $player_name = '';
    if ($player_index !== '' && is_user_logged_in()) {
        $user_id = get_current_user_id();
        $player = function_exists('intersoccer_get_player_by_index')
            ? intersoccer_get_player_by_index($user_id, $player_index)
            : null;
        if ($player) {
            $player_name = trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''));
        }
    }

    wp_send_json_success([
        'message' => __('Player assignment updated.', 'player-management'),
        'player_name' => $player_name,
    ]);
}
add_action('wp_ajax_intersoccer_update_cart_player', 'intersoccer_ajax_update_cart_player');
