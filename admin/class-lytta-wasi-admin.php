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

        // Admin Notices Hook
        add_action('admin_notices', array($this, 'plugin_dependencies_notices'));

        add_settings_section(
            'lytta_wasi_plugin_page_section',
            __('Wasi Configuration', 'lytta-wasi-sync'),
            function () {
            echo '<p class="description">' . esc_html__('Enter your Wasi credentials to enable synchronization.', 'lytta-wasi-sync') . '</p>';
        },
            'lytta_wasi_plugin_page'
        );

        add_settings_field('license_key', __('Wasi Sync PRO License', 'lytta-wasi-sync'), array($this, 'license_key_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('target_platform', __('Data Destination (Target)', 'lytta-wasi-sync'), array($this, 'target_platform_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');

        add_settings_field('company_id', __('Company ID', 'lytta-wasi-sync'), array($this, 'company_id_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('token', __('Token', 'lytta-wasi-sync'), array($this, 'token_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('email_report', __('Email Report', 'lytta-wasi-sync'), array($this, 'email_report_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('sync_limit', __('Sync Limit (Properties per run)', 'lytta-wasi-sync'), array($this, 'sync_limit_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
        add_settings_field('sync_frequency', __('Cron Frequency', 'lytta-wasi-sync'), array($this, 'sync_frequency_render'), 'lytta_wasi_plugin_page', 'lytta_wasi_plugin_page_section');
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

        // Cleanup Old legacy v11 cron if it exists
        wp_clear_scheduled_hook('lytta_wasi_event_v11');

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
        elseif ($active_tab == 'mapping') {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/views/mapping-page.php';
        }
        else {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/views/settings-page.php';
        }
    }

    public function plugin_dependencies_notices()
    {
        if (!current_user_can('manage_options'))
            return;

        $options = get_option('lytta_wasi_settings', []);
        $target = isset($options['target_platform']) ? $options['target_platform'] : 'directorist';

        if ($target === 'directorist' && !defined('ATBDP_VERSION')) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Wasi Sync PRO:</strong> ' . esc_html__('You selected Directorist as the target platform, but the Directorist plugin is not active!', 'lytta-wasi-sync') . '</p></div>';
        }
        elseif ($target === 'acf' && !class_exists('ACF')) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Wasi Sync PRO:</strong> ' . esc_html__('You selected ACF as the target platform, but the Advanced Custom Fields plugin is not active!', 'lytta-wasi-sync') . '</p></div>';
        }
    }

    public function license_key_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['license_key']) ? esc_attr($options['license_key']) : '';
        echo "<input type='text' name='lytta_wasi_settings[license_key]' value='{$val}' class='regular-text code' placeholder='" . esc_attr__('Enter key for UNLIMITED version', 'lytta-wasi-sync') . "'>";
        echo "<br><small>" . wp_kses_post(__('FREE Version (0.00€) is limited to 10 properties and a single image. Enter the key to unlock the <strong>PRO Version (Premium)</strong>.', 'lytta-wasi-sync')) . "</small>";
    }

    public function target_platform_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['target_platform']) ? $options['target_platform'] : 'directorist';
        echo "<select name='lytta_wasi_settings[target_platform]'>
            <option value='directorist' " . selected($val, 'directorist', false) . ">" . esc_html__('Directorist Module', 'lytta-wasi-sync') . "</option>
            <option value='acf' " . selected($val, 'acf', false) . ">" . esc_html__('Advanced Custom Fields (ACF Standard) Module', 'lytta-wasi-sync') . "</option>
        </select>";
        echo "<p class='description'>" . esc_html__('Choose where to save the properties downloaded from Wasi into WordPress.', 'lytta-wasi-sync') . "</p>";
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
        echo "<p class='description'>" . esc_html__('Leave empty to disable email notifications. Defaults to the main site admin email.', 'lytta-wasi-sync') . "</p>";
    }

    public function sync_limit_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['sync_limit']) ? esc_attr($options['sync_limit']) : '10';
        echo "<input type='number' name='lytta_wasi_settings[sync_limit]' value='{$val}' class='small-text'>";
        echo "<p class='description'>" . wp_kses_post(__('Set the number of properties to synchronize during each automatic background run.', 'lytta-wasi-sync')) . "</p>";
        echo "<p class='description'><strong>" . esc_html__('Performance Tip:', 'lytta-wasi-sync') . "</strong> " . wp_kses_post(__('Higher limits process more properties per run but consume more server Memory and Time. If your catalog has <strong>over 1000 properties</strong>, do NOT set this value too high (keep it around 30-50) to prevent PHP timeout errors. Instead, temporarily set the Cron Frequency to "Hourly" to ingest the entire catalog over a few days securely, then revert it to a slower frequency.', 'lytta-wasi-sync')) . "</p>";
    }

    public function sync_frequency_render()
    {
        $options = get_option('lytta_wasi_settings');
        $val = isset($options['sync_frequency']) ? $options['sync_frequency'] : 'twicedaily';
        echo "<select name='lytta_wasi_settings[sync_frequency]'>
            <option value='hourly' " . selected($val, 'hourly', false) . ">" . esc_html__('Hourly', 'lytta-wasi-sync') . "</option>
            <option value='every_two_hours' " . selected($val, 'every_two_hours', false) . ">" . esc_html__('Every 2 Hours', 'lytta-wasi-sync') . "</option>
            <option value='every_six_hours' " . selected($val, 'every_six_hours', false) . ">" . esc_html__('Every 6 Hours', 'lytta-wasi-sync') . "</option>
            <option value='twicedaily' " . selected($val, 'twicedaily', false) . ">" . esc_html__('Twice a day', 'lytta-wasi-sync') . "</option>
            <option value='daily' " . selected($val, 'daily', false) . ">" . esc_html__('Once a day', 'lytta-wasi-sync') . "</option>
            <option value='weekly' " . selected($val, 'weekly', false) . ">" . esc_html__('Once a week', 'lytta-wasi-sync') . "</option>
        </select>";
    }

}
