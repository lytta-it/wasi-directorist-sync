<?php
/*
Plugin Name: Lytta - Wasi to Directorist API Sync
Description: Complete & Auto Sync (Cron Activated). Mappatura intelligente Wasi -> Directorist con supporto HTML.
Version: 1.0
Author: Lytta
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Controllo di sicurezza
if ( ! function_exists( 'lytta_wasi_config' ) ) {

    // ---------------------------------------------------
    // 1. CONFIGURAZIONE
    // ---------------------------------------------------
    function lytta_wasi_config() {
        return [
            'company_id' => '27306135',
            'token'      => 'P2pf_XECn_jC9n_t0fP', // <--- TOKEN QUI
            'post_type'  => 'at_biz_dir',
        ];
    }

    // ---------------------------------------------------
    // 2. AUTOMAZIONE (CRON JOB)
    // ---------------------------------------------------
    add_filter( 'cron_schedules', function ( $schedules ) {
        $schedules['lytta_hourly'] = array(
            'interval' => 3600, // 1 ora
            'display'  => __( 'Ogni Ora (Lytta)' )
        );
        return $schedules;
    } );

    if ( ! wp_next_scheduled( 'lytta_wasi_event' ) ) {
        wp_schedule_event( time(), 'lytta_hourly', 'lytta_wasi_event' );
    }

    add_action( 'lytta_wasi_event', 'lytta_run_import' );

    // ---------------------------------------------------
    // 3. MOTORE DI SINCRONIZZAZIONE
    // ---------------------------------------------------
    function lytta_run_import() {
        // Aumenta il tempo massimo a 15 minuti per scaricare tutto
        if ( function_exists( 'set_time_limit' ) ) set_time_limit( 900 );
        
        $config = lytta_wasi_config();

        // PRODUZIONE: take=100 e ordina per data aggiornamento
        $url = "https://api.wasi.co/v1/property/search?id_company={$config['company_id']}&wasi_token={$config['token']}&take=100&orderby=updated_at&direction=desc";
        
        $response = wp_remote_get( $url, ['timeout' => 120] );
        
        if ( is_wp_error( $response ) ) {
            error_log("Lytta Error: " . $response->get_error_message());
            return "Errore Critico API.";
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ( ! $data || ! is_array($data) ) return "Dati non validi da Wasi. Controlla il Token.";

        $ids_vivi = []; // Per la pulizia
        $log = "<h3>Importazione Lytta v1.0 Avviata</h3>";
        $count = 0;

        foreach ( $data as $key => $immobile ) {
            // Saltiamo chiavi non numeriche
            if ( ! is_numeric($key) || ! isset($immobile['id_property']) ) continue;
            
            $wasi_id = $immobile['id_property'];
            $ids_vivi[] = $wasi_id; // Segniamo che questo immobile esiste
            $count++;

            $title = isset($immobile['title']) ? $immobile['title'] : 'Senza Titolo';
            
            // --- GESTIONE DESCRIZIONE HTML + WRAPPER CSS ---
            $descrizione_raw = isset($immobile['observations']) ? $immobile['observations'] : '';
            // Decodifica i caratteri speciali HTML
            $descrizione_html = html_entity_decode($descrizione_raw);
            // Se non c'è HTML, aggiungi i br
            if ( strpos($descrizione_html, '<') === false ) {
                $descrizione_html = nl2br($descrizione_html);
            }
            // Aggiungiamo il wrapper per nascondere il duplicato via CSS
            $contenuto_sicuro = '<div id="lytta-wasi-content">' . $descrizione_html . '</div>';


            // --- A. GESTIONE POST (Crea o Aggiorna) ---
            $existing_post = get_posts([
                'post_type'  => $config['post_type'],
                'meta_key'   => 'wasi_id',
                'meta_value' => $wasi_id,
                'posts_per_page' => 1
            ]);

            $post_args = [
                'post_title'   => $title,
                'post_content' => $contenuto_sicuro, // Salviamo nel contenuto standard (col wrapper)
                'post_status'  => 'publish',
                'post_type'    => $config['post_type'],
            ];

            if ( $existing_post ) {
                $post_args['ID'] = $existing_post[0]->ID;
                $post_id = wp_update_post( $post_args );
            } else {
                $post_id = wp_insert_post( $post_args );
                if( is_wp_error($post_id) ) continue;
                update_post_meta( $post_id, 'wasi_id', $wasi_id );
            }

            if( is_wp_error($post_id) ) continue;

            // --- B. PREZZI E TIPO GESTIONE ---
            $price = 0; 
            $tipo_gestion = ''; 
            
            if ( !empty($immobile['for_rent']) && $immobile['for_rent'] == true ) {
                $price = isset($immobile['rent_price']) ? $immobile['rent_price'] : 0;
                $tipo_gestion = 'Arriendo';
            } elseif ( !empty($immobile['for_sale']) && $immobile['for_sale'] == true ) {
                $price = isset($immobile['sale_price']) ? $immobile['sale_price'] : 0;
                $tipo_gestion = 'Venta';
            }

            // Salvataggio Campi Base
            update_post_meta( $post_id, '_custom-number-2', $wasi_id ); // CODICE
            update_post_meta( $post_id, '_price', $price );
            update_post_meta( $post_id, '_atbd_listing_pricing', 'price' );
            update_post_meta( $post_id, '_custom-select-4', $tipo_gestion );
            
            // Salviamo anche nel campo custom (Directorist lo userà nei tab)
            update_post_meta( $post_id, '_custom-textarea', $contenuto_sicuro );
            
            update_post_meta( $post_id, '_directory_type', '76' );

            // --- C. DATI GEOGRAFICI ---
            if(isset($immobile['address'])) update_post_meta( $post_id, '_address', $immobile['address'] );
            if(isset($immobile['latitude'])) update_post_meta( $post_id, '_manual_lat', $immobile['latitude'] );
            if(isset($immobile['longitude'])) update_post_meta( $post_id, '_manual_lng', $immobile['longitude'] );
            if(isset($immobile['zip_code'])) update_post_meta( $post_id, '_zip', $immobile['zip_code'] );

            // --- D. DATI TECNICI ---
            if(isset($immobile['built_area'])) update_post_meta( $post_id, '_custom-number', $immobile['built_area'] );
            if(isset($immobile['bedrooms'])) update_post_meta( $post_id, '_custom-select', $immobile['bedrooms'] );
            if(isset($immobile['floor'])) update_post_meta( $post_id, '_custom-text', $immobile['floor'] ); 
            if(isset($immobile['zone_label'])) update_post_meta( $post_id, '_custom-text-2', $immobile['zone_label'] );

            // ESTRATO
            if(isset($immobile['stratum'])) {
                $estrato = intval($immobile['stratum']);
                // if($estrato > 6) $estrato = 6; // Decommentare per limitare a 6
                update_post_meta( $post_id, '_custom-number-3', $estrato );
            }

            // BAGNI
            if(isset($immobile['bathrooms'])) {
                $b = intval($immobile['bathrooms']);
                $val_bagni = ($b >= 3) ? "3 o más baños" : "$b baños";
                update_post_meta( $post_id, '_custom-select-2', $val_bagni );
            }

            // ETÀ E STATO
            $condizione = "Usado";
            if(isset($immobile['property_condition_label']) && stripos($immobile['property_condition_label'], 'New') !== false) {
                 $condizione = "Nuevo";
            }
            update_post_meta( $post_id, '_custom-select-6', $condizione );
            update_post_meta( $post_id, '_custom-select-7', 'Disponibile' );

            // --- E. CARATTERISTICHE (Features) ---
            if(isset($immobile['features'])) {
                lytta_map_features($post_id, $immobile['features']);
            }
            
            // --- F. CATEGORIE ---
            lytta_set_categories($post_id, $immobile);

            // --- G. LOCATION ---
            lytta_set_locations($post_id, $immobile);

            // --- H. IMMAGINI ---
            if ( ! has_post_thumbnail( $post_id ) && ! empty($immobile['galleries'][0]) ) {
                 lytta_process_gallery($post_id, $immobile['galleries'][0]);
            }

        } // Fine Loop

        // --- PULIZIA AUTOMATICA (Cleanup) ---
        if(count($ids_vivi) > 0) {
            lytta_cleanup_posts($ids_vivi, $config['post_type']);
        }

        return $log . "Processati $count immobili. Sync Completato.";
    }

    // ---------------------------------------------------
    // FUNZIONE PULIZIA (Mette in bozza se non esiste più)
    // ---------------------------------------------------
    function lytta_cleanup_posts($active_ids, $post_type) {
        $all_posts = get_posts([
            'post_type' => $post_type, 
            'posts_per_page' => -1, 
            'meta_key' => 'wasi_id', 
            'post_status' => 'publish'
        ]);

        foreach ($all_posts as $p) {
            $w_id = get_post_meta($p->ID, 'wasi_id', true);
            if ( ! empty($w_id) && ! in_array($w_id, $active_ids) ) {
                wp_update_post(['ID' => $p->ID, 'post_status' => 'draft']);
            }
        }
    }

    // ---------------------------------------------------
    // FUNZIONI HELPER (LOCATION, CATEGORIE, FEATURES, GALLERY)
    // ---------------------------------------------------

    function lytta_set_locations($post_id, $immobile) {
        $city = isset($immobile['city_label']) ? trim($immobile['city_label']) : '';
        $zone = isset($immobile['zone_label']) ? trim($immobile['zone_label']) : '';

        if ( empty($city) ) return;

        $taxonomy = 'at_biz_dir-location'; 
        $terms_ids = [];

        // Città
        $parent = term_exists( $city, $taxonomy );
        if ( ! $parent ) {
            $new = wp_insert_term( $city, $taxonomy );
            $parent_id = !is_wp_error($new) ? $new['term_id'] : 0;
        } else {
            $parent_id = is_array($parent) ? $parent['term_id'] : $parent;
        }
        if($parent_id) $terms_ids[] = (int)$parent_id;

        // Zona
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
        $cat_name = '';

        if(in_array($id_type, [2,14,19,20,21,25,33])) {
            $cat_name = 'Apartamento';
        } elseif(in_array($id_type, [1,7,10,11,13,22,24,27,28])) {
            $cat_name = 'Casa';
        } elseif(in_array($id_type, [3,6,12,16,18])) {
            $cat_name = 'Local comercial';
        } elseif(in_array($id_type, [4,15])) {
            $cat_name = 'Oficina';
        } elseif(in_array($id_type, [8,23,26,30])) {
            $cat_name = 'Bodega';
        } elseif(in_array($id_type, [5,17,29])) {
            $cat_name = 'Lote';
        } elseif(in_array($id_type, [31,32])) {
            $cat_name = 'Terreno';
        } elseif(in_array($id_type, [15])) { 
            $cat_name = 'Loft'; 
        } else {
            $cat_name = 'Residencial';
        }

        $term = term_exists( $cat_name, $taxonomy );
        if ( $term ) {
            $tid = is_array($term) ? $term['term_id'] : $term;
            wp_set_object_terms( $post_id, (int)$tid, $taxonomy );
        } 
    }

    function lytta_map_features( $post_id, $wasi_features ) {
        $generali = []; $sicurezza = []; $servizi = [];
        
        $all = array_merge(
            isset($wasi_features['internal']) ? $wasi_features['internal'] : [],
            isset($wasi_features['external']) ? $wasi_features['external'] : []
        );

        foreach ($all as $f) {
            $n = trim($f['nombre']);
            
            // Gruppo 1
            if(in_array($n, ['Garaje','Piscina','Jardín','Aire acondicionado','Calefacción','Ascensor'])) $generali[] = $n;
            if($n == 'Balcón' || $n == 'Terraza') $generali[] = 'Balcón/Terraza';
            if($n == 'Amoblado' || stripos($n, 'Amoblado') !== false) $generali[] = 'Amueblado';

            // Gruppo 2
            if($n == 'Vigilancia') $sicurezza[] = 'Sistemas de seguridad 24 horas';
            if($n == 'Circuito cerrado de tv') $sicurezza[] = 'Videovigilancia';
            if($n == 'Portería / recepción') $sicurezza[] = 'Control de acceso';

            // Gruppo 3
            if($n == 'Trans. público cercano') $servizi[] = 'Transporte público';
            if($n == 'Colegios / universidades') $servizi[] = 'Escuelas';
            if($n == 'Supermercado' || $n == 'Zona comercial') $servizi[] = 'Supermercados';
            if($n == 'Parques cercanos') $servizi[] = 'Parques';
            if($n == 'Centros comerciales') $servizi[] = 'Centro comercial';
            if(stripos($n, 'Metro') !== false) $servizi[] = 'Metro';
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

    // Registrazione Shortcode
    add_shortcode('lytta_sync_manual', 'lytta_run_import');

} // Fine controllo funzione esistente