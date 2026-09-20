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

        if (!empty($search_term)) {
            $search_total = $this->utils->count_matching_players($search_term);
            $total_users_with_players = $search_total;
            $total_pages = $search_total > 0 ? (int) ceil($search_total / $per_page) : 1;
            $all_players = $this->utils->get_matching_players_page($search_term, $current_page, $per_page);
        } else {
            $user_query_args = [
                'role__in' => ['customer', 'subscriber'],
                'number' => $per_page,
                'offset' => $offset,
                'fields' => ['ID', 'user_email'],
                'meta_query' => [
                    [
                        'key' => 'intersoccer_players',
                        'compare' => 'EXISTS',
                    ],
                ],
            ];

            $users = get_users($user_query_args);
            $total_users_with_players = $users_with_players;
            $total_pages = (int) ceil($total_users_with_players / $per_page);
            $all_players = $this->utils->process_player_batch($users, '', $per_page);
        }

        $this->utils->log_memory('after_paginated_users_loaded');
        
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

            <?php
            $overview_filter = isset($_GET['overview_filter']) ? sanitize_key(wp_unslash($_GET['overview_filter'])) : '';
            $allowed_overview = ['no_players', 'never_booked', 'incomplete'];
            if (in_array($overview_filter, $allowed_overview, true)) :
                ?>
                <div class="notice notice-info">
                    <p>
                        <?php
                        if ($overview_filter === 'never_booked') {
                            esc_html_e('Nurture queue: Overview filter “Never booked (lifetime)”. Prioritize parents of players with 0 events — use Events column = 0 and export for outreach.', 'player-management');
                        } elseif ($overview_filter === 'no_players') {
                            esc_html_e('Nurture queue: Overview filter “Parents with 0 kids”. These are customer accounts without player profiles — invite them to Manage Players.', 'player-management');
                        } else {
                            esc_html_e('Nurture queue: Overview filter “Incomplete profiles”. Ask parents to complete DOB and medical/dietary/allergies.', 'player-management');
                        }
                        ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-players')); ?>"><?php esc_html_e('Back to Overview', 'player-management'); ?></a>
                    </p>
                </div>
                <?php
            endif;

            $export_url = add_query_arg(
                array_filter([
                    'action' => 'intersoccer_players_export',
                    'search' => $pagination_info['search_term'] ?? '',
                ], static fn($v) => $v !== ''),
                admin_url('admin-post.php')
            );
            $export_url = wp_nonce_url($export_url, 'intersoccer_players_export');

            ?>

            <!-- Search Form - AC 2: search by player name AND parent name/email -->
            <form method="get" class="search-form">
                <input type="hidden" name="page" value="intersoccer-players-all">
                <div class="search-container">
                    <input type="text" name="search" value="<?php echo esc_attr($pagination_info['search_term']); ?>" 
                        placeholder="<?php _e('Search by player name, parent name/email, AVS...', 'player-management'); ?>"
                        style="width: 350px;"
                        data-field="player-search">
                    <input type="submit" class="button button-primary" value="<?php _e('Search', 'player-management'); ?>">
                    <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">
                        <?php _e('Export to CSV', 'player-management'); ?>
                    </a>
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

            <!-- Players Table - AC 2: sortable by name/DOB; row → view/edit -->
            <div class="intersoccer-player-management" data-field="admin-player-list">
                <table id="player-table" class="wp-list-table widefat fixed striped" data-field="player-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="name"><?php _e('Name', 'player-management'); ?> <span class="sort-indicator">⇅</span></th>
                            <th class="sortable" data-sort="dob"><?php _e('DOB', 'player-management'); ?> <span class="sort-indicator">⇅</span></th>
                            <th><?php _e('Parent', 'player-management'); ?></th>
                            <th><?php _e('Gender', 'player-management'); ?></th>
                            <th><?php _e('Canton', 'player-management'); ?></th>
                            <th><?php _e('Medical/Dietary', 'player-management'); ?></th>
                            <th class="actions-column"><?php _e('Actions', 'player-management'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_players)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;" data-field="empty-state">
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
                                    data-user-id="<?php echo esc_attr($player['user_id']); ?>"
                                    data-field="player-row">
                                    <td class="display-name" data-label="<?php esc_attr_e('Name', 'player-management'); ?>" data-field="player-name">
                                        <strong><?php echo esc_html(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? '')); ?></strong>
                                    </td>
                                    <td class="display-dob" data-label="<?php esc_attr_e('DOB', 'player-management'); ?>" data-field="player-dob">
                                        <?php echo esc_html($player['dob'] ?? 'N/A'); ?>
                                        <?php if (!empty($player['age'])): ?>
                                            <br><small>(<?php echo esc_html($player['age']); ?> <?php _e('years', 'player-management'); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="display-parent" data-label="<?php esc_attr_e('Parent', 'player-management'); ?>" data-field="parent-info">
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $player['user_id'])); ?>">
                                            <?php echo esc_html($player['parent_name'] ?? ''); ?>
                                        </a>
                                        <br><small><?php echo esc_html($player['user_email'] ?? ''); ?></small>
                                    </td>
                                    <td class="display-gender" data-label="<?php esc_attr_e('Gender', 'player-management'); ?>" data-field="player-gender">
                                        <?php echo esc_html(intersoccer_translate_gender($player['gender'] ?? '')); ?>
                                    </td>
                                    <td class="display-canton" data-label="<?php esc_attr_e('Canton', 'player-management'); ?>" data-field="player-canton">
                                        <?php echo esc_html($player['canton'] ?? ''); ?>
                                        <?php if (!empty($player['city'])): ?>
                                            <br><small><?php echo esc_html($player['city']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="display-medical" data-label="<?php esc_attr_e('Medical/Dietary', 'player-management'); ?>" data-field="player-medical">
                                        <?php 
                                        $medical = $player['medical_conditions'] ?? '';
                                        if ($medical !== '') {
                                            echo '<span class="has-medical" title="' . esc_attr($medical) . '">⚕️ ';
                                            echo esc_html(strlen($medical) > 30 ? substr($medical, 0, 30) . '...' : $medical);
                                            echo '</span>';
                                        } else {
                                            echo '<span class="no-medical">—</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="actions" data-label="<?php esc_attr_e('Actions', 'player-management'); ?>">
                                        <button type="button" class="button button-small view-player-btn" 
                                                data-player-index="<?php echo esc_attr($player['index']); ?>"
                                                data-user-id="<?php echo esc_attr($player['user_id']); ?>"
                                                data-field="view-player-btn">
                                            <?php _e('View', 'player-management'); ?>
                                        </button>
                                        <button type="button" class="button button-small button-primary edit-player-btn" 
                                                data-player-index="<?php echo esc_attr($player['index']); ?>"
                                                data-user-id="<?php echo esc_attr($player['user_id']); ?>"
                                                data-field="edit-player-btn">
                                            <?php _e('Edit', 'player-management'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Player Detail Modal - AC 2: view/edit player fields -->
            <div id="player-detail-modal" class="intersoccer-modal" style="display: none;" data-field="player-modal">
                <div class="intersoccer-modal-overlay"></div>
                <div class="intersoccer-modal-content">
                    <button type="button" class="intersoccer-modal-close" aria-label="<?php esc_attr_e('Close', 'player-management'); ?>">&times;</button>
                    <h2 id="modal-title" data-field="modal-title"><?php _e('Player Details', 'player-management'); ?></h2>
                    <div id="modal-body" data-field="modal-body">
                        <div class="modal-loading"><?php _e('Loading...', 'player-management'); ?></div>
                    </div>
                    <div id="modal-edit-form" style="display: none;" data-field="modal-edit-form">
                        <form id="admin-edit-player-form">
                            <input type="hidden" name="player_index" id="edit-player-index">
                            <input type="hidden" name="user_id" id="edit-user-id">
                            <div class="form-row">
                                <label for="edit-first-name"><?php _e('First Name', 'player-management'); ?> *</label>
                                <input type="text" id="edit-first-name" name="first_name" required data-field="edit-first-name">
                            </div>
                            <div class="form-row">
                                <label for="edit-last-name"><?php _e('Last Name', 'player-management'); ?> *</label>
                                <input type="text" id="edit-last-name" name="last_name" required data-field="edit-last-name">
                            </div>
                            <div class="form-row">
                                <label for="edit-dob"><?php _e('Date of Birth', 'player-management'); ?> *</label>
                                <input type="date" id="edit-dob" name="dob" required data-field="edit-dob">
                            </div>
                            <div class="form-row">
                                <label for="edit-gender"><?php _e('Gender', 'player-management'); ?> *</label>
                                <select id="edit-gender" name="gender" required data-field="edit-gender">
                                    <option value="male"><?php _e('Male', 'player-management'); ?></option>
                                    <option value="female"><?php _e('Female', 'player-management'); ?></option>
                                    <option value="other"><?php _e('Other', 'player-management'); ?></option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="edit-avs"><?php _e('AVS Number', 'player-management'); ?></label>
                                <input type="text" id="edit-avs" name="avs_number" data-field="edit-avs">
                            </div>
                            <div class="form-row">
                                <label for="edit-medical"><?php _e('Medical/Dietary/Allergies', 'player-management'); ?></label>
                                <textarea id="edit-medical" name="medical_conditions" rows="3" data-field="edit-medical"></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="button button-primary" data-field="save-player-btn"><?php _e('Save Changes', 'player-management'); ?></button>
                                <button type="button" class="button cancel-edit-btn"><?php _e('Cancel', 'player-management'); ?></button>
                            </div>
                            <div class="edit-message" style="display: none;"></div>
                        </form>
                    </div>
                </div>
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
            function escapeRegExp(str) {
                return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }
            if (searchTerm) {
                $('#player-table tbody tr').each(function() {
                    var $row = $(this);
                    var html = $row.html();
                    var regex = new RegExp('(' + escapeRegExp(searchTerm) + ')', 'gi');
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

            // AC 2: Client-side sorting by name/DOB
            var sortDirection = {};
            $('.sortable').on('click', function() {
                var $th = $(this);
                var sortKey = $th.data('sort');
                var $table = $('#player-table');
                var $tbody = $table.find('tbody');
                var $rows = $tbody.find('tr[data-field="player-row"]').get();

                sortDirection[sortKey] = !sortDirection[sortKey];
                var asc = sortDirection[sortKey];

                $rows.sort(function(a, b) {
                    var aVal, bVal;
                    if (sortKey === 'name') {
                        aVal = $(a).find('.display-name').text().trim().toLowerCase();
                        bVal = $(b).find('.display-name').text().trim().toLowerCase();
                    } else if (sortKey === 'dob') {
                        aVal = $(a).find('.display-dob').text().trim().split('\n')[0];
                        bVal = $(b).find('.display-dob').text().trim().split('\n')[0];
                    }
                    if (aVal < bVal) return asc ? -1 : 1;
                    if (aVal > bVal) return asc ? 1 : -1;
                    return 0;
                });

                $.each($rows, function(idx, row) {
                    $tbody.append(row);
                });

                // Update sort indicators
                $('.sortable .sort-indicator').text('⇅');
                $th.find('.sort-indicator').text(asc ? '↑' : '↓');
            });

            // AC 2: View player modal
            var $modal = $('#player-detail-modal');
            var $modalBody = $('#modal-body');
            var $editForm = $('#modal-edit-form');

            $('.view-player-btn').on('click', function() {
                var playerIndex = $(this).data('player-index');
                var userId = $(this).data('user-id');
                var $row = $(this).closest('tr');

                var playerName = $row.find('.display-name').text().trim();
                var dob = $row.find('.display-dob').text().trim().split('\n')[0];
                var parent = $row.find('.display-parent a').text().trim();
                var parentEmail = $row.find('.display-parent small').text().trim();
                var gender = $row.find('.display-gender').text().trim();
                var canton = $row.find('.display-canton').text().trim();
                var medical = $row.find('.display-medical').attr('title') || $row.find('.display-medical').text().trim();

                var html = '<dl class="player-details">' +
                    '<dt><?php echo esc_js(__('Name', 'player-management')); ?></dt><dd>' + escapeHtml(playerName) + '</dd>' +
                    '<dt><?php echo esc_js(__('Date of Birth', 'player-management')); ?></dt><dd>' + escapeHtml(dob) + '</dd>' +
                    '<dt><?php echo esc_js(__('Gender', 'player-management')); ?></dt><dd>' + escapeHtml(gender) + '</dd>' +
                    '<dt><?php echo esc_js(__('Parent', 'player-management')); ?></dt><dd>' + escapeHtml(parent) + ' (' + escapeHtml(parentEmail) + ')</dd>' +
                    '<dt><?php echo esc_js(__('Location', 'player-management')); ?></dt><dd>' + escapeHtml(canton) + '</dd>' +
                    '<dt><?php echo esc_js(__('Medical/Dietary/Allergies', 'player-management')); ?></dt><dd>' + escapeHtml(medical || '—') + '</dd>' +
                    '</dl>';

                $('#modal-title').text('<?php echo esc_js(__('Player Details', 'player-management')); ?>: ' + playerName);
                $modalBody.html(html).show();
                $editForm.hide();
                $modal.show();
            });

            // AC 2: Edit player modal
            $('.edit-player-btn').on('click', function() {
                var playerIndex = $(this).data('player-index');
                var userId = $(this).data('user-id');
                var $row = $(this).closest('tr');

                var firstName = $row.data('first-name') || $row.find('.display-name strong').text().trim().split(' ')[0];
                var lastName = $row.data('last-name') || $row.find('.display-name strong').text().trim().split(' ').slice(1).join(' ');
                var dob = $row.find('.display-dob').text().trim().split('\n')[0];
                var gender = $row.data('gender') || $row.find('.display-gender').text().trim().toLowerCase();
                var avs = $row.data('avs-number') || '';
                var medical = $row.find('.display-medical').attr('title') || '';

                // Map gender display text back to value
                if (gender === '<?php echo esc_js(__('Male', 'player-management')); ?>'.toLowerCase()) gender = 'male';
                else if (gender === '<?php echo esc_js(__('Female', 'player-management')); ?>'.toLowerCase()) gender = 'female';
                else if (gender !== 'male' && gender !== 'female') gender = 'other';

                $('#edit-player-index').val(playerIndex);
                $('#edit-user-id').val(userId);
                $('#edit-first-name').val(firstName);
                $('#edit-last-name').val(lastName);
                $('#edit-dob').val(dob);
                $('#edit-gender').val(gender);
                $('#edit-avs').val(avs);
                $('#edit-medical').val(medical);

                $('#modal-title').text('<?php echo esc_js(__('Edit Player', 'player-management')); ?>');
                $modalBody.hide();
                $editForm.show();
                $('.edit-message').hide();
                $modal.show();
            });

            // Close modal
            $('.intersoccer-modal-close, .intersoccer-modal-overlay, .cancel-edit-btn').on('click', function() {
                $modal.hide();
            });

            // Save player edit - AC 2
            $('#admin-edit-player-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('[data-field="save-player-btn"]');
                var $msg = $form.find('.edit-message');

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'player-management')); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intersoccer_edit_player',
                        nonce: '<?php echo wp_create_nonce('intersoccer_player_nonce'); ?>',
                        user_id: $('#edit-user-id').val(),
                        player_index: $('#edit-player-index').val(),
                        player_first_name: $('#edit-first-name').val(),
                        player_last_name: $('#edit-last-name').val(),
                        player_dob: $('#edit-dob').val(),
                        player_gender: $('#edit-gender').val(),
                        player_avs_number: $('#edit-avs').val(),
                        player_medical: $('#edit-medical').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $msg.removeClass('error').addClass('success').text('<?php echo esc_js(__('Player updated successfully!', 'player-management')); ?>').show();
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $msg.removeClass('success').addClass('error').text(response.data?.message || '<?php echo esc_js(__('Failed to update player.', 'player-management')); ?>').show();
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Save Changes', 'player-management')); ?>');
                        }
                    },
                    error: function() {
                        $msg.removeClass('success').addClass('error').text('<?php echo esc_js(__('Network error. Please try again.', 'player-management')); ?>').show();
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Save Changes', 'player-management')); ?>');
                    }
                });
            });

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
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

            /* AC 2: Sortable columns */
            .sortable {
                cursor: pointer;
                user-select: none;
            }
            .sortable:hover {
                background: #f1f1f1;
            }
            .sort-indicator {
                margin-left: 4px;
                opacity: 0.5;
            }

            /* AC 2: Player detail modal */
            .intersoccer-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .intersoccer-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
            }
            .intersoccer-modal-content {
                position: relative;
                background: #fff;
                padding: 24px;
                border-radius: 8px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            }
            .intersoccer-modal-close {
                position: absolute;
                top: 12px;
                right: 12px;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                line-height: 1;
            }
            .intersoccer-modal-close:hover {
                color: #d63638;
            }
            .player-details {
                display: grid;
                grid-template-columns: 1fr 2fr;
                gap: 8px 16px;
                margin: 0;
            }
            .player-details dt {
                font-weight: 600;
                color: #1d2327;
            }
            .player-details dd {
                margin: 0;
                color: #50575e;
            }
            #modal-edit-form .form-row {
                margin-bottom: 16px;
            }
            #modal-edit-form label {
                display: block;
                margin-bottom: 4px;
                font-weight: 600;
            }
            #modal-edit-form input,
            #modal-edit-form select,
            #modal-edit-form textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
            }
            #modal-edit-form .form-actions {
                margin-top: 20px;
                display: flex;
                gap: 8px;
            }
            .edit-message {
                margin-top: 12px;
                padding: 8px 12px;
                border-radius: 4px;
            }
            .edit-message.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .edit-message.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
            .has-medical {
                color: #0073aa;
            }
            .no-medical {
                color: #999;
            }
            .actions-column {
                width: 140px;
            }
        </style>
        <?php
    }

    /**
     * AJAX endpoint for loading more players (for future infinite scroll implementation)
     */
    public function ajax_load_more_players() {
        if (!check_ajax_referer('intersoccer_player_list_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token', 'player-management')], 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'player-management')], 403);
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