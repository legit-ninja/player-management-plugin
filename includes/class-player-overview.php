<?php
/**
 * File: class-player-overview.php
 * Description: Overview dashboard for player management
 * Author: Jeremy Lee (Refactored by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Player_Management_Overview {
    private $utils;
    
    public function __construct($utils) {
        $this->utils = $utils;
    }
    
    /**
     * Render the overview page
     */
    public function render() {
        $this->utils->log_memory('overview_page_start');
        
        // Check for cached data first (cache for 30 minutes)
        $cache_key = 'intersoccer_overview_data_v3';
        $cached_data = get_transient($cache_key);
        
        if ($cached_data && !isset($_GET['refresh'])) {
            $this->utils->log_memory('overview_using_cached_data');
            $overview_data = $cached_data;
        } else {
            $this->utils->log_memory('overview_generating_fresh_data');
            $overview_data = $this->generate_overview_data();
            
            // Only cache if data generation was successful
            if (!isset($overview_data['error'])) {
                set_transient($cache_key, $overview_data, 30 * MINUTE_IN_SECONDS);
                $this->utils->log_memory('overview_data_cached');
            }
        }
        
        // Load Chart.js with fallback
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
        
        $this->render_html($overview_data);
        
        $this->utils->log_memory('overview_render_complete');
    }
    
    /**
     * Generate overview data with improved error handling
     */
    private function generate_overview_data() {
        global $wpdb;
        
        // Set higher memory limit for this operation
        $original_memory_limit = ini_get('memory_limit');
        if (intval($original_memory_limit) < 512) {
            ini_set('memory_limit', '512M');
        }
        
        // Initialize safe data structure
        $data = [
            'total_players' => 0,
            'users_without_players' => 0,
            'assigned_count' => 0,
            'unassigned_count' => 0,
            'canton_data' => [],
            'gender_data' => ['male' => 0, 'female' => 0, 'other' => 0],
            'top_cantons' => [],
            'generation_time' => current_time('mysql'),
            'total_users_processed' => 0,
            'processing_method' => 'batch_v3'
        ];
        
        try {
            $this->utils->log_memory('overview_start');
            
            // Step 1: Get basic user counts
            $user_counts = $this->utils->get_user_counts();
            
            // Count users with players using optimized query
            $users_with_players_query = new WP_User_Query([
                'role__in' => ['customer', 'subscriber'],
                'fields' => 'ID',
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS'
                    ]
                ],
                'count_total' => true
            ]);
            
            $total_users_with_players = $users_with_players_query->get_total();
            $data['users_without_players'] = $user_counts['total'] - $total_users_with_players;
            
            $this->utils->log_memory('overview_after_user_counts');
            
            // Step 2: Process players in small, safe batches
            $batch_size = 25; // Very small batches for safety
            $max_batches = 20; // Limit total processing
            $processed_batches = 0;
            $offset = 0;
            
            do {
                // Get a small batch of users
                $batch_users = get_users([
                    'role__in' => ['customer', 'subscriber'],
                    'number' => $batch_size,
                    'offset' => $offset,
                    'fields' => ['ID'],
                    'meta_query' => [
                        [
                            'key' => 'intersoccer_players',
                            'compare' => 'EXISTS'
                        ]
                    ]
                ]);
                
                if (empty($batch_users)) {
                    break; // No more users
                }
                
                foreach ($batch_users as $user) {
                    try {
                        $data['total_users_processed']++;
                        
                        // Get billing info
                        $billing_info = $this->utils->get_user_billing_info($user->ID);
                        
                        // Get and validate players
                        $players = $this->utils->get_user_players($user->ID);
                        if (empty($players)) continue;
                        
                        foreach ($players as $player) {
                            $data['total_players']++;
                            
                            // Count by canton
                            $canton = $billing_info['state'];
                            if (!isset($data['canton_data'][$canton])) {
                                $data['canton_data'][$canton] = 0;
                            }
                            $data['canton_data'][$canton]++;
                            
                            // Count by gender with validation
                            $gender = strtolower($player['gender'] ?? 'other');
                            if (in_array($gender, ['male', 'female'])) {
                                $data['gender_data'][$gender]++;
                            } else {
                                $data['gender_data']['other']++;
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
                
                // Safety checks
                if ($processed_batches >= $max_batches) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('InterSoccer Overview: Reached batch limit for safety');
                    }
                    break;
                }
                
                // Memory check
                $current_memory = memory_get_usage(true);
                if ($current_memory > (300 * 1024 * 1024)) { // 300MB limit
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('InterSoccer Overview: Memory limit reached: ' . $this->utils->format_bytes($current_memory));
                    }
                    break;
                }
                
            } while (count($batch_users) === $batch_size);
            
            $this->utils->log_memory('overview_after_player_processing');
            
            // Step 3: Calculate assigned vs unassigned (simplified)
            $data['unassigned_count'] = $data['total_players'];
            $data['assigned_count'] = 0;
            
            // Only calculate if we have reasonable data size
            if ($data['total_players'] < 2000) {
                $assignment_data = $this->calculate_assignment_ratio();
                if ($assignment_data) {
                    $data['assigned_count'] = (int)($data['total_players'] * $assignment_data['ratio']);
                    $data['unassigned_count'] = $data['total_players'] - $data['assigned_count'];
                }
            }
            
            // Step 4: Process canton data
            if (!empty($data['canton_data'])) {
                arsort($data['canton_data']);
                $data['top_cantons'] = array_slice($data['canton_data'], 0, 5, true);
            } else {
                $data['top_cantons'] = ['Unknown' => 0];
            }
            
            $this->utils->log_memory('overview_generation_complete');
            
        } catch (Exception $e) {
            error_log('InterSoccer Overview Critical Error: ' . $e->getMessage());
            
            // Return safe fallback data
            $data = [
                'total_players' => 0,
                'users_without_players' => 0,
                'assigned_count' => 0,
                'unassigned_count' => 0,
                'canton_data' => ['Unknown' => 0],
                'gender_data' => ['male' => 0, 'female' => 0, 'other' => 0],
                'top_cantons' => ['Unknown' => 0],
                'generation_time' => current_time('mysql'),
                'total_users_processed' => 0,
                'error' => $e->getMessage(),
                'processing_method' => 'fallback_error'
            ];
        } finally {
            // Restore memory limit
            ini_set('memory_limit', $original_memory_limit);
        }
        
        return $data;
    }
    
    /**
     * Calculate assignment ratio using sampling
     */
    private function calculate_assignment_ratio() {
        global $wpdb;
        
        try {
            // Check if roster table exists
            $roster_table = $wpdb->prefix . 'intersoccer_rosters';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $roster_table));
            
            if (!$table_exists) {
                return null;
            }
            
            // Get sample of roster players
            $roster_players = $wpdb->get_results(
                "SELECT DISTINCT first_name, last_name FROM {$roster_table} LIMIT 50",
                ARRAY_A
            );
            
            if (empty($roster_players)) {
                return null;
            }
            
            // Create lookup
            $assigned_lookup = [];
            foreach ($roster_players as $roster_player) {
                $key = strtolower(trim($roster_player['first_name'])) . '|' . strtolower(trim($roster_player['last_name']));
                $assigned_lookup[$key] = true;
            }
            
            // Sample players to check assignment
            $sample_users = get_users([
                'role__in' => ['customer', 'subscriber'],
                'number' => 10, // Small sample
                'fields' => ['ID'],
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS'
                    ]
                ]
            ]);
            
            $sample_assigned = 0;
            $sample_total = 0;
            
            foreach ($sample_users as $user) {
                $players = $this->utils->get_user_players($user->ID);
                foreach ($players as $player) {
                    $sample_total++;
                    $first_name = strtolower(trim($player['first_name'] ?? ''));
                    $last_name = strtolower(trim($player['last_name'] ?? ''));
                    $key = $first_name . '|' . $last_name;
                    
                    if (isset($assigned_lookup[$key])) {
                        $sample_assigned++;
                    }
                    
                    if ($sample_total >= 50) break 2; // Limit sample size
                }
            }
            
            if ($sample_total > 0) {
                return ['ratio' => $sample_assigned / $sample_total];
            }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InterSoccer: Assignment calculation error: ' . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Render the HTML for the overview page
     */
    private function render_html($data) {
        // Localize script for chart labels
        wp_localize_script('chart-js', 'intersoccerChartLabels', [
            'assigned' => __('Assigned', 'player-management'),
            'unassigned' => __('Unassigned', 'player-management'),
            'male' => __('Male', 'player-management'),
            'female' => __('Female', 'player-management'),
            'other' => __('Other', 'player-management'),
            'noData' => __('No Data', 'player-management'),
            'chartLoadingFailed' => __('Chart loading failed', 'player-management'),
        ]);

        // Include the HTML rendering
        include plugin_dir_path(__FILE__) . 'templates/overview-template.php';
    }
}