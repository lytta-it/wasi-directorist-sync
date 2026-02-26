<?php
/*
Diagnostic Script: Lytta - Lista Totale Campi
Description: Mostra TUTTI i metadati dell'ultimo immobile modificato.
Version: 2.0
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('lytta_spy_full', 'lytta_show_all_meta');

function lytta_show_all_meta() {
    // Prende l'ultimo immobile modificato (quello dove hai fatto le prove)
    $args = [
        'post_type' => 'at_biz_dir', 
        'posts_per_page' => 1,
        'orderby' => 'modified',
        'order'   => 'DESC' 
    ];

    $posts = get_posts($args);
    if ( empty($posts) ) return "Nessun immobile trovato.";

    $post = $posts[0];
    $meta = get_post_meta($post->ID);

    $output = "<h3>Analisi Totale per: " . $post->post_title . "</h3>";
    $output .= "<p>Scorri questa lista. Quando vedi il testo della tua descrizione a destra, segnati il nome in <strong>GRASSETTO</strong> a sinistra.</p>";
    $output .= "<table border='1' cellpadding='5' style='width:100%; border-collapse:collapse; font-size:13px; font-family:monospace;'>";
    $output .= "<tr style='background:#333; color:#fff;'><th>NOME CAMPO (Chiave)</th><th>VALORE (Contenuto)</th></tr>";

    foreach($meta as $key => $values) {
        // Saltiamo i campi di sistema noiosi (_edit_lock, ecc)
        if ( strpos($key, '_edit') !== false ) continue;

        $val = $values[0];
        
        // Se è un array serializzato, lo rendiamo leggibile
        if ( is_serialized($val) ) {
            $val = print_r(unserialize($val), true);
            $val = "<pre style='margin:0; white-space:pre-wrap;'>" . esc_html($val) . "</pre>";
        } else {
            $val = esc_html(substr($val, 0, 300)); // Tagliamo se è lunghissimo
        }

        // Evidenziamo se sembra una descrizione
        $style = "";
        if ( strlen($val) > 50 && strpos($key, '_') === 0 ) {
            $style = "style='background:#ffffcc;'"; // Evidenzia campi lunghi
        }

        $output .= "<tr $style>";
        $output .= "<td style='font-weight:bold; color:#d00;'>$key</td>";
        $output .= "<td>$val</td>";
        $output .= "</tr>";
    }
    $output .= "</table>";

    return $output;
}
