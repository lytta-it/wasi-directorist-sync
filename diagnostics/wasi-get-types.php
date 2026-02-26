<?php
/*
Diagnostic Script: Wasi Get Types
Description: Scarica la lista di tutte le tipologie immobiliari di Wasi.
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_shortcode('wasi_get_types', 'wasi_get_all_types');

function wasi_get_all_types() {
    $id_company = '27306135';
    $token = 'P2pf_XECn_jC9n_t0fP'; // <--- TOKEN

    $url = "https://api.wasi.co/v1/property-type/all?id_company=$id_company&wasi_token=$token";
    $response = wp_remote_get( $url );
    
    if ( is_wp_error( $response ) ) return "Errore API.";
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $output = "<h3>Lista Tipi Wasi</h3><ul>";
    foreach($data as $type) {
        if(isset($type['id_property_type'])) {
            $output .= "<li>ID: <strong>" . $type['id_property_type'] . "</strong> = " . $type['nombre'] . "</li>";
        }
    }
    $output .= "</ul>";
    return $output;
}
