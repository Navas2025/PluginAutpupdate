<?php
if (!defined('ABSPATH')) {
    exit;
}

add_filter('site_transient_update_themes', function ($transient) {
    $api_key = get_option('plugin_updater_api_key', '');
    if (!$api_key) {
        return $transient;
    }

    $args = [
        'timeout' => 5,
        'headers' => []
    ];

    $cached_updates = get_transient('plugin_updater_theme_update_data');
    if (false === $cached_updates) {
        $response = wp_remote_get(
            PLUGIN_UPDATER_SERVER . 'get-themes.php?apiKey=' . urlencode($api_key),
            $args
        );

        if (is_wp_error($response)) {
            return $transient;
        }

        $cached_updates = json_decode(wp_remote_retrieve_body($response), true);
        set_transient('plugin_updater_theme_update_data', $cached_updates, 900);
    }

    if (isset($cached_updates['error']) || empty($cached_updates)) {
        return $transient;
    }

    $all_themes = wp_get_themes();

    if (!is_object($transient)) {
        $transient = new stdClass();
        $transient->response = [];
    }

    foreach ($cached_updates as $theme) {
        $theme_slug = $theme['slug'];
        
        if (isset($all_themes[$theme_slug])) {
            $installed_theme = $all_themes[$theme_slug];
            $installed_version = $installed_theme->get('Version');
            
            if (version_compare($installed_version, $theme['version'], '<')) {
                $transient->response[$theme_slug] = [
                    'theme'       => $theme_slug,
                    'new_version' => $theme['version'],
                    'package'     => $theme['download_url'],
                    'url'         => ''
                ];
            }
        }
    }

    return $transient;
}, 10, 1);

add_action('upgrader_process_complete', function ($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'theme') {
        delete_site_transient('update_themes');
        delete_transient('plugin_updater_theme_update_data');
    }
}, 10, 2);