<?php
/**
 * Handles the actual importation and orchestration of data from Wasi.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Wasi_Importer
{

    private $api;
    private $config;
    private $adapter;

    public function __construct()
    {
        $this->api = new Lytta_Wasi_API();

        $options = get_option('lytta_wasi_settings', []);

        $this->config = [
            'author_id' => !empty($options['author_id']) ? intval($options['author_id']) : 1,
            'email_report' => isset($options['email_report']) ? sanitize_email($options['email_report']) : get_option('admin_email'),
            'enable_email' => true,
            'sync_limit' => !empty($options['sync_limit']) ? intval($options['sync_limit']) : 10,
            'mapping' => !empty($options['mapping']) ? wp_kses_post($options['mapping']) : '',
            'target' => !empty($options['target_platform']) ? sanitize_text_field($options['target_platform']) : 'directorist',
            'license_key' => !empty($options['license_key']) ? sanitize_text_field($options['license_key']) : ''
        ];

        // Load the correct adapter
        if ($this->config['target'] === 'acf') {
            $this->adapter = new Lytta_Adapter_ACF($this->config['mapping']);
        }
        else {
            // Default to Directorist
            $this->adapter = new Lytta_Adapter_Directorist($this->config['mapping']);
        }
    }

    private function is_pro_licensed()
    {
        // Implement a robust license check later. For now, we assume if any key exists it's active.
        return !empty($this->config['license_key']);
    }

    public function run_import($atts = [])
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST))
            return;

        $start_time = time();
        $max_execution_time = 45;
        if (function_exists('set_time_limit'))
            @set_time_limit(300);

        $limit = (isset($atts['limit'])) ? intval($atts['limit']) : intval($this->config['sync_limit']);

        // --- FREEMIUM RESTRICTIONS ---
        $is_pro = $this->is_pro_licensed();
        // If not PRO, hardcap the total properties synchronized ever to 10
        if (!$is_pro) {
            $limit = 10;
        }

        $data = $this->api->get_properties($limit);

        if (is_wp_error($data)) {
            return "Errore API: " . $data->get_error_message();
        }

        $stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0, 'drafted' => 0];
        $plan_id = $this->adapter->get_default_plan_id();

        foreach ($data as $key => $immobile) {
            if ((time() - $start_time) > $max_execution_time)
                break;
            if (!is_numeric($key) || !isset($immobile['id_property']))
                continue;

            // --- FREEMIUM HARD STOP COUNT ---
            if (!$is_pro && ($stats['inserted'] + $stats['updated']) >= 10) {
                // We reached the 10 limit stop for Free Version
                error_log("Lytta Wasi Sync: Free Version limit of 10 properties reached.");
                break;
            }

            $wasi_id = sanitize_text_field($immobile['id_property']);
            $title = isset($immobile['title']) ? sanitize_text_field($immobile['title']) : 'Senza Titolo';

            $desc_raw = isset($immobile['observations']) ? $immobile['observations'] : '';
            $desc_html = wp_kses_post(html_entity_decode($desc_raw)); // Sanitized HTML
            if (strpos($desc_html, '<') === false)
                $desc_html = nl2br($desc_html);

            // Fetch adapter-specific args (like post_type logic)
            $post_args = $this->adapter->map_post_args($wasi_id, $title, $desc_html, $this->config['author_id']);

            // Check if post already exists
            $existing_post = get_posts([
                'post_type' => $post_args['post_type'],
                'meta_key' => 'wasi_id', // Note: Make sure Directorist mapping didn't change this core key
                'meta_value' => $wasi_id,
                'posts_per_page' => 1,
                'post_status' => 'any'
            ]);

            $post_id = 0;
            $is_update = false;

            if ($existing_post) {
                $post_args['ID'] = $existing_post[0]->ID;
                $is_update = true;
                $post_id = wp_update_post($post_args);
            }
            else {
                $post_id = wp_insert_post($post_args);
                if (!is_wp_error($post_id))
                    update_post_meta($post_id, 'wasi_id', $wasi_id);
            }

            if (is_wp_error($post_id)) {
                $stats['errors']++;
                continue;
            }

            // Adapter logic
            $this->adapter->set_taxonomies($post_id, $immobile);
            $this->adapter->map_fields($post_id, $immobile, $wasi_id, $desc_html, $plan_id);

            // Images
            // Free Version constraint: Do not download galleries unless PRO, just main thumbnail
            $should_download_images = !$is_update; // Usually only download on insert to save time

            if ($should_download_images && !empty($immobile['galleries'])) {
                if ((time() - $start_time) < ($max_execution_time - 5)) {
                    $images_to_process = $is_pro ? $immobile['galleries'][0] : [$immobile['galleries'][0][0]]; // Only 1 image if Free
                    if (!empty($images_to_process)) {
                        $this->adapter->process_gallery($post_id, $images_to_process);
                    }
                }
            }

            $this->adapter->force_update_trigger($post_id);

            if ($is_update)
                $stats['updated']++;
            else
                $stats['inserted']++;
        }

        // Cleanup Orchestration (Deleting orphans)
        if ((defined('DOING_CRON') && DOING_CRON) || isset($atts['cleanup'])) {
            $dummy_args = $this->adapter->map_post_args('', '', '', 0);
            $orphans_removed = $this->run_cleanup($dummy_args['post_type']);
            $stats['drafted'] = $orphans_removed;
        }

        if ($this->config['enable_email'] && !empty($this->config['email_report'])) {
            $this->send_report_email($stats, $is_pro);
        }

        $license_msg = $is_pro ? 'PRO Active' : 'FREE Version LIMIT: 10';
        return "Sync V12.0 OK [{$license_msg}]: Nuovi {$stats['inserted']}, Agg. {$stats['updated']}, Rimossi {$stats['drafted']}.";
    }

    private function run_cleanup($target_post_type)
    {
        $wasi_active_ids = $this->api->get_all_active_property_ids();
        if (empty($wasi_active_ids))
            return 0; // Fail safe

        $all_wp_posts = get_posts([
            'post_type' => $target_post_type,
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
            'meta_query' => [['key' => 'wasi_id', 'compare' => 'EXISTS']]
        ]);

        $count_cleaned = 0;
        foreach ($all_wp_posts as $p_id) {
            $wp_wasi_id = get_post_meta($p_id, 'wasi_id', true);
            if (!empty($wp_wasi_id) && !in_array($wp_wasi_id, $wasi_active_ids)) {
                wp_update_post(['ID' => $p_id, 'post_status' => 'draft']);
                $count_cleaned++;
            }
        }
        return $count_cleaned;
    }

    private function send_report_email($stats, $is_pro)
    {
        $subject = "Report Sync Lytta - " . date("d/m/Y H:i");
        $message = "Report Sync Lytta - " . date("d/m/Y H:i") . "\n\n";

        if (!$is_pro) {
            $message .= "ATTENZIONE: Stai utilizzando la versione FREE del modulo.\n";
            $message .= "Il limite massimo di esportazione è bloccato a 10 immobili.\n";
            $message .= "Acquista una licenza PRO per sincronizzare l'intero catalogo.\n\n";
        }

        $message .= "Nuovi Immobili: " . $stats['inserted'] . "\n";
        $message .= "Aggiornati: " . $stats['updated'] . "\n";
        $message .= "Rimossi/Bozze: " . $stats['drafted'] . "\n";
        $message .= "Errori: " . $stats['errors'] . "\n";

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($this->config['email_report'], $subject, $message, $headers);
    }
}
