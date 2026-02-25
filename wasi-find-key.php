<?php
/*
Plugin Name: Lytta - Cerca Chiave
Description: Trova il nome del campo nascosto cercando una parola specifica.
Version: 1.0
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('lytta_find_key', 'lytta_search_meta_key');

function lytta_search_meta_key() {
    // Cerchiamo ovunque nel database un campo che contenga "TROVAMI"
    global $wpdb;
    
    // Query diretta al database per essere sicuri al 100%
    $results = $wpdb->get_results( "
        SELECT post_id, meta_key, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_value LIKE '%TROVAMI%'
    " );

    if ( empty($results) ) return "<h3>⚠️ Non ho trovato 'TROVAMI'. Assicurati di aver salvato l'immobile!</h3>";

    $output = "<h3>Ecco dove si nasconde la descrizione:</h3>";
    $output .= "<table border='1' cellpadding='10' style='background:#fff; border-collapse:collapse;'>";
    $output .= "<tr style='background:#eee'><th>ID Post</th><th>NOME CAMPO (Copia questo!)</th><th>Valore Trovato</th></tr>";

    foreach($results as $row) {
        // Ignoriamo i log di revisione o roba di sistema
        if ( strpos($row->meta_key, '_wp_') !== false ) continue;
        
        $output .= "<tr>";
        $output .= "<td>" . $row->post_id . "</td>";
        $output .= "<td style='background:#adff2f; font-weight:bold; font-size:18px;'>" . $row->meta_key . "</td>";
        $output .= "<td>" . esc_html(substr($row->meta_value, 0, 100)) . "...</td>";
        $output .= "</tr>";
    }
    $output .= "</table>";

    return $output;
}