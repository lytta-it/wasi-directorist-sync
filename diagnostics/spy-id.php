// AGGIUNGI QUESTO IN FONDO AL FILE
add_shortcode('lytta_spy_ids', function() {
    $terms = get_terms([
        'taxonomy' => 'at_biz_dir-type',
        'hide_empty' => false,
    ]);
    
    if ( empty($terms) || is_wp_error($terms) ) return "<h3>⚠️ Nessun Directory Type trovato! Crea un tipo in Directorist.</h3>";

    $output = "<h3>🔍 LISTA ID DIRECTORY TYPE</h3><ul>";
    foreach ($terms as $term) {
        $output .= "<li><strong>" . $term->name . "</strong> (Slug: " . $term->slug . ") -> <strong>ID: " . $term->term_id . "</strong></li>";
    }
    $output .= "</ul>";
    
    // Controlla anche i Piani Tariffari già che ci siamo
    $plans = get_posts(['post_type' => 'atbdp_plan', 'numberposts' => -1]);
    $output .= "<h3>💰 LISTA ID PIANI</h3><ul>";
    foreach ($plans as $p) {
        $output .= "<li>" . $p->post_title . " -> <strong>ID: " . $p->ID . "</strong></li>";
    }
    $output .= "</ul>";

    return $output;
});
