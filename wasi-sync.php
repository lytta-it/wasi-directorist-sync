<?php
/**
 * Plugin Name:       Wasi to Directorist Sync
 * Plugin URI:        https://www.lytta.it
 * Description:       Sync properties from Wasi to Directorist effortlessly. Pro level API integration and intelligent data mapping.
 * Version:           12.0.0
 * Author:            Lytta.it
 * Author URI:        https://www.lytta.it
 * Text Domain:       lytta-wasi-sync
 * Domain Path:       /languages
 * GitHub Plugin URI: lytta-it/wasi-directorist-sync
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LYTTA_WASI_VERSION', '12.0.0');
define('LYTTA_WASI_DIR', plugin_dir_path(__FILE__));

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-lytta-wasi-sync.php';

/**
 * Begins execution of the plugin.
 */
function run_lytta_wasi_sync()
{
    $plugin = new Lytta_Wasi_Sync();
    $plugin->run();
}
run_lytta_wasi_sync();