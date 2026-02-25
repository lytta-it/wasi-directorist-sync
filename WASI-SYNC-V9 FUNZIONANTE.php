<?php
/*
Plugin Name: Lytta - Wasi to Directorist API Sync (Platinum v9.0)
Plugin URI: https://www.lytta.it
Description: Sync Wasi -> Directorist V9.0. TARGET LOCK: Corretto slug tassonomia (atbdp_listing_types). Ora il layout grafico si caricherà subito.
Version: 9.0
Author: Lytta
Author URI: https://www.lytta.it
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'lytta_wasi_config' ) ) {

    function lytta_wasi_config() {
        return [
            'company_id'    => '27306135',
            'token'         => 'P2pf_XECn_jC9n_t0fP', 
            'post_type'     => 'at_biz_dir',
            'author_id'     => 4, 
            'email_report'  => 'info@lytta.it', 
            'enable_email'  => true, 
            
            // CONFIGURAZIONE CORRETTA DAL TUO SCANNER
            'dir_type_id'   => 76,                 // ID "Propiedades"
            'dir_type_slug' => 'atbdp_listing_types' // <--- IL NOME CORRETTO DELLA TASSONOMIA
        ];
    }

    if ( ! wp_next_scheduled( 'lytta_wasi_event_v9' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'lytta_wasi_event_v9' );
    }

    add_action( 'lytta_wasi_event_v9', 'lytta_run_import' );

    function lytta_run_import( $atts = [] ) {
        if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) ) return;

        $start_time = time();
        $max_execution_time = 45; 
        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 300 ); 
        
        $limit = ( isset($atts['limit']) ) ? intval($atts['limit']) : 10;
        $config = lytta_wasi_config();
        $stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0];

        $plan_id = lytta_get_default_plan_id();

        $url = "https://api.wasi.co/v1/property/search?id_company={$config['company_id']}&wasi_token={$config['token']}&take={$limit}&orderby=updated_at&direction=desc";
        
        $response = wp_remote_get( $url, ['timeout' => 60] );
        if ( is_wp_error( $response ) ) return "Errore API: " . $response->get_error_message();
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ( ! $data || ! is_array($data) ) return "JSON non valido.";

        foreach ( $data as $key => $immobile ) {
            if ( (time() - $start_time) > $max_execution_time ) break; 
            if ( ! is_numeric($key) || ! isset($immobile['id_property']) ) continue;
            
            $wasi_id = $immobile['id_property'];
            $title   = isset($immobile['title']) ? $immobile['title'] : 'Senza Titolo';
            
            $desc_raw = isset($immobile['observations']) ? $immobile['observations'] : '';
            $desc_html = html_entity_decode($desc_raw);
            if ( strpos($desc_html, '<') === false ) $desc_html = nl2br($desc_html);
            
            // Contenuto con classe per nasconderlo via CSS se necessario
            $contenuto_sicuro = '<div class="lytta-desc-wrapper">' . $desc_html . '</div>';

            $existing_post = get_posts([
                'post_type'  => $config['post_type'],
                'meta_key'   => 'wasi_id',
                'meta_value' => $wasi_id,
                'posts_per_page' => 1,
                'post_status' => 'any' 
            ]);

            $post_id = 0;
            $is_update = false;

            $post_args = [
                'post_title'   => $title,
                'post_content' => $contenuto_sicuro, 
                'post_excerpt' => strip_tags($desc_html),
                'post_status'  => 'draft', 
                'post_type'    => $config['post_type'],
                'post_author'  => $config['author_id'],
            ];

            if ( $existing_post ) {
                $post_args['ID'] = $existing_post[0]->ID;
                $is_update = true;
                $post_id = wp_update_post( $post_args );
            } else {
                $post_id = wp_insert_post( $post_args );
                if( !is_wp_error($post_id) ) update_post_meta( $post_id, 'wasi_id', $wasi_id );
            }

            if( is_wp_error($post_id) ) continue;

            // --- TARGET LOCK: TASSONOMIA CORRETTA ---
            // Usiamo lo slug corretto scoperto dallo scanner: 'atbdp_listing_types'
            wp_set_object_terms( $post_id, (int)$config['dir_type_id'], $config['dir_type_slug'] );

            // Mappatura
            lytta_map_fields($post_id, $immobile, $wasi_id, $desc_html, $plan_id, $config['dir_type_id']);

            // Immagini
            $existing_gallery = get_post_meta($post_id, '_listing_img', true);
            $has_thumb = has_post_thumbnail($post_id);
            $should_download_images = ( empty($existing_gallery) && !$has_thumb ) || !$is_update;
            
            if ( $should_download_images && !empty($immobile['galleries'][0]) ) {
                if ( (time() - $start_time) < ($max_execution_time - 5) ) {
                    lytta_process_gallery($post_id, $immobile['galleries'][0]);
                }
            }
            
            // --- TRIGGER AGGIORNA ---
            lytta_force_update_trigger($post_id, $config['dir_type_slug'], $config['dir_type_id']);

            if($is_update) $stats['updated']++; else $stats['inserted']++;
        } 

        return "Sync V9.0 OK: Nuovi {$stats['inserted']}, Agg. {$stats['updated']}.";
    }

    function lytta_force_update_trigger($post_id, $tax_slug, $term_id) {
        // 1. Riaffermiamo la tassonomia corretta
        wp_set_object_terms( $post_id, (int)$term_id, $tax_slug );

        // 2. Pubblica
        wp_update_post( array(
            'ID' => $post_id,
            'post_status' => 'publish',
            'edit_date' => true
        ));

        // 3. Trigger hooks Directorist
        do_action( 'save_post', $post_id, get_post($post_id), true );

        // 4. Clean Cache
        clean_post_cache( $post_id );
        global $wpdb;
        $wpdb->query( "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE ('_transient_atbdp_%')" );
    }

    function lytta_get_default_plan_id() {
        $plans = get_posts([
            'post_type' => 'atbdp_plan', 
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'orderby' => 'ID', 
            'order' => 'ASC'
        ]);
        if ( !empty($plans) ) return $plans[0]->ID;
        return 0; 
    }

    function lytta_map_fields($post_id, $immobile, $wasi_id, $descrizione, $plan_id, $dir_type_id) {
        
        if($plan_id > 0) {
            update_post_meta( $post_id, '_plan_id', $plan_id );
            update_post_meta( $post_id, '_fm_plans', $plan_id );
        }

        update_post_meta( $post_id, '_custom-textarea', $descrizione );       
        update_post_meta( $post_id, '_atbd_listing_description', $descrizione ); 
        update_post_meta( $post_id, '_listing_description', $descrizione );      
        
        // Scadenza 2030 (4 anni)
        $future_date = date('Y-m-d H:i:s', strtotime('+4 years'));
        update_post_meta( $post_id, '_expiry_date', $future_date );
        delete_post_meta( $post_id, '_never_expire' );

        update_post_meta( $post_id, '_listing_status', '1' ); 
        update_post_meta( $post_id, '_claimed', '1' );
        update_post_meta( $post_id, '_verified', '1' );
        update_post_meta( $post_id, '_admin_approval', '1' );

        // Layout Default
        update_post_meta( $post_id, '_header_style', 'default' );

        // Directory Type
        update_post_meta( $post_id, '_custom-number-2', $wasi_id ); 
        update_post_meta( $post_id, '_directory_type', $dir_type_id );
        
        // Prezzi
        $price = 0; $tipo_gestion = ''; 
        if ( !empty($immobile['for_rent']) && isset($immobile['rent_price']) && $immobile['rent_price'] > 0 ) {
            $price = $immobile['rent_price']; $tipo_gestion = 'Arriendo';
        }
        if ( $price == 0 && !empty($immobile['for_sale']) && isset($immobile['sale_price']) && $immobile['sale_price'] > 0 ) {
            $price = $immobile['sale_price']; $tipo_gestion = 'Venta';
        }
        if ( $price == 0 ) {
            if(!empty($immobile['for_rent'])) $tipo_gestion = 'Arriendo';
            elseif(!empty($immobile['for_sale'])) $tipo_gestion = 'Venta';
        }
        $price = preg_replace('/[^0-9]/', '', strval($price));
        update_post_meta( $post_id, '_price', $price );
        update_post_meta( $post_id, '_atbd_listing_pricing', 'price' );
        update_post_meta( $post_id, '_custom-select-4', $tipo_gestion );

        // Dati Tecnici
        if(isset($immobile['built_area'])) update_post_meta( $post_id, '_custom-number', $immobile['built_area'] );
        if(isset($immobile['stratum'])) update_post_meta( $post_id, '_custom-number-3', intval($immobile['stratum']) );
        if(isset($immobile['floor'])) update_post_meta( $post_id, '_custom-text', $immobile['floor'] );

        if(isset($immobile['bedrooms'])) {
            $bed = intval($immobile['bedrooms']);
            $val_bed = ($bed >= 4) ? "4 o más" : strval($bed);
            update_post_meta( $post_id, '_custom-select', $val_bed );
        }
        if(isset($immobile['bathrooms'])) {
            $bath = intval($immobile['bathrooms']);
            $val_bath = "1 baño"; if($bath == 2) $val_bath = "2 baños"; if($bath >= 3) $val_bath = "3 o más baños";
            update_post_meta( $post_id, '_custom-select-2', $val_bath );
        }

        // Età e Stato
        $val_eta = "Usada (1-5 años)"; 
        if(isset($immobile['building_date']) && intval($immobile['building_date']) > 1900) {
            $year = intval($immobile['building_date']);
            $age = intval(date("Y")) - $year;
            if($age < 1) $val_eta = "Nueva (menos de 1 año)";
            elseif($age >= 1 && $age <= 5) $val_eta = "Usada (1-5 años)";
            else $val_eta = "Antigua (más de 5 años)";
        }
        update_post_meta( $post_id, '_custom-select-3', $val_eta );

        $condizione = "Bueno"; 
        $wasi_cond = isset($immobile['property_condition_label']) ? strtolower($immobile['property_condition_label']) : '';
        if(strpos($wasi_cond, 'new') !== false || strpos($wasi_cond, 'nuevo') !== false) $condizione = "Nuevo";
        elseif(strpos($wasi_cond, 'reform') !== false || strpos($wasi_cond, 'remodeled') !== false) $condizione = "Reformado";
        update_post_meta( $post_id, '_custom-select-6', $condizione );
        update_post_meta( $post_id, '_custom-select-7', 'Disponibile' );

        // Geo
        if(isset($immobile['address'])) {
            update_post_meta( $post_id, '_address', $immobile['address'] );
            update_post_meta( $post_id, '_listing_address', $immobile['address'] );
        }
        if(isset($immobile['zip_code'])) update_post_meta( $post_id, '_zip', $immobile['zip_code'] );
        if(isset($immobile['zone_label'])) update_post_meta( $post_id, '_custom-text-2', $immobile['zone_label'] );
        if(isset($immobile['latitude'])) {
            update_post_meta( $post_id, '_manual_lat', floatval($immobile['latitude']) );
            update_post_meta( $post_id, '_geo_lat', floatval($immobile['latitude']) );
        }
        if(isset($immobile['longitude'])) {
            update_post_meta( $post_id, '_manual_lng', floatval($immobile['longitude']) );
            update_post_meta( $post_id, '_geo_lng', floatval($immobile['longitude']) );
        }

        if(isset($immobile['video']) && !empty($immobile['video'])) update_post_meta( $post_id, '_video', $immobile['video'] );

        lytta_map_features($post_id, isset($immobile['features']) ? $immobile['features'] : []);
        lytta_set_categories($post_id, $immobile);
        lytta_set_locations($post_id, $immobile);
    }

    function lytta_set_locations($post_id, $immobile) {
        $city = isset($immobile['city_label']) ? trim($immobile['city_label']) : '';
        $zone = isset($immobile['zone_label']) ? trim($immobile['zone_label']) : '';
        if ( empty($city) ) return;
        $taxonomy = 'at_biz_dir-location'; 
        $terms_ids = [];
        $parent = term_exists( $city, $taxonomy );
        if ( ! $parent ) {
            $new = wp_insert_term( $city, $taxonomy );
            $parent_id = !is_wp_error($new) ? $new['term_id'] : 0;
        } else {
            $parent_id = is_array($parent) ? $parent['term_id'] : $parent;
        }
        if($parent_id) $terms_ids[] = (int)$parent_id;
        if ( ! empty($zone) && $parent_id ) {
            $child = term_exists( $zone, $taxonomy );
            if ( ! $child ) {
                $new_child = wp_insert_term( $zone, $taxonomy, ['parent' => $parent_id] );
                if ( ! is_wp_error($new_child) ) $terms_ids[] = (int)$new_child['term_id'];
            } else {
                $ids[] = (int)(is_array($child) ? $child['term_id'] : $child);
            }
        }
        if(!empty($terms_ids)) wp_set_object_terms( $post_id, $terms_ids, $taxonomy );
    }

    function lytta_set_categories($post_id, $immobile) {
        if ( ! isset($immobile['id_property_type']) ) return;
        $taxonomy = 'at_biz_dir-category';
        $id_type = $immobile['id_property_type'];
        $cat_name = 'Residencial';
        if(in_array($id_type, [2,14,19,20,21,25,33])) $cat_name = 'Apartamento';
        elseif(in_array($id_type, [1,7,10,11,13,22,24,27,28])) $cat_name = 'Casa';
        elseif(in_array($id_type, [3,6,12,16,18])) $cat_name = 'Local comercial';
        elseif(in_array($id_type, [4,15])) $cat_name = 'Oficina';
        elseif(in_array($id_type, [8,23,26,30])) $cat_name = 'Bodega';
        elseif(in_array($id_type, [5,17,29])) $cat_name = 'Lote';
        elseif(in_array($id_type, [31,32])) $cat_name = 'Terreno';
        elseif(in_array($id_type, [15])) $cat_name = 'Loft';
        
        $term = term_exists( $cat_name, $taxonomy );
        if ( $term ) {
            $tid = is_array($term) ? $term['term_id'] : $term;
            wp_set_object_terms( $post_id, (int)$tid, $taxonomy );
        } 
    }

    function lytta_map_features( $post_id, $wasi_features ) {
        $generali = []; $sicurezza = []; $servizi = [];
        $all = array_merge( isset($wasi_features['internal'])?$wasi_features['internal']:[], isset($wasi_features['external'])?$wasi_features['external']:[] );
        foreach ($all as $f) {
            $n = trim($f['nombre']);
            if(in_array($n, ['Garaje','Piscina','Jardín','Aire acondicionado','Calefacción','Ascensor'])) $generali[] = $n;
            if($n == 'Balcón' || $n == 'Terraza') $generali[] = 'Balcón/Terraza';
            if(stripos($n, 'Amoblado') !== false) $generali[] = 'Amueblado';
            if($n == 'Vigilancia') $sicurezza[] = 'Sistemas de seguridad 24 horas';
            if($n == 'Circuito cerrado de tv') $sicurezza[] = 'Videovigilancia';
            if($n == 'Portería / recepción') $sicurezza[] = 'Control de acceso';
            if($n == 'Trans. público cercano') $servizi[] = 'Transporte público';
            if($n == 'Colegios / universidades') $servizi[] = 'Escuelas';
            if(strpos($n, 'Supermercado') !== false || strpos($n, 'Zona comercial') !== false) $servizi[] = 'Supermercados';
            if($n == 'Parques cercanos') $servizi[] = 'Parques';
            if($n == 'Centros comerciales') $servizi[] = 'Centro comercial';
            if(stripos($n, 'Metro') !== false) $servizi[] = 'Metro';
            if(stripos($n, 'Centros de salud') !== false) $servizi[] = 'Centros de salud';
        }
        if(!empty($generali)) update_post_meta($post_id, '_custom-checkbox', $generali);
        if(!empty($sicurezza)) update_post_meta($post_id, '_custom-checkbox-2', $sicurezza);
        if(!empty($servizi)) update_post_meta($post_id, '_custom-checkbox-3', $servizi);
    }

    function lytta_process_gallery($post_id, $images) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        $g_ids = [];
        foreach ($images as $k => $img) {
            $url = !empty($img['url_original']) ? $img['url_original'] : ( !empty($img['url_big']) ? $img['url_big'] : '' );
            if ( empty($url) ) continue;
            
            $mid = media_sideload_image( $url, $post_id, null, 'id' );
            if ( ! is_wp_error( $mid ) ) {
                $g_ids[] = $mid;
                if ( $k == 0 ) {
                    set_post_thumbnail( $post_id, $mid );
                    update_post_meta( $post_id, '_listing_prv_img', $mid );
                    update_post_meta( $post_id, '_thumbnail_id', $mid );
                }
            }
        }
        if(!empty($g_ids)) update_post_meta( $post_id, '_listing_img', $g_ids );
    }

    function lytta_send_email_report($subject, $message, $stats = null) {
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail( lytta_wasi_config()['email_report'], $subject, $message, $headers );
    }

    add_shortcode('lytta_sync_manual', 'lytta_run_import');

}