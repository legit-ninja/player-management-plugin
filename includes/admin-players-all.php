<?php
/**
 * Admin Feature: All Players Tab for Managing Player Data
 */

// Handle actions on admin_init
add_action('admin_init', 'intersoccer_players_all_tab_init');
function intersoccer_players_all_tab_init() {
    // Handle player deletion
    if (isset($_POST['delete_player']) && !empty($_POST['delete_player_nonce']) && wp_verify_nonce($_POST['delete_player_nonce'], 'delete_player_action')) {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $player_index = isset($_POST['player_index']) ? absint($_POST['player_index']) : -1;

        if ($user_id && $player_index >= 0) {
            $players = get_user_meta($user_id, 'intersoccer_players', true) ?: array();
            if (isset($players[$player_index])) {
                unset($players[$player_index]);
                $players = array_values($players); // Reindex array
                update_user_meta($user_id, 'intersoccer_players', $players);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Player deleted successfully.', 'intersoccer-player-management') . '</p></div>';
                });
            }
        }
    }
}

// Render the All Players tab content
function intersoccer_render_players_all_tab() {
    // Pagination and filtering parameters
    $players_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $players_per_page;

    // Filter parameters
    $filter_email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
    $filter_player_name = isset($_GET['player_name']) ? sanitize_text_field($_GET['player_name']) : '';
    $filter_age_range = isset($_GET['age_range']) ? sanitize_text_field($_GET['age_range']) : '';

    // Fetch all users with the 'customer' role
    $args = array(
        'role' => 'customer',
        'number' => -1, // Fetch all users to filter and paginate
    );
    $users = get_users($args);

    // Collect all players with filtering
    $all_players = array();
    foreach ($users as $user) {
        $players = get_user_meta($user->ID, 'intersoccer_players', true);

        // Ensure $players is an array
        if (!is_array($players)) {
            $players = array();
        }

        foreach ($players as $index => $player) {
            // Ensure $player is an array and has required fields
            if (!is_array($player) || !isset($player['name']) || !isset($player['dob'])) {
                continue;
            }

            $player['user_id'] = $user->ID;
            $player['user_email'] = $user->user_email;
            $player['index'] = $index;

            // Calculate age for filtering
            $dob = $player['dob'];
            $age = $dob ? date_diff(date_create($dob), date_create('today'))->y : 0;
            $player['age'] = $age;

            // Apply filters
            $matches_email = empty($filter_email) || stripos($user->user_email, $filter_email) !== false;
            $matches_player_name = empty($filter_player_name) || stripos($player['name'], $filter_player_name) !== false;
            $matches_age_range = true;
            if (!empty($filter_age_range)) {
                list($min_age, $max_age) = explode('-', $filter_age_range);
                $min_age = (int) $min_age;
                $max_age = (int) $max_age;
                $matches_age_range = $age >= $min_age && $age <= $max_age;
            }

            if ($matches_email && $matches_player_name && $matches_age_range) {
                $all_players[] = $player;
            }
        }
    }

    // Calculate pagination
    $total_players = count($all_players);
    $total_pages = ceil($total_players / $players_per_page);

    // Slice the players array for the current page
    $displayed_players = array_slice($all_players, $offset, $players_per_page);
    ?>

    <!-- All Players -->
    <h2><?php _e('All Players', 'intersoccer-player-management'); ?></h2>

    <!-- Filters -->
    <form method="get" action="">
        <input type="hidden" name="page" value="intersoccer-players" />
        <input type="hidden" name="tab" value="players" />
        <p class="search-box">
            <label for="email"><?php _e('User Email:', 'intersoccer-player-management'); ?></label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($filter_email); ?>" placeholder="<?php _e('Enter user email', 'intersoccer-player-management'); ?>" />

            <label for="player_name"><?php _e('Player Name:', 'intersoccer-player-management'); ?></label>
            <input type="text" id="player_name" name="player_name" value="<?php echo esc_attr($filter_player_name); ?>" placeholder="<?php _e('Enter player name', 'intersoccer-player-management'); ?>" />

            <label for="age_range"><?php _e('Age Range:', 'intersoccer-player-management'); ?></label>
            <select id="age_range" name="age_range">
                <option value=""><?php _e('All Ages', 'intersoccer-player-management'); ?></option>
                <option value="3-5" <?php selected($filter_age_range, '3-5'); ?>><?php _e('3-5 (Mini-Half Day)', 'intersoccer-player-management'); ?></option>
                <option value="5-13" <?php selected($filter_age_range, '5-13'); ?>><?php _e('5-13 (Full Day)', 'intersoccer-player-management'); ?></option>
            </select>

            <input type="submit" class="button" value="<?php _e('Filter', 'intersoccer-player-management'); ?>" />
            <a href="?page=intersoccer-players&tab=players" class="button"><?php _e('Clear Filters', 'intersoccer-player-management'); ?></a>
        </p>
    </form>

    <?php if (empty($users)): ?>
        <p><?php _e('No users with the "customer" role found. Please ensure users are assigned the "customer" role.', 'intersoccer-player-management'); ?></p>
    <?php elseif (empty($all_players)): ?>
        <p><?php _e('No players found in user meta. Please ensure users have players added in their "intersoccer_players" meta. You can add players via user profiles or a custom form.', 'intersoccer-player-management'); ?></p>
    <?php else: ?>
        <!-- Pagination Info -->
        <p>
            <?php
            $start = $offset + 1;
            $end = min($offset + $players_per_page, $total_players);
            printf(__('Showing %d-%d of %d players', 'intersoccer-player-management'), $start, $end, $total_players);
            ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('User Email', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Player Name', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Date of Birth', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Age', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Medical Conditions', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Consent File', 'intersoccer-player-management'); ?></th>
                    <th><?php _e('Actions', 'intersoccer-player-management'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($displayed_players as $player): ?>
                    <tr>
                        <td><?php echo esc_html($player['user_email']); ?></td>
                        <td><?php echo esc_html($player['name']); ?></td>
                        <td><?php echo esc_html($player['dob']); ?></td>
                        <td><?php echo esc_html($player['age']); ?></td>
                        <td><?php echo esc_html($player['medical_conditions']); ?></td>
                        <td>
                            <?php if (!empty($player['consent_file'])): ?>
                                <a href="<?php echo esc_url($player['consent_file']); ?>" target="_blank"><?php _e('View File', 'intersoccer-player-management'); ?></a>
                            <?php else: ?>
                                <?php _e('N/A', 'intersoccer-player-management'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="" onsubmit="return confirm('<?php _e('Are you sure you want to delete this player?', 'intersoccer-player-management'); ?>');">
                                <?php wp_nonce_field('delete_player_action', 'delete_player_nonce'); ?>
                                <input type="hidden" name="user_id" value="<?php echo esc_attr($player['user_id']); ?>" />
                                <input type="hidden" name="player_index" value="<?php echo esc_attr($player['index']); ?>" />
                                <button type="submit" name="delete_player" class="button"><?php _e('Delete', 'intersoccer-player-management'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Links -->
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                $base_url = add_query_arg(array(
                    'page' => 'intersoccer-players',
                    'tab' => 'players',
                    'email' => urlencode($filter_email),
                    'player_name' => urlencode($filter_player_name),
                    'age_range' => urlencode($filter_age_range),
                ), admin_url('admin.php'));

                // Previous page link
                if ($current_page > 1) {
                    $prev_page = $current_page - 1;
                    echo '<a class="prev-page" href="' . esc_url(add_query_arg('paged', $prev_page, $base_url)) . '">« ' . __('Previous', 'intersoccer-player-management') . '</a> ';
                }

                // Page numbers
                for ($i = 1; $i <= $total_pages; $i++) {
                    if ($i == $current_page) {
                        echo '<span class="current-page">' . esc_html($i) . '</span> ';
                    } else {
                        echo '<a class="page-number" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html($i) . '</a> ';
                    }
                }

                // Next page link
                if ($current_page < $total_pages) {
                    $next_page = $current_page + 1;
                    echo '<a class="next-page" href="' . esc_url(add_query_arg('paged', $next_page, $base_url)) . '">' . __('Next', 'intersoccer-player-management') . ' »</a>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

    <style>
        .search-box {
            margin-bottom: 20px;
        }
        .search-box label {
            margin-right: 10px;
        }
        .search-box input[type="email"],
        .search-box input[type="text"],
        .search-box select {
            margin-right: 10px;
            padding: 5px;
        }
        .tablenav {
            margin-top: 10px;
        }
        .tablenav .tablenav-pages a,
        .tablenav .tablenav-pages span {
            margin: 0 5px;
        }
        .tablenav .tablenav-pages .current-page {
            font-weight: bold;
        }
    </style>
    <?php
}
?>
