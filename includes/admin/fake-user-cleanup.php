<?php
/**
 * File: admin/fake-user-cleanup.php
 * 
 * Fake User Cleanup Admin Page for Player Management Plugin
 * Handles scanning and deleting fake users based on email pattern, no orders, and no intersoccer_players meta.
 * 
 * Integration: Add to player-management-plugin/admin/fake-user-cleanup.php
 * Main plugin file update: Add require_once for this file in is_admin() block
 */

if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Fake_User_Cleanup_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_intersoccer_scan_fake_users', array($this, 'ajax_scan_fake_users'));
        add_action('wp_ajax_intersoccer_delete_fake_users_batch', array($this, 'ajax_delete_fake_users_batch'));
        add_action('wp_ajax_intersoccer_validate_assumptions', array($this, 'ajax_validate_assumptions'));
    }
    
    /**
     * Add submenu under Users
     */
    public function add_admin_menu() {
        add_submenu_page(
            'users.php',
            'Fake User Cleanup',
            'Fake User Cleanup',
            'manage_options',
            'intersoccer-fake-user-cleanup',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue scripts only on our admin page
     */
    public function enqueue_scripts($hook) {
        if ('users_page_intersoccer-fake-user-cleanup' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'intersoccer-cleanup-js',
            plugin_dir_url(__FILE__) . '../js/cleanup.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('intersoccer-cleanup-js', 'intersoccer_cleanup', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('intersoccer-cleanup-nonce'),
        ));
        
        // Add admin styles
        wp_add_inline_style('wp-admin', '
            .intersoccer-cleanup-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                padding: 20px;
                margin: 20px 0;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .cleanup-progress {
                background: #f1f1f1;
                border-radius: 3px;
                height: 20px;
                margin: 10px 0;
                overflow: hidden;
            }
            .cleanup-progress-bar {
                background: #0073aa;
                height: 100%;
                transition: width 0.3s ease;
            }
            .validation-results {
                background: #f9f9f9;
                border-left: 4px solid #00a0d2;
                padding: 15px;
                margin: 15px 0;
            }
            .warning-box {
                background: #fff8e1;
                border-left: 4px solid #ffb900;
                padding: 15px;
                margin: 15px 0;
            }
            .error-box {
                background: #ffeaea;
                border-left: 4px solid #d63638;
                padding: 15px;
                margin: 15px 0;
            }
        ');
    }
    
    /**
     * Admin page HTML
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>InterSoccer Fake User Cleanup</h1>
            <p>This tool identifies and removes fake users created during the security incident. Users are validated based on email pattern, absence of WooCommerce orders, and missing intersoccer_players metadata.</p>
            
            <!-- Security Validation Section -->
            <div class="intersoccer-cleanup-card">
                <h2>Step 1: Validate Security Assumptions</h2>
                <p>Before cleanup, let's validate our assumptions about the security incident sources.</p>
                <button id="validate-assumptions" class="button button-primary">
                    <span class="dashicons dashicons-search"></span> Run Security Validation
                </button>
                <div id="validation-results"></div>
            </div>
            
            <!-- Scan Section -->
            <div class="intersoccer-cleanup-card">
                <h2>Step 2: Scan for Fake Users</h2>
                <p>Identifies users matching pattern: <code>^[a-z]{8}[0-9]{2}@(gmail|outlook|yahoo|hotmail)\.com$</code></p>
                <button id="scan-fake-users" class="button button-primary">
                    <span class="dashicons dashicons-analytics"></span> Scan for Fake Users
                </button>
                <div id="scan-results"></div>
            </div>
            
            <!-- Cleanup Section -->
            <div class="intersoccer-cleanup-card" id="cleanup-section" style="display: none;">
                <h2>Step 3: Remove Fake Users</h2>
                <div class="warning-box">
                    <strong>⚠️ Warning:</strong> This action cannot be undone. Users will be permanently deleted from the database.
                    <br>Process runs in batches of 100 users to prevent timeouts.
                </div>
                <div id="cleanup-summary"></div>
                <label>
                    <input type="checkbox" id="dry-run-mode"> Dry Run Mode (Log only, don't delete)
                </label>
                <br><br>
                <button id="delete-fake-users" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span> Start Cleanup Process
                </button>
                <div id="delete-progress"></div>
            </div>
            
            <!-- Log Viewer Section -->
            <div class="intersoccer-cleanup-card">
                <h2>Debug & Audit Logs</h2>
                <p>Monitor cleanup progress and review actions taken.</p>
                <button id="view-logs" class="button">
                    <span class="dashicons dashicons-visibility"></span> View Recent Logs
                </button>
                <div id="log-viewer"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let fakeUserIds = [];
            
            // Security validation
            $('#validate-assumptions').on('click', function() {
                const $btn = $(this).prop('disabled', true).text('Validating...');
                
                $.ajax({
                    url: intersoccer_cleanup.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'intersoccer_validate_assumptions',
                        nonce: intersoccer_cleanup.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            displayValidationResults(response.data);
                        } else {
                            showError('Validation failed: ' + response.data.message);
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Security Validation');
                    }
                });
            });
            
            // Scan for fake users
            $('#scan-fake-users').on('click', function() {
                const $btn = $(this).prop('disabled', true).text('Scanning...');
                $('#scan-results').html('<div class="cleanup-progress"><div class="cleanup-progress-bar" style="width: 0%"></div></div>');
                
                $.ajax({
                    url: intersoccer_cleanup.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'intersoccer_scan_fake_users',
                        nonce: intersoccer_cleanup.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            displayScanResults(response.data);
                            fakeUserIds = response.data.ids;
                            if (response.data.fake > 0) {
                                $('#cleanup-section').show();
                            }
                        } else {
                            showError('Scan failed: ' + response.data.message);
                        }
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-analytics"></span> Scan for Fake Users');
                    }
                });
            });
            
            // Delete fake users
            $('#delete-fake-users').on('click', function() {
                const isDryRun = $('#dry-run-mode').is(':checked');
                const confirmMsg = isDryRun 
                    ? 'Run dry-run mode (no actual deletions)?'
                    : 'Are you sure? This will permanently delete all identified fake users.';
                
                if (!confirm(confirmMsg)) return;
                
                const $btn = $(this).prop('disabled', true);
                deleteFakeUsersInBatches(isDryRun);
            });
            
            function displayValidationResults(data) {
                let html = '<div class="validation-results">';
                html += '<h3>Security Incident Analysis</h3>';
                html += `<p><strong>Recent User Registrations:</strong> ${data.recent_registrations}</p>`;
                html += `<p><strong>Pattern Matches:</strong> ${data.pattern_matches}</p>`;
                html += `<p><strong>Registration Spike Date:</strong> ${data.spike_date || 'Not detected'}</p>`;
                html += `<p><strong>Most Common Domains:</strong> ${data.common_domains.join(', ')}</p>`;
                
                if (data.recommendations) {
                    html += '<h4>Security Recommendations:</h4><ul>';
                    data.recommendations.forEach(rec => {
                        html += `<li>${rec}</li>`;
                    });
                    html += '</ul>';
                }
                html += '</div>';
                
                $('#validation-results').html(html);
            }
            
            function displayScanResults(data) {
                let html = '<div class="validation-results">';
                html += '<h3>Scan Results</h3>';
                html += `<p><strong>Total users with pattern match:</strong> ${data.total}</p>`;
                html += `<p><strong>Excluded (have orders/metadata):</strong> ${data.excluded}</p>`;
                html += `<p><strong>Fake users identified for cleanup:</strong> ${data.fake}</p>`;
                
                if (data.sample_emails && data.sample_emails.length > 0) {
                    html += '<h4>Sample Fake User Emails:</h4>';
                    html += '<div style="font-family: monospace; background: #f0f0f0; padding: 10px; max-height: 150px; overflow-y: auto;">';
                    data.sample_emails.forEach(email => {
                        html += email + '<br>';
                    });
                    html += '</div>';
                }
                html += '</div>';
                
                $('#scan-results').html(html);
                $('#cleanup-summary').html(`<p>Ready to process <strong>${data.fake}</strong> fake users.</p>`);
            }
            
            function deleteFakeUsersInBatches(isDryRun) {
                const batchSize = 100;
                const total = fakeUserIds.length;
                let processed = 0;
                
                $('#delete-progress').html(`
                    <p>${isDryRun ? 'Dry Run' : 'Deleting'}: 0 / ${total}</p>
                    <div class="cleanup-progress">
                        <div class="cleanup-progress-bar" id="progress-bar" style="width: 0%"></div>
                    </div>
                `);
                
                function processBatch() {
                    if (fakeUserIds.length === 0) {
                        $('#delete-progress').append(`<p style="color: green;">✅ ${isDryRun ? 'Dry run' : 'Cleanup'} complete! Processed ${processed} users.</p>`);
                        $('#delete-fake-users').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Start Cleanup Process');
                        return;
                    }
                    
                    const batch = fakeUserIds.splice(0, batchSize);
                    
                    $.ajax({
                        url: intersoccer_cleanup.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'intersoccer_delete_fake_users_batch',
                            nonce: intersoccer_cleanup.nonce,
                            batch: batch,
                            dry_run: isDryRun ? 1 : 0
                        },
                        success: function(response) {
                            if (response.success) {
                                processed += response.data.processed;
                                const percentage = Math.round((processed / total) * 100);
                                $('#delete-progress p').text(`${isDryRun ? 'Dry Run' : 'Deleting'}: ${processed} / ${total}`);
                                $('#progress-bar').css('width', percentage + '%');
                                processBatch(); // Continue with next batch
                            } else {
                                showError('Batch processing failed: ' + response.data.message);
                            }
                        },
                        error: function() {
                            showError('AJAX error during batch processing');
                        }
                    });
                }
                
                processBatch();
            }
            
            function showError(message) {
                $('#scan-results, #delete-progress').append(`<div class="error-box">${message}</div>`);
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Validate security assumptions
     */
    public function ajax_validate_assumptions() {
        check_ajax_referer('intersoccer-cleanup-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $this->log_message('=== Security Validation Started ===');
        
        global $wpdb;
        
        // Count recent registrations (last 3 months)
        $three_months_ago = date('Y-m-d H:i:s', strtotime('-3 months'));
        $recent_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered > %s",
            $three_months_ago
        ));
        
        // Count pattern matches
        $regex = '^[a-z]{8}[0-9]{2}@(gmail|outlook|yahoo|hotmail)\.com$';
        $pattern_matches = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email REGEXP '$regex'"
        );
        
        // Find registration spike date
        $daily_registrations = $wpdb->get_results(
            "SELECT DATE(user_registered) as reg_date, COUNT(*) as count 
             FROM {$wpdb->users} 
             WHERE user_registered > DATE_SUB(NOW(), INTERVAL 3 MONTH)
             GROUP BY DATE(user_registered)
             HAVING count > 100
             ORDER BY count DESC
             LIMIT 5"
        );
        
        // Get domain distribution
        $domains = $wpdb->get_results(
            "SELECT SUBSTRING_INDEX(user_email, '@', -1) as domain, COUNT(*) as count
             FROM {$wpdb->users}
             WHERE user_email REGEXP '$regex'
             GROUP BY domain
             ORDER BY count DESC"
        );
        
        $common_domains = array_map(function($d) { return $d->domain . ' (' . $d->count . ')'; }, $domains);
        
        // Generate recommendations
        $recommendations = array();
        if ($pattern_matches > 1000) {
            $recommendations[] = 'Install Google reCAPTCHA on registration forms';
            $recommendations[] = 'Enable user registration moderation';
            $recommendations[] = 'Implement rate limiting for registration endpoint';
        }
        if (!empty($daily_registrations)) {
            $recommendations[] = 'Review server access logs for the spike date: ' . $daily_registrations[0]->reg_date;
            $recommendations[] = 'Check for compromised plugins during incident period';
        }
        
        $this->log_message("Validation: Recent registrations: $recent_count, Pattern matches: $pattern_matches");
        
        wp_send_json_success(array(
            'recent_registrations' => $recent_count,
            'pattern_matches' => $pattern_matches,
            'spike_date' => !empty($daily_registrations) ? $daily_registrations[0]->reg_date : null,
            'common_domains' => $common_domains,
            'recommendations' => $recommendations,
            'daily_spikes' => $daily_registrations
        ));
    }
    
    /**
     * AJAX: Scan for fake users
     */
    public function ajax_scan_fake_users() {
        check_ajax_referer('intersoccer-cleanup-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $this->log_message('=== Fake User Scan Started ===');
        
        global $wpdb;
        $regex = '^[a-z]{8}[0-9]{2}@(gmail|outlook|yahoo|hotmail)\.com$';
        
        // Get all users matching email pattern
        $users = $wpdb->get_results(
            "SELECT ID, user_email, user_registered FROM {$wpdb->users} WHERE user_email REGEXP '$regex'"
        );
        
        $fake_users = array();
        $excluded_users = 0;
        $sample_emails = array();
        
        foreach ($users as $user) {
            // Check for WooCommerce orders
            $orders = wc_get_orders(array(
                'customer_id' => $user->ID,
                'limit' => 1,
                'return' => 'ids'
            ));
            $has_orders = !empty($orders);
            
            // Check for intersoccer_players metadata
            $players_meta = get_user_meta($user->ID, 'intersoccer_players', true);
            $has_meta = !empty($players_meta);
            
            if (!$has_orders && !$has_meta) {
                $fake_users[] = $user->ID;
                if (count($sample_emails) < 20) {
                    $sample_emails[] = $user->user_email;
                }
            } else {
                $excluded_users++;
            }
            
            // Log validation for debugging
            $this->log_message("Scan: User ID {$user->ID} ({$user->user_email}) - Orders: " . ($has_orders ? 'Yes' : 'No') . ", Meta: " . ($has_meta ? 'Yes' : 'No'));
        }
        
        $this->log_message("Scan complete: " . count($fake_users) . " fake users identified, $excluded_users excluded");
        
        wp_send_json_success(array(
            'total' => count($users),
            'fake' => count($fake_users),
            'excluded' => $excluded_users,
            'ids' => $fake_users,
            'sample_emails' => $sample_emails
        ));
    }
    
    /**
     * AJAX: Delete fake users in batches
     */
    public function ajax_delete_fake_users_batch() {
        check_ajax_referer('intersoccer-cleanup-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $batch = isset($_POST['batch']) ? array_map('intval', $_POST['batch']) : array();
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] == '1';
        $processed = 0;
        
        foreach ($batch as $user_id) {
            // Final safety check
            if (!$this->is_safe_to_delete($user_id)) {
                $this->log_message("Safety check failed for user ID: $user_id - skipping");
                continue;
            }
            
            if ($dry_run) {
                $this->log_message("DRY RUN: Would delete user ID: $user_id");
                $processed++;
            } else {
                if (wp_delete_user($user_id)) {
                    $this->log_message("Deleted fake user ID: $user_id");
                    $processed++;
                } else {
                    $this->log_message("Failed to delete user ID: $user_id");
                }
            }
        }
        
        wp_send_json_success(array('processed' => $processed));
    }
    
    /**
     * Final safety check before deletion
     */
    private function is_safe_to_delete($user_id) {
        // Don't delete admin users
        if (user_can($user_id, 'manage_options')) {
            return false;
        }
        
        // Don't delete users with content
        if (count_user_posts($user_id) > 0) {
            return false;
        }
        
        // Double-check WooCommerce orders
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => 1,
            'return' => 'ids'
        ));
        if (!empty($orders)) {
            return false;
        }
        
        // Double-check intersoccer_players metadata
        $players_meta = get_user_meta($user_id, 'intersoccer_players', true);
        if (!empty($players_meta)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Log messages to debug.log
     */
    private function log_message($message) {
        if (WP_DEBUG_LOG) {
            error_log("InterSoccer Fake User Cleanup: $message");
        }
    }
}

