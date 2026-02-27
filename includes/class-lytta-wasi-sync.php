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
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
        $this->init_github_updater();
    }

    private function set_locale()
    {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'lytta-wasi-sync',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-lytta-wasi-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/adapters/class-lytta-adapter-directorist.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/adapters/class-lytta-adapter-acf.php';
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

    private function define_public_hooks()
    {
        add_action('init', array($this, 'register_custom_post_type'));
    }

    public function register_custom_post_type()
    {
        $options = get_option('lytta_wasi_settings');
        $target = isset($options['target_platform']) ? $options['target_platform'] : 'directorist';
        if ($target === 'acf') {
            $cpt_slug = isset($options['acf_cpt_slug']) && !empty($options['acf_cpt_slug']) ? $options['acf_cpt_slug'] : 'property';

            // Native standard formats are ignored
            if ($cpt_slug === 'post' || $cpt_slug === 'page') {
                return;
            }

            $labels = array(
                'name' => _x('Properties', 'Post Type General Name', 'lytta-wasi-sync'),
                'singular_name' => _x('Property', 'Post Type Singular Name', 'lytta-wasi-sync'),
                'menu_name' => __('Properties', 'lytta-wasi-sync'),
                'all_items' => __('All Properties', 'lytta-wasi-sync'),
                'add_new_item' => __('Add New Property', 'lytta-wasi-sync'),
                'add_new' => __('Add New', 'lytta-wasi-sync'),
            );
            $args = array(
                'label' => __('Property', 'lytta-wasi-sync'),
                'labels' => $labels,
                'supports' => array('title', 'editor', 'thumbnail', 'revisions', 'custom-fields'),
                'hierarchical' => false,
                'public' => true,
                'show_ui' => true,
                'show_in_menu' => true,
                'menu_position' => 25,
                'menu_icon' => 'dashicons-admin-home',
                'show_in_admin_bar' => true,
                'show_in_nav_menus' => true,
                'can_export' => true,
                'has_archive' => true,
                'exclude_from_search' => false,
                'publicly_queryable' => true,
                'capability_type' => 'post',
                'show_in_rest' => true,
            );
            register_post_type($cpt_slug, $args);

            $tax_labels = array(
                'name' => _x('Property Categories', 'taxonomy general name', 'lytta-wasi-sync'),
                'singular_name' => _x('Property Category', 'taxonomy singular name', 'lytta-wasi-sync'),
                'menu_name' => __('Categories', 'lytta-wasi-sync'),
            );
            $tax_args = array(
                'hierarchical' => true,
                'labels' => $tax_labels,
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'property-category'),
                'show_in_rest' => true,
            );
            register_taxonomy('property_category', array($cpt_slug), $tax_args);
        }
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
