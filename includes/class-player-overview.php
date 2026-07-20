<?php
/**
 * File: class-player-overview.php
 * Description: Actionable KPI overview for player management (identity/onboarding funnel).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Player_Management_Overview {
    private $utils;

    public function __construct($utils) {
        $this->utils = $utils;
    }

    /**
     * Render the overview page.
     */
    public function render() {
        $this->utils->log_memory('overview_page_start');

        $cache_key = 'intersoccer_overview_data_v4';
        $cached_data = get_transient($cache_key);

        if ($cached_data && !isset($_GET['refresh'])) {
            $this->utils->log_memory('overview_using_cached_data');
            $overview_data = $cached_data;
        } else {
            $this->utils->log_memory('overview_generating_fresh_data');
            $overview_data = $this->generate_overview_data();

            if (!isset($overview_data['error'])) {
                set_transient($cache_key, $overview_data, 30 * MINUTE_IN_SECONDS);
                $this->utils->log_memory('overview_data_cached');
            }
        }

        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            [],
            '3.9.1',
            true
        );

        $this->render_html($overview_data);
        $this->utils->log_memory('overview_render_complete');
    }

    /**
     * Generate overview KPI payload (v4).
     *
     * @return array<string, mixed>
     */
    private function generate_overview_data() {
        $original_memory_limit = ini_get('memory_limit');
        if (intval($original_memory_limit) < 512) {
            ini_set('memory_limit', '512M');
        }

        $data = function_exists('intersoccer_pm_overview_empty_payload')
            ? intersoccer_pm_overview_empty_payload()
            : [
                'total_players' => 0,
                'users_without_players' => 0,
                'never_booked_lifetime' => 0,
                'incomplete_profiles' => 0,
                'canton_data' => [],
                'generation_time' => '',
                'total_users_processed' => 0,
                'processing_method' => 'batch_v4',
            ];

        $data['generation_time'] = current_time('mysql');
        $data['canton_data'] = [];

        try {
            $this->utils->log_memory('overview_start');

            $user_counts = $this->utils->get_user_counts();

            $users_with_players_query = new WP_User_Query([
                'role__in' => ['customer', 'subscriber'],
                'fields' => 'ID',
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS',
                    ],
                ],
                'count_total' => true,
            ]);

            $total_users_with_players = $users_with_players_query->get_total();
            $data['users_without_players'] = max(0, (int) $user_counts['total'] - (int) $total_users_with_players);

            $this->utils->log_memory('overview_after_user_counts');

            $batch_size = 25;
            $max_batches = 20;
            $processed_batches = 0;
            $offset = 0;

            do {
                $batch_users = get_users([
                    'role__in' => ['customer', 'subscriber'],
                    'number' => $batch_size,
                    'offset' => $offset,
                    'fields' => ['ID'],
                    'meta_query' => [
                        [
                            'key' => 'intersoccer_players',
                            'compare' => 'EXISTS',
                        ],
                    ],
                ]);

                if (empty($batch_users)) {
                    break;
                }

                foreach ($batch_users as $user) {
                    try {
                        $data['total_users_processed']++;

                        $billing_info = $this->utils->get_user_billing_info($user->ID);
                        $players = $this->utils->get_user_players($user->ID);
                        if (empty($players)) {
                            continue;
                        }

                        foreach ($players as $index => $player) {
                            if (!is_array($player)) {
                                continue;
                            }

                            $data['total_players']++;

                            $canton = $billing_info['state'] ?? 'Unknown';
                            if ($canton === '') {
                                $canton = 'Unknown';
                            }
                            if (!isset($data['canton_data'][$canton])) {
                                $data['canton_data'][$canton] = 0;
                            }
                            $data['canton_data'][$canton]++;

                            if (function_exists('intersoccer_pm_player_never_booked_lifetime')
                                && intersoccer_pm_player_never_booked_lifetime($user->ID, $index)
                            ) {
                                $data['never_booked_lifetime']++;
                            }

                            if (function_exists('intersoccer_pm_player_profile_is_incomplete')
                                && intersoccer_pm_player_profile_is_incomplete($player)
                            ) {
                                $data['incomplete_profiles']++;
                            }
                        }
                    } catch (Exception $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('InterSoccer Overview: Error processing user ' . $user->ID . ': ' . $e->getMessage());
                        }
                        continue;
                    }
                }

                $processed_batches++;
                $offset += $batch_size;

                if ($processed_batches >= $max_batches) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('InterSoccer Overview: Reached batch limit for safety');
                    }
                    break;
                }

                $current_memory = memory_get_usage(true);
                if ($current_memory > (300 * 1024 * 1024)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('InterSoccer Overview: Memory limit reached: ' . $this->utils->format_bytes($current_memory));
                    }
                    break;
                }
            } while (count($batch_users) === $batch_size);

            if (function_exists('intersoccer_pm_overview_canton_chart_data')) {
                $data['canton_data'] = intersoccer_pm_overview_canton_chart_data($data['canton_data'], 8);
            } elseif (!empty($data['canton_data'])) {
                arsort($data['canton_data']);
            } else {
                $data['canton_data'] = ['Unknown' => 0];
            }

            $this->utils->log_memory('overview_generation_complete');
        } catch (Exception $e) {
            error_log('InterSoccer Overview Critical Error: ' . $e->getMessage());

            $data = function_exists('intersoccer_pm_overview_empty_payload')
                ? intersoccer_pm_overview_empty_payload()
                : $data;
            $data['generation_time'] = current_time('mysql');
            $data['error'] = $e->getMessage();
            $data['processing_method'] = 'fallback_error';
            $data['canton_data'] = ['Unknown' => 0];
        } finally {
            ini_set('memory_limit', $original_memory_limit);
        }

        return $data;
    }

    /**
     * Render the HTML for the overview page.
     *
     * @param array $data Overview payload.
     */
    private function render_html($data) {
        wp_localize_script('chart-js', 'intersoccerChartLabels', [
            'noData' => __('No Data', 'player-management'),
            'chartLoadingFailed' => __('Chart loading failed', 'player-management'),
            'players' => __('Participants', 'player-management'),
        ]);

        include plugin_dir_path(__FILE__) . 'templates/overview-template.php';
    }
}
