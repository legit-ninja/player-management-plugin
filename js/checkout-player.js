/**
 * Checkout Player Assignment — AC C8–C10
 *
 * @package PlayerManagement
 */

(function($) {
    'use strict';

    if (typeof $ === 'undefined') {
        console.error('InterSoccer Checkout: jQuery not loaded.');
        return;
    }

    var IntersoccerCheckout = {
        init: function() {
            this.bindEvents();
            this.validateOnLoad();
        },

        bindEvents: function() {
            var self = this;

            // Update cart when player selection changes
            $(document.body).on('change', '.intersoccer-player-select', function() {
                self.handlePlayerChange($(this));
            });

            // Validate before checkout submission
            $(document.body).on('checkout_place_order', function() {
                return self.validateAllAssignments();
            });

            // Re-validate when checkout updates
            $(document.body).on('updated_checkout', function() {
                self.validateOnLoad();
            });

            // Update cart totals triggers
            $(document.body).on('updated_cart_totals', function() {
                self.validateOnLoad();
            });
        },

        handlePlayerChange: function($select) {
            var cartItemKey = $select.data('cart-key');
            var playerIndex = $select.val();
            var $container = $select.closest('.intersoccer-player-assign');
            var $error = $container.find('.intersoccer-player-error');

            // Clear error
            $container.removeClass('has-error');
            $error.hide();

            // Update via AJAX
            if (typeof intersoccerCheckout !== 'undefined') {
                $.ajax({
                    url: intersoccerCheckout.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'intersoccer_update_cart_player',
                        nonce: intersoccerCheckout.nonce,
                        cart_item_key: cartItemKey,
                        player_index: playerIndex
                    },
                    success: function(response) {
                        if (response.success) {
                            // Trigger cart update
                            $(document.body).trigger('wc_update_cart');
                            $(document.body).trigger('update_checkout');
                        }
                    },
                    error: function() {
                        console.error('InterSoccer: Failed to update player assignment.');
                    }
                });
            }
        },

        validateOnLoad: function() {
            var self = this;
            var hasErrors = false;

            $('.intersoccer-player-select').each(function() {
                var $select = $(this);
                var $container = $select.closest('.intersoccer-player-assign');
                var $error = $container.find('.intersoccer-player-error');

                if ($select.val() === '' || $select.val() === null) {
                    // Show error only if the field has been interacted with
                    if ($select.data('touched')) {
                        $container.addClass('has-error');
                        $error.show();
                        hasErrors = true;
                    }
                } else {
                    $container.removeClass('has-error');
                    $error.hide();
                }
            });

            // Mark as touched on blur
            $('.intersoccer-player-select').off('blur.intersoccer').on('blur.intersoccer', function() {
                $(this).data('touched', true);
                self.validateOnLoad();
            });

            return !hasErrors;
        },

        validateAllAssignments: function() {
            var hasErrors = false;
            var errorMessages = [];

            $('.intersoccer-player-select').each(function() {
                var $select = $(this);
                var $container = $select.closest('.intersoccer-player-assign');
                var $error = $container.find('.intersoccer-player-error');

                $select.data('touched', true);

                if ($select.val() === '' || $select.val() === null) {
                    $container.addClass('has-error');
                    $error.show();
                    hasErrors = true;

                    // Get product name from parent row
                    var productName = $container.closest('tr, .cart_item').find('.product-name a, .product-name').first().text().trim();
                    if (productName) {
                        errorMessages.push(productName);
                    }
                } else {
                    $container.removeClass('has-error');
                    $error.hide();
                }
            });

            if (hasErrors) {
                // Scroll to first error
                var $firstError = $('.intersoccer-player-assign.has-error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 500);
                }

                // Show WooCommerce notice
                if (typeof intersoccerCheckout !== 'undefined' && errorMessages.length > 0) {
                    // The server-side validation will show the notice
                    // This just prevents form submission
                }

                return false;
            }

            return true;
        }
    };

    $(document).ready(function() {
        IntersoccerCheckout.init();
    });

})(jQuery);
