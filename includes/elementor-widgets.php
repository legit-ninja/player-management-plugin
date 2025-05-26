<?php

/**
 * File: elementor-widgets.php
 * Description: Extends Elementor’s Single Product widget to inject player and day selection fields into the product form variations table for the InterSoccer Player Management plugin. Ensures fields follow the same formatting as other variations.
 * Dependencies: None
 * Changes:
 * - Updated form validation to check hidden camp_days[] inputs instead of visible checkboxes (2025-05-15).
 * - Added price container for Courses to display subtotal and discounted price (2025-05-23).
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Inject player, day selection, and price container into the variations table
add_action('wp_footer', function () {
    if (!is_product()) {
        return;
    }

    // Get current product
    global $product;
    if (!is_a($product, 'WC_Product')) {
        return;
    }

    $product_id = $product->get_id();
    $user_id = get_current_user_id();
    $is_variable = $product->is_type('variable');

    // Player selection HTML
    ob_start();
?>
    <tr class="intersoccer-player-selection intersoccer-injected">
        <th><label for="player_assignment_select"><?php esc_html_e('Select an Attendee', 'intersoccer-player-management'); ?></label></th>
        <td>
            <div class="intersoccer-player-content">
                <?php if (!$user_id) : ?>
                    <p class="intersoccer-login-prompt">Please <a href="https://intersoccer.ch/membership-login/">log in</a> or <a href="https://intersoccer.ch/membership-login/">register</a>.</p>
                <?php else : ?>
                    <p>Loading players...</p>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    <?php
    $player_selection_html = ob_get_clean();

    // Day selection HTML
    $day_selection_html = '';
    if ($product->get_type() === 'variable') {
        ob_start();
    ?>
        <tr class="intersoccer-day-selection intersoccer-injected" style="display: none;">
            <th><label><?php esc_html_e('Select Days', 'intersoccer-player-management'); ?></label></th>
            <td>
                <div class="intersoccer-day-checkboxes"></div>
                <div class="intersoccer-day-notification" style="margin-top: 10px; color: #007cba;"></div>
                <span class="error-message" style="color: red; display: none;"></span>
            </td>
        </tr>
    <?php
        $day_selection_html = ob_get_clean();
    }

    // Price container HTML for Courses
    $price_container_html = '';
    if ($product->get_type() === 'variable') {
        ob_start();
    ?>
        <tr class="intersoccer-price-container intersoccer-injected" style="display: none;">
            <th><label><?php esc_html_e('Price Details', 'intersoccer-player-management'); ?></label></th>
            <td>
                <div class="intersoccer-price-details">
                    <p class="intersoccer-subtotal" style="display: none;">Subtotal: <span class="subtotal-amount"></span></p>
                    <p class="intersoccer-discounted" style="display: none;">Discounted Price: <span class="discounted-amount"></span></p>
                </div>
            </td>
        </tr>
    <?php
        $price_container_html = ob_get_clean();
    }

    // Inject fields and price container
    ?>
    <script>
        jQuery(document).ready(function($) {
            console.log('InterSoccer: Document ready, attempting to inject fields');

            function injectFields() {
                console.log('InterSoccer: injectFields called');

                var $form = $('form.cart');
                if (!$form.length) {
                    $form = $('.woocommerce-product-details form.cart, .product form.cart, .single-product form.cart');
                }
                if (!$form.length) {
                    console.error('InterSoccer: Product form not found with any selector');
                    return;
                }
                console.log('InterSoccer: Found product form:', $form);

                if ($form.find('.intersoccer-injected').length > 0) {
                    console.log('InterSoccer: Fields already injected, skipping');
                    return;
                }

                var $variationsTable = $form.find('.variations');
                if ($variationsTable.length) {
                    var $tbody = $variationsTable.find('tbody');
                    if ($tbody.length) {
                        $tbody.append(<?php echo json_encode($player_selection_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                        <?php if ($day_selection_html) : ?>
                            $tbody.append(<?php echo json_encode($day_selection_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                        <?php endif; ?>
                        <?php if ($price_container_html) : ?>
                            $tbody.append(<?php echo json_encode($price_container_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                        <?php endif; ?>
                        console.log('InterSoccer: Injected fields and price container into variations table');
                    } else {
                        console.error('InterSoccer: Variations table tbody not found, appending to variations table');
                        $variationsTable.append(<?php echo json_encode($player_selection_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                        <?php if ($day_selection_html) : ?>
                            $variationsTable.append(<?php echo json_encode($day_selection_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                        <?php endif; ?>
                        <?php if ($price_container_html) : ?>
                            $variationsTable.append(<?php echo json_encode($price_container_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                        <?php endif; ?>
                    }
                } else {
                    console.error('InterSoccer: Variations table not found, appended to form');
                    $form.append(<?php echo json_encode($player_selection_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                    <?php if ($day_selection_html) : ?>
                        $form.append(<?php echo json_encode($day_selection_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                    <?php endif; ?>
                    <?php if ($price_container_html) : ?>
                        $form.append(<?php echo json_encode($price_container_html, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>);
                    <?php endif; ?>
                }

                $form.find('.intersoccer-player-content').each(function() {
                    var $this = $(this);
                    var htmlContent = $this.html();
                    $this.html(htmlContent);
                });

                var $addToCartButton = $form.find('button.single_add_to_cart_button');
                if ($addToCartButton.length) {
                    $addToCartButton.prop('disabled', true);
                    console.log('InterSoccer: Add to Cart button disabled by default');
                }

                if (intersoccerCheckout.user_id && intersoccerCheckout.user_id !== '0') {
                    $.ajax({
                        url: intersoccerCheckout.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'intersoccer_get_user_players',
                            nonce: intersoccerCheckout.nonce,
                            user_id: intersoccerCheckout.user_id
                        },
                        success: function(response) {
                            console.log('InterSoccer: Player fetch response:', response);
                            var $playerContent = $form.find('.intersoccer-player-content');
                            if (response.success && response.data.players) {
                                if (response.data.players.length > 0) {
                                    var $select = $('<select name="player_assignment" id="player_assignment_select" class="player-select intersoccer-player-select"></select>');
                                    $select.append('<option value=""><?php esc_html_e('Select an Attendee', 'intersoccer-player-management'); ?></option>');
                                    $.each(response.data.players, function(index, player) {
                                        $select.append('<option value="' + index + '">' + player.first_name + ' ' + player.last_name + '</option>');
                                    });
                                    $playerContent.html($select);
                                    $playerContent.append('<span class="error-message" style="color: red; display: none;"></span>');

                                    $select.on('change', function() {
                                        if ($(this).val()) {
                                            $addToCartButton.prop('disabled', false);
                                            console.log('InterSoccer: Add to Cart button enabled');
                                        } else {
                                            $addToCartButton.prop('disabled', true);
                                            console.log('InterSoccer: Add to Cart button disabled');
                                        }
                                    });
                                } else {
                                    $playerContent.html('<p>No players registered. <a href="/my-account/manage-players/">Add a player</a>.</p>');
                                }
                                $playerContent.find('p').each(function() {
                                    var $this = $(this);
                                    var htmlContent = $this.html();
                                    $this.html(htmlContent);
                                });
                            } else {
                                $playerContent.html('<p>Error loading players.</p>');
                            }
                        },
                        error: function(xhr) {
                            console.error('InterSoccer: Failed to fetch players:', xhr.responseText);
                            $form.find('.intersoccer-player-content').html('<p>Error loading players.</p>');
                        }
                    });
                }
            }

            injectFields();
            setTimeout(injectFields, 3000);

            $('form.cart').on('found_variation', function() {
                if (!$('form.cart').find('.intersoccer-day-selection').length) {
                    console.log('InterSoccer: Day selection container missing after variation change, re-injecting');
                    injectFields();
                }
            });
        });
    </script>
<?php
});

// Validate player/day selection and fetch price for Courses
add_action('wp_footer', function () {
    if (!is_product()) {
        return;
    }
?>
    <script>
        jQuery(document).ready(function($) {
            var $form = $('form.cart');
            if (!$form.length) {
                $form = $('.woocommerce-product-details form.cart');
            }
            if (!$form.length) {
                $form = $('.product form.cart');
            }
            if (!$form.length) {
                console.error('InterSoccer: Product form not found for validation');
                return;
            }

            // Handle variation change for Courses
            $form.on('found_variation', function(event, variation) {
                if (!variation) return;

                $.ajax({
                    url: intersoccerCheckout.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'intersoccer_get_course_metadata',
                        nonce: intersoccerCheckout.nonce,
                        product_id: <?php echo $product_id; ?>,
                        variation_id: variation.variation_id
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var $priceContainer = $form.find('.intersoccer-price-container');
                            var regularPrice = parseFloat(variation.display_regular_price);
                            var customPrice = response.data.weekly_discount ?
                                regularPrice - (response.data.weekly_discount * (response.data.total_weeks - response.data.remaining_weeks)) :
                                regularPrice;

                            if (response.data.activity_type === 'course' && customPrice < regularPrice) {
                                $priceContainer.show();
                                $form.find('.intersoccer-subtotal').show().find('.subtotal-amount').text(wc_price(regularPrice));
                                $form.find('.intersoccer-discounted').show().find('.discounted-amount').text(wc_price(customPrice));
                                $form.find('input[name="adjusted_price"]').val(customPrice);
                                $form.find('input[name="remaining_weeks"]').val(response.data.remaining_weeks);
                            } else {
                                $priceContainer.hide();
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('InterSoccer: Failed to fetch course metadata:', xhr.responseText);
                    }
                });
            });

            // Format price to match WooCommerce
            function wc_price(price) {
                return '<?php echo wc_price(0); ?>'.replace('0.00', price.toFixed(2));
            }

            $form.on('submit', function(e) {
                var playerId = $form.find('.player-select').val();
                if (!playerId && intersoccerCheckout.user_id && intersoccerCheckout.user_id !== '0') {
                    e.preventDefault();
                    $form.find('.intersoccer-player-selection .error-message').text('Please Select an Attendee.').show();
                    setTimeout(() => $form.find('.intersoccer-player-selection .error-message').hide(), 5000);
                }
                var bookingType = $form.find('select[name="attribute_pa_booking-type"]').val();
                if (bookingType === 'single-days') {
                    var selectedDays = $form.find('input[name="camp_days[]"]:checked').length;
                    if (!selectedDays) {
                        e.preventDefault();
                        $form.find('.intersoccer-day-selection .error-message').text('Please select at least one day.').show();
                        setTimeout(() => $form.find('.intersoccer-day-selection .error-message').hide(), 5000);
                    }
                }
            });
        });
    </script>
    <style>
        .intersoccer-price-container {
            margin-top: 15px;
            padding: 10px;
            background-color: var(--wp--preset--color--bg-color, #1B1A1A);
            color: var(--wp--preset--color--text-dark, #FFFEFE);
            border: 1px solid var(--wp--preset--color--text-light, #8F8E8E);
            border-radius: 4px;
        }

        .intersoccer-price-details p {
            margin: 5px 0;
        }
    </style>
<?php
});
?>