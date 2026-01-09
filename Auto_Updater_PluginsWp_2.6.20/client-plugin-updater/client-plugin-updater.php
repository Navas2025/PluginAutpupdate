<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://actualizarplugins.online
 * @since             2.6.20
 * @package           Client_Plugin_Updater_PluginsWP
 *
 * @wordpress-plugin
 * Plugin Name:       API Key AutoUpdate Plugins-WP
 * Plugin URI:        https://plugins-wp.online
 * Description:       Plugin para activar API Keys y gestionar actualizaciones de plugins y temas - de la web Plugins-Wp
 * Version:           2.6.20
 * Author:            Navas
 * Author URI:        https://plugins-wp.online
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       client-plugin-updater
 * Domain Path:       /languages
 */
// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Proteger acceso directo
}

define('PLUGIN_UPDATER_SERVER', 'https://actualizarplugins.online/api/');
define('CACHE_DURATION', 12 * HOUR_IN_SECONDS); // Duracion de cache (12 horas)

// Cargar archivos necesarios
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/update-hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/theme-update-hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handler.php'; // Añadir esta

/**
 * Modificar el nombre y el autor del plugin que se muestra en la lista de plugins.
 */
function plugin_updater_modify_plugin_info($plugins) {
    $plugin_file = plugin_basename(__FILE__);
    if (isset($plugins[$plugin_file])) {
        // Modificar el nombre
        $white_label_name = get_option('plugin_updater_white_label_name');
        if (empty($white_label_name)) {
            $white_label_name = 'API Key AutoUpdate';
        }
        $plugins[$plugin_file]['Name'] = $white_label_name;

        // Modificar el autor
        $white_label_author = get_option('plugin_updater_white_label_author');
        if (empty($white_label_author)) {
            $white_label_author = 'Navas';
        }
        $plugins[$plugin_file]['Author'] = $white_label_author;
    }
    return $plugins;
}
add_filter('all_plugins', 'plugin_updater_modify_plugin_info');

/**
 * Cambiar el texto del bot??n de actualizacion para los plugins gestionados remotamente.
 */
function plugin_updater_change_update_button_text() {
    $remote_plugins = get_option('plugin_updater_remote_plugins', array());
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var remotePlugins = <?php echo json_encode($remote_plugins); ?>;
        $('tr[data-plugin]').each(function() {
            var pluginFile = $(this).attr('data-plugin').toLowerCase();
            for (var i = 0; i < remotePlugins.length; i++) {
                if (pluginFile.indexOf(remotePlugins[i].toLowerCase()) !== -1) {
                    $(this).find('a.update-link').text('Actualizar Plugins Desde AutouPdate');
                }
            }
        });
    });
    </script>
    <?php
}
add_action('admin_footer-plugins.php', 'plugin_updater_change_update_button_text');

/**
 * Cambiar el autor mostrado en la lista de plugins para nuestro plugin.
 */
function plugin_updater_change_author_text() {
    $plugin_file = plugin_basename(__FILE__);
    $custom_author = get_option('plugin_updater_white_label_author', 'Navas');
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var plugin = '<?php echo esc_js($plugin_file); ?>';
        var customAuthor = '<?php echo esc_js($custom_author); ?>';
        var $row = $('tr[data-plugin="' + plugin + '"]');
        if ($row.length) {
            var $authorDiv = $row.find('.plugin-version-author-uri');
            if ($authorDiv.length) {
                var text = $authorDiv.text();
                var parts = text.split('|');
                if (parts.length > 1) {
                    parts[1] = ' Por ' + customAuthor;
                    $authorDiv.text(parts.join('|'));
                }
            }
        }
    });
    </script>
    <?php
}
add_action('admin_footer-plugins.php', 'plugin_updater_change_author_text');

/**
 * Verificar actualizaciones remotas con cache temporal.
 */
function plugin_updater_check_remote_updates() {
    $transient_key = 'plugin_updater_remote_updates_cache';
    $cached_data = get_transient($transient_key);

    // Si hay datos en cach??, devolverlos
    if ($cached_data !== false) {
        return $cached_data;
    }

    // Obtener la API Key almacenada
    $api_key = trim(get_option('plugin_updater_api_key', ''));

    // Salir si no hay API Key v??lida
    if (empty($api_key)) {
        error_log("No se ha configurado una API Key v??lida.");
        return array();
    }

    // Construir URL del servidor
    $server_url = PLUGIN_UPDATER_SERVER . 'check-updates';
    $response = wp_remote_get($server_url, array(
        'timeout' => 10,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
        ),
    ));

    // Manejar errores de respuesta
    if (is_wp_error($response)) {
        error_log("Error al verificar actualizaciones remotas: " . $response->get_error_message());
        return array();
    }

    // Procesar respuesta
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        error_log("Respuesta inv??lida del servidor de actualizaciones.");
        return array();
    }

    // Guardar datos en cache por 12 horas
    set_transient($transient_key, $data, CACHE_DURATION);
    return $data;
}

/**
 * Actualizar la lista de plugins remotos solo si hay cambios.
 */
function plugin_updater_update_remote_plugins_list() {
    // Verificar si el usuario tiene permisos
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verificar si estamos en la pagina de plugins
    if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'client-plugin-updater') {
        return;
    }

    // Verificar actualizaciones remotas
    $remote_updates = plugin_updater_check_remote_updates();

    // Actualizar opcion solo si hay datos nuevos
    if (!empty($remote_updates)) {
        update_option('plugin_updater_remote_plugins', $remote_updates);
    }
}
add_action('admin_init', 'plugin_updater_update_remote_plugins_list');