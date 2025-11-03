<?php
/**
 * File: class-player-list.php
 * Description: Player list management for admin interface
 * Author: Jeremy Lee (Refactored by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Player_Management_List {
    private $utils;
    
    public function __construct($utils) {
        $this->utils = $utils;
    }
    
    /**
     * Render the all players page
     */
    public function render() {
        // Temporarily suppress warnings for production
        $old_error_reporting = error_reporting();
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            error_reporting(E_ERROR | E_PARSE);
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'player-management'));
        }

        $this->utils->log_memory('render_all_players_page_start');

        // Get pagination parameters
        $current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = 50; // Show 50 players per page
        $offset = ($current_page - 1) * $per_page;
        
        // Get search parameter
        $search_term = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("InterSoccer: Rendering players page $current_page, offset $offset, search: '$search_term'");
        }

        // Get user counts for display
        $user_counts = $this->utils->get_user_counts();
        
        $users_with_players = count(get_users([
            'role__in' => ['customer', 'subscriber'],
            'fields' => 'ID',
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ]
        ]));
        
        $users_without_players = $user_counts['total'] - $users_with_players;

        $this->utils->log_memory('after_user_counts');

        // Load users in paginated batches
        $user_query_args = [
            'role__in' => ['customer', 'subscriber'],
            'number' => $per_page,
            'offset' => $offset,
            'fields' => ['ID', 'user_email'],
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        // Handle search
        if (!empty($search_term)) {
            // Load more users when searching since we'll filter players
            $user_query_args['number'] = $per_page * 3;
            $user_query_args['offset'] = max(0, ($current_page - 1) * $per_page * 3);
        }

        $users = get_users($user_query_args);
        
        $this->utils->log_memory('after_paginated_users_loaded');

        // Calculate pagination
        if (empty($search_term)) {
            $total_users_with_players = $users_with_players;
            $total_pages = ceil($total_users_with_players / $per_page);
        } else {
            $search_total = $this->utils->count_matching_players($search_term);
            $total_pages = ceil($search_total / $per_page);
            $total_users_with_players = $search_total;
        }

        // Process players for this page
        $all_players = $this->utils->process_player_batch($users, $search_term, $per_page);
        
        // Calculate page stats
        $page_stats = $this->calculate_page_stats($all_players);
        
        $this->utils->log_memory('data_processing_complete');

        // Enqueue scripts and styles
        wp_enqueue_script('jquery');
        $this->add_responsive_styles();
        
        // Get pagination info
        $pagination_info = $this->utils->get_pagination_info(
            $current_page, 
            $total_pages, 
            count($all_players), 
            $total_users_with_players, 
            $search_term
        );
        
        // Render the HTML
        $this->render_html($all_players, $pagination_info, $page_stats, $users_without_players);
        
        $this->utils->log_memory('render_complete');
        error_reporting($old_error_reporting);
    }
    
    /**
     * Calculate statistics for current page
     */
    private function calculate_page_stats($players) {
        $stats = [
            'total' => count($players),
            'male' => 0,
            'female' => 0,
            'other' => 0
        ];
        
        foreach ($players as $player) {
            $gender = strtolower($player['gender'] ?? 'other');
            if ($gender === 'male') {
                $stats['male']++;
            } elseif ($gender === 'female') {
                $stats['female']++;
            } else {
                $stats['other']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Render the HTML for the players list page
     */
    private function render_html($all_players, $pagination_info, $page_stats, $users_without_players) {
        ?>
        <div class="wrap">
            <h1><?php _e('All Players', 'player-management'); ?></h1>

            <!-- Search Form -->
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="intersoccer-players-all">
                <div class="search-container">
                    <input type="text" name="search" value="<?php echo esc_attr($pagination_info['search_term']); ?>" 
                        placeholder="<?php _e('Search by name, email, or AVS number...', 'player-management'); ?>"
                        style="width: 300px;">
                    <input type="submit" class="button button-primary" value="<?php _e('Search', 'player-management'); ?>">
                    <?php if (!empty($pagination_info['search_term'])): ?>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-players-all'); ?>" class="button">
                            <?php _e('Clear Search', 'player-management'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Pagination Info -->
            <div class="pagination-info">
                <strong><?php _e('Showing:', 'player-management'); ?></strong>
                <?php printf(
                    __('Page %d of %d | %d players on this page | %d total items | %d users without players', 'player-management'),
                    $pagination_info['current_page'],
                    $pagination_info['total_pages'],
                    $pagination_info['players_on_page'],
                    $pagination_info['total_items'],
                    $users_without_players
                ); ?>
                <?php if ($pagination_info['has_search']): ?>
                    <br><strong><?php _e('Search results for:', 'player-management'); ?></strong> "<?php echo esc_html($pagination_info['search_term']); ?>"
                <?php endif; ?>
            </div>

            <!-- Quick Stats for Current Page -->
            <div class="dashboard-section quick-stats">
                <div>
                    <h3><?php _e('Players on Page', 'player-management'); ?></h3>
                    <p><?php echo esc_html($page_stats['total']); ?></p>
                </div>
                <div>
                    <h3><?php _e('Male (Page)', 'player-management'); ?></h3>
                    <p><?php echo esc_html($page_stats['male']); ?></p>
                </div>
                <div>
                    <h3><?php _e('Female (Page)', 'player-management'); ?></h3>
                    <p><?php echo esc_html($page_stats['female']); ?></p>
                </div>
                <div>
                    <h3><?php _e('Total Pages', 'player-management'); ?></h3>
                    <p><?php echo esc_html($pagination_info['total_pages']); ?></p>
                </div>
            </div>

            <!-- Pagination Links -->
            <?php $this->render_pagination_links($pagination_info); ?>

            <!-- Players Table -->
            <div class="intersoccer-player-management">
                <table id="player-table" class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('User ID', 'player-management'); ?></th>
                            <th><?php _e('Canton', 'player-management'); ?></th>
                            <th><?php _e('City', 'player-management'); ?></th>
                            <th><?php _e('First Name', 'player-management'); ?></th>
                            <th><?php _e('Last Name', 'player-management'); ?></th>
                            <th><?php _e('DOB', 'player-management'); ?></th>
                            <th><?php _e('Gender', 'player-management'); ?></th>
                            <th><?php _e('AVS Number', 'player-management'); ?></th>
                            <th><?php _e('Age', 'player-management'); ?></th>
                            <th><?php _e('Medical Conditions', 'player-management'); ?></th>
                            <th><?php _e('Creation Date', 'player-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_players)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px;">
                                    <?php if ($pagination_info['has_search']): ?>
                                        <?php _e('No players found matching your search.', 'player-management'); ?>
                                    <?php else: ?>
                                        <?php _e('No players found on this page.', 'player-management'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_players as $player): ?>
                                <tr data-player-index="<?php echo esc_attr($player['index']); ?>" 
                                    data-user-id="<?php echo esc_attr($player['user_id']); ?>">
                                    <td class="display-user-id" data-label="User ID">
                                        <a href="/wp-admin/user-edit.php?user_id=<?php echo esc_attr($player['user_id']); ?>">
                                            <?php echo esc_html($player['user_id']); ?>
                                        </a>
                                    </td>
                                    <td class="display-canton" data-label="Canton"><?php echo esc_html($player['canton']); ?></td>
                                    <td class="display-city" data-label="City"><?php echo esc_html($player['city']); ?></td>
                                    <td class="display-first-name" data-label="First Name"><?php echo esc_html($player['first_name'] ?? ''); ?></td>
                                    <td class="display-last-name" data-label="Last Name"><?php echo esc_html($player['last_name'] ?? ''); ?></td>
                                    <td class="display-dob" data-label="DOB"><?php echo esc_html($player['dob'] ?? ''); ?></td>
                                    <td class="display-gender" data-label="Gender"><?php echo esc_html($player['gender'] ?? ''); ?></td>
                                    <td class="display-avs-number" data-label="AVS Number"><?php echo esc_html($player['avs_number'] ?? ''); ?></td>
                                    <td class="display-age" data-label="Age"><?php echo esc_html($player['age'] ?? 'N/A'); ?></td>
                                    <td class="display-medical-conditions" data-label="Medical Conditions">
                                        <?php 
                                        $medical = $player['medical_conditions'] ?? '';
                                        echo esc_html(strlen($medical) > 20 ? substr($medical, 0, 20) . '...' : $medical); 
                                        ?>
                                    </td>
                                    <td class="display-creation-date" data-label="Creation Date">
                                        <?php echo esc_html($player['creation_timestamp'] ? date('Y-m-d', $player['creation_timestamp']) : 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Pagination -->
            <?php $this->render_pagination_links($pagination_info); ?>

            <!-- Performance Info (only show in debug mode) -->
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <div style="background: #f9f9f9; padding: 10px; margin-top: 20px; font-size: 12px; color: #666;">
                    <strong>Debug Info:</strong>
                    Memory Peak: <?php echo $this->utils->format_bytes(memory_get_peak_usage(true)); ?> |
                    Players on Page: <?php echo count($all_players); ?> |
                    Page: <?php echo $pagination_info['current_page']; ?>/<?php echo $pagination_info['total_pages']; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Add loading indicator for pagination clicks
            $('.pagination-links a').on('click', function() {
                $('<div class="loading">Loading players...</div>').insertAfter('.pagination-info');
            });
            
            // Add search highlighting
            var searchTerm = '<?php echo esc_js($pagination_info['search_term']); ?>';
            if (searchTerm) {
                $('#player-table tbody tr').each(function() {
                    var $row = $(this);
                    var html = $row.html();
                    var regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\        $this->utils->log_memory('') + ')', 'gi');
                    html = html.replace(regex, '<mark style="background-color: yellow;">$1</mark>');
                    $row.html(html);
                });
            }
            
            // Enhanced search with enter key
            $('input[name="search"]').on('keypress', function(e) {
                if (e.which === 13) {
                    $(this).closest('form').submit();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render pagination links
     */
    private function render_pagination_links($pagination_info) {
        if ($pagination_info['total_pages'] <= 1) {
            return; // Don't show pagination if only one page
        }

        $base_url = admin_url('admin.php?page=intersoccer-players-all');
        if ($pagination_info['has_search']) {
            $base_url .= '&search=' . urlencode($pagination_info['search_term']);
        }
        
        echo '<div class="pagination-links">';
        
        // Previous page
        if ($pagination_info['current_page'] > 1) {
            echo '<a href="' . $base_url . '&paged=' . ($pagination_info['current_page'] - 1) . '">&laquo; ' . __('Previous', 'player-management') . '</a>';
        }
        
        // Page numbers
        $start_page = max(1, $pagination_info['current_page'] - 2);
        $end_page = min($pagination_info['total_pages'], $pagination_info['current_page'] + 2);
        
        if ($start_page > 1) {
            echo '<a href="' . $base_url . '&paged=1">1</a>';
            if ($start_page > 2) {
                echo '<span>...</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $pagination_info['current_page']) {
                echo '<span class="current">' . $i . '</span>';
            } else {
                echo '<a href="' . $base_url . '&paged=' . $i . '">' . $i . '</a>';
            }
        }
        
        if ($end_page < $pagination_info['total_pages']) {
            if ($end_page < $pagination_info['total_pages'] - 1) {
                echo '<span>...</span>';
            }
            echo '<a href="' . $base_url . '&paged=' . $pagination_info['total_pages'] . '">' . $pagination_info['total_pages'] . '</a>';
        }
        
        // Next page
        if ($pagination_info['current_page'] < $pagination_info['total_pages']) {
            echo '<a href="' . $base_url . '&paged=' . ($pagination_info['current_page'] + 1) . '">' . __('Next', 'player-management') . ' &raquo;</a>';
        }
        
        echo '</div>';
    }

    /**
     * Add responsive styles for better mobile experience
     */
    private function add_responsive_styles() {
        ?>
        <style>
            .pagination-info {
                background: #f1f1f1;
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 15px;
                font-size: 14px;
            }
            .pagination-links {
                text-align: center;
                margin: 20px 0;
            }
            .pagination-links a, .pagination-links span {
                display: inline-block;
                padding: 8px 12px;
                margin: 0 4px;
                text-decoration: none;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .pagination-links .current {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
            .pagination-links a:hover {
                background: #f1f1f1;
                border-color: #999;
            }
            .search-form {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
                border-left: 4px solid #0073aa;
            }
            .search-container {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .quick-stats { 
                display: flex; 
                justify-content: space-between; 
                margin-bottom: 15px; 
            }
            .quick-stats div { 
                text-align: center; 
                flex: 1; 
                padding: 10px; 
                background: #f9f9f9; 
                border: 1px solid #ddd; 
                border-radius: 5px; 
                margin: 0 5px;
            }
            .quick-stats div h3 { 
                margin: 0 0 5px; 
                font-size: 14px; 
            }
            .quick-stats div p { 
                margin: 0; 
                font-size: 16px; 
                font-weight: bold; 
            }
            .loading {
                display: none;
                text-align: center;
                padding: 20px;
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                margin: 10px 0;
            }
            
            @media (max-width: 768px) {
                .pagination-links a, .pagination-links span {
                    padding: 6px 8px;
                    margin: 0 2px;
                    font-size: 12px;
                }
                
                .search-container {
                    flex-direction: column;
                    align-items: stretch;
                }
                .search-container input[type="text"] {
                    width: 100% !important;
                    margin-bottom: 10px;
                }
                
                .quick-stats {
                    flex-direction: column;
                }
                
                .quick-stats div {
                    margin: 5px 0;
                }
                
                .pagination-info {
                    font-size: 12px;
                    line-height: 1.4;
                }

                .intersoccer-player-management table, 
                .intersoccer-player-management thead, 
                .intersoccer-player-management tbody, 
                .intersoccer-player-management th, 
                .intersoccer-player-management td, 
                .intersoccer-player-management tr {
                    display: block;
                }
                
                .intersoccer-player-management thead tr { 
                    position: absolute; 
                    top: -9999px; 
                    left: -9999px; 
                }
                
                .intersoccer-player-management tr { 
                    margin-bottom: 15px; 
                    border: 1px solid #ddd; 
                    padding: 10px;
                    border-radius: 4px;
                }
                
                .intersoccer-player-management td { 
                    border: none; 
                    position: relative; 
                    padding-left: 50%; 
                    padding-bottom: 8px;
                }
                
                .intersoccer-player-management td:before {
                    content: attr(data-label) ": ";
                    position: absolute;
                    left: 10px;
                    width: 45%;
                    padding-right: 10px;
                    white-space: nowrap;
                    font-weight: bold;
                    color: #666;
                }
            }
            
            /* Loading spinner */
            .loading {
                position: relative;
            }
            
            .loading:after {
                content: '';
                display: inline-block;
                width: 20px;
                height: 20px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-left: 10px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }

    /**
     * AJAX endpoint for loading more players (for future infinite scroll implementation)
     */
    public function ajax_load_more_players() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'player-management'));
        }
        
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        // Use the same logic as render but return JSON
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $user_query_args = [
            'role__in' => ['customer', 'subscriber'],
            'number' => $per_page,
            'offset' => $offset,
            'fields' => ['ID', 'user_email'],
            'meta_query' => [
                [
                    'key' => 'intersoccer_players',
                    'compare' => 'EXISTS'
                ]
            ]
        ];
        
        if (!empty($search)) {
            $user_query_args['search'] = '*' . $search . '*';
            $user_query_args['search_columns'] = ['user_email', 'user_login'];
        }
        
        $users = get_users($user_query_args);
        $all_players = $this->utils->process_player_batch($users, $search, $per_page);
        
        wp_send_json_success([
            'players' => $all_players,
            'has_more' => count($users) === $per_page,
            'player_count' => count($all_players)
        ]);
    }
}