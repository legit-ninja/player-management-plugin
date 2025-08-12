<?php
/**
 * File: class-player-utils.php
 * Description: Utility functions for player management
 * Author: Jeremy Lee (Refactored by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Player_Management_Utils {
    
    /**
     * Log memory usage with checkpoint name
     */
    public function log_memory($checkpoint) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $memory_usage = memory_get_usage(true);
            $memory_peak = memory_get_peak_usage(true);
            error_log("INTERSOCCER_MEMORY: $checkpoint - Current: " . $this->format_bytes($memory_usage) . ", Peak: " . $this->format_bytes($memory_peak));
        }
    }
    
    /**
     * Format bytes into human readable format
     */
    public function format_bytes($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Count matching players for search functionality
     */
    public function count_matching_players($search_term) {
        $all_users = get_users([
            'role__in' => ['customer', 'subscriber'],
            'fields' => ['ID'],
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ],
            'number' => 1000 // Limit for performance
        ]);
        
        $matching_count = 0;
        foreach ($all_users as $user) {
            $players = get_user_meta($user->ID, 'intersoccer_players', true) ?: [];
            if (!is_array($players)) continue;
            
            foreach ($players as $player) {
                if (!is_array($player)) continue;
                
                $full_name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
                $user_email = get_user_by('ID', $user->ID)->user_email ?? '';
                $avs_number = $player['avs_number'] ?? '';
                
                $search_matches = (
                    stripos($full_name, $search_term) !== false ||
                    stripos($user_email, $search_term) !== false ||
                    stripos($avs_number, $search_term) !== false
                );
                
                if ($search_matches) {
                    $matching_count++;
                }
            }
        }
        
        return $matching_count;
    }
    
    /**
     * Check if a date is in the past
     */
    public function is_date_past($end_date, $today) {
        try {
            $end = DateTime::createFromFormat('d/m/Y', $end_date);
            $today_date = DateTime::createFromFormat('Y-m-d H:i:s', $today);
            return $end && $today_date && $end < $today_date;
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer: Date comparison error - " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Validate player data structure
     */
    public function validate_player_data($players) {
        if (!is_array($players)) {
            return false;
        }
        
        foreach ($players as $player) {
            if (!is_array($player)) {
                return false;
            }
            // Basic validation - ensure required fields exist
            if (!isset($player['first_name']) || !isset($player['last_name'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Safely get user billing info
     */
    public function get_user_billing_info($user_id) {
        return [
            'state' => get_user_meta($user_id, 'billing_state', true) ?: 'Unknown',
            'city' => get_user_meta($user_id, 'billing_city', true) ?: '',
            'country' => get_user_meta($user_id, 'billing_country', true) ?: ''
        ];
    }
    
    /**
     * Get user counts efficiently
     */
    public function get_user_counts() {
        $user_counts = count_users();
        return [
            'customers' => $user_counts['avail_roles']['customer'] ?? 0,
            'subscribers' => $user_counts['avail_roles']['subscriber'] ?? 0,
            'total' => ($user_counts['avail_roles']['customer'] ?? 0) + ($user_counts['avail_roles']['subscriber'] ?? 0)
        ];
    }
    
    /**
     * Clear all plugin caches
     */
    public function clear_all_caches() {
        delete_transient('intersoccer_overview_data');
        delete_transient('intersoccer_overview_data_v2');
        delete_transient('intersoccer_player_counts');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('InterSoccer: All caches cleared');
        }
    }
    
    /**
     * Get players for a specific user with validation
     */
    public function get_user_players($user_id) {
        $players = get_user_meta($user_id, 'intersoccer_players', true) ?: [];
        
        if (!$this->validate_player_data($players)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("InterSoccer: Invalid player data for user {$user_id}");
            }
            return [];
        }
        
        return $players;
    }
    
    /**
     * Safely process player data with error handling
     */
    public function process_player_batch($users, $search_term = '', $per_page = 50) {
        $processed_players = [];
        $player_count = 0;
        $today = current_time('mysql');
        
        foreach ($users as $user) {
            if ($player_count >= $per_page) {
                break; // Stop when we have enough players
            }
            
            try {
                $players = $this->get_user_players($user->ID);
                if (empty($players)) continue;
                
                $billing_info = $this->get_user_billing_info($user->ID);
                
                foreach ($players as $index => $player) {
                    if ($player_count >= $per_page) {
                        break 2; // Break out of both loops
                    }
                    
                    // Apply search filter if needed
                    if (!empty($search_term)) {
                        if (!$this->player_matches_search($player, $user->user_email, $search_term)) {
                            continue;
                        }
                    }
                    
                    // Enhance player data
                    $enhanced_player = $this->enhance_player_data($player, $user, $billing_info, $index, $today);
                    $processed_players[] = $enhanced_player;
                    $player_count++;
                }
                
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("InterSoccer: Error processing user {$user->ID}: " . $e->getMessage());
                }
                continue; // Skip this user and continue
            }
        }
        
        return $processed_players;
    }
    
    /**
     * Check if player matches search term
     */
    private function player_matches_search($player, $user_email, $search_term) {
        $full_name = ($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '');
        $avs_number = $player['avs_number'] ?? '';
        
        return (
            stripos($full_name, $search_term) !== false ||
            stripos($user_email, $search_term) !== false ||
            stripos($avs_number, $search_term) !== false
        );
    }
    
    /**
     * Enhance player data with additional information
     */
    private function enhance_player_data($player, $user, $billing_info, $index, $today) {
        $enhanced = $player;
        $enhanced['user_id'] = $user->ID;
        $enhanced['user_email'] = $user->user_email;
        $enhanced['index'] = $index;
        $enhanced['canton'] = $billing_info['state'];
        $enhanced['city'] = $billing_info['city'];
        $enhanced['event_count'] = 0; // Will be calculated later if needed
        $enhanced['past_events'] = [];
        $enhanced['event_types'] = [];
        
        // Calculate age if DOB is available
        $dob = $player['dob'] ?? '';
        if ($dob) {
            try {
                $age = date_diff(date_create($dob), date_create($today))->y;
                if ($age >= 3 && $age <= 14) {
                    $enhanced['age'] = $age;
                    $enhanced['event_age_groups'] = ["Age $age"];
                } else {
                    $enhanced['age'] = $age;
                    $enhanced['event_age_groups'] = [];
                }
            } catch (Exception $e) {
                $enhanced['age'] = null;
                $enhanced['event_age_groups'] = [];
            }
        }
        
        return $enhanced;
    }
    
    /**
     * Get pagination info for display
     */
    public function get_pagination_info($current_page, $total_pages, $players_on_page, $total_items, $search_term = '') {
        $info = [
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'players_on_page' => $players_on_page,
            'total_items' => $total_items,
            'search_term' => $search_term,
            'has_search' => !empty($search_term)
        ];
        
        return $info;
    }
}