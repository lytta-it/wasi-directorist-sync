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

define('LYTTA_WASI_VERSION', '13.0.0');
define('LYTTA_WASI_DIR', plugin_dir_path(__FILE__));
define('LYTTA_WASI_SECRET', 'Lyt74_W4si_Pr0_2026!#');

/**
 * Helper to dynamically verify if the License Key matches the Company ID Hash.
 */
function lytta_wasi_is_pro()
{
    $options = get_option('lytta_wasi_settings', []);
    $key = isset($options['license_key']) ? trim($options['license_key']) : '';
    $cid = isset($options['company_id']) ? trim($options['company_id']) : '';

    if (empty($key) || empty($cid))
        return false;

    // Developer bypass
    if ($key === 'wasi-pro-dev' || $key === 'wasi_sync_lytta_2026')
        return true;

    $raw_string = LYTTA_WASI_SECRET . '_CID_' . $cid;
    $hashed = strtoupper(md5($raw_string));

    $part1 = substr($hashed, 0, 5);
    $part2 = substr($hashed, 5, 5);
    $part3 = substr($hashed, 10, 5);
    $part4 = substr($hashed, 15, 5);

    $expected_key = "PRO-LYTTA-{$part1}-{$part2}-{$part3}-{$part4}";

    return ($key === $expected_key);
}

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