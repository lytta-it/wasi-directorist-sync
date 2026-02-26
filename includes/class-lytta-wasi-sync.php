<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lytta_Wasi_Sync
{

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct()
    {
        if (defined('LYTTA_WASI_VERSION')) {
            $this->version = LYTTA_WASI_VERSION;
        }
        else {
            $this->version = '12.0.0';
        }
        $this->plugin_name = 'lytta-wasi-sync';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_cron_hooks();
        $this->init_github_updater();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-lytta-wasi-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-lytta-wasi-importer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-lytta-wasi-admin.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-lytta-github-updater.php';
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new Lytta_Wasi_Admin($this->get_plugin_name(), $this->get_version());
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        add_action('admin_init', array($plugin_admin, 'register_settings'));
    }

    private function define_cron_hooks()
    {
        $importer = new Lytta_Wasi_Importer();
        add_action('lytta_wasi_cron_event', array($importer, 'run_import'));
        add_shortcode('lytta_sync_manual', array($importer, 'run_import')); // For manual testing via shortcode
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
    }

    public function add_custom_cron_intervals($schedules)
    {
        $schedules['every_two_hours'] = array('interval' => 7200, 'display' => 'Every 2 Hours');
        $schedules['every_six_hours'] = array('interval' => 21600, 'display' => 'Every 6 Hours');
        $schedules['weekly'] = array('interval' => 604800, 'display' => 'Weekly');
        return $schedules;
    }

    private function init_github_updater()
    {
        if (is_admin()) {
            new Lytta_GitHub_Updater(__FILE__, 'lytta-it/wasi-directorist-sync');
        }
    }

    public function run()
    {
    // Init
    }

    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    public function get_version()
    {
        return $this->version;
    }
}