// Initialize only if in admin
if (is_admin()) {
    new InterSoccer_Fake_User_Cleanup_Admin();
}

/**
 * WP-CLI Integration for batch operations
 */
if (defined('WP_CLI') && WP_CLI) {
    class InterSoccer_Cleanup_CLI_Command {
        
        /**
         * Scan for fake users via CLI
         * 
         * ## EXAMPLES
         * 
         *     wp intersoccer scan-fake-users
         */
        public function scan_fake_users() {
            global $wpdb;
            $regex = '^[a-z]{8}[0-9]{2}@(gmail|outlook|yahoo|hotmail)\.com$';
            
            WP_CLI::line("Scanning for fake users...");
            
            $users = $wpdb->get_results(
                "SELECT ID, user_email FROM {$wpdb->users} WHERE user_email REGEXP '$regex'"
            );
            
            $fake_count = 0;
            foreach ($users as $user) {
                $orders = wc_get_orders(array('customer_id' => $user->ID, 'limit' => 1));
                $players_meta = get_user_meta($user->ID, 'intersoccer_players', true);
                
                if (empty($orders) && empty($players_meta)) {
                    $fake_count++;
                    WP_CLI::line("Fake: ID {$user->ID} - {$user->user_email}");
                }
            }
            
            WP_CLI::success("Found $fake_count fake users out of " . count($users) . " pattern matches");
        }
        
        /**
         * Delete fake users via CLI
         * 
         * ## OPTIONS
         * 
         * [--dry-run]
         * : Show what would be deleted without actually deleting
         * 
         * [--batch-size=<size>]
         * : Number of users to process per batch (default: 100)
         * 
         * ## EXAMPLES
         * 
         *     wp intersoccer cleanup-fake-users --dry-run
         *     wp intersoccer cleanup-fake-users --batch-size=50
         */
        public function cleanup_fake_users($args, $assoc_args) {
            $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
            $batch_size = WP_CLI\Utils\get_flag_value($assoc_args, 'batch-size', 100);
            
            global $wpdb;
            $regex = '^[a-z]{8}[0-9]{2}@(gmail|outlook|yahoo|hotmail)\.com$';
            
            $users = $wpdb->get_results(
                "SELECT ID, user_email FROM {$wpdb->users} WHERE user_email REGEXP '$regex'"
            );
            
            $to_delete = array();
            foreach ($users as $user) {
                $orders = wc_get_orders(array('customer_id' => $user->ID, 'limit' => 1));
                $players_meta = get_user_meta($user->ID, 'intersoccer_players', true);
                
                if (empty($orders) && empty($players_meta)) {
                    $to_delete[] = $user;
                }
            }
            
            if ($dry_run) {
                WP_CLI::line("DRY RUN - Would delete " . count($to_delete) . " users:");
                foreach ($to_delete as $user) {
                    WP_CLI::line("  ID {$user->ID}: {$user->user_email}");
                }
                return;
            }
            
            $progress = WP_CLI\Utils\make_progress_bar('Deleting users', count($to_delete));
            $deleted = 0;
            
            foreach (array_chunk($to_delete, $batch_size) as $batch) {
                foreach ($batch as $user) {
                    if (wp_delete_user($user->ID)) {
                        $deleted++;
                    }
                    $progress->tick();
                }
                // Small delay between batches
                sleep(1);
            }
            
            $progress->finish();
            WP_CLI::success("Deleted $deleted fake users");
        }
    }
    
    WP_CLI::add_command('intersoccer', 'InterSoccer_Cleanup_CLI_Command');
}