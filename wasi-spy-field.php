<?php
/*
Plugin Name: Lytta - Campo Spia
Description: Trova dove si è nascosta la parola "VERIFICA_QUI".
Version: 1.0
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('lytta_spy_field', 'lytta_find_the_key');

function lytta_find_the_key() {
    // Cerchiamo l'immobile dove hai scritto la parola chiave
    $args = [
        'post_type'  => 'at_biz_dir',
        'meta_value' => 'VERIFICA_QUI', // Cerchiamo proprio questo valore
        'meta_compare' => 'LIKE'
    ];

    $posts = get_posts($args);

    if ( empty($posts) ) return "<h3>⚠️ Non ho trovato nessun immobile con la scritta 'VERIFICA_QUI'. Assicurati di aver salvato!</h3>";

    $post = $posts[0];
    $meta = get_post_meta($post->ID);

    $output = "<h3>Trovato! Ecco il campo giusto:</h3>";
    $output .= "<table border='1' cellpadding='10'>";
    
    foreach($meta as $key => $val) {
        $v = $val[0];
        // Se il valore contiene la nostra parola magica, bingo!
        if ( strpos($v, 'VERIFICA_QUI') !== false ) {
            $output .= "<tr style='background:#adff2f; font-weight:bold; font-size:18px;'>";
            $output .= "<td>IL CAMPO GIUSTO È:</td><td>$key</td>";
            $output .= "</tr>";
        }
    }
    $output .= "</table>";

    return $output;
}