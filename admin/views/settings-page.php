<?php
/**
 * Provide a admin area view for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h2><span class="dashicons dashicons-update"></span> Wasi to Directorist Sync</h2>
    <p><em>Developed by <a href="https://www.lytta.it/" target="_blank">Lytta.it</a></em></p>

    <div style="display:flex; gap: 20px; flex-wrap: wrap;">
        <div style="flex: 2; min-width: 400px;">
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
            <h3>Data Reference</h3>
            <p>Use these IDs to build your category mapping rules.</p>
            
            <h4>WASI Property Types</h4>
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
        echo '<p>Could not load Wasi Types. Check credentials.</p>';
    }
?>
            </div>

            <h4 style="margin-top:20px;">Directorist Categories (Target)</h4>
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
        echo '<p>No Directorist categories found.</p>';
    }
?>
            </div>
        </div>
        <?php
else: ?>
            <div style="flex: 1; min-width: 300px; padding: 15px; background: #fff8e5; border-left: 4px solid #f56e28;">
                <p><strong>Setup Required:</strong> Please enter your Company ID and Token and save changes to load reference data.</p>
            </div>
        <?php
endif; ?>
    </div>
</div>
