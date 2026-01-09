<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manejar instalacion/actualizacion de plugins via AJAX
 */
add_action('wp_ajax_plugin_updater_manage_plugin', function () {
    check_ajax_referer('plugin_updater_nonce', 'security');

    if (!current_user_can('install_plugins')) {
        wp_send_json_error(['message' => 'No tienes permisos para instalar plugins.']);
    }

    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $action = isset($_POST['plugin_action']) ? sanitize_text_field($_POST['plugin_action']) : '';

    if (empty($slug)) {
        wp_send_json_error(['message' => 'Slug no proporcionado.']);
    }

    // Obtener la URL de descarga desde los transients de WordPress
    $download_url = '';
    $plugin_file = '';

    if ($action === 'install' || $action === 'update') {
        // Obtener datos de actualizacion desde el transient
        $update_plugins = get_site_transient('update_plugins');

        if ($update_plugins && isset($update_plugins->response)) {
            // Buscar el plugin por slug en los datos de actualizacion
            foreach ($update_plugins->response as $file => $plugin_data) {
                // Extraer el slug del archivo del plugin
                $parts = explode('/', $file);
                $plugin_slug = isset($parts[0]) ? $parts[0] : '';

                if (strtolower($plugin_slug) === strtolower($slug)) {
                    $download_url = isset($plugin_data->package) ? $plugin_data->package : '';
                    $plugin_file = $file;
                    break;
                }
            }
        }

        // Si no se encuentra en update_plugins, buscar en no_update
        if (empty($download_url) && $update_plugins && isset($update_plugins->no_update)) {
            foreach ($update_plugins->no_update as $file => $plugin_data) {
                $parts = explode('/', $file);
                $plugin_slug = isset($parts[0]) ? $parts[0] : '';

                if (strtolower($plugin_slug) === strtolower($slug)) {
                    $download_url = isset($plugin_data->package) ? $plugin_data->package : '';
                    $plugin_file = $file;
                    break;
                }
            }
        }
    }

    // Si aun no tenemos URL, intentar obtenerla del servidor directamente
    if (empty($download_url)) {
        $api_key = get_option('plugin_updater_api_key', '');
        $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';

        $response = wp_remote_get(
            $server_url . 'get-plugins.php?apiKey=' . urlencode($api_key),
            array('timeout' => 15)
        );

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $plugins = json_decode($body);

            if (is_array($plugins)) {
                foreach ($plugins as $plugin) {
                    if (isset($plugin->slug) && strtolower($plugin->slug) === strtolower($slug)) {
                        $download_url = isset($plugin->download_url) ? $plugin->download_url : '';
                        break;
                    }
                }
            }
        }
    }

    if (empty($download_url)) {
        wp_send_json_error(['message' => 'URL de descarga no encontrada. Por favor, verifica que el plugin este disponible en el servidor.']);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    if ($action === 'install') {
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($download_url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $plugin_file = $upgrader->plugin_info();
        if ($plugin_file) {
            activate_plugin($plugin_file);
            wp_send_json_success(['message' => 'Plugin instalado y activado correctamente.']);
        }

        wp_send_json_success(['message' => 'Plugin instalado correctamente.']);

    } elseif ($action === 'update') {
        // Si no tenemos el plugin_file, buscarlo en los plugins instalados
        if (empty($plugin_file)) {
            $installed_plugins = get_plugins();
            foreach ($installed_plugins as $file => $plugin_data) {
                $parts = explode('/', $file);
                $plugin_slug = isset($parts[0]) ? $parts[0] : '';

                if (strtolower($plugin_slug) === strtolower($slug)) {
                    $plugin_file = $file;
                    break;
                }
            }
        }

        if (empty($plugin_file)) {
            wp_send_json_error(['message' => 'No se pudo encontrar el archivo del plugin instalado.']);
        }

        // Verificar si el plugin esta activo para reactivarlo despues
        $was_active = is_plugin_active($plugin_file);

        // ACTUALIZAR usando el archivo del plugin (NO el slug)
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($plugin_file);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Si el plugin estaba activo, reactivarlo
        if ($was_active) {
            activate_plugin($plugin_file);
        }

        // Limpiar transients para forzar actualizacion de datos
        delete_site_transient('update_plugins');
        wp_clean_plugins_cache();

        wp_send_json_success(['message' => 'Plugin actualizado correctamente.']);
    }

    wp_send_json_error(['message' => 'Accion no valida.']);
});

/**
 * Manejar instalacion/actualizacion de temas via AJAX
 */
add_action('wp_ajax_plugin_updater_manage_theme', function () {
    check_ajax_referer('plugin_updater_nonce', 'security');

    if (!current_user_can('install_themes')) {
        wp_send_json_error(['message' => 'No tienes permisos para instalar temas.']);
    }

    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $action = isset($_POST['theme_action']) ? sanitize_text_field($_POST['theme_action']) : '';

    if (empty($slug)) {
        wp_send_json_error(['message' => 'Slug no proporcionado.']);
    }

    // Obtener la URL de descarga desde los transients de WordPress
    $download_url = '';

    if ($action === 'install' || $action === 'update') {
        // Obtener datos de actualizacion desde el transient
        $update_themes = get_site_transient('update_themes');

        if ($update_themes && isset($update_themes->response)) {
            // Buscar el tema por slug
            if (isset($update_themes->response[$slug])) {
                $theme_data = $update_themes->response[$slug];
                $download_url = isset($theme_data['package']) ? $theme_data['package'] : '';
            }
        }

        // Si no se encuentra en response, buscar en no_update
        if (empty($download_url) && $update_themes && isset($update_themes->no_update)) {
            if (isset($update_themes->no_update[$slug])) {
                $theme_data = $update_themes->no_update[$slug];
                $download_url = isset($theme_data['package']) ? $theme_data['package'] : '';
            }
        }
    }

    // Si aun no tenemos URL, intentar obtenerla del servidor directamente
    if (empty($download_url)) {
        $api_key = get_option('plugin_updater_api_key', '');
        $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';

        $response = wp_remote_get(
            $server_url . 'get-themes.php?apiKey=' . urlencode($api_key),
            array('timeout' => 15)
        );

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $themes = json_decode($body);

            if (is_array($themes)) {
                foreach ($themes as $theme) {
                    if (isset($theme->slug) && strtolower($theme->slug) === strtolower($slug)) {
                        $download_url = isset($theme->download_url) ? $theme->download_url : '';
                        break;
                    }
                }
            }
        }
    }

    if (empty($download_url)) {
        wp_send_json_error(['message' => 'URL de descarga no encontrada. Por favor, verifica que el tema este disponible en el servidor.']);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/theme-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/theme.php';

    if ($action === 'install') {
        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($download_url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => 'Tema instalado correctamente.']);

    } elseif ($action === 'update') {
        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($slug);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Limpiar transients para forzar actualizacion de datos
        delete_site_transient('update_themes');
        wp_clean_themes_cache();

        wp_send_json_success(['message' => 'Tema actualizado correctamente.']);
    }

    wp_send_json_error(['message' => 'Accion no valida.']);
});