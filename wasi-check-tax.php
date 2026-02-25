<?php
/*
Plugin Name: Wasi Taxonomy Spy
Description: Scopre i nomi reali delle categorie e location.
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('wasi_check_tax', 'wasi_spy_taxonomies');

function wasi_spy_taxonomies() {
    $post_type = 'at_biz_dir';
    $taxonomies = get_object_taxonomies($post_type, 'objects');

    if ( empty($taxonomies) ) {
        return "Nessuna tassonomia trovata per '$post_type'. Sei sicuro che lo slug sia giusto?";
    }

    $out = "<h3>Analisi Tassonomie per: $post_type</h3>";
    $out .= "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    $out .= "<tr style='background:#eee'><th>Nome Visibile (Label)</th><th>SLUG TECNICO (Quello che serve a noi)</th><th>Esempi di termini</th></tr>";

    foreach ($taxonomies as $tax) {
        // Prendiamo 3 termini di esempio per capire se è quella giusta
        $terms = get_terms([
            'taxonomy' => $tax->name,
            'number' => 3,
            'hide_empty' => false,
        ]);
        
        $term_list = [];
        if(!is_wp_error($terms)) {
            foreach($terms as $t) $term_list[] = $t->name;
        }
        $esempi = implode(", ", $term_list);

        $out .= "<tr>";
        $out .= "<td><strong>" . $tax->label . "</strong></td>";
        $out .= "<td style='color:red; font-weight:bold;'>" . $tax->name . "</td>";
        $out .= "<td>$esempi</td>";
        $out .= "</tr>";
    }
    $out .= "</table>";

    return $out;
}