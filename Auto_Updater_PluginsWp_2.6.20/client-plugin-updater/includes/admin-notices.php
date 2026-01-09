<?php
/**
 * Admin Notices para notificar actualizaciones disponibles
 * Sistema integrado con update-hooks.php existente
 * 
 * @package Client_Plugin_Updater_GPL
 * @since 2.6.21
 */

// Si se accede directamente, abortar
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostrar notificación cuando hay plugins o temas disponibles para actualizar
 */
function plugin_updater_show_update_notice() {
    // Solo mostrar en el admin
    if (!is_admin()) {
        return;
    }

    // No mostrar en las páginas del propio plugin
    $plugin_pages = array('client-plugin-updater', 'plugin-updater-plugins', 'plugin-updater-themes', 'plugin-updater-white-label');
    if (isset($_GET['page']) && in_array(sanitize_text_field($_GET['page']), $plugin_pages)) {
        return;
    }

    // Verificar si el usuario puede gestionar opciones
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verificar si el aviso fue cerrado temporalmente (24 horas)
    $dismissed = get_transient('plugin_updater_notice_dismissed');
    if ($dismissed) {
        return;
    }

    // Obtener la API Key
    $api_key = get_option('plugin_updater_api_key', '');
    if (empty($api_key)) {
        return;
    }

    // Usar el MISMO transient que usa update-hooks.php
    $cached_updates = get_transient('plugin_updater_update_data');
    
    // Si no hay datos en caché, no mostrar nada aún
    if (false === $cached_updates) {
        return;
    }

    // Contar actualizaciones disponibles
    $updates_info = plugin_updater_count_updates_from_cache($cached_updates);
    
    $total_updates = $updates_info['plugins'] + $updates_info['themes'];
    
    if ($total_updates === 0) {
        return;
    }

    // Construir mensaje
    $message_parts = array();
    if ($updates_info['plugins'] > 0) {
        $message_parts[] = '<strong>' . esc_html($updates_info['plugins']) . '</strong> ' . 
                          ($updates_info['plugins'] === 1 ? 'plugin' : 'plugins');
    }
    if ($updates_info['themes'] > 0) {
        $message_parts[] = '<strong>' . esc_html($updates_info['themes']) . '</strong> ' . 
                          ($updates_info['themes'] === 1 ? 'tema' : 'temas');
    }
    
    $message = implode(' y ', $message_parts);

    // Mostrar el aviso
    ?>
    <div class="notice notice-warning is-dismissible plugin-updater-notice" data-notice="plugin-updater-updates">
        <p>
            <strong>🔔 Tienes <?php echo $message; ?> disponible<?php echo $total_updates === 1 ? '' : 's'; ?> para actualizar en tu PluginAutoUpdate.</strong>
            <?php if ($updates_info['plugins'] > 0): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=plugin-updater-plugins')); ?>" class="button button-primary" style="margin-left:10px;">
                    Ver Plugins (<?php echo esc_html($updates_info['plugins']); ?>)
                </a>
            <?php endif; ?>
            <?php if ($updates_info['themes'] > 0): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=plugin-updater-themes')); ?>" class="button button-primary" style="margin-left:5px;">
                    Ver Temas (<?php echo esc_html($updates_info['themes']); ?>)
                </a>
            <?php endif; ?>
        </p>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $(document).on('click', '.plugin-updater-notice .notice-dismiss', function() {
            $.post(ajaxurl, {
                action: 'plugin_updater_dismiss_notice',
                nonce: '<?php echo wp_create_nonce('plugin_updater_dismiss_notice'); ?>'
            });
        });
    });
    </script>
    <?php
}
add_action('admin_notices', 'plugin_updater_show_update_notice');

/**
 * Contar actualizaciones desde el caché (mismo que usa update-hooks.php)
 */
function plugin_updater_count_updates_from_cache($cached_updates) {
    $count = array(
        'plugins' => 0,
        'themes' => 0
    );

    if (!is_array($cached_updates)) {
        return $count;
    }

    // Cargar plugins instalados
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();

    // Cargar temas instalados
    $all_themes = wp_get_themes();

    // Contar plugins con actualizaciones
    foreach ($cached_updates as $remote_plugin) {
        if (!isset($remote_plugin['slug']) || !isset($remote_plugin['version'])) {
            continue;
        }

        $remote_slug = $remote_plugin['slug'];
        $remote_version = $remote_plugin['version'];

        // Verificar si es un plugin instalado
        $plugin_found = false;
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);
            
            if ($plugin_slug === '.' || empty($plugin_slug)) {
                $plugin_slug = basename($plugin_file, '.php');
            }

            if (strtolower($plugin_slug) === strtolower($remote_slug)) {
                $installed_version = $plugin_data['Version'];
                
                if (version_compare($installed_version, $remote_version, '<')) {
                    $count['plugins']++;
                }
                $plugin_found = true;
                break;
            }
        }

        // Si no es plugin, verificar si es tema
        if (!$plugin_found) {
            foreach ($all_themes as $theme_slug => $theme_obj) {
                if (strtolower($theme_slug) === strtolower($remote_slug)) {
                    $installed_version = $theme_obj->get('Version');
                    
                    if (version_compare($installed_version, $remote_version, '<')) {
                        $count['themes']++;
                    }
                    break;
                }
            }
        }
    }

    return $count;
}

/**
 * AJAX: Descartar aviso temporalmente (24 horas)
 */
function plugin_updater_dismiss_notice_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'plugin_updater_dismiss_notice')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    set_transient('plugin_updater_notice_dismissed', true, 24 * HOUR_IN_SECONDS);
    
    wp_send_json_success();
}
add_action('wp_ajax_plugin_updater_dismiss_notice', 'plugin_updater_dismiss_notice_ajax');
