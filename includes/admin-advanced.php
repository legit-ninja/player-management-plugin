<?php
/**
 * Admin Advanced Settings
 * Changes (Updated):
 * - Removed `use WP_Background_Process;` to avoid namespace error.
 * - Added conditional check for WP_Background_Process class.
 * - Implemented synchronous attribute creation fallback if library is missing.
 * - Added admin notice to install wp-background-processing library.
 * - Retained CSV import, batch processing (when library available), and CodeMirror.
 * Testing:
 * - Upload wc-product-export-3-5-2025.csv in Players & Orders > Advanced, verify pa_camp-terms and pa_intersoccer-venues taxonomies.
 * - Check attribute creation (with/without wp-background-processing), confirm attributes appear in WooCommerce > Attributes.
 * - Create a camp term in Camp Terms post type, verify it appears in product attributes.
 * - Purge players, confirm batch deletion and audit log.
 * - Edit JSON in textarea, ensure CodeMirror loads and validates syntax.
 * - If wp-background-processing is not installed, verify admin notice appears.
 */

defined('ABSPATH') or die('No script kiddies please!');

// Define background process class only if library is available
if (class_exists('WP_Background_Process')) {
    class InterSoccer_Attribute_Process extends WP_Background_Process {
        protected $action = 'intersoccer_create_attributes';

        protected function task($attribute) {
            $attribute_id = wc_create_attribute([
                'name' => $attribute['label'],
                'slug' => $attribute['slug'],
                'type' => 'select',
                'order_by' => 'menu_order',
                'has_archives' => false,
            ]);
            if (!is_wp_error($attribute_id)) {
                register_taxonomy($attribute['slug'], 'product', [
                    'label' => $attribute['label'],
                    'hierarchical' => false,
                    'public' => false,
                    'show_ui' => true,
                ]);
                foreach ($attribute['values'] as $slug => $name) {
                    if (!term_exists($slug, $attribute['slug'])) {
                        wp_insert_term($name, $attribute['slug'], ['slug' => $slug]);
                    }
                }
            }
            return false;
        }
    }
}

add_action('init', function() {
    register_post_type('intersoccer_camp_term', [
        'labels' => ['name' => __('Camp Terms', 'intersoccer-player-management')],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'custom-fields'],
    ]);
});

add_action('admin_notices', function() {
    if (!class_exists('WP_Background_Process')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Please install the wp-background-processing library for asynchronous attribute creation in InterSoccer Player Management.', 'intersoccer-player-management') . '</p></div>';
    }
});

