<?php
/**
 * Provide a admin area view for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h2>
        <span class="dashicons dashicons-update"></span> Wasi Sync PRO 
        <?php
$api_check = new Lytta_Wasi_API();
if ($api_check->is_configured()) {
    // Quick ping using get_property_types which is fast
    $check = $api_check->get_property_types();
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
        <a href="?page=lytta-wasi-sync&tab=settings" class="nav-tab nav-tab-active"><?php esc_html_e('Base API Settings', 'lytta-wasi-sync'); ?></a>
        <a href="?page=lytta-wasi-sync&tab=mapping" class="nav-tab"><?php esc_html_e('Category Mapping', 'lytta-wasi-sync'); ?></a>
        <a href="?page=lytta-wasi-sync&tab=contact" class="nav-tab"><?php esc_html_e('Contact, Support & PRO License', 'lytta-wasi-sync'); ?></a>
    </h2>

    <?php
$options = get_option('lytta_wasi_settings', []);
$is_pro = lytta_wasi_is_pro();
if (!$is_pro):
?>
    <div style="background: #fff8e5; border-left: 4px solid #f56e28; padding: 10px 15px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <p style="margin:0;"><strong><?php esc_html_e('Warning:', 'lytta-wasi-sync'); ?></strong> <?php echo wp_kses_post(__('You are using the <strong style="color:red;">LIMITED FREE Version</strong>. A <strong>maximum of 10 properties</strong> will be downloaded with only 1 cover image. To unlock mass downloading, enter the "PRO License" below or <a href="?page=lytta-wasi-sync&tab=contact">click here to purchase one</a>.', 'lytta-wasi-sync')); ?></p>
    </div>
    <?php
endif; ?>

    <div style="display:flex; gap: 20px; flex-wrap: wrap; margin-top:20px;">
        <div style="flex: 2; min-width: 400px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <form action="options.php" method="post">
                <?php
settings_fields('lytta_wasi_plugin_page');
do_settings_sections('lytta_wasi_plugin_page');
submit_button();
?>
            </form>
        </div>

        <?php
$api = new Lytta_Wasi_API();
if ($api->is_configured()):
?>
        <div style="flex: 1; min-width: 300px; background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px;">
            <h3><?php esc_html_e('Data Reference', 'lytta-wasi-sync'); ?></h3>
            <p><?php esc_html_e('Use these IDs to build your category mapping rules.', 'lytta-wasi-sync'); ?></p>
            
            <h4><?php esc_html_e('WASI Property Types', 'lytta-wasi-sync'); ?></h4>
            <div style="max-height: 250px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                <?php
    $types = $api->get_property_types();
    if (!empty($types) && !is_wp_error($types)) {
        echo '<ul style="margin: 0;">';
        foreach ($types as $val) {
            if (is_array($val) && (isset($val['id_property_type']) || isset($val['id']))) {
                $id = isset($val['id_property_type']) ? $val['id_property_type'] : $val['id'];
                $name = isset($val['nombre']) ? $val['nombre'] : (isset($val['name']) ? $val['name'] : 'Unknown');
                echo "<li><strong>{$id}</strong>: {$name}</li>";
            }
        }
        echo '</ul>';
    }
    else {
        echo '<p>' . esc_html__('Could not load Wasi Types. Check credentials.', 'lytta-wasi-sync') . '</p>';
    }
?>
            </div>

            <h4 style="margin-top:20px;"><?php esc_html_e('Directorist Categories (Target)', 'lytta-wasi-sync'); ?></h4>
            <div style="max-height: 250px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                <?php
    $terms = get_terms([
        'taxonomy' => 'at_biz_dir-category',
        'hide_empty' => false,
    ]);
    if (!is_wp_error($terms) && !empty($terms)) {
        echo '<ul style="margin:0;">';
        foreach ($terms as $term) {
            echo "<li>{$term->name}</li>";
        }
        echo '</ul>';
    }
    else {
        echo '<p>' . esc_html__('No Directorist categories found.', 'lytta-wasi-sync') . '</p>';
    }
?>
            </div>
        </div>
        <?php
else: ?>
            <div style="flex: 1; min-width: 300px; padding: 15px; background: #fff8e5; border-left: 4px solid #f56e28;">
                <p><strong><?php esc_html_e('Setup Required:', 'lytta-wasi-sync'); ?></strong> <?php esc_html_e('Please enter your Company ID and Token and save changes to load reference data.', 'lytta-wasi-sync'); ?></p>
            </div>
        <?php
endif; ?>
    </div>
</div>
