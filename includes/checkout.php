<?php
/**
 * Checkout Modifications: Player Selection and New Player Addition with Event Tickets Integration
 */

// Add player selection dropdown to each cart item on checkout
add_filter('woocommerce_checkout_cart_item_quantity', 'add_player_selection_to_checkout', 10, 3);
function add_player_selection_to_checkout($quantity, $cart_item, $cart_item_key) {
    if (!is_checkout()) {
        return $quantity;
    }

    $product_id = $cart_item['product_id'];
    $event_id = get_post_meta($product_id, '_tribe_event_id', true);

    // Only show player selection for products linked to an Event Tickets event
    if (!$event_id || !tribe_is_event($event_id)) {
        return $quantity;
    }

    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
    $player_names = array_column($players, 'name');

    // Add a "Select Player" option
    $player_names = array_merge(array('' => __('Select a Player', 'intersoccer-player-management')), array_combine($player_names, $player_names));

    ob_start();
    ?>
    <div class="player-selection">
        <label><?php _e('Select Player for this Event:', 'intersoccer-player-management'); ?></label>
        <select name="player_selection[<?php echo esc_attr($cart_item_key); ?>]" class="player-selection-dropdown">
            <?php foreach ($player_names as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($cart_item['player_selection'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <a href="#" class="add-new-player-link"><?php _e('Add New Player', 'intersoccer-player-management'); ?></a>
    </div>
    <?php
    return $quantity . ob_get_clean();
}

// Add form for adding a new player on checkout
add_action('woocommerce_before_checkout_form', 'add_new_player_form_to_checkout');
function add_new_player_form_to_checkout() {
    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();

    // Check if any cart items require player selection
    $requires_player_selection = false;
    foreach (WC()->cart()->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $event_id = get_post_meta($product_id, '_tribe_event_id', true);
        if ($event_id && tribe_is_event($event_id)) {
            $requires_player_selection = true;
            break;
        }
    }

    if (!$requires_player_selection) {
        return;
    }

    // If no players exist, show the form by default
    $show_form = empty($players) ? 'block' : 'none';
    ?>
    <div id="new-player-form" style="display: <?php echo esc_attr($show_form); ?>; margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
        <h3><?php _e('Add a New Player', 'intersoccer-player-management'); ?></h3>
        <p><?php _e('Please add a player to proceed with your order.', 'intersoccer-player-management'); ?></p>
        <div class="new-player-fields">
            <label style="display: block; margin-bottom: 10px;">
                <?php _e('Player Name', 'intersoccer-player-management'); ?>
                <input type="text" id="new-player-name" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                <span class="error-message" style="display: none; color: red; font-size: 0.9em;"><?php _e('Player name is required.', 'intersoccer-player-management'); ?></span>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <?php _e('Date of Birth', 'intersoccer-player-management'); ?>
                <input type="date" id="new-player-dob" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                <span class="error-message" style="display: none; color: red; font-size: 0.9em;"><?php _e('Date of birth is required.', 'intersoccer-player-management'); ?></span>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="checkbox" id="new-player-has-medical-conditions" />
                <?php _e('Has Known Medical Conditions?', 'intersoccer-player-management'); ?>
            </label>
            <div class="medical-conditions" style="display: none; margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 5px;">
                    <?php _e('Medical Conditions', 'intersoccer-player-management'); ?>
                    <textarea id="new-player-medical-conditions" style="width: 100%; max-width: 300px; height: 100px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                    <span class="error-message" style="display: none; color: red; font-size: 0.9em;"><?php _e('Medical conditions are required if checked.', 'intersoccer-player-management'); ?></span>
                </label>
            </div>
            <label style="display: block; margin-bottom: 10px;">
                <?php _e('Medical Consent Form (PDF/Image)', 'intersoccer-player-management'); ?>
                <input type="file" id="new-player-consent-file" accept=".pdf,.jpg,.png" style="width: 100%; max-width: 300px;" />
            </label>
            <button type="button" id="save-new-player" class="button"><?php _e('Save Player', 'intersoccer-player-management'); ?></button>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            // Toggle new player form
            $(document).on('click', '.add-new-player-link', function(e) {
                e.preventDefault();
                $('#new-player-form').slideToggle();
            });

            // Toggle medical conditions field
            $('#new-player-has-medical-conditions').on('change', function() {
                var $medicalConditions = $(this).closest('.new-player-fields').find('.medical-conditions');
                var $textarea = $medicalConditions.find('textarea');
                var $error = $medicalConditions.find('.error-message');

                if ($(this).is(':checked')) {
                    $medicalConditions.show();
                    $textarea.addClass('required');
                } else {
                    $medicalConditions.hide();
                    $textarea.removeClass('required').val('');
                    $error.hide();
                }
            });

            // Save new player via AJAX
            $('#save-new-player').on('click', function() {
                var name = $('#new-player-name').val().trim();
                var dob = $('#new-player-dob').val();
                var hasMedicalConditions = $('#new-player-has-medical-conditions').is(':checked');
                var medicalConditions = $('#new-player-medical-conditions').val().trim();
                var consentFile = $('#new-player-consent-file')[0].files[0];

                // Reset error states
                $('.new-player-fields .error-message').hide();
                $('#new-player-name, #new-player-dob, #new-player-medical-conditions').css('border', '1px solid #ccc');

                // Validation
                var hasErrors = false;
                if (!name) {
                    $('#new-player-name').next('.error-message').show();
                    $('#new-player-name').css('border', '1px solid red');
                    hasErrors = true;
                }
                if (!dob) {
                    $('#new-player-dob').next('.error-message').show();
                    $('#new-player-dob').css('border', '1px solid red');
                    hasErrors = true;
                }
                if (hasMedicalConditions && !medicalConditions) {
                    $('#new-player-medical-conditions').next('.error-message').show();
                    $('#new-player-medical-conditions').css('border', '1px solid red');
                    hasErrors = true;
                }

                if (hasErrors) {
                    return;
                }

                // Prepare form data
                var formData = new FormData();
                formData.append('action', 'intersoccer_add_player_checkout');
                formData.append('nonce', '<?php echo wp_create_nonce('intersoccer_add_player_checkout_nonce'); ?>');
                formData.append('name', name);
                formData.append('dob', dob);
                formData.append('medical_conditions', hasMedicalConditions && medicalConditions ? medicalConditions : 'No known medical conditions');
                if (consentFile) {
                    formData.append('consent_file', consentFile);
                }

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Refresh the page to update dropdowns
                            location.reload();
                        } else {
                            alert(response.data.message || '<?php _e('Failed to add player. Please try again.', 'intersoccer-player-management'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred. Please try again.', 'intersoccer-player-management'); ?>');
                    }
                });
            });
        });
    </script>
    <?php
}

// AJAX handler to add a new player during checkout
add_action('wp_ajax_intersoccer_add_player_checkout', 'intersoccer_add_player_checkout');
function intersoccer_add_player_checkout() {
    check_ajax_referer('intersoccer_add_player_checkout_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in to add a player.', 'intersoccer-player-management')));
    }

    $user_id = get_current_user_id();
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $dob = isset($_POST['dob']) ? sanitize_text_field($_POST['dob']) : '';
    $medical_conditions = isset($_POST['medical_conditions']) ? sanitize_textarea_field($_POST['medical_conditions']) : 'No known medical conditions';

    // Validate required fields
    if (empty($name) || empty($dob)) {
        wp_send_json_error(array('message' => __('Name and Date of Birth are required.', 'intersoccer-player-management')));
    }

    // Validate DOB
    $age = date_diff(date_create($dob), date_create('today'))->y;
    if ($age < 4 || $age > 18) {
        wp_send_json_error(array('message' => __('Player must be between 4 and 18 years old.', 'intersoccer-player-management')));
    }

    // Handle file upload
    $consent_url = '';
    if (!empty($_FILES['consent_file']['name'])) {
        $upload_overrides = array('test_form' => false);
        $upload = wp_handle_upload($_FILES['consent_file'], $upload_overrides);
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => __('Failed to upload consent file: ', 'intersoccer-player-management') . $upload['error']));
        }
        $consent_url = $upload['url'];
    }

    // Add the new player to user meta
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
    $players[] = array(
        'name' => $name,
        'dob' => $dob,
        'medical_conditions' => $medical_conditions,
        'consent_url' => $consent_url,
    );

    update_user_meta($user_id, 'intersoccer_players', $players);
    wp_send_json_success();
}

// Save player selection to order item meta
add_action('woocommerce_checkout_create_order_line_item', 'save_player_selection_to_order', 10, 4);
function save_player_selection_to_order($item, $cart_item_key, $values, $order) {
    if (isset($_POST['player_selection'][$cart_item_key]) && !empty($_POST['player_selection'][$cart_item_key])) {
        $player_name = sanitize_text_field($_POST['player_selection'][$cart_item_key]);
        $item->add_meta_data('Player', $player_name, true);
    }
}

// Validate player selection on checkout
add_action('woocommerce_checkout_process', 'validate_player_selection_on_checkout');
function validate_player_selection_on_checkout() {
    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();

    // Check if any cart items require player selection
    $requires_player_selection = false;
    foreach (WC()->cart()->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $event_id = get_post_meta($product_id, '_tribe_event_id', true);
        if ($event_id && tribe_is_event($event_id)) {
            $requires_player_selection = true;
            break;
        }
    }

    if (!$requires_player_selection) {
        return;
    }

    if (empty($players)) {
        wc_add_notice(__('You must add at least one player to proceed with your order.', 'intersoccer-player-management'), 'error');
        return;
    }

    foreach (WC()->cart()->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $event_id = get_post_meta($product_id, '_tribe_event_id', true);
        if ($event_id && tribe_is_event($event_id)) {
            if (!isset($_POST['player_selection'][$cart_item_key]) || empty($_POST['player_selection'][$cart_item_key])) {
                wc_add_notice(__('Please select a player for each event ticket in your cart.', 'intersoccer-player-management'), 'error');
            }
        }
    }
}
?>