function intersoccer_render_advanced_tab() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'intersoccer-player-management'));
    }

    wp_enqueue_script('codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.js', [], '5.65.7', true);
    wp_enqueue_script('codemirror-json', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/mode/javascript/javascript.min.js', [], '5.65.7', true);
    wp_enqueue_style('codemirror', 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.7/codemirror.min.css', [], '5.65.7');

    $message = '';
    $current_year = date('Y');
    $cutoff_year = $current_year - 14;

    if (isset($_POST['auto_create_attributes']) && wp_verify_nonce($_POST['advanced_nonce'], 'intersoccer_advanced_actions')) {
        $attributes_to_create = [
            'pa_booking-type' => ['label' => 'Booking Type', 'values' => ['week' => 'Week', 'full_term' => 'Full Term', 'day' => 'Day', 'buyclub' => 'BuyClub']],
            'pa_intersoccer-venues' => ['label' => 'Venues', 'values' => [
                'varembe' => 'Varembe',
                'seefeld' => 'FC Seefeld',
                'nyon' => 'Nyon',
                'rochettaz' => 'Stade de Rochettaz',
            ]],
        ];

        if (class_exists('WP_Background_Process')) {
            $process = new InterSoccer_Attribute_Process();
            foreach ($attributes_to_create as $attribute_name => $attribute_data) {
                $process->push_to_queue([
                    'slug' => $attribute_name,
                    'label' => $attribute_data['label'],
                    'values' => $attribute_data['values'],
                ]);
            }
            $process->save()->dispatch();
            $message .= __('Attribute creation queued. Check status in Site Health > Info.', 'intersoccer-player-management') . '<br>';
        } else {
            // Synchronous fallback
            foreach ($attributes_to_create as $attribute_name => $attribute_data) {
                $attribute_id = wc_create_attribute([
                    'name' => $attribute_data['label'],
                    'slug' => $attribute_name,
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                ]);
                if (!is_wp_error($attribute_id)) {
                    register_taxonomy($attribute_name, 'product', [
                        'label' => $attribute_data['label'],
                        'hierarchical' => false,
                        'public' => false,
                        'show_ui' => true,
                    ]);
                    foreach ($attribute_data['values'] as $slug => $name) {
                        if (!term_exists($slug, $attribute_name)) {
                            wp_insert_term($name, $attribute_name, ['slug' => $slug]);
                        }
                    }
                }
            }
            $message .= __('Attributes created synchronously.', 'intersoccer-player-management') . '<br>';
        }
    }

    if (isset($_POST['import_camp_terms']) && wp_verify_nonce($_POST['advanced_nonce'], 'intersoccer_advanced_actions')) {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $csv_data = file_get_contents($_FILES['csv_file']['tmp_name']);
            $lines = explode("\n", $csv_data);
            $terms = [];
            foreach ($lines as $line) {
                $data = str_getcsv($line);
                if (isset($data[64]) && strpos($data[64], 'Summer Week') !== false) {
                    $terms[] = $data[64];
                }
            }
            foreach ($terms as $term) {
                wp_insert_term($term, 'pa_camp-terms', ['slug' => sanitize_title($term)]);
            }
            $message .= __('Camp terms imported successfully.', 'intersoccer-player-management') . '<br>';
        } else {
            $message .= __('No CSV file uploaded.', 'intersoccer-player-management') . '<br>';
        }
    }

    if (isset($_POST['purge_older_players']) && wp_verify_nonce($_POST['advanced_nonce'], 'intersoccer_advanced_actions')) {
        if (!isset($_POST['confirm_purge']) || $_POST['confirm_purge'] !== 'yes') {
            $message .= __('Please confirm player purge.', 'intersoccer-player-management') . '<br>';
        } else {
            $args = [
                'post_type' => 'player',
                'posts_per_page' => 100,
                'meta_query' => [
                    [
                        'key' => 'date_of_birth',
                        'value' => "$cutoff_year-12-31",
                        'compare' => '<=',
                        'type' => 'DATE',
                    ],
                ],
            ];
            $query = new WP_Query($args);
            $deleted_count = 0;
            while ($query->have_posts()) {
                $query->the_post();
                $dob = get_post_meta(get_the_ID(), 'date_of_birth', true);
                $birth_year = (int) substr($dob, 0, 4);
                if ($current_year - $birth_year >= 14) {
                    wp_delete_post(get_the_ID(), true);
                    $deleted_count++;
                    error_log(sprintf('Purged player ID %d (DOB: %s) on %s', get_the_ID(), $dob, current_time('mysql')));
                }
            }
            wp_reset_postdata();
            $message .= sprintf(__('Deleted %d players in batch.', 'intersoccer-player-management'), $deleted_count) . '<br>';
        }
    }

    ?>
    <div class="wrap">
        <h1><?php _e('Advanced Settings', 'intersoccer-player-management'); ?></h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('intersoccer_advanced_actions', 'advanced_nonce'); ?>
            <h2><?php _e('Auto-Create Attributes', 'intersoccer-player-management'); ?></h2>
            <p><input type="submit" name="auto_create_attributes" class="button button-primary" value="<?php _e('Create Attributes', 'intersoccer-player-management'); ?>"></p>
            <h2><?php _e('Import Camp Terms from CSV', 'intersoccer-player-management'); ?></h2>
            <p><input type="file" name="csv_file" accept=".csv"></p>
            <p><input type="submit" name="import_camp_terms" class="button button-primary" value="<?php _e('Import Terms', 'intersoccer-player-management'); ?>"></p>
            <h2><?php _e('Purge Older Players', 'intersoccer-player-management'); ?></h2>
            <p><?php printf(__('Purge players born before %s.', 'intersoccer-player-management'), $cutoff_year); ?></p>
            <p><label><input type="checkbox" name="confirm_purge" value="yes"> <?php _e('Confirm Purge', 'intersoccer-player-management'); ?></label></p>
            <p><input type="submit" name="purge_older_players" class="button button-danger" value="<?php _e('Purge Players', 'intersoccer-player-management'); ?>"></p>
            <h2><?php _e('Custom JSON Attributes', 'intersoccer-player-management'); ?></h2>
            <textarea id="json_attributes" name="json_attributes" rows="10" cols="50"></textarea>
            <p><input type="submit" name="save_json_attributes" class="button button-primary" value="<?php _e('Save JSON', 'intersoccer-player-management'); ?>"></p>
        </form>
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo $message; ?></p></div>
        <?php endif; ?>
        <script>
            jQuery(document).ready(function($) {
                if (typeof CodeMirror !== 'undefined') {
                    CodeMirror.fromTextArea(document.getElementById('json_attributes'), {
                        mode: 'application/json',
                        lineNumbers: true,
                        theme: 'default'
                    });
                }
            });
        </script>
    </div>
    <?php
}
?>
