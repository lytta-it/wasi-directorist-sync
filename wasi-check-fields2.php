<?php
/*
Plugin Name: Wasi Diagnostics (Last Post)
Description: Analizza l'ultimo immobile creato (il Test Listing).
Version: 1.1
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('wasi_check_last', 'wasi_analyze_last_post');

function wasi_analyze_last_post() {
    // Cerchiamo l'ULTIMO immobile inserito (il tuo Test Listing)
    $args = [
        'post_type' => 'at_biz_dir', 
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order'   => 'DESC' // Prende il più recente
    ];

    $posts = get_posts($args);

    if ( empty($posts) ) return "Nessun immobile trovato.";

    $immobile = $posts[0];
    $id = $immobile->ID;
    $meta = get_post_meta($id);

    $output = "<h3>Analisi Immobile: " . $immobile->post_title . "</h3>";
    $output .= "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%; font-family:monospace;'>";
    $output .= "<tr style='background:#ddd;'><th>CHIAVE (Key)</th><th>VALORE (Value)</th></tr>";

    foreach ($meta as $key => $values) {
        // Nascondiamo roba inutile di sistema
        if ( strpos($key, '_edit') !== false || strpos($key, '_wp') !== false ) continue;

        $val = is_array($values) ? $values[0] : $values;
        
        // Se è un array serializzato (tipo le checkbox), lo mostriamo leggibile
        if ( is_serialized($val) ) {
            $val = print_r(unserialize($val), true);
            $val = "<pre>" . $val . "</pre>";
        }

        $output .= "<tr><td><strong>$key</strong></td><td>$val</td></tr>";
    }
    $output .= "</table>";

    return $output;
}