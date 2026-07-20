<?php
/**
 * File: templates/overview-template.php
 * Description: Actionable KPI overview — attention first, one distribution chart.
 */

if (!defined('ABSPATH')) {
    exit;
}

$total_players = (int) ($data['total_players'] ?? 0);
$users_without = (int) ($data['users_without_players'] ?? 0);
$never_booked = (int) ($data['never_booked_lifetime'] ?? 0);
$incomplete = (int) ($data['incomplete_profiles'] ?? 0);
$canton_data = is_array($data['canton_data'] ?? null) ? $data['canton_data'] : ['Unknown' => 0];
$generation_time = (string) ($data['generation_time'] ?? '');
$has_error = isset($data['error']);

$url_no_players = function_exists('intersoccer_pm_overview_filter_url')
    ? intersoccer_pm_overview_filter_url('no_players')
    : admin_url('admin.php?page=intersoccer-all-players');
$url_never_booked = function_exists('intersoccer_pm_overview_filter_url')
    ? intersoccer_pm_overview_filter_url('never_booked')
    : admin_url('admin.php?page=intersoccer-all-players');
$url_incomplete = function_exists('intersoccer_pm_overview_filter_url')
    ? intersoccer_pm_overview_filter_url('incomplete')
    : admin_url('admin.php?page=intersoccer-all-players');

$refresh_url = add_query_arg('refresh', '1');

$inline_css = '
    .intersoccer-overview .overview-status {
        color: #646970;
        font-size: 13px;
        margin: 8px 0 20px;
    }
    .intersoccer-overview .kpi-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .intersoccer-overview .kpi-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 16px 18px;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
    }
    .intersoccer-overview .kpi-card h2 {
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
        line-height: 1.3;
    }
    .intersoccer-overview .kpi-value {
        margin: 0 0 10px;
        font-size: 28px;
        font-weight: 600;
        color: #1d2327;
        line-height: 1.2;
    }
    .intersoccer-overview .kpi-card .button {
        margin-top: 0;
    }
    .intersoccer-overview .attention-section {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 16px 20px;
        margin-bottom: 24px;
    }
    .intersoccer-overview .attention-section h2 {
        margin: 0 0 12px;
        font-size: 14px;
        font-weight: 600;
    }
    .intersoccer-overview .attention-list {
        margin: 0;
        padding-left: 1.25em;
    }
    .intersoccer-overview .attention-list li {
        margin-bottom: 8px;
        line-height: 1.5;
    }
    .intersoccer-overview .attention-empty {
        margin: 0;
        color: #646970;
    }
    .intersoccer-overview .distribution-section {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 16px 20px;
        margin-bottom: 20px;
        max-width: 720px;
    }
    .intersoccer-overview .distribution-section h2 {
        margin: 0 0 12px;
        font-size: 14px;
        font-weight: 600;
    }
    .intersoccer-overview .chart-container {
        width: 100%;
        height: 260px;
        position: relative;
    }
    .intersoccer-overview .chart-error {
        color: #d63638;
        font-size: 12px;
        margin-top: 8px;
        font-style: italic;
    }
    @media (max-width: 782px) {
        .intersoccer-overview .kpi-strip {
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .intersoccer-overview .kpi-value {
            font-size: 24px;
        }
    }
';

echo '<style>' . $inline_css . '</style>';
?>

<div class="wrap intersoccer-overview">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Players overview', 'player-management'); ?>
    </h1>
    <a href="<?php echo esc_url($refresh_url); ?>" class="page-title-action">
        <?php esc_html_e('Refresh', 'player-management'); ?>
    </a>
    <hr class="wp-header-end">

    <p class="overview-status">
        <?php
        printf(
            /* translators: %s: generation datetime */
            esc_html__('Updated %s', 'player-management'),
            esc_html($generation_time !== '' ? $generation_time : '—')
        );
        ?>
        ·
        <a href="<?php echo esc_url($refresh_url); ?>"><?php esc_html_e('Refresh', 'player-management'); ?></a>
        <?php if ($has_error) : ?>
            · <span class="notice-error" style="color:#d63638;"><?php esc_html_e('Partial data — see notice below.', 'player-management'); ?></span>
        <?php endif; ?>
    </p>

    <?php if ($has_error) : ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Warning:', 'player-management'); ?></strong>
                <?php esc_html_e('Overview data could not be fully generated. Showing partial or fallback figures.', 'player-management'); ?>
            </p>
        </div>
    <?php endif; ?>

    <section class="kpi-strip" aria-label="<?php esc_attr_e('Key metrics', 'player-management'); ?>">
        <article class="kpi-card" title="<?php esc_attr_e('Total participant profiles on parent accounts', 'player-management'); ?>">
            <h2><?php esc_html_e('Participants', 'player-management'); ?></h2>
            <p class="kpi-value"><?php echo esc_html(number_format_i18n($total_players)); ?></p>
        </article>

        <article class="kpi-card" title="<?php esc_attr_e('Customer accounts with no participant profiles', 'player-management'); ?>">
            <h2><?php esc_html_e('Parents with 0 kids', 'player-management'); ?></h2>
            <p class="kpi-value"><?php echo esc_html(number_format_i18n($users_without)); ?></p>
            <?php if ($users_without > 0) : ?>
                <a class="button button-secondary" href="<?php echo esc_url($url_no_players); ?>">
                    <?php esc_html_e('Review', 'player-management'); ?>
                </a>
            <?php endif; ?>
        </article>

        <article class="kpi-card" title="<?php esc_attr_e('Participants with no completed or processing bookings ever', 'player-management'); ?>">
            <h2><?php esc_html_e('Never booked (lifetime)', 'player-management'); ?></h2>
            <p class="kpi-value"><?php echo esc_html(number_format_i18n($never_booked)); ?></p>
            <?php if ($never_booked > 0) : ?>
                <a class="button button-secondary" href="<?php echo esc_url($url_never_booked); ?>">
                    <?php esc_html_e('Review', 'player-management'); ?>
                </a>
            <?php endif; ?>
        </article>

        <article class="kpi-card" title="<?php esc_attr_e('Participants missing date of birth or medical information', 'player-management'); ?>">
            <h2><?php esc_html_e('Incomplete profiles', 'player-management'); ?></h2>
            <p class="kpi-value"><?php echo esc_html(number_format_i18n($incomplete)); ?></p>
            <?php if ($incomplete > 0) : ?>
                <a class="button button-secondary" href="<?php echo esc_url($url_incomplete); ?>">
                    <?php esc_html_e('Review', 'player-management'); ?>
                </a>
            <?php endif; ?>
        </article>
    </section>

    <section class="attention-section" aria-labelledby="overview-attention-heading">
        <h2 id="overview-attention-heading"><?php esc_html_e('Attention', 'player-management'); ?></h2>
        <?php if ($users_without === 0 && $never_booked === 0 && $incomplete === 0) : ?>
            <p class="attention-empty"><?php esc_html_e('No follow-ups right now.', 'player-management'); ?></p>
        <?php else : ?>
            <ul class="attention-list">
                <?php if ($users_without > 0) : ?>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: number of accounts */
                            esc_html(_n(
                                '%s account with no participants.',
                                '%s accounts with no participants.',
                                $users_without,
                                'player-management'
                            )),
                            esc_html(number_format_i18n($users_without))
                        );
                        ?>
                        <a href="<?php echo esc_url($url_no_players); ?>"><?php esc_html_e('Review', 'player-management'); ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($never_booked > 0) : ?>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: number of participants */
                            esc_html(_n(
                                '%s participant has never booked (lifetime).',
                                '%s participants have never booked (lifetime).',
                                $never_booked,
                                'player-management'
                            )),
                            esc_html(number_format_i18n($never_booked))
                        );
                        ?>
                        <a href="<?php echo esc_url($url_never_booked); ?>"><?php esc_html_e('Review', 'player-management'); ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($incomplete > 0) : ?>
                    <li>
                        <?php
                        printf(
                            /* translators: %s: number of participants */
                            esc_html(_n(
                                '%s incomplete participant profile.',
                                '%s incomplete participant profiles.',
                                $incomplete,
                                'player-management'
                            )),
                            esc_html(number_format_i18n($incomplete))
                        );
                        ?>
                        <a href="<?php echo esc_url($url_incomplete); ?>"><?php esc_html_e('Review', 'player-management'); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="distribution-section" aria-labelledby="overview-canton-heading">
        <h2 id="overview-canton-heading"><?php esc_html_e('Participants by canton', 'player-management'); ?></h2>
        <div class="chart-container" role="img" aria-label="<?php esc_attr_e('Bar chart of participants by canton', 'player-management'); ?>">
            <canvas id="cantonChart"></canvas>
            <div id="cantonChart-error" class="chart-error" style="display: none;">
                <?php esc_html_e('Chart loading failed', 'player-management'); ?>
            </div>
        </div>
    </section>

    <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><?php esc_html_e('Debug information', 'player-management'); ?></h2>
            </div>
            <div class="inside">
                <p>
                    <strong><?php esc_html_e('Users processed:', 'player-management'); ?></strong>
                    <?php echo esc_html((string) ($data['total_users_processed'] ?? 0)); ?>
                    |
                    <strong><?php esc_html_e('Method:', 'player-management'); ?></strong>
                    <?php echo esc_html((string) ($data['processing_method'] ?? 'unknown')); ?>
                    |
                    <strong><?php esc_html_e('Cache key:', 'player-management'); ?></strong>
                    intersoccer_overview_data_v4
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const cantonLabels = <?php echo wp_json_encode(array_keys($canton_data)); ?>;
    const cantonValues = <?php echo wp_json_encode(array_map('intval', array_values($canton_data))); ?>;
    const noDataLabel = (typeof intersoccerChartLabels !== 'undefined' && intersoccerChartLabels.noData)
        ? intersoccerChartLabels.noData
        : 'No Data';
    const playersLabel = (typeof intersoccerChartLabels !== 'undefined' && intersoccerChartLabels.players)
        ? intersoccerChartLabels.players
        : 'Participants';

    const labels = cantonLabels.length ? cantonLabels : [noDataLabel];
    const values = cantonValues.length ? cantonValues : [0];

    function showChartError(id) {
        const el = document.getElementById(id + '-error');
        if (el) {
            el.style.display = 'block';
        }
    }

    if (typeof Chart === 'undefined') {
        showChartError('cantonChart');
        return;
    }

    try {
        const ctx = document.getElementById('cantonChart');
        if (!ctx) {
            return;
        }
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: playersLabel,
                    data: values,
                    backgroundColor: 'rgba(34, 113, 177, 0.65)',
                    borderColor: 'rgba(34, 113, 177, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    } catch (e) {
        showChartError('cantonChart');
    }
});
</script>
