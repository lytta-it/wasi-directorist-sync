<?php
/*
Plugin Name: Directorist Field Spy
Description: Mostra i nomi reali dei campi nel database.
Version: 1.0
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('spia_directorist', 'spia_campi_funzione');

function spia_campi_funzione() {
    // Cerca un solo immobile esistente
    $args = array(
        'post_type' => 'at_biz_dir', // Slug confermato
        'posts_per_page' => 1,
    );
    $loop = new WP_Query($args);

    $output = "<h3>Analisi Campi Directorist</h3>";

    if($loop->have_posts()) {
        while($loop->have_posts()) {
            $loop->the_post();
            $id = get_the_ID();
            $output .= "<p>Analizzo l'immobile: <strong>" . get_the_title() . "</strong> (ID: $id)</p>";
            
            // Prende TUTTI i meta dati (i campi nascosti)
            $all_meta = get_post_meta($id);
            
            $output .= "<pre style='background:#eee; padding:10px; border:1px solid #ccc;'>";
            foreach($all_meta as $key => $value) {
                // Nascondo i campi di sistema inutili
                if(strpos($key, '_edit') !== false || strpos($key, '_wp') !== false) continue;
                
                // Mostra CHIAVE -> VALORE
                $output .= "CHIAVE: [$key]  ===>  VALORE: " . print_r($value[0], true) . "\n";
            }
            $output .= "</pre>";
        }
    } else {
        $output .= "Nessun immobile trovato. Creane uno a mano di prova per favore!";
    }
    wp_reset_postdata();
    return $output;
}