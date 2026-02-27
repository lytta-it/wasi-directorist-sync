<?php
/**
 * Adapter mapping logic for Advanced Custom Fields (ACF)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Adapter_ACF
{

    private $config;

    public function __construct($config_mapping)
    {
        // Here we could allow user to configure their custom post type, but for simplicity
        // developers usually build custom post types named 'property' or 'immobili'.
        // We'll use a dynamic ACF configuration, but default to 'property'. 
        $this->config = [
            'mapping' => $config_mapping
        ];
    }

    public function map_post_args($wasi_id, $title, $desc_html, $author_id)
    {
        return [
            'post_title' => $title,
            'post_content' => wp_kses_post($desc_html),
            'post_excerpt' => wp_trim_words(strip_tags($desc_html), 20),
            'post_status' => 'draft',
            'post_type' => 'property', // ACF logic usually acts on "property" CPT
            'post_author' => $author_id,
        ];
    }

    public function set_taxonomies($post_id, $immobile)
    {
        // For ACF, developers usually create 'property_category' and 'property_location'
        if (isset($immobile['id_property_type'])) {
            $cat_name = 'Residencial';
            // Simple mapping for now
            $id_type = $immobile['id_property_type'];
            if (in_array($id_type, [2, 14, 19, 20, 21, 25, 33]))
                $cat_name = 'Apartamento';
            elseif (in_array($id_type, [1, 7, 10, 11, 13, 22, 24, 27, 28]))
                $cat_name = 'Casa';

            wp_set_object_terms($post_id, $cat_name, 'property_category');
        }

        $city = isset($immobile['city_label']) ? trim($immobile['city_label']) : '';
        if (!empty($city)) {
            wp_set_object_terms($post_id, sanitize_text_field($city), 'property_location');
        }
    }

    public function map_fields($post_id, $immobile, $wasi_id, $descrizione, $plan_id)
    {
        $options = get_option('lytta_wasi_settings', []);
        $field_mapping = isset($options['field_mapping']) ? $options['field_mapping'] : [];

        $get_meta_key = function ($wasi_slug) use ($field_mapping) {
            return !empty($field_mapping[$wasi_slug]) ? $field_mapping[$wasi_slug] : 'wasi_' . $wasi_slug;
        };

        // 1. AUTO-MAPPING: Dump all Wasi attributes natively so nothing is lost
        foreach ($immobile as $key => $val) {
            if (is_scalar($val) && strval($val) !== '') {
                $meta_k = $get_meta_key($key);
                update_post_meta($post_id, $meta_k, sanitize_text_field(strval($val)));
            }
        }

        // Also dump a flat list of all features
        $feature_names = [];
        $wasi_features = isset($immobile['features']) ? $immobile['features'] : [];
        $all_feat = array_merge(isset($wasi_features['internal']) ? $wasi_features['internal'] : [], isset($wasi_features['external']) ? $wasi_features['external'] : []);
        foreach ($all_feat as $f) {
            if (isset($f['nombre']))
                $feature_names[] = trim($f['nombre']);
        }
        if (!empty($feature_names)) {
            $meta_k = $get_meta_key('all_features');
            update_post_meta($post_id, $meta_k, sanitize_text_field(implode(', ', $feature_names)));
        }

        // 2. SPECIFIC ACF MAPPING: Core fields for easy template access
        update_post_meta($post_id, 'wasi_id', sanitize_text_field($wasi_id));

        $price = 0;
        $tipo_gestion = '';
        if (!empty($immobile['for_rent']) && isset($immobile['rent_price']) && $immobile['rent_price'] > 0) {
            $price = $immobile['rent_price'];
            $tipo_gestion = 'Arriendo';
        }
        if ($price == 0 && !empty($immobile['for_sale']) && isset($immobile['sale_price']) && $immobile['sale_price'] > 0) {
            $price = $immobile['sale_price'];
            $tipo_gestion = 'Venta';
        }
        $price = preg_replace('/[^0-9]/', '', strval($price));

        // Developer-friendly meta keys
        update_post_meta($post_id, 'property_price', $price);
        update_post_meta($post_id, 'property_type', sanitize_text_field($tipo_gestion));

        if (isset($immobile['built_area']))
            update_post_meta($post_id, 'property_area', sanitize_text_field($immobile['built_area']));
        if (isset($immobile['bedrooms']))
            update_post_meta($post_id, 'property_bedrooms', intval($immobile['bedrooms']));
        if (isset($immobile['bathrooms']))
            update_post_meta($post_id, 'property_bathrooms', intval($immobile['bathrooms']));

        if (isset($immobile['address']))
            update_post_meta($post_id, 'property_address', sanitize_text_field($immobile['address']));
        if (isset($immobile['latitude']))
            update_post_meta($post_id, 'property_lat', floatval($immobile['latitude']));
        if (isset($immobile['longitude']))
            update_post_meta($post_id, 'property_lng', floatval($immobile['longitude']));

        if (isset($immobile['video']) && !empty($immobile['video']))
            update_post_meta($post_id, 'property_video', esc_url_raw($immobile['video']));

        // Extract features to a comma-separated string for easy ACF text field parsing
        $feature_names = [];
        $wasi_features = isset($immobile['features']) ? $immobile['features'] : [];
        $all = array_merge(isset($wasi_features['internal']) ? $wasi_features['internal'] : [], isset($wasi_features['external']) ? $wasi_features['external'] : []);
        foreach ($all as $f) {
            $feature_names[] = trim($f['nombre']);
        }
        if (!empty($feature_names)) {
            update_post_meta($post_id, 'property_features', sanitize_text_field(implode(', ', $feature_names)));
        }
    }

    public function process_gallery($post_id, $images)
    {
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        // ACF uses a serialized array or an ACF Gallery field. We upload the images and attach them to the post.
        // The developer will get the attachment IDs.
        $gallery_ids = [];
        foreach ($images as $k => $img) {
            $url = !empty($img['url_original']) ? $img['url_original'] : (!empty($img['url_big']) ? $img['url_big'] : '');
            if (empty($url))
                continue;

            $mid = media_sideload_image(esc_url_raw($url), $post_id, null, 'id');
            if (!is_wp_error($mid)) {
                $gallery_ids[] = $mid;
                if ($k == 0)
                    set_post_thumbnail($post_id, $mid);
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($post_id, 'property_gallery', $gallery_ids);
        }
    }

    public function force_update_trigger($post_id)
    {
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
    }

    public function get_default_plan_id()
    {
        return 0; // Not applicable for ACF
    }
}
