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
            'Configurazione Wasi',
            function () {
            echo '<p class="description">Inserisci le tue credenziali Wasi per abilitare la sincronizzazione.</p>';
        },
            'lytta_wasi_plugin_page'
        );

        add_settings_field('license_key', 'Licenza PRO Wasi Sync', array($this, 'license_key_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('target_platform', 'Destinazione Dati (Target)', array($this, 'target_platform_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');

        add_settings_field('company_id', 'Company ID', array($this, 'company_id_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('token', 'Token', array($this, 'token_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('email_report', 'Email Report', array($this, 'email_report_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('sync_limit', 'Sync Limit (Proprietà per run)', array($this, 'sync_limit_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('sync_frequency', 'Frequenza Cron', array($this, 'sync_frequency_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('mapping', 'Mappatura Categorie', array($this, 'mapping_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
    }

    public function sanitize_settings($input)
    {
        $sanitized = array();

        $sanitized['license_key'] = isset($input['license_key']) ? sanitize_text_field($input['license_key']) : '';
        $sanitized['target_platform'] = isset($input['target_platform']) ? sanitize_text_field($input['target_platform']) : 'directorist';
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
            'Wasi Sync PRO by Lytta',
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
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        if ($active_tab == 'contact') {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/views/contact-page.php';
        }
        else {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/views/settings-page.php';
        }
    }

    public function license_key_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['license_key']) ? esc_attr($options['license_key']) : '';
        echo "<input type='text' name='lytta_wasi_settings[license_key]' value='{$val}' class='regular-text code' placeholder='Inserisci per versione ILLIMITATA'>";
        echo "<br><small>Versione FREE (0.00€) limitata a max 10 immobili e singola galleria. Inserisci la chiave per sbloccare la <strong>Versione PRO (Premium)</strong>.</small>";
    }

    public function target_platform_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['target_platform']) ? $options['target_platform'] : 'directorist';
        echo "<select name='lytta_wasi_settings[target_platform]'>
            <option value='directorist' " . selected($val, 'directorist', false) . ">Modulo Directorist</option>
            <option value='acf' " . selected($val, 'acf', false) . ">Modulo Advanced Custom Fields (ACF Standard)</option>
        </select>";
        echo "<p class='description'>Scegli dove vuoi salvare su WordPress gli immobili scaricati da Wasi.</p>";
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
        $val = isset($options['email_report']) ? esc_attr($options['email_report']) : get_option('admin_email');
        echo "<input type='email' name='lytta_wasi_settings[email_report]' value='{$val}' class='regular-text'>";
        echo "<p class='description'>Lascia vuoto per disabilitare le notifiche email. Di default usa l'email principale del sito.</p>";
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
            <option value='hourly' " . selected($val, 'hourly', false) . ">Ogni Ora</option>
            <option value='every_two_hours' " . selected($val, 'every_two_hours', false) . ">Ogni 2 Ore</option>
            <option value='every_six_hours' " . selected($val, 'every_six_hours', false) . ">Ogni 6 Ore</option>
            <option value='twicedaily' " . selected($val, 'twicedaily', false) . ">Due volte al giorno</option>
            <option value='daily' " . selected($val, 'daily', false) . ">Una volta al giorno</option>
            <option value='weekly' " . selected($val, 'weekly', false) . ">Una volta a settimana</option>
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
