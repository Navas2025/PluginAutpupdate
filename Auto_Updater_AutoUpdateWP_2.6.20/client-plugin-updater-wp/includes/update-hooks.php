<?php
if (!defined('ABSPATH')) {
    exit; // Proteger acceso directo
}

add_filter('site_transient_update_plugins', function ($transient) {
    $api_key = get_option('plugin_updater_api_key', '');
    if (!$api_key) {
        return $transient;
    }

    // Configurar argumentos para la petición remota
    $args = [
        'timeout' => 5, // Reducir el timeout a 5 segundos
        'headers' => []
    ];

    // (Si el servidor envía Last-Modified, se podría incluir aquí el header If-Modified-Since)

    // Intentar obtener la respuesta remota cacheada durante 15 minutos (900 segundos)
    $cached_updates = get_transient('plugin_updater_update_data');
    if (false === $cached_updates) {
        $response = wp_remote_get(
            PLUGIN_UPDATER_SERVER . 'get-plugins.php?apiKey=' . urlencode($api_key),
            $args
        );

        if (is_wp_error($response)) {
            add_action('admin_notices', function () use ($response) {
                echo '<div class="notice notice-error is-dismissible"><p>Error al conectar con el servidor: ' . $response->get_error_message() . '</p></div>';
            });
            return $transient;
        }

        $cached_updates = json_decode(wp_remote_retrieve_body($response), true);
        // Cachear la respuesta por 15 minutos
        set_transient('plugin_updater_update_data', $cached_updates, 900);
    }

    if (isset($cached_updates['error'])) {
        return $transient;
    }

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();

    if (!is_object($transient)) {
        $transient = new stdClass();
        $transient->response = [];
    }

    // Recorrer la respuesta remota y comparar versiones
    foreach ($cached_updates as $plugin) {
        $plugin_file = null;
        foreach ($all_plugins as $plugin_slug => $plugin_data) {
            if (strpos($plugin_slug, $plugin['slug']) !== false) {
                $plugin_file = $plugin_slug;
                break;
            }
        }
        if ($plugin_file) {
            $installed_version = $all_plugins[$plugin_file]['Version'];
            if (version_compare($installed_version, $plugin['version'], '<')) {
                $transient->response[$plugin_file] = (object)[
                    'slug'        => $plugin['slug'],
                    'new_version' => $plugin['version'],
                    'package'     => $plugin['download_url'],
                    'url'         => '',
                ];
            }
        }
    }

    return $transient;
}, 10, 1);

add_action('upgrader_process_complete', function ($upgrader_object, $options) {
    if ($options['action'] === 'update' && $options['type'] === 'plugin') {
        delete_site_transient('update_plugins');
        delete_transient('plugin_updater_update_data');
        echo '<script>
            setTimeout(function() {
                location.reload();
            }, 3000);
        </script>';
    }
}, 10, 2);