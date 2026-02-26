<?php
/*
Diagnostic Script: Wasi Counter
Description: Mostra il numero totale di immobili presenti su Wasi.
Version: 1.0
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('wasi_count_total', 'wasi_get_total_properties');

function wasi_get_total_properties() {
    // CONFIGURAZIONE
    $id_company = '27306135';
    $wasi_token = 'P2pf_XECn_jC9n_t0fP'; // <--- TOKEN QUI

    // Chiediamo solo 1 immobile (take=1) per essere veloci. 
    // Wasi ci restituirà comunque il "total" nell'intestazione del JSON.
    $url = "https://api.wasi.co/v1/property/search?id_company=$id_company&wasi_token=$wasi_token&take=1";

    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) {
        return "<p style='color:red;'>Errore di connessione: " . $response->get_error_message() . "</p>";
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! $data ) {
        return "<p style='color:red;'>Dati non validi ricevuti da Wasi.</p>";
    }

    // Lettura del campo 'total'
    if ( isset($data['total']) ) {
        $count = $data['total'];
        return "<div style='padding:20px; background:#eaffea; border:1px solid #00aa00; font-size:18px;'>
                    ✅ <strong>Totale Immobili su Wasi:</strong> $count
                </div>";
    } else {
        return "<p style='color:orange;'>Il campo 'total' non è stato trovato nella risposta.</p>";
    }
}
