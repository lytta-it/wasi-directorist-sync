<?php
/**
 * Adapter mapping logic for Directorist plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Adapter_Directorist
{

    private $config;

    public function __construct($config_mapping)
    {
        $this->config = [
            'dir_type_id' => 76,
            'dir_type_slug' => 'atbdp_listing_types',
            'mapping' => $config_mapping
        ];
    }

    public function map_post_args($wasi_id, $title, $desc_html, $author_id)
    {
        return [
            'post_title' => $title,
            'post_content' => '<div class="lytta-wasi-content">' . $desc_html . '</div>',
            'post_excerpt' => wp_trim_words(strip_tags($desc_html), 20),
            'post_status' => 'draft',
            'post_type' => 'at_biz_dir',
            'post_author' => $author_id,
        ];
    }

    public function set_taxonomies($post_id, $immobile)
    {
        // Base mapping
        wp_set_object_terms($post_id, (int)$this->config['dir_type_id'], $this->config['dir_type_slug']);

        // Dynamic mapping 
        if (isset($immobile['id_property_type'])) {
            $taxonomy = 'at_biz_dir-category';
            $id_type = $immobile['id_property_type'];
            $mapping_str = $this->config['mapping'];
            $cat_name = 'Residencial';

            if (!empty($mapping_str)) {
                $lines = explode("\n", $mapping_str);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line))
                        continue;
                    $parts = explode('=', $line);
                    if (count($parts) == 2) {
                        $ids = explode(',', trim($parts[0]));
                        $mapped_cat = sanitize_text_field(trim($parts[1]));
                        if (in_array($id_type, $ids)) {
                            $cat_name = $mapped_cat;
                            break;
                        }
                    }
                }
            }
            else {
                if (in_array($id_type, [2, 14, 19, 20, 21, 25, 33]))
                    $cat_name = 'Apartamento';
                elseif (in_array($id_type, [1, 7, 10, 11, 13, 22, 24, 27, 28]))
                    $cat_name = 'Casa';
                elseif (in_array($id_type, [3, 6, 12, 16, 18]))
                    $cat_name = 'Local comercial';
                elseif (in_array($id_type, [4, 15]))
                    $cat_name = 'Oficina';
                elseif (in_array($id_type, [8, 23, 26, 30]))
                    $cat_name = 'Bodega';
                elseif (in_array($id_type, [5, 17, 29]))
                    $cat_name = 'Lote';
                elseif (in_array($id_type, [31, 32]))
                    $cat_name = 'Terreno';
                elseif (in_array($id_type, [15]))
                    $cat_name = 'Loft';
            }

            $term = term_exists($cat_name, $taxonomy);
            if ($term) {
                $tid = is_array($term) ? $term['term_id'] : $term;
                wp_set_object_terms($post_id, (int)$tid, $taxonomy);
            }
        }

        // Location mapping
        $city = isset($immobile['city_label']) ? trim($immobile['city_label']) : '';
        $zone = isset($immobile['zone_label']) ? trim($immobile['zone_label']) : '';
        if (!empty($city)) {
            $loc_tax = 'at_biz_dir-location';
            $terms_ids = [];
            $parent = term_exists($city, $loc_tax);
            if (!$parent) {
                $new = wp_insert_term(sanitize_text_field($city), $loc_tax);
                $parent_id = !is_wp_error($new) ? $new['term_id'] : 0;
            }
            else {
                $parent_id = is_array($parent) ? $parent['term_id'] : $parent;
            }
            if ($parent_id)
                $terms_ids[] = (int)$parent_id;

            if (!empty($zone) && $parent_id) {
                $child = term_exists($zone, $loc_tax);
                if (!$child) {
                    $new_child = wp_insert_term(sanitize_text_field($zone), $loc_tax, ['parent' => $parent_id]);
                    if (!is_wp_error($new_child))
                        $terms_ids[] = (int)$new_child['term_id'];
                }
                else {
                    $terms_ids[] = (int)(is_array($child) ? $child['term_id'] : $child);
                }
            }
            if (!empty($terms_ids))
                wp_set_object_terms($post_id, array_unique($terms_ids), $loc_tax);
        }
    }

    public function map_fields($post_id, $immobile, $wasi_id, $descrizione, $plan_id)
    {
        if ($plan_id > 0) {
            update_post_meta($post_id, '_plan_id', $plan_id);
            update_post_meta($post_id, '_fm_plans', $plan_id);
        }

        update_post_meta($post_id, '_custom-textarea', $descrizione);
        update_post_meta($post_id, '_atbd_listing_description', $descrizione);
        update_post_meta($post_id, '_listing_description', $descrizione);

        $future_date = date('Y-m-d H:i:s', strtotime('+4 years'));
        update_post_meta($post_id, '_expiry_date', $future_date);
        delete_post_meta($post_id, '_never_expire');

        update_post_meta($post_id, '_listing_status', '1');
        update_post_meta($post_id, '_claimed', '1');
        update_post_meta($post_id, '_verified', '1');
        update_post_meta($post_id, '_admin_approval', '1');
        update_post_meta($post_id, '_header_style', 'default');

        update_post_meta($post_id, '_custom-number-2', sanitize_text_field($wasi_id));
        update_post_meta($post_id, '_directory_type', $this->config['dir_type_id']);

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
        if ($price == 0) {
            if (!empty($immobile['for_rent']))
                $tipo_gestion = 'Arriendo';
            elseif (!empty($immobile['for_sale']))
                $tipo_gestion = 'Venta';
        }
        $price = preg_replace('/[^0-9]/', '', strval($price));
        update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_atbd_listing_pricing', 'price');
        update_post_meta($post_id, '_custom-select-4', sanitize_text_field($tipo_gestion));

        if (isset($immobile['built_area']))
            update_post_meta($post_id, '_custom-number', sanitize_text_field($immobile['built_area']));
        if (isset($immobile['stratum']))
            update_post_meta($post_id, '_custom-number-3', intval($immobile['stratum']));
        if (isset($immobile['floor']))
            update_post_meta($post_id, '_custom-text', sanitize_text_field($immobile['floor']));

        if (isset($immobile['bedrooms'])) {
            $bed = intval($immobile['bedrooms']);
            $val_bed = ($bed >= 4) ? "4 o más" : strval($bed);
            update_post_meta($post_id, '_custom-select', $val_bed);
        }
        if (isset($immobile['bathrooms'])) {
            $bath = intval($immobile['bathrooms']);
            $val_bath = "1 baño";
            if ($bath == 2)
                $val_bath = "2 baños";
            if ($bath >= 3)
                $val_bath = "3 o más baños";
            update_post_meta($post_id, '_custom-select-2', $val_bath);
        }

        $val_eta = "Usada (1-5 años)";
        if (isset($immobile['building_date']) && intval($immobile['building_date']) > 1900) {
            $year = intval($immobile['building_date']);
            $age = intval(date("Y")) - $year;
            if ($age < 1)
                $val_eta = "Nueva (menos de 1 año)";
            elseif ($age >= 1 && $age <= 5)
                $val_eta = "Usada (1-5 años)";
            else
                $val_eta = "Antigua (más de 5 años)";
        }
        update_post_meta($post_id, '_custom-select-3', $val_eta);

        $condizione = "Bueno";
        $wasi_cond = isset($immobile['property_condition_label']) ? strtolower($immobile['property_condition_label']) : '';
        if (strpos($wasi_cond, 'new') !== false || strpos($wasi_cond, 'nuevo') !== false)
            $condizione = "Nuevo";
        elseif (strpos($wasi_cond, 'reform') !== false || strpos($wasi_cond, 'remodeled') !== false)
            $condizione = "Reformado";
        update_post_meta($post_id, '_custom-select-6', $condizione);
        update_post_meta($post_id, '_custom-select-7', 'Disponibile');

        if (isset($immobile['address'])) {
            update_post_meta($post_id, '_address', sanitize_text_field($immobile['address']));
            update_post_meta($post_id, '_listing_address', sanitize_text_field($immobile['address']));
        }
        if (isset($immobile['zip_code']))
            update_post_meta($post_id, '_zip', sanitize_text_field($immobile['zip_code']));
        if (isset($immobile['zone_label']))
            update_post_meta($post_id, '_custom-text-2', sanitize_text_field($immobile['zone_label']));
        if (isset($immobile['latitude'])) {
            update_post_meta($post_id, '_manual_lat', floatval($immobile['latitude']));
            update_post_meta($post_id, '_geo_lat', floatval($immobile['latitude']));
        }
        if (isset($immobile['longitude'])) {
            update_post_meta($post_id, '_manual_lng', floatval($immobile['longitude']));
            update_post_meta($post_id, '_geo_lng', floatval($immobile['longitude']));
        }

        if (isset($immobile['video']) && !empty($immobile['video']))
            update_post_meta($post_id, '_video', esc_url_raw($immobile['video']));

        // Features
        $generali = [];
        $sicurezza = [];
        $servizi = [];
        $wasi_features = isset($immobile['features']) ? $immobile['features'] : [];
        $all = array_merge(isset($wasi_features['internal']) ? $wasi_features['internal'] : [], isset($wasi_features['external']) ? $wasi_features['external'] : []);
        foreach ($all as $f) {
            $n = trim($f['nombre']);
            if (in_array($n, ['Garaje', 'Piscina', 'Jardín', 'Aire acondicionado', 'Calefacción', 'Ascensor']))
                $generali[] = $n;
            if ($n == 'Balcón' || $n == 'Terraza')
                $generali[] = 'Balcón/Terraza';
            if (stripos($n, 'Amoblado') !== false)
                $generali[] = 'Amueblado';
            if ($n == 'Vigilancia')
                $sicurezza[] = 'Sistemas de seguridad 24 horas';
            if ($n == 'Circuito cerrado de tv')
                $sicurezza[] = 'Videovigilancia';
            if ($n == 'Portería / recepción')
                $sicurezza[] = 'Control de acceso';
            if ($n == 'Trans. público cercano')
                $servizi[] = 'Transporte público';
            if ($n == 'Colegios / universidades')
                $servizi[] = 'Escuelas';
            if (strpos($n, 'Supermercado') !== false || strpos($n, 'Zona comercial') !== false)
                $servizi[] = 'Supermercados';
            if ($n == 'Parques cercanos')
                $servizi[] = 'Parques';
            if ($n == 'Centros comerciales')
                $servizi[] = 'Centro comercial';
            if (stripos($n, 'Metro') !== false)
                $servizi[] = 'Metro';
            if (stripos($n, 'Centros de salud') !== false)
                $servizi[] = 'Centros de salud';
        }
        if (!empty($generali))
            update_post_meta($post_id, '_custom-checkbox', array_map('sanitize_text_field', $generali));
        if (!empty($sicurezza))
            update_post_meta($post_id, '_custom-checkbox-2', array_map('sanitize_text_field', $sicurezza));
        if (!empty($servizi))
            update_post_meta($post_id, '_custom-checkbox-3', array_map('sanitize_text_field', $servizi));

    }

    public function process_gallery($post_id, $images)
    {
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        $g_ids = [];
        foreach ($images as $k => $img) {
            $url = !empty($img['url_original']) ? $img['url_original'] : (!empty($img['url_big']) ? $img['url_big'] : '');
            if (empty($url))
                continue;

            $mid = media_sideload_image(esc_url_raw($url), $post_id, null, 'id');
            if (!is_wp_error($mid)) {
                $g_ids[] = $mid;
                if ($k == 0) {
                    set_post_thumbnail($post_id, $mid);
                    update_post_meta($post_id, '_listing_prv_img', $mid);
                    update_post_meta($post_id, '_thumbnail_id', $mid);
                }
            }
        }
        if (!empty($g_ids))
            update_post_meta($post_id, '_listing_img', $g_ids);
    }

    public function force_update_trigger($post_id)
    {
        wp_set_object_terms($post_id, (int)$this->config['dir_type_id'], $this->config['dir_type_slug']);
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish',
            'edit_date' => true
        ));
        do_action('save_post', $post_id, get_post($post_id), true);

        if (function_exists('clean_post_cache')) {
            clean_post_cache($post_id);
        }
        global $wpdb;
        $wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE ('_transient_atbdp_%')");
    }

    public function get_default_plan_id()
    {
        $plans = get_posts([
            'post_type' => 'atbdp_plan',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        if (!empty($plans))
            return $plans[0]->ID;
        return 0;
    }
}
