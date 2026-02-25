<?php
/**
 * Handles the actual importation and mapping of data from Wasi to Directorist.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Wasi_Importer
{

    private $api;
    private $config;

    public function __construct()
    {
        $this->api = new Lytta_Wasi_API();

        $options = get_option('lytta_wasi_settings', []);

        $this->config = [
            'post_type' => 'at_biz_dir',
            'author_id' => !empty($options['author_id']) ? intval($options['author_id']) : 4,
            'email_report' => !empty($options['email_report']) ? sanitize_email($options['email_report']) : 'info@lytta.it',
            'enable_email' => true,
            'sync_limit' => !empty($options['sync_limit']) ? intval($options['sync_limit']) : 10,
            'mapping' => !empty($options['mapping']) ? wp_kses_post($options['mapping']) : '',

            // Hardcoded taxonomy constants optimized for performance
            'dir_type_id' => 76,
            'dir_type_slug' => 'atbdp_listing_types'
        ];
    }

    public function run_import($atts = [])
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST))
            return;

        $start_time = time();
        $max_execution_time = 45;
        if (function_exists('set_time_limit'))
            @set_time_limit(300);

        $limit = (isset($atts['limit'])) ? intval($atts['limit']) : intval($this->config['sync_limit']);

        $data = $this->api->get_properties($limit);

        if (is_wp_error($data)) {
            return "Errore API: " . $data->get_error_message();
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0, 'drafted' => 0];
        $plan_id = $this->get_default_plan_id();

        foreach ($data as $key => $immobile) {
            if ((time() - $start_time) > $max_execution_time)
                break;
            if (!is_numeric($key) || !isset($immobile['id_property']))
                continue;

            $wasi_id = sanitize_text_field($immobile['id_property']);
            $title = isset($immobile['title']) ? sanitize_text_field($immobile['title']) : 'Senza Titolo';

            $desc_raw = isset($immobile['observations']) ? $immobile['observations'] : '';
            $desc_html = wp_kses_post(html_entity_decode($desc_raw)); // Sanitized HTML
            if (strpos($desc_html, '<') === false)
                $desc_html = nl2br($desc_html);

            // Safe content wrapper
            $contenuto_sicuro = '<div class="lytta-wasi-content">' . $desc_html . '</div>';

            $existing_post = get_posts([
                'post_type' => $this->config['post_type'],
                'meta_key' => 'wasi_id',
                'meta_value' => $wasi_id,
                'posts_per_page' => 1,
                'post_status' => 'any'
            ]);

            $post_id = 0;
            $is_update = false;

            $post_args = [
                'post_title' => $title,
                'post_content' => $contenuto_sicuro,
                'post_excerpt' => wp_trim_words(strip_tags($desc_html), 20),
                'post_status' => 'draft',
                'post_type' => $this->config['post_type'],
                'post_author' => $this->config['author_id'],
            ];

            if ($existing_post) {
                $post_args['ID'] = $existing_post[0]->ID;
                $is_update = true;
                $post_id = wp_update_post($post_args);
            }
            else {
                $post_id = wp_insert_post($post_args);
                if (!is_wp_error($post_id))
                    update_post_meta($post_id, 'wasi_id', $wasi_id);
            }

            if (is_wp_error($post_id)) {
                $stats['errors']++;
                continue;
            }

            wp_set_object_terms($post_id, (int)$this->config['dir_type_id'], $this->config['dir_type_slug']);

            $this->map_fields($post_id, $immobile, $wasi_id, $desc_html, $plan_id);

            // Images
            $existing_gallery = get_post_meta($post_id, '_listing_img', true);
            $has_thumb = has_post_thumbnail($post_id);
            $should_download_images = (empty($existing_gallery) && !$has_thumb) || !$is_update;

            if ($should_download_images && !empty($immobile['galleries'][0])) {
                if ((time() - $start_time) < ($max_execution_time - 5)) {
                    $this->process_gallery($post_id, $immobile['galleries'][0]);
                }
            }

            $this->force_update_trigger($post_id, $this->config['dir_type_slug'], $this->config['dir_type_id']);

            if ($is_update)
                $stats['updated']++;
            else
                $stats['inserted']++;
        }

        // Cleanup Orchestration
        if ((defined('DOING_CRON') && DOING_CRON) || isset($atts['cleanup'])) {
            $orphans_removed = $this->run_cleanup();
            $stats['drafted'] = $orphans_removed;
        }

        if ($this->config['enable_email'] && !empty($this->config['email_report'])) {
            $this->send_report_email($stats);
        }

        return "Sync V12.0 OK: Nuovi {$stats['inserted']}, Agg. {$stats['updated']}, Rimossi {$stats['drafted']}.";
    }

    private function run_cleanup()
    {
        $wasi_active_ids = $this->api->get_all_active_property_ids();
        if (empty($wasi_active_ids))
            return 0; // Fail safe

        $all_wp_posts = get_posts([
            'post_type' => $this->config['post_type'],
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [['key' => 'wasi_id', 'compare' => 'EXISTS']]
        ]);

        $count_cleaned = 0;
        foreach ($all_wp_posts as $p_id) {
            $wp_wasi_id = get_post_meta($p_id, 'wasi_id', true);
            if (!empty($wp_wasi_id) && !in_array($wp_wasi_id, $wasi_active_ids)) {
                wp_update_post(['ID' => $p_id, 'post_status' => 'draft']);
                $count_cleaned++;
            }
        }
        return $count_cleaned;
    }

    private function map_fields($post_id, $immobile, $wasi_id, $descrizione, $plan_id)
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

        $this->map_features($post_id, isset($immobile['features']) ? $immobile['features'] : []);
        $this->set_categories($post_id, $immobile);
        $this->set_locations($post_id, $immobile);
    }

    private function set_locations($post_id, $immobile)
    {
        $city = isset($immobile['city_label']) ? trim($immobile['city_label']) : '';
        $zone = isset($immobile['zone_label']) ? trim($immobile['zone_label']) : '';
        if (empty($city))
            return;
        $taxonomy = 'at_biz_dir-location';

        $terms_ids = [];
        $parent = term_exists($city, $taxonomy);
        if (!$parent) {
            $new = wp_insert_term(sanitize_text_field($city), $taxonomy);
            $parent_id = !is_wp_error($new) ? $new['term_id'] : 0;
        }
        else {
            $parent_id = is_array($parent) ? $parent['term_id'] : $parent;
        }

        if ($parent_id)
            $terms_ids[] = (int)$parent_id;

        if (!empty($zone) && $parent_id) {
            $child = term_exists($zone, $taxonomy);
            if (!$child) {
                $new_child = wp_insert_term(sanitize_text_field($zone), $taxonomy, ['parent' => $parent_id]);
                if (!is_wp_error($new_child))
                    $terms_ids[] = (int)$new_child['term_id'];
            }
            else {
                $ids[] = (int)(is_array($child) ? $child['term_id'] : $child); // this should be terms_ids
                $terms_ids[] = (int)(is_array($child) ? $child['term_id'] : $child);
            }
        }
        if (!empty($terms_ids))
            wp_set_object_terms($post_id, array_unique($terms_ids), $taxonomy);
    }

    private function set_categories($post_id, $immobile)
    {
        if (!isset($immobile['id_property_type']))
            return;
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

    private function map_features($post_id, $wasi_features)
    {
        $generali = [];
        $sicurezza = [];
        $servizi = [];
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

    private function process_gallery($post_id, $images)
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

    private function force_update_trigger($post_id, $tax_slug, $term_id)
    {
        wp_set_object_terms($post_id, (int)$term_id, $tax_slug);
        wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish',
            'edit_date' => true
        ));
        do_action('save_post', $post_id, get_post($post_id), true);

        // Caching cleanup
        if (function_exists('clean_post_cache')) {
            clean_post_cache($post_id);
        }
        global $wpdb;
        $wpdb->query("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE ('_transient_atbdp_%')");
    }

    private function get_default_plan_id()
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

    private function send_report_email($stats)
    {
        $subject = "Report Sync Lytta - " . date("d/m/Y H:i");
        $message = "Report Sync Lytta - " . date("d/m/Y H:i") . "\n\n";
        $message .= "Nuovi Immobili: " . $stats['inserted'] . "\n";
        $message .= "Aggiornati: " . $stats['updated'] . "\n";
        $message .= "Rimossi/Bozze: " . $stats['drafted'] . "\n";
        $message .= "Errori: " . $stats['errors'] . "\n";

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($this->config['email_report'], $subject, $message, $headers);
    }
}
