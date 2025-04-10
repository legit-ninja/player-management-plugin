<?php
/**
 * Player Achievements Widget
 */
class InterSoccer_Player_Achievements_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'intersoccer_player_achievements_widget',
            __('InterSoccer Player Achievements', 'intersoccer-player-management'),
            array('description' => __('Displays player achievements and milestones.', 'intersoccer-player-management'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $player_name = !empty($instance['player_name']) ? $instance['player_name'] : '';

        // Fetch all customer users
        $users = get_users(array(
            'role' => 'customer',
            'number' => -1,
        ));

        $achievements = array();
        foreach ($users as $user) {
            $user_achievements = get_user_meta($user->ID, 'intersoccer_achievements', true);
            if (is_array($user_achievements) && !empty($user_achievements)) {
                foreach ($user_achievements as $achievement) {
                    if ($player_name && stripos($achievement['player_name'], $player_name) === false) {
                        continue;
                    }
                    $achievements[] = array(
                        'player_name' => $achievement['player_name'],
                        'title' => $achievement['title'],
                        'date' => $achievement['date'],
                        'description' => $achievement['description'],
                        'user_email' => $user->user_email,
                    );
                }
            }
        }

        if (empty($achievements)) {
            echo '<p>' . __('No achievements found.', 'intersoccer-player-management') . '</p>';
        } else {
            echo '<ul class="intersoccer-achievements">';
            foreach ($achievements as $achievement) {
                echo '<li>';
                echo '<strong>' . esc_html($achievement['player_name']) . ' (' . esc_html($achievement['user_email']) . ')</strong><br>';
                echo esc_html($achievement['title']) . ' - ' . esc_html($achievement['date']) . '<br>';
                echo esc_html($achievement['description']);
                echo '</li>';
            }
            echo '</ul>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $player_name = !empty($instance['player_name']) ? $instance['player_name'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'intersoccer-player-management'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('player_name')); ?>"><?php _e('Player Name (optional):', 'intersoccer-player-management'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('player_name')); ?>" name="<?php echo esc_attr($this->get_field_name('player_name')); ?>" type="text" value="<?php echo esc_attr($player_name); ?>">
            <small><?php _e('Leave blank to show achievements for all players.', 'intersoccer-player-management'); ?></small>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['player_name'] = !empty($new_instance['player_name']) ? sanitize_text_field($new_instance['player_name']) : '';
        return $instance;
    }
}
?>

