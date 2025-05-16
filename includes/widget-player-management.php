<?php
/**
 * Player Management Widget
 * Changes:
 * - Created a new widget for managing players, replicating shortcode functionality.
 * - Added form to add players with fields: name, DOB, gender, region, medical conditions.
 * - Included table to list players with edit/delete options via AJAX.
 * - Ensured accessibility with ARIA attributes.
 * - Integrated with My Account page via Elementor or sidebar.
 * Testing:
 * - Add widget to My Account page via Elementor or sidebar, verify form and table render.
 * - Add a player with region (e.g., Geneva), confirm data saves to intersoccer_players meta.
 * - Test DOB validation, ensure invalid formats are rejected.
 * - Add duplicate player name, confirm error message.
 * - Check loading spinner during AJAX requests.
 * - Verify ARIA attributes with a screen reader (e.g., NVDA).
 * - Ensure widget works on standalone pages and My Account endpoint.
 * - Verify no translation loading notices in server logs.
 */

defined('ABSPATH') or die('No script kiddies please!');

class InterSoccer_Player_Management_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'intersoccer_player_management_widget',
            __('InterSoccer Player Management', 'intersoccer-player-management'),
            array('description' => __('A widget to manage players for InterSoccer.', 'intersoccer-player-management'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to manage your players.', 'intersoccer-player-management') . '</p>';
            echo $args['after_widget'];
            return;
        }

        wp_enqueue_script('intersoccer-player-management', plugin_dir_url(__FILE__) . '../js/player-management.js', ['jquery'], '1.8', true);
        wp_localize_script('intersoccer-player-management', 'intersoccerPlayer', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_player_management'),
        ]);
        wp_enqueue_style('intersoccer-player-management', plugin_dir_url(__FILE__) . '../css/styles.css', [], '1.2');

        $user_id = get_current_user_id();
        $cache_key = 'intersoccer_players_' . $user_id;
        $players = wp_cache_get($cache_key, 'intersoccer');
        if (false === $players) {
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
            wp_cache_set($cache_key, $players, 'intersoccer', 3600);
        }

        $regions = ['Geneva', 'Zurich', 'Basel', 'Lausanne', 'Zug', 'Nyon']; // From FINAL Summer Camps Numbers 2024.xlsx

        ?>
        <div class="intersoccer-player-management" role="region" aria-label="<?php _e('Player Management Widget', 'intersoccer-player-management'); ?>">
            <form id="add-player-form" aria-describedby="form-instructions">
                <p id="form-instructions" class="screen-reader-text"><?php _e('Fill out the form to add a new player.', 'intersoccer-player-management'); ?></p>
                <p>
                    <label for="player_name_<?php echo esc_attr($this->id); ?>"><?php _e('Player Name:', 'intersoccer-player-management'); ?></label>
                    <input type="text" id="player_name_<?php echo esc_attr($this->id); ?>" name="player_name" required aria-required="true">
                </p>
                <p>
                    <label for="player_dob_<?php echo esc_attr($this->id); ?>"><?php _e('Date of Birth (YYYY-MM-DD):', 'intersoccer-player-management'); ?></label>
                    <input type="text" id="player_dob_<?php echo esc_attr($this->id); ?>" name="player_dob" pattern="\d{4}-\d{2}-\d{2}" placeholder="YYYY-MM-DD" required aria-required="true">
                </p>
                <p>
                    <label for="player_gender_<?php echo esc_attr($this->id); ?>"><?php _e('Gender:', 'intersoccer-player-management'); ?></label>
                    <select id="player_gender_<?php echo esc_attr($this->id); ?>" name="player_gender" required aria-required="true">
                        <option value="male"><?php _e('Male', 'intersoccer-player-management'); ?></option>
                        <option value="female"><?php _e('Female', 'intersoccer-player-management'); ?></option>
                        <option value="other"><?php _e('Other', 'intersoccer-player-management'); ?></option>
                    </select>
                </p>
                <p>
                    <label for="player_region_<?php echo esc_attr($this->id); ?>"><?php _e('Region:', 'intersoccer-player-management'); ?></label>
                    <select id="player_region_<?php echo esc_attr($this->id); ?>" name="player_region" required aria-required="true">
                        <option value=""><?php _e('Select Region', 'intersoccer-player-management'); ?></option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo esc_attr($region); ?>"><?php echo esc_html($region); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="player_medical_<?php echo esc_attr($this->id); ?>"><?php _e('Medical Conditions:', 'intersoccer-player-management'); ?></label>
                    <textarea id="player_medical_<?php echo esc_attr($this->id); ?>" name="player_medical" aria-describedby="medical-instructions"></textarea>
                    <span id="medical-instructions" class="screen-reader-text"><?php _e('Optional field for medical conditions.', 'intersoccer-player-management'); ?></span>
                </p>
                <p>
                    <button type="submit" class="button" aria-label="<?php _e('Add Player', 'intersoccer-player-management'); ?>">
                        <?php _e('Add Player', 'intersoccer-player-management'); ?>
                        <span class="spinner" style="display: none;"></span>
                    </button>
                </p>
            </form>
            <h3><?php _e('Your Players', 'intersoccer-player-management'); ?></h3>
            <table class="wp-list-table widefat fixed striped" role="grid" aria-label="<?php _e('List of Players', 'intersoccer-player-management'); ?>">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Name', 'intersoccer-player-management'); ?></th>
                        <th scope="col"><?php _e('DOB', 'intersoccer-player-management'); ?></th>
                        <th scope="col"><?php _e('Gender', 'intersoccer-player-management'); ?></th>
                        <th scope="col"><?php _e('Region', 'intersoccer-player-management'); ?></th>
                        <th scope="col"><?php _e('Medical Conditions', 'intersoccer-player-management'); ?></th>
                        <th scope="col"><?php _e('Actions', 'intersoccer-player-management'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($players)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No players added yet.', 'intersoccer-player-management'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($players as $index => $player): ?>
                            <tr data-player-index="<?php echo esc_attr($index); ?>">
                                <td><?php echo esc_html($player['name']); ?></td>
                                <td><?php echo esc_html($player['dob'] ?? 'N/A'); ?></td>
                                <td><?php echo esc_html($player['gender'] ?? 'N/A'); ?></td>
                                <td><?php echo esc_html($player['region'] ?? 'N/A'); ?></td>
                                <td><?php echo esc_html($player['medical_conditions'] ?? 'None'); ?></td>
                                <td>
                                    <button class="button edit-player" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php _e('Edit player', 'intersoccer-player-management'); ?> <?php echo esc_attr($player['name']); ?>"><?php _e('Edit', 'intersoccer-player-management'); ?></button>
                                    <button class="button delete-player" data-index="<?php echo esc_attr($index); ?>" aria-label="<?php _e('Delete player', 'intersoccer-player-management'); ?> <?php echo esc_attr($player['name']); ?>"><?php _e('Delete', 'intersoccer-player-management'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'intersoccer-player-management'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

// Register the widget
add_action('widgets_init', function() {
    register_widget('InterSoccer_Player_Management_Widget');
});
?>
