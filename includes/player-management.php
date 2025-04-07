<?php
/**
 * Player Management - Elementor Widget and Form Logic
 */

// Register the Elementor widget
add_action('elementor/widgets/register', 'register_player_management_widget');
function register_player_management_widget($widgets_manager) {
    class Player_Management_Widget extends \Elementor\Widget_Base {
        public function get_name() {
            return 'player_management';
        }

        public function get_title() {
            return __('Player Management', 'woocommerce');
        }

        public function get_icon() {
            return 'eicon-person';
        }

        public function get_categories() {
            return ['woocommerce'];
        }

        protected function render() {
            display_players_form();
        }
    }

    $widgets_manager->register(new Player_Management_Widget());
}

// The display_players_form function
function display_players_form() {
    if (!is_user_logged_in()) {
        wc_add_notice(__('Please log in to manage players.', 'woocommerce'), 'error');
        return;
    }

    $user_id = get_current_user_id();
    $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();

    // Handle form submission
    if (isset($_POST['save_players']) && !empty($_POST['players_nonce']) && wp_verify_nonce($_POST['players_nonce'], 'save_players_action')) {
        $new_players = array();
        $names = $_POST['player_name'] ?? array();
        $dobs = $_POST['player_dob'] ?? array();
        $has_conditions = $_POST['has_medical_conditions'] ?? array();
        $conditions = $_POST['medical_conditions'] ?? array();
        $consents = $_FILES['medical_consent'] ?? array();
        $existing_names = array_column($players, 'name');

        // Process only non-empty entries
        for ($i = 0; $i < count($names); $i++) {
            $name = isset($names[$i]) ? sanitize_text_field($names[$i]) : '';
            $dob = isset($dobs[$i]) ? sanitize_text_field($dobs[$i]) : '';

            // Skip completely empty entries
            if (empty($name) && empty($dob)) {
                continue;
            }

            // Validate name
            if (empty($name)) {
                wc_add_notice(__('A player name is empty.', 'woocommerce'), 'error');
                continue;
            }

            // Check for duplicates, but allow the same name if it's an existing player being edited
            if (in_array($name, $existing_names) && $name !== ($players[$i]['name'] ?? '')) {
                wc_add_notice(sprintf(__('Player name "%s" already exists.', 'woocommerce'), $name), 'error');
                continue;
            }

            // Validate DOB
            if (empty($dob)) {
                wc_add_notice(sprintf(__('Date of birth for %s is required.', 'woocommerce'), $name), 'error');
                continue;
            }

            $age = date_diff(date_create($dob), date_create('today'))->y;
            if ($age < 4 || $age > 18) {
                wc_add_notice(sprintf(__('Player %s must be between 4 and 18 years old.', 'woocommerce'), $name), 'error');
                continue;
            }

            // Handle medical conditions
            $condition = !empty($has_conditions[$i]) && !empty($conditions[$i]) ? sanitize_textarea_field($conditions[$i]) : 'No known medical conditions';
            if (!empty($has_conditions[$i]) && empty($conditions[$i])) {
                wc_add_notice(sprintf(__('Medical conditions for %s are required if checked.', 'woocommerce'), $name), 'error');
                continue;
            }

            // Handle file upload
            $consent_url = $players[$i]['consent_url'] ?? '';
            if (!empty($consents['name'][$i]) && $consents['error'][$i] == 0) {
                $file = array(
                    'name' => $consents['name'][$i],
                    'type' => $consents['type'][$i],
                    'tmp_name' => $consents['tmp_name'][$i],
                    'error' => $consents['error'][$i],
                    'size' => $consents['size'][$i]
                );
                $upload_overrides = array('test_form' => false);
                $upload = wp_handle_upload($file, $upload_overrides);
                if (isset($upload['error'])) {
                    wc_add_notice(sprintf(__('Failed to upload consent file for %s: %s', 'woocommerce'), $name, $upload['error']), 'error');
                    continue;
                }
                $consent_url = $upload['url'];
            }

            $new_players[] = array(
                'name' => $name,
                'dob' => $dob,
                'medical_conditions' => $condition,
                'consent_url' => $consent_url,
            );
        }

        if (!empty($new_players)) {
            $result = update_user_meta($user_id, 'intersoccer_players', $new_players);
            if ($result) {
                wc_add_notice(__('Players updated successfully!', 'woocommerce'), 'success');
                $players = $new_players;
            } else {
                wc_add_notice(__('Failed to save players. Please try again.', 'woocommerce'), 'error');
                error_log('Failed to update user meta for user ' . $user_id);
            }
        } else {
            wc_add_notice(__('No valid players to save.', 'woocommerce'), 'error');
        }
    }
    ?>
    <h2><?php _e('Manage Players', 'woocommerce'); ?></h2>
    <p><?php _e('Add your children as Players for Summer Football Camps.', 'woocommerce'); ?></p>
    <form method="post" id="manage-players-form" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('save_players_action', 'players_nonce'); ?>
        <div id="players-list">
            <?php foreach ($players as $i => $player): ?>
                <div class="player-entry" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 5px;">
                    <label style="display: block; margin-bottom: 10px;">
                        <?php _e('Player Name', 'woocommerce'); ?>
                        <input type="text" name="player_name[<?php echo $i; ?>]" value="<?php echo esc_attr($player['name']); ?>" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        <span class="error-message" style="color: red; display: none; font-size: 0.9em;"><?php _e('Player name is required.', 'woocommerce'); ?></span>
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <?php _e('Date of Birth', 'woocommerce'); ?>
                        <input type="date" name="player_dob[<?php echo $i; ?>]" value="<?php echo esc_attr($player['dob']); ?>" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
                        <span class="error-message" style="color: red; display: none; font-size: 0.9em;"><?php _e('Date of birth is required.', 'woocommerce'); ?></span>
                    </label>
                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="has_medical_conditions[<?php echo $i; ?>]" class="has-medical-conditions" <?php checked($player['medical_conditions'] !== 'No known medical conditions'); ?> />
                        <?php _e('Has Known Medical Conditions?', 'woocommerce'); ?>
                    </label>
                    <div class="medical-conditions" style="display: <?php echo $player['medical_conditions'] !== 'No known medical conditions' ? 'block' : 'none'; ?>; margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px;">
                            <?php _e('Medical Conditions', 'woocommerce'); ?>
                            <textarea name="medical_conditions[<?php echo $i; ?>]" style="width: 100%; max-width: 300px; height: 100px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"><?php echo esc_textarea($player['medical_conditions'] !== 'No known medical conditions' ? $player['medical_conditions'] : ''); ?></textarea>
                            <span class="error-message" style="color: red; display: none; font-size: 0.9em;"><?php _e('Medical conditions are required if checked.', 'woocommerce'); ?></span>
                        </label>
                    </div>
                    <label style="display: block; margin-bottom: 10px;">
                        <?php _e('Medical Consent Form (PDF/Image)', 'woocommerce'); ?>
                        <input type="file" name="medical_consent[<?php echo $i; ?>]" accept=".pdf,.jpg,.png" style="width: 100%; max-width: 300px;" />
                        <?php if (!empty($player['consent_url'])): ?>
                            <a href="<?php echo esc_url($player['consent_url']); ?>" target="_blank" style="display: block; margin-top: 5px;"><?php _e('View Current Consent', 'woocommerce'); ?></a>
                        <?php endif; ?>
                    </label>
                    <button type="button" class="remove-player button" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;"><?php _e('Remove', 'woocommerce'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-player" class="button" style="background: #007cba; color: white; padding: 8px 16px; border: none; border-radius: 4px; margin: 10px 0;"><?php _e('Add Another Player', 'woocommerce'); ?></button>
        <p><input type="submit" name="save_players" class="button" value="<?php _e('Save Players', 'woocommerce'); ?>" style="background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px;" /></p>
    </form>

    <div id="player-template" style="display: none;">
        <div class="player-entry" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <label style="display: block; margin-bottom: 10px;">
                <?php _e('Player Name', 'woocommerce'); ?>
                <input type="text" name="player_name_template" value="" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" disabled />
                <span class="error-message" style="color: red; display: none; font-size: 0.9em;"><?php _e('Player name is required.', 'woocommerce'); ?></span>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <?php _e('Date of Birth', 'woocommerce'); ?>
                <input type="date" name="player_dob_template" value="" style="width: 100%; max-width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" disabled />
                <span class="error-message" style="color: red; display: none; font-size: 0.9em;"><?php _e('Date of birth is required.', 'woocommerce'); ?></span>
            </label>
            <label style="display: block; margin-bottom: 10px;">
                <input type="checkbox" name="has_medical_conditions_template" class="has-medical-conditions" />
                <?php _e('Has Known Medical Conditions?', 'woocommerce'); ?>
            </label>
            <div class="medical-conditions" style="display: none; margin-bottom: 10px;">
                <label style="display: block; margin-bottom: 5px;">
                    <?php _e('Medical Conditions', 'woocommerce'); ?>
                    <textarea name="medical_conditions_template" style="width: 100%; max-width: 300px; height: 100px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
                    <span class="error-message" style="color: red; display: none; font-size: 0.9em;"><?php _e('Medical conditions are required if checked.', 'woocommerce'); ?></span>
                </label>
            </div>
            <label style="display: block; margin-bottom: 10px;">
                <?php _e('Medical Consent Form (PDF/Image)', 'woocommerce'); ?>
                <input type="file" name="medical_consent_template" accept=".pdf,.jpg,.png" style="width: 100%; max-width: 300px;" disabled />
            </label>
            <button type="button" class="remove-player button" style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;"><?php _e('Remove', 'woocommerce'); ?></button>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Function to reindex form inputs
            function reindexFormInputs() {
                $('#players-list .player-entry').each(function(index) {
                    $(this).find('input[name^="player_name"]').attr('name', 'player_name[' + index + ']');
                    $(this).find('input[name^="player_dob"]').attr('name', 'player_dob[' + index + ']');
                    $(this).find('input[name^="has_medical_conditions"]').attr('name', 'has_medical_conditions[' + index + ']');
                    $(this).find('textarea[name^="medical_conditions"]').attr('name', 'medical_conditions[' + index + ']');
                    $(this).find('input[name^="medical_consent"]').attr('name', 'medical_consent[' + index + ']');
                });
            }

            // Add new player
            $('#add-player').click(function() {
                var $newEntry = $('#player-template .player-entry').clone();
                $newEntry.find('input, textarea').each(function() {
                    if ($(this).attr('name') === 'player_name_template') {
                        $(this).attr('name', 'player_name[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'player_dob_template') {
                        $(this).attr('name', 'player_dob[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'has_medical_conditions_template') {
                        $(this).attr('name', 'has_medical_conditions[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'medical_conditions_template') {
                        $(this).attr('name', 'medical_conditions[' + $('#players-list .player-entry').length + ']');
                    } else if ($(this).attr('name') === 'medical_consent_template') {
                        $(this).attr('name', 'medical_consent[' + $('#players-list .player-entry').length + ']');
                    }
                    $(this).css({
                        'display': 'block',
                        'width': '100%',
                        'max-width': '300px',
                        'padding': '8px'
                    }).prop('disabled', false);
                });
                $newEntry.find('.error-message').hide();
                $('#players-list').append($newEntry);
                reindexFormInputs();
            });

            // Remove player
            $(document).on('click', '.remove-player', function() {
                if (confirm('<?php _e('Are you sure?', 'woocommerce'); ?>')) {
                    $(this).closest('.player-entry').remove();
                    reindexFormInputs();
                }
            });

            // Toggle medical conditions field
            $(document).on('change', '.has-medical-conditions', function() {
                var $parent = $(this).closest('.player-entry');
                var $medicalConditions = $parent.find('.medical-conditions');
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

            // Custom validation on form submission
            $('#manage-players-form').on('submit', function(e) {
                var hasErrors = false;
                $('#players-list .player-entry').each(function() {
                    var $entry = $(this);
                    var $name = $entry.find('input[name^="player_name"]');
                    var $dob = $entry.find('input[name^="player_dob"]');
                    var $hasConditions = $entry.find('input[name^="has_medical_conditions"]');
                    var $conditions = $entry.find('textarea[name^="medical_conditions"]');
                    var $nameError = $name.next('.error-message');
                    var $dobError = $dob.next('.error-message');
                    var $conditionsError = $conditions.next('.error-message');

                    // Reset error states
                    $nameError.hide();
                    $dobError.hide();
                    $conditionsError.hide();
                    $name.css('border', '1px solid #ccc');
                    $dob.css('border', '1px solid #ccc');
                    $conditions.css('border', '1px solid #ccc');

                    // Debug values
                    console.log('Name:', $name.val(), 'DOB:', $dob.val(), 'Conditions:', $conditions.val());

                    // Validate name
                    var nameValue = $name.val() ? $name.val().trim() : '';
                    if (!nameValue) {
                        $nameError.show();
                        $name.css('border', '1px solid red');
                        hasErrors = true;
                    }

                    // Validate DOB
                    var dobValue = $dob.val();
                    if (!dobValue) {
                        $dobError.show();
                        $dob.css('border', '1px solid red');
                        hasErrors = true;
                    }

                    // Validate medical conditions
                    if ($hasConditions.is(':checked')) {
                        var conditionsValue = $conditions.val() ? $conditions.val().trim() : '';
                        if (!conditionsValue) {
                            $conditionsError.show();
                            $conditions.css('border', '1px solid red');
                            hasErrors = true;
                        }
                    }
                });

                if (hasErrors) {
                    e.preventDefault();
                    alert('<?php _e('Please fill in all required fields.', 'woocommerce'); ?>');
                } else if ($('#players-list .player-entry').length === 0) {
                    e.preventDefault();
                    alert('<?php _e('Please add at least one player.', 'woocommerce'); ?>');
                }
            });
        });
    </script>
    <?php
}
?>

