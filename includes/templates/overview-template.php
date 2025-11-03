<?php
/**
 * File: templates/overview-template.php
 * Description: HTML template for overview dashboard
 * Author: Jeremy Lee (Refactored by Claude)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// WordPress admin-style CSS
$inline_css = '
    .intersoccer-overview .dashboard-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
        gap: 20px; 
        margin-bottom: 20px; 
    }
    .intersoccer-overview .dashboard-card { 
        background: #fff; 
        padding: 20px; 
        border: 1px solid #c3c4c7; 
        border-radius: 4px; 
        text-align: center; 
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .intersoccer-overview .dashboard-card h3 { 
        margin: 0 0 15px; 
        font-size: 14px; 
        color: #1d2327; 
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .intersoccer-overview .dashboard-card p { 
        margin: 0; 
        font-size: 32px; 
        font-weight: 400; 
        color: #0073aa; 
        line-height: 1.2;
    }
    .intersoccer-overview .chart-container { 
        width: 100%; 
        height: 200px; 
        position: relative; 
        margin-top: 10px;
    }
    .intersoccer-overview .chart-error { 
        color: #d63638; 
        font-size: 12px; 
        margin-top: 10px; 
        font-style: italic;
    }
    .intersoccer-overview .cache-info { 
        background: #f6f7f7; 
        color: #50575e; 
        padding: 12px 16px; 
        margin-bottom: 20px; 
        border-radius: 4px; 
        font-size: 13px; 
        border-left: 4px solid #72aee6;
    }
    .intersoccer-overview .cache-info.error { 
        background: #fcf9e8; 
        border-left-color: #dba617; 
        color: #646970;
    }
    .intersoccer-overview .error-card { 
        background: #fcf0f1 !important; 
        border-color: #d63638 !important;
    }
    .intersoccer-overview .error-card p {
        color: #d63638 !important;
    }
    @media (max-width: 782px) {
        .intersoccer-overview .dashboard-grid { 
            grid-template-columns: 1fr; 
            gap: 15px;
        }
        .intersoccer-overview .chart-container { 
            height: 180px; 
        }
        .intersoccer-overview .dashboard-card {
            padding: 15px;
        }
        .intersoccer-overview .dashboard-card p {
            font-size: 24px;
        }
    }
';

echo '<style>' . $inline_css . '</style>';
?>

<div class="wrap intersoccer-overview">
    <h1 class="wp-heading-inline">
        <?php _e('Players Overview Dashboard', 'player-management'); ?>
    </h1>
    <a href="<?php echo add_query_arg('refresh', '1'); ?>" class="page-title-action">
        <?php _e('Refresh Data', 'player-management'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- Cache Information -->
    <div class="cache-info <?php echo isset($data['error']) ? 'error' : ''; ?>">
        <strong><?php _e('Data Status:', 'player-management'); ?></strong>
        <?php _e('Generated:', 'player-management'); ?> <?php echo esc_html($data['generation_time']); ?> |
        <?php _e('Users Processed:', 'player-management'); ?> <?php echo esc_html($data['total_users_processed']); ?> |
        <?php _e('Method:', 'player-management'); ?> <?php echo esc_html($data['processing_method'] ?? 'standard'); ?>
        <?php if (isset($data['error'])): ?>
            | <span style="color: #d63638; font-weight: 600;"><?php _e('Error:', 'player-management'); ?> <?php echo esc_html($data['error']); ?></span>
        <?php endif; ?>
        | <em><?php _e('Data cached for 30 minutes.', 'player-management'); ?></em>
    </div>
    
    <?php if (isset($data['error'])): ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Warning:', 'player-management'); ?></strong>
            <?php _e('The overview data could not be fully generated due to an error. Showing partial or fallback data.', 'player-management'); ?>
            <?php _e('Please check the debug logs or try refreshing the data.', 'player-management'); ?></p>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <!-- Total Players -->
        <div class="dashboard-card <?php echo ($data['total_players'] == 0 && isset($data['error'])) ? 'error-card' : ''; ?>">
            <h3><?php _e('Total Players', 'player-management'); ?></h3>
            <p><?php echo esc_html($data['total_players']); ?></p>
        </div>

        <!-- Users Without Players -->
        <div class="dashboard-card">
            <h3><?php _e('Users Without Players', 'player-management'); ?></h3>
            <p><?php echo esc_html($data['users_without_players']); ?></p>
        </div>

        <!-- Assigned vs Unassigned -->
        <div class="dashboard-card">
            <h3><?php _e('Assigned vs Unassigned', 'player-management'); ?></h3>
            <div class="chart-container">
                <canvas id="assignedChart"></canvas>
                <div id="assignedChart-error" class="chart-error" style="display: none;"><?php _e('Chart loading failed', 'player-management'); ?></div>
            </div>
        </div>

        <!-- Gender Breakdown -->
        <div class="dashboard-card">
            <h3><?php _e('Gender Breakdown', 'player-management'); ?></h3>
            <div class="chart-container">
                <canvas id="genderChart"></canvas>
                <div id="genderChart-error" class="chart-error" style="display: none;"><?php _e('Chart loading failed', 'player-management'); ?></div>
            </div>
        </div>

        <!-- Players by Canton -->
        <div class="dashboard-card">
            <h3><?php _e('Players by Canton', 'player-management'); ?></h3>
            <div class="chart-container">
                <canvas id="cantonChart"></canvas>
                <div id="cantonChart-error" class="chart-error" style="display: none;"><?php _e('Chart loading failed', 'player-management'); ?></div>
            </div>
        </div>

        <!-- Top 5 Cantons -->
        <div class="dashboard-card">
            <h3><?php _e('Top 5 Cantons', 'player-management'); ?></h3>
            <div class="chart-container">
                <canvas id="topCantonsChart"></canvas>
                <div id="topCantonsChart-error" class="chart-error" style="display: none;"><?php _e('Chart loading failed', 'player-management'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Performance Info -->
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php _e('Debug Information', 'player-management'); ?></h2>
            </div>
            <div class="inside">
                <p>
                    <strong><?php _e('Memory Peak:', 'player-management'); ?></strong> <?php echo $this->utils->format_bytes(memory_get_peak_usage(true)); ?> |
                    <strong><?php _e('Total Players:', 'player-management'); ?></strong> <?php echo $data['total_players']; ?> |
                    <strong><?php _e('Users Processed:', 'player-management'); ?></strong> <?php echo $data['total_users_processed']; ?> |
                    <strong><?php _e('Processing Method:', 'player-management'); ?></strong> <?php echo $data['processing_method'] ?? 'unknown'; ?> |
                    <strong><?php _e('Cache Key:', 'player-management'); ?></strong> intersoccer_overview_data_v3
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    // Chart data from PHP (with validation)
    const chartData = {
        assigned: <?php echo max(0, (int)$data['assigned_count']); ?>,
        unassigned: <?php echo max(0, (int)$data['unassigned_count']); ?>,
        genderData: {
            male: <?php echo max(0, (int)$data['gender_data']['male']); ?>,
            female: <?php echo max(0, (int)$data['gender_data']['female']); ?>,
            other: <?php echo max(0, (int)$data['gender_data']['other']); ?>
        },
        cantonLabels: <?php echo json_encode(array_keys($data['canton_data'])); ?>,
        cantonValues: <?php echo json_encode(array_values($data['canton_data'])); ?>,
        topCantonLabels: <?php echo json_encode(array_keys($data['top_cantons'])); ?>,
        topCantonValues: <?php echo json_encode(array_values($data['top_cantons'])); ?>
    };

    // Validate chart data
    if (!chartData.cantonLabels || chartData.cantonLabels.length === 0) {
        chartData.cantonLabels = [intersoccerChartLabels.noData];
        chartData.cantonValues = [0];
    }
    if (!chartData.topCantonLabels || chartData.topCantonLabels.length === 0) {
        chartData.topCantonLabels = [intersoccerChartLabels.noData];
        chartData.topCantonValues = [0];
    }

    // Check if Chart.js is loaded with timeout
    let chartCheckAttempts = 0;
    const maxChartCheckAttempts = 50; // 5 seconds
    
    function initCharts() {
        if (typeof Chart === 'undefined') {
            chartCheckAttempts++;
            if (chartCheckAttempts < maxChartCheckAttempts) {
                setTimeout(initCharts, 100);
                return;
            } else {
                console.error('Chart.js failed to load after 5 seconds');
                document.querySelectorAll('.chart-error').forEach(function(el) {
                    el.style.display = 'block';
                    el.textContent = intersoccerChartLabels.chartLoadingFailed;
                });
                return;
            }
        }

        // Chart.js is loaded, proceed with rendering
        console.log('Chart.js loaded successfully, rendering charts...');

        // Common chart options with WordPress admin colors
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { 
                        color: '#1d2327',
                        font: {
                            family: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif',
                            size: 12
                        }
                    }
                }
            }
        };

        const barOptions = {
            ...commonOptions,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        color: '#646970',
                        font: { size: 11 }
                    },
                    grid: { color: '#c3c4c7' }
                },
                x: {
                    ticks: { 
                        color: '#646970',
                        font: { size: 11 }
                    },
                    grid: { color: '#c3c4c7' }
                }
            },
            plugins: { legend: { display: false } }
        };

        // WordPress admin color palette
        const wpColors = {
            primary: '#0073aa',
            secondary: '#005177',
            accent: '#72aee6',
            success: '#00a32a',
            warning: '#dba617',
            error: '#d63638',
            neutral: '#646970'
        };

        // Render charts with WordPress styling

        // Assigned Chart
        try {
            const assignedCtx = document.getElementById('assignedChart');
            if (assignedCtx) {
                new Chart(assignedCtx, {
                    type: 'pie',
                    data: {
                        labels: [intersoccerChartLabels.assigned, intersoccerChartLabels.unassigned],
                        datasets: [{
                            data: [chartData.assigned, chartData.unassigned],
                            backgroundColor: [wpColors.success, wpColors.neutral],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        ...commonOptions,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#1d2327',
                                    padding: 15,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Error rendering assigned chart:', e);
            document.getElementById('assignedChart-error').style.display = 'block';
        }

        // Gender Chart
        try {
            const genderCtx = document.getElementById('genderChart');
            if (genderCtx) {
                new Chart(genderCtx, {
                    type: 'pie',
                    data: {
                        labels: [intersoccerChartLabels.male, intersoccerChartLabels.female, intersoccerChartLabels.other],
                        datasets: [{
                            data: [chartData.genderData.male, chartData.genderData.female, chartData.genderData.other],
                            backgroundColor: [wpColors.primary, wpColors.accent, wpColors.neutral],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        ...commonOptions,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#1d2327',
                                    padding: 15,
                                    usePointStyle: true
                                }
                            }
                        }
                    }
                });
            }
        } catch (e) {
            console.error('Error rendering gender chart:', e);
            document.getElementById('genderChart-error').style.display = 'block';
        }

        // Canton Chart
        try {
            const cantonCtx = document.getElementById('cantonChart');
            if (cantonCtx) {
                new Chart(cantonCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.cantonLabels,
                        datasets: [{
                            label: 'Players',
                            data: chartData.cantonValues,
                            backgroundColor: wpColors.primary,
                            borderColor: wpColors.secondary,
                            borderWidth: 1
                        }]
                    },
                    options: barOptions
                });
            }
        } catch (e) {
            console.error('Error rendering canton chart:', e);
            document.getElementById('cantonChart-error').style.display = 'block';
        }

        // Top Cantons Chart
        try {
            const topCantonsCtx = document.getElementById('topCantonsChart');
            if (topCantonsCtx) {
                new Chart(topCantonsCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.topCantonLabels,
                        datasets: [{
                            label: 'Registrations',
                            data: chartData.topCantonValues,
                            backgroundColor: wpColors.accent,
                            borderColor: wpColors.primary,
                            borderWidth: 1
                        }]
                    },
                    options: barOptions
                });
            }
        } catch (e) {
            console.error('Error rendering top cantons chart:', e);
            document.getElementById('topCantonsChart-error').style.display = 'block';
        }

        console.log('InterSoccer Overview charts loaded successfully');
    }

    // Start the chart initialization process
    initCharts();
});
</script>