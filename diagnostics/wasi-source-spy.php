<?php
/*
Plugin Name: Lytta - Wasi Source Spy
Description: Mostra i dati GREZZI originali che arrivano dall'API di Wasi (utile per controllare i nomi dei campi).
Version: 1.0
Author: Lytta
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('wasi_spy_source', 'lytta_spy_wasi_api');

function lytta_spy_wasi_api() {
    // 1. Configurazione
    $id_company = '27306135';
    $wasi_token = 'P2pf_XECn_jC9n_t0fP';

    // 2. Chiamata API (Ne prendiamo solo 1 per analisi)
    $url = "https://api.wasi.co/v1/property/search?id_company=$id_company&wasi_token=$wasi_token&take=1";

    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) {
        return "<p style='color:red'>Errore API: " . $response->get_error_message() . "</p>";
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! $data ) return "Nessun dato valido ricevuto.";

    // 3. Output a schermo
    $output = "<h3>🔍 Dati Grezzi Wasi (Raw JSON)</h3>";
    $output .= "<p>Ecco esattamente cosa ci invia Wasi per il primo immobile trovato. Usa questi nomi per la mappatura.</p>";
    
    // Prendiamo il primo immobile dell'array (la chiave 0 solitamente, o la prima che capita)
    $first_property = null;
    foreach($data as $k => $v) {
        if(is_array($v) && isset($v['id_property'])) {
            $first_property = $v;
            break;
        }
    }

    if($first_property) {
        $output .= "<textarea style='width:100%; height:600px; font-family:monospace; font-size:12px; background:#f0f0f1; padding:10px;'>";
        $output .= print_r($first_property, true);
        $output .= "</textarea>";
    } else {
        $output .= "Non ho trovato immobili nella risposta. Risposta completa:<br>";
        $output .= "<pre>" . print_r($data, true) . "</pre>";
    }

    return $output;
}