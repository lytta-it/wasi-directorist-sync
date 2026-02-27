<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file deletes all Wasi Sync PRO settings, scheduled crons, and safely
 * eradicates all WordPress posts (properties) that were imported by the plugin,
 * leaving the database perfectly clean.
 *
 * @package Lytta_Wasi_Sync
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Clear the scheduled Cron Job
wp_clear_scheduled_hook('lytta_wasi_cron_event');
// Clear the legacy one just in case
wp_clear_scheduled_hook('lytta_wasi_event_v11');

// 2. Eradicate all imported properties from the Database
// We identify Wasi properties purely by the presence of the 'wasi_id' meta key.
// This is the safest way to ensure we don't delete non-Wasi properties.
$args = array(
    'post_type' => 'any',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids', // Only fetch IDs for memory efficiency
    'meta_query' => array(
            array(
            'key' => 'wasi_id',
            'compare' => 'EXISTS',
        ),
    ),
);

$query = new WP_Query($args);

if (!empty($query->posts)) {
    foreach ($query->posts as $post_id) {
        // Force delete, bypassing the trash bin
        wp_delete_post($post_id, true);
    }
}

// 3. Delete plugin options from wp_options table
delete_option('lytta_wasi_settings');

// Done. The site is now fully clean.
