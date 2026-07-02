/**
 * File: assets/js/cleanup.js
 * JavaScript for InterSoccer Fake User Cleanup functionality
 */

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
            error: function(xhr, status, error) {
                showError('AJAX error: ' + error);
                console.log('Validation error:', xhr.responseText);
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
        
        // Simulate progress during scan
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90;
            $('.cleanup-progress-bar').css('width', progress + '%');
        }, 500);
        
        $.ajax({
            url: intersoccer_cleanup.ajax_url,
            type: 'POST',
            data: {
                action: 'intersoccer_scan_fake_users',
                nonce: intersoccer_cleanup.nonce
            },
            success: function(response) {
                clearInterval(progressInterval);
                $('.cleanup-progress-bar').css('width', '100%');
                
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
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                showError('Scan error: ' + error);
                console.log('Scan error:', xhr.responseText);
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
            ? 'Run dry-run mode (no actual deletions)? This will log what would be deleted.'
            : 'Are you sure you want to permanently delete all identified fake users? This action cannot be undone.';
        
        if (!confirm(confirmMsg)) return;
        
        const $btn = $(this).prop('disabled', true);
        deleteFakeUsersInBatches(isDryRun);
    });
    
    // View logs functionality
    $('#view-logs').on('click', function() {
        const $btn = $(this).prop('disabled', true).text('Loading logs...');
        
        // For now, show a message about checking debug.log
        // In future versions, could implement AJAX log viewer
        $('#log-viewer').html(`
            <div class="validation-results">
                <h4>Log Files Location:</h4>
                <p><strong>WordPress Debug Log:</strong> <code>wp-content/debug.log</code></p>
                <p><strong>Custom Cleanup Log:</strong> <code>wp-content/intersoccer-cleanup.log</code></p>
                <p><strong>Access via SSH:</strong> <code>tail -f wp-content/debug.log</code></p>
                <p>All cleanup actions are logged with timestamp and user details for auditing purposes.</p>
            </div>
        `);
        
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> View Recent Logs');
    });
    
    function displayValidationResults(data) {
        let html = '<div class="validation-results">';
        html += '<h3>Security Incident Analysis</h3>';
        html += `<p><strong>Recent User Registrations (3 months):</strong> ${data.recent_registrations}</p>`;
        html += `<p><strong>Users Matching Fake Pattern:</strong> ${data.pattern_matches}</p>`;
        
        if (data.spike_date) {
            html += `<p><strong>Registration Spike Date:</strong> ${data.spike_date}</p>`;
        }
        
        if (data.common_domains && data.common_domains.length > 0) {
            html += `<p><strong>Most Common Domains:</strong> ${data.common_domains.join(', ')}</p>`;
        }
        
        if (data.daily_spikes && data.daily_spikes.length > 0) {
            html += '<h4>Daily Registration Spikes:</h4><ul>';
            data.daily_spikes.forEach(spike => {
                html += `<li>${spike.reg_date}: ${spike.count} registrations</li>`;
            });
            html += '</ul>';
        }
        
        if (data.recommendations && data.recommendations.length > 0) {
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
        html += `<p><strong>Total users with email pattern:</strong> ${data.total}</p>`;
        html += `<p><strong>Excluded (have orders/metadata):</strong> ${data.excluded}</p>`;
        html += `<p><strong>Fake users identified:</strong> <span style="color: #d63638; font-weight: bold;">${data.fake}</span></p>`;
        
        if (data.fake === 0) {
            html += '<p style="color: green;">✅ No fake users found! Your site appears clean.</p>';
        } else {
            html += `<p style="color: #d63638;">⚠️ ${data.fake} fake users ready for cleanup.</p>`;
        }
        
        if (data.sample_emails && data.sample_emails.length > 0) {
            html += '<h4>Sample Fake User Emails (first 20):</h4>';
            html += '<div style="font-family: monospace; background: #f0f0f0; padding: 10px; max-height: 150px; overflow-y: auto; border: 1px solid #ddd;">';
            data.sample_emails.forEach(email => {
                html += email + '<br>';
            });
            html += '</div>';
        }
        
        html += '</div>';
        $('#scan-results').html(html);
        
        if (data.fake > 0) {
            $('#cleanup-summary').html(`
                <p>Ready to process <strong>${data.fake}</strong> fake users in batches of 100.</p>
                <p><em>Estimated time: ${Math.ceil(data.fake / 100)} minutes</em></p>
            `);
        }
    }
    
    function deleteFakeUsersInBatches(isDryRun) {
        const batchSize = 100;
        const total = fakeUserIds.length;
        let processed = 0;
        let deleted = 0;
        
        $('#delete-progress').html(`
            <p>${isDryRun ? 'Dry Run' : 'Deleting'}: 0 / ${total} users</p>
            <div class="cleanup-progress">
                <div class="cleanup-progress-bar" id="progress-bar" style="width: 0%"></div>
            </div>
            <div id="batch-status"></div>
        `);
        
        function processBatch() {
            if (fakeUserIds.length === 0) {
                const statusColor = isDryRun ? '#00a0d2' : '#00a32a';
                $('#delete-progress').append(`
                    <p style="color: ${statusColor}; font-weight: bold;">
                        ✅ ${isDryRun ? 'Dry run' : 'Cleanup'} complete! 
                        ${isDryRun ? 'Analyzed' : 'Processed'} ${processed} users.
                        ${!isDryRun ? `Successfully deleted ${deleted} fake users.` : ''}
                    </p>
                `);
                $('#delete-fake-users').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Start Cleanup Process');
                
                // Show next steps
                if (!isDryRun && deleted > 0) {
                    $('#delete-progress').append(`
                        <div class="validation-results" style="margin-top: 15px;">
                            <h4>Next Steps:</h4>
                            <ul>
                                <li>Review debug logs for any errors</li>
                                <li>Consider implementing reCAPTCHA on registration forms</li>
                                <li>Monitor user registrations for unusual patterns</li>
                                <li>Update security plugins and WordPress core</li>
                            </ul>
                        </div>
                    `);
                }
                return;
            }
            
            const batch = fakeUserIds.splice(0, batchSize);
            const batchNum = Math.ceil((processed + 1) / batchSize);
            const totalBatches = Math.ceil((processed + fakeUserIds.length + batch.length) / batchSize);
            
            $('#batch-status').html(`Processing batch ${batchNum} of ${totalBatches}...`);
            
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
                        processed += batch.length;
                        deleted += response.data.processed;
                        const percentage = Math.round((processed / total) * 100);
                        
                        $('#delete-progress p').text(`${isDryRun ? 'Dry Run' : 'Deleting'}: ${processed} / ${total} users (${deleted} ${isDryRun ? 'analyzed' : 'deleted'})`);
                        $('#progress-bar').css('width', percentage + '%');
                        
                        // Small delay between batches to prevent server overload
                        setTimeout(processBatch, 500);
                    } else {
                        showError('Batch processing failed: ' + response.data.message);
                        $('#delete-fake-users').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showError(`AJAX error during batch processing: ${error}`);
                    console.log('Batch error:', xhr.responseText);
                    $('#delete-fake-users').prop('disabled', false);
                }
            });
        }
        
        processBatch();
    }
    
    function showError(message) {
        const errorHtml = `<div class="error-box">❌ ${message}</div>`;
        $('#scan-results, #delete-progress, #validation-results').append(errorHtml);
        
        // Also log to console for debugging
        console.error('InterSoccer Cleanup Error:', message);
    }
    
    // Utility function to format numbers with commas
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // Auto-refresh progress for long operations
    function startProgressMonitor() {
        // Could implement real-time progress updates via separate AJAX calls
        // For now, the batched approach provides sufficient feedback
    }
});