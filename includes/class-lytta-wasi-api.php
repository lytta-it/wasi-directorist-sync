<?php
/**
 * The API service for connecting to Wasi.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Wasi_API
{

    private $company_id;
    private $token;
    private $base_url = 'https://api.wasi.co/v1/';

    public function __construct()
    {
        $options = get_option('lytta_wasi_settings', []);
        $this->company_id = !empty($options['company_id']) ? sanitize_text_field($options['company_id']) : '';
        $this->token = !empty($options['token']) ? sanitize_text_field($options['token']) : '';
    }

    public function is_configured()
    {
        return !empty($this->company_id) && !empty($this->token);
    }

    public function get_properties($limit = 10)
    {
        if (!$this->is_configured())
            return new WP_Error('wasi_not_configured', 'Missing Company ID or Token.');

        $url = $this->base_url . "property/search?id_company={$this->company_id}&wasi_token={$this->token}&take={$limit}&orderby=updated_at&direction=desc";

        $response = wp_remote_get($url, ['timeout' => 60]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !is_array($data)) {
            return new WP_Error('wasi_invalid_json', 'Invalid JSON payload from Wasi API.');
        }

        return $data;
    }

    public function get_all_active_property_ids()
    {
        if (!$this->is_configured())
            return [];

        if (function_exists('ini_set'))
            @ini_set('memory_limit', '512M');

        $url = $this->base_url . "property/search?id_company={$this->company_id}&wasi_token={$this->token}&take=2000&fields=id_property";
        $response = wp_remote_get($url, ['timeout' => 60]);

        if (is_wp_error($response))
            return [];

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !is_array($data))
            return [];

        $wasi_active_ids = [];
        foreach ($data as $k => $prop) {
            if (isset($prop['id_property'])) {
                $wasi_active_ids[] = $prop['id_property'];
            }
        }

        return $wasi_active_ids;
    }

    public function get_property_types()
    {
        if (!$this->is_configured())
            return [];

        $url = $this->base_url . "property-type/all?id_company={$this->company_id}&wasi_token={$this->token}";
        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response))
            return [];

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return isset($data['property_type']) ? $data['property_type'] : (isset($data['property_types']) ? $data['property_types'] : $data);
    }
}
