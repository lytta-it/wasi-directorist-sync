<?php
/**
 * Plugin Name:       Wasi Sync PRO by Lytta
 * Plugin URI:        https://www.lytta.it
 * Description:       Il connettore API definitivo per agenzie immobiliari. Sincronizza Wasi con WordPress usando Directorist o Advanced Custom Fields (ACF).
 * Version:           12.0.0
 * Author:            Lytta Web Agency
 * Author URI:        https://www.lytta.it/
 * License: GPLv2 or later
 * Text Domain:       lytta-wasi-sync
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/lytta-it/wasi-sync-pro
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