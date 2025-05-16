<?php

/**
 * Player Trading Cards
 * Changes:
 * - Added shortcode to display player trading cards with achievements.
 * - Integrated with intersoccer_achievements meta.
 * - Added caching for achievement queries.
 * - Used PDF generation with dompdf for downloadable cards.
 * - Ensured initialization on init to avoid translation issues.
 * Testing:
 * - Add [intersoccer_trading_cards] shortcode to a page, verify cards display for logged-in user’s players.
 * - Check achievement data from intersoccer_achievements meta.
 * - Download a PDF card, confirm it contains player name and achievements.
 * - Verify caching reduces database queries.
 * - Ensure no translation loading notices in server logs.
 */

defined('ABSPATH') or die('No script kiddies please!');

add_action('init', function () {
    add_shortcode('intersoccer_trading_cards', 'intersoccer_trading_cards_shortcode');
});

function intersoccer_trading_cards_shortcode()
{
    if (!is_user_logged_in()) {
        return '<p>' . esc_html__('Please log in to view your trading cards.', 'intersoccer-player-management') . '</p>';
    }

    $user_id = get_current_user_id();
    $cache_key = 'intersoccer_trading_cards_' . $user_id;
    $data = wp_cache_get($cache_key, 'intersoccer');
    if (false === $data) {
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        $achievements = get_user_meta($user_id, 'intersoccer_achievements', true) ?: [];
        $data = ['players' => $players, 'achievements' => $achievements];
        wp_cache_set($cache_key, $data, 'intersoccer', 3600);
    }

    ob_start();
?>
    <div class="intersoccer-trading-cards">
        <h2><?php _e('Your Player Trading Cards', 'intersoccer-player-management'); ?></h2>
        <?php foreach ($data['players'] as $index => $player): ?>
            <div class="trading-card" data-player-index="<?php echo esc_attr($index); ?>">
                <h3><?php echo esc_html($player['name']); ?></h3>
                <p><strong><?php _e('DOB:', 'intersoccer-player-management'); ?></strong> <?php echo esc_html($player['dob'] ?? 'N/A'); ?></p>
                <p><strong><?php _e('Achievements:', 'intersoccer-player-management'); ?></strong></p>
                <ul>
                    <?php foreach ($data['achievements'] as $achievement): ?>
                        <?php if ($achievement['player_name'] === $player['name']): ?>
                            <li><?php echo esc_html($achievement['title']) . ' (' . esc_html($achievement['date']) . ')'; ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url(add_query_arg(['download_card' => $index, 'nonce' => wp_create_nonce('intersoccer_download_card')])); ?>" class="button"><?php _e('Download Card', 'intersoccer-player-management'); ?></a>
            </div>
        <?php endforeach; ?>
    </div>
<?php

    if (isset($_GET['download_card']) && wp_verify_nonce($_GET['nonce'], 'intersoccer_download_card')) {
        $index = absint($_GET['download_card']);
        if (isset($data['players'][$index])) {
            intersoccer_generate_trading_card_pdf($data['players'][$index], $data['achievements']);
        }
    }

    return ob_get_clean();
}

function intersoccer_generate_trading_card_pdf($player, $achievements)
{
    require_once plugin_dir_path(__FILE__) . '../vendor/autoload.php';
    $dompdf = new Dompdf\Dompdf();
    $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .card { border: 1px solid #000; padding: 20px; width: 300px; }
                h1 { font-size: 24px; }
                p { font-size: 16px; }
                ul { list-style-type: none; padding: 0; }
                li { margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>' . esc_html($player['name']) . '</h1>
                <p><strong>DOB:</strong> ' . esc_html($player['dob'] ?? 'N/A') . '</p>
                <p><strong>Achievements:</strong></p>
                <ul>
                    ' . implode('', array_map(function ($achievement) use ($player) {
        return $achievement['player_name'] === $player['name'] ? '<li>' . esc_html($achievement['title']) . ' (' . esc_html($achievement['date']) . ')</li>' : '';
    }, $achievements)) . '
                </ul>
            </div>
        </body>
        </html>
    ';
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('trading-card-' . sanitize_title($player['name']) . '.pdf', ['Attachment' => true]);
    exit;
}
?>
