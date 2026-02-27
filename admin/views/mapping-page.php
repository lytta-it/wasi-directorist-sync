<?php
/**
 * Provide a admin area view for the mapping settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$api = new Lytta_Wasi_API();
$options = get_option('lytta_wasi_settings', []);
$target = isset($options['target_platform']) ? $options['target_platform'] : 'directorist';

if (isset($_POST['lytta_wasi_mapping_nonce']) && wp_verify_nonce($_POST['lytta_wasi_mapping_nonce'], 'lytta_save_mapping')) {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized user', 'lytta-wasi-sync'));
    }

    $new_mapping = [];
    if (!empty($_POST['wasi_map']) && is_array($_POST['wasi_map'])) {
        foreach ($_POST['wasi_map'] as $wasi_id => $wp_cat) {
            $wasi_id = sanitize_text_field($wasi_id);
            $wp_cat = sanitize_text_field($wp_cat);
            if (!empty($wp_cat)) {
                $new_mapping[] = "{$wasi_id}={$wp_cat}";
            }
        }
    }

    $options['mapping'] = implode("\n", $new_mapping);
    update_option('lytta_wasi_settings', $options);

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Mapping saved logically!', 'lytta-wasi-sync') . '</p></div>';
}

// Parse existing mapping to pre-fill dropdowns
$current_mapping_string = isset($options['mapping']) ? $options['mapping'] : '';
$current_rules = [];
$lines = explode("\n", $current_mapping_string);
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($w_ids, $cat_name) = explode('=', $line, 2);
        // It could be multiple Wasi IDs mapped to the same category "1,2=Apartment"
        $ids_array = explode(',', $w_ids);
        foreach ($ids_array as $wid) {
            $current_rules[trim($wid)] = trim($cat_name);
        }
    }
}
?>

<div class="wrap">
    <h2>
        <span class="dashicons dashicons-update"></span> Wasi Sync PRO 
        <?php
if ($api->is_configured()) {
    $check = $api->get_property_types();
    if (!is_wp_error($check)) {
        echo '<span style="font-size:12px; vertical-align:middle; background:#46b450; color:white; padding:3px 8px; border-radius:4px; margin-left:10px;">' . esc_html__('Connected', 'lytta-wasi-sync') . '</span>';
    }
    else {
        echo '<span style="font-size:12px; vertical-align:middle; background:#dc3232; color:white; padding:3px 8px; border-radius:4px; margin-left:10px;">' . esc_html__('API Failed', 'lytta-wasi-sync') . '</span>';
    }
}
else {
    echo '<span style="font-size:12px; vertical-align:middle; background:#999; color:white; padding:3px 8px; border-radius:4px; margin-left:10px;">' . esc_html__('Not Configured', 'lytta-wasi-sync') . '</span>';
}
?>
    </h2>
    <p><em>Developed and maintained by <a href="https://www.lytta.it/" target="_blank"><strong>Lytta</strong></a></em></p>

    <h2 class="nav-tab-wrapper">
        <a href="?page=lytta-wasi-sync&tab=settings" class="nav-tab"><?php esc_html_e('Base API Settings', 'lytta-wasi-sync'); ?></a>
        <a href="?page=lytta-wasi-sync&tab=mapping" class="nav-tab nav-tab-active"><?php esc_html_e('Category Mapping', 'lytta-wasi-sync'); ?></a>
        <a href="?page=lytta-wasi-sync&tab=contact" class="nav-tab"><?php esc_html_e('Contact, Support & PRO License', 'lytta-wasi-sync'); ?></a>
    </h2>

    <div style="margin-top:20px; max-width: 800px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h3><?php esc_html_e('Visual Category Mapper', 'lytta-wasi-sync'); ?></h3>
        <p><?php esc_html_e('Match the property types from Wasi directly to your WordPress categories.', 'lytta-wasi-sync'); ?></p>

        <?php if (!$api->is_configured()): ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e('Please configure your Company ID and Token in the Base Settings first.', 'lytta-wasi-sync'); ?></p></div>
        <?php
else:
    $wasi_types = $api->get_property_types();
    if (is_wp_error($wasi_types)) {
        echo '<div class="notice notice-error inline"><p>' . esc_html__('Error loading Wasi data.', 'lytta-wasi-sync') . '</p></div>';
    }
    else {
        // Fetch WP Categories based on Target Platform
        $wp_categories = [];
        if ($target === 'directorist' && defined('ATBDP_VERSION')) {
            $terms = get_terms(['taxonomy' => 'at_biz_dir-category', 'hide_empty' => false]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $wp_categories[$t->name] = $t->name;
                }
            }
        }
        elseif ($target === 'acf') {
            // For ACF we assume standard 'category' taxonomy unless specified
            $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
            if (!is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $wp_categories[$t->name] = $t->name;
                }
            }
        }
?>
            <form method="post" action="">
                <?php wp_nonce_field('lytta_save_mapping', 'lytta_wasi_mapping_nonce'); ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50%;"><strong><?php esc_html_e('Wasi Property Type', 'lytta-wasi-sync'); ?></strong></th>
                            <th style="width: 50%;"><strong><?php echo sprintf(esc_html__('%s Category', 'lytta-wasi-sync'), ucfirst($target)); ?></strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
        foreach ($wasi_types as $val) {
            if (!is_array($val))
                continue;

            $id = isset($val['id_property_type']) ? $val['id_property_type'] : (isset($val['id']) ? $val['id'] : '');
            $name = isset($val['nombre']) ? $val['nombre'] : (isset($val['name']) ? $val['name'] : 'Unknown');

            if (empty($id))
                continue;

            $selected_cat = isset($current_rules[$id]) ? $current_rules[$id] : '';
?>
                            <tr>
                                <td>
                                    <?php echo esc_html("{$id} - {$name}"); ?>
                                </td>
                                <td>
                                    <?php if (!empty($wp_categories)): ?>
                                        <select name="wasi_map[<?php echo esc_attr($id); ?>]" style="width:100%; max-width: 300px;">
                                            <option value=""><?php esc_html_e('-- Do not import --', 'lytta-wasi-sync'); ?></option>
                                            <?php foreach ($wp_categories as $cat_slug => $cat_name): ?>
                                                <option value="<?php echo esc_attr($cat_name); ?>" <?php selected($selected_cat, $cat_name); ?>>
                                                    <?php echo esc_html($cat_name); ?>
                                                </option>
                                            <?php
                endforeach; ?>
                                        </select>
                                    <?php
            else: ?>
                                        <span style="color:#dc3232;"><?php esc_html_e('No categories found. Please create them in WordPress first.', 'lytta-wasi-sync'); ?></span>
                                    <?php
            endif; ?>
                                </td>
                            </tr>
                            <?php
        }
?>
                    </tbody>
                </table>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Mapping', 'lytta-wasi-sync'); ?>">
                </p>
            </form>
        <?php
    }
endif; ?>
    </div>
</div>
