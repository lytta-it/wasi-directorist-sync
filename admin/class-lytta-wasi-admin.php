<?php
/**
 * The admin-specific functionality of the plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Wasi_Admin
{

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function register_settings()
    {
        register_setting('lytta_wasi_plugin_page', 'lytta_wasi_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'lytta_wasi_plugin_page_section',
            'API Configuration (Wasi to Directorist)',
            function () {
            echo '<p class="description">Enter your Wasi App credentials below. A valid Company ID and Token are required to sync properties.</p>'; },
            'lytta_wasi_plugin_page'
        );

        add_settings_field('company_id', 'Company ID', array($this, 'company_id_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('token', 'Token', array($this, 'token_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('email_report', 'Email Report', array($this, 'email_report_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('sync_limit', 'Sync Limit (Properties per run)', array($this, 'sync_limit_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('sync_frequency', 'Sync Frequency', array($this, 'sync_frequency_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('mapping', 'Category Mapping Rules', array($this, 'mapping_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();

        $sanitized['company_id'] = sanitize_text_field($input['company_id']);
        $sanitized['token'] = sanitize_text_field($input['token']);
        $sanitized['email_report'] = sanitize_email($input['email_report']);
        $sanitized['sync_limit'] = absint($input['sync_limit']);
        $sanitized['sync_frequency'] = sanitize_key($input['sync_frequency']);
        $sanitized['mapping'] = wp_kses_post($input['mapping']);

        // Schedule cron if frequency changed
        $old_options = get_option('lytta_wasi_settings');
        if (!empty($sanitized['sync_frequency']) && (!isset($old_options['sync_frequency']) || $old_options['sync_frequency'] !== $sanitized['sync_frequency'])) {
            wp_clear_scheduled_hook('lytta_wasi_cron_event');
            wp_schedule_event(time(), $sanitized['sync_frequency'], 'lytta_wasi_cron_event');
        }

        return $sanitized;
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'Wasi to Directorist Sync by Lytta',
            'Wasi Sync',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page'),
            'dashicons-update',
            85
        );
    }

    public function display_plugin_setup_page()
    {
        // Include the view file securely
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/views/settings-page.php';
    }

    public function company_id_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['company_id']) ? esc_attr($options['company_id']) : '';
        echo "<input type='text' name='lytta_wasi_settings[company_id]' value='{$val}' class='regular-text'>";
    }

    public function token_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['token']) ? esc_attr($options['token']) : '';
        echo "<input type='password' name='lytta_wasi_settings[token]' value='{$val}' class='regular-text'>";
    }

    public function email_report_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['email_report']) ? esc_attr($options['email_report']) : '';
        echo "<input type='email' name='lytta_wasi_settings[email_report]' value='{$val}' class='regular-text'>";
    }

    public function sync_limit_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['sync_limit']) ? esc_attr($options['sync_limit']) : '10';
        echo "<input type='number' name='lytta_wasi_settings[sync_limit]' value='{$val}' class='small-text'>";
    }

    public function sync_frequency_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['sync_frequency']) ? $options['sync_frequency'] : 'twicedaily';
        echo "<select name='lytta_wasi_settings[sync_frequency]'>
            <option value='hourly' " . selected($val, 'hourly', false) . ">Hourly</option>
            <option value='twicedaily' " . selected($val, 'twicedaily', false) . ">Twice Daily</option>
            <option value='daily' " . selected($val, 'daily', false) . ">Daily</option>
        </select>";
    }

    public function mapping_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['mapping']) ? esc_textarea($options['mapping']) : '';
        echo "<textarea name='lytta_wasi_settings[mapping]' rows='5' cols='50' class='large-text code'>{$val}</textarea>";
        echo "<p class='description'>Format: <code>WASI_ID=Category Name</code>. Multiple IDs can be comma separated. One rule per line.<br>E.g. <code>2,14=Apartamento</code>.</p>";
    }

}
