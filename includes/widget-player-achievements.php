<?php

/**
 * Widget Player Achievements
 * Changes:
 * - Registered WordPress widget for player achievements.
 * - Added pagination and caching for achievement queries.
 * - Integrated with intersoccer_achievements meta.
 * - Added styling options via widget settings.
 * - Ensured initialization on init to avoid translation issues.
 * Testing:
 * - Add Player Achievements widget to a sidebar, set player name filter, verify achievements display.
 * - Check pagination links, ensure they navigate correctly.
 * - Update background color in widget settings, confirm it applies.
 * - Verify caching reduces database queries.
 * - Ensure no translation loading notices in server logs.
 */

defined('ABSPATH') or die('No script kiddies please!');

add_action('init', function () {
    add_action('widgets_init', function () {
        register_widget('InterSoccer_Player_Achievements_Widget');
    });
});

class InterSoccer_Player_Achievements_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'intersoccer_player_achievements',
            __('InterSoccer Player Achievements', 'intersoccer-player-management'),
            ['description' => __('Displays player achievements.', 'intersoccer-player-management')]
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];
        $title = !empty($instance['title']) ? $instance['title'] : __('Player Achievements', 'intersoccer-player-management');
        $player_name = !empty($instance['player_name']) ? $instance['player_name'] : '';
        $background_color = !empty($instance['background_color']) ? $instance['background_color'] : '#ffffff';
        $per_page = 10;
        $current_page = max(1, absint($_GET['achievements_page'] ?? 1));
        $offset = ($current_page - 1) * $per_page;

        $cache_key = 'intersoccer_achievements_' . md5($player_name . $current_page);
        $achievements = wp_cache_get($cache_key, 'intersoccer');
        if (false === $achievements) {
            $args = [
                'role' => 'customer',
                'meta_key' => 'intersoccer_achievements',
                'meta_compare' => 'EXISTS',
                'number' => $per_page,
                'offset' => $offset,
            ];
            $users = get_users($args);
            $achievements = [];
            foreach ($users as $user) {
                $user_achievements = get_user_meta($user->ID, 'intersoccer_achievements', true);
                if (is_array($user_achievements)) {
                    $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
                    $player_names = array_column($players, 'name');
                    foreach ($user_achievements as $achievement) {
                        if (!in_array($achievement['player_name'], $player_names)) {
                            continue;
                        }
                        if ($player_name && stripos($achievement['player_name'], $player_name) === false) {
                            continue;
                        }
                        $achievements[] = [
                            'player_name' => $achievement['player_name'],
                            'title' => $achievement['title'],
                            'date' => $achievement['date'],
                            'description' => $achievement['description'],
                            'user_email' => $user->user_email,
                        ];
                    }
                }
            }
            wp_cache_set($cache_key, $achievements, 'intersoccer', 3600);
        }

        $total_users = count(get_users(['role' => 'customer', 'meta_key' => 'intersoccer_achievements', 'meta_compare' => 'EXISTS']));
        $total_pages = ceil($total_users / $per_page);

?>
        <style>
            .intersoccer-achievements {
                background-color: <?php echo esc_attr($background_color); ?>;
                padding: 10px;
            }

            .intersoccer-achievements li {
                margin-bottom: 10px;
            }

            .achievements-pagination a,
            .achievements-pagination span {
                margin: 0 5px;
            }
        </style>
        <?php echo $args['before_title'] . esc_html($title) . $args['after_title']; ?>
        <div class="intersoccer-achievements">
            <ul>
                <?php foreach ($achievements as $achievement): ?>
                    <li>
                        <strong><?php echo esc_html($achievement['player_name']); ?>:</strong>
                        <?php echo esc_html($achievement['title']); ?> (<?php echo esc_html($achievement['date']); ?>)
                        <p><?php echo esc_html($achievement['description']); ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($total_pages > 1): ?>
                <div class="achievements-pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg('achievements_page', $current_page - 1)); ?>">« <?php _e('Previous', 'intersoccer-player-management'); ?></a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i === $current_page): ?>
                            <span><?php echo esc_html($i); ?></span>
                        <?php else: ?>
                            <a href="<?php echo esc_url(add_query_arg('achievements_page', $i)); ?>"><?php echo esc_html($i); ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg('achievements_page', $current_page + 1)); ?>"><?php _e('Next', 'intersoccer-player-management'); ?> »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $player_name = !empty($instance['player_name']) ? $instance['player_name'] : '';
        $background_color = !empty($instance['background_color']) ? $instance['background_color'] : '#ffffff';
    ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title:', 'intersoccer-player-management'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('player_name')); ?>"><?php _e('Player Name (optional):', 'intersoccer-player-management'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('player_name')); ?>" name="<?php echo esc_attr($this->get_field_name('player_name')); ?>" type="text" value="<?php echo esc_attr($player_name); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('background_color')); ?>"><?php _e('Background Color:', 'intersoccer-player-management'); ?></label>
            <input class="widefat color-picker" id="<?php echo esc_attr($this->get_field_id('background_color')); ?>" name="<?php echo esc_attr($this->get_field_name('background_color')); ?>" type="text" value="<?php echo esc_attr($background_color); ?>">
        </p>
        <script>
            jQuery(document).ready(function($) {
                $('.color-picker').wpColorPicker();
            });
        </script>
<?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = !empty($new_instance['title']) ? sanitize_text_field($new_instance['title']) : '';
        $instance['player_name'] = !empty($new_instance['player_name']) ? sanitize_text_field($new_instance['player_name']) : '';
        $instance['background_color'] = !empty($new_instance['background_color']) ? sanitize_hex_color($new_instance['background_color']) : '#ffffff';
        return $instance;
    }
}
?>
