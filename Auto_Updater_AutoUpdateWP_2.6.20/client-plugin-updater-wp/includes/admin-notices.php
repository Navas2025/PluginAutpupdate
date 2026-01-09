<?php
/**
 * Admin Notices para notificar actualizaciones disponibles
 * 
 * @package Client_Plugin_Updater_AutoUpdateWP
 * @since 2.6.21
 */

// Si se accede directamente, abortar
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mostrar notificación cuando hay plugins disponibles para actualizar
 */
function plugin_updater_show_update_notice() {
    // Solo mostrar en el admin
    if (!is_admin()) {
        return;
    }

    // No mostrar en la propia página del plugin
    if (isset($_GET['page']) && sanitize_text_field($_GET['page']) === 'client-plugin-updater') {
        return;
    }

    // Verificar si el usuario puede gestionar plugins
    if (!current_user_can('update_plugins')) {
        return;
    }

    // Verificar si el aviso fue cerrado temporalmente (24 horas)
    $dismissed = get_transient('plugin_updater_notice_dismissed');
    if ($dismissed) {
        return;
    }

    // Obtener plugins remotos disponibles para actualizar
    $remote_plugins = get_option('plugin_updater_remote_plugins', array());
    
    if (empty($remote_plugins)) {
        return;
    }

    // Contar cuántos plugins tienen actualizaciones
    $updates_available = plugin_updater_count_available_updates($remote_plugins);
    
    if ($updates_available === 0) {
        return;
    }

    // URL de la página de actualizaciones
    $updates_page_url = admin_url('admin.php?page=client-plugin-updater');

    // Mostrar el aviso
    ?>
    <div class="notice notice-warning is-dismissible plugin-updater-notice" data-notice="plugin-updater-updates">
        <p>
            <strong>🔔 AutoUpdate WP:</strong> 
            Tienes <strong><?php echo esc_html($updates_available); ?></strong> 
            <?php echo ($updates_available === 1) ? 'plugin' : 'plugins'; ?> 
            disponible<?php echo ($updates_available === 1) ? '' : 's'; ?> para actualizar.
            <a href="<?php echo esc_url($updates_page_url); ?>" class="button button-primary" style="margin-left: 10px;">
                Ver Actualizaciones
            </a>
        </p>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Manejar el cierre del aviso
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
 * Contar cuántos plugins tienen actualizaciones disponibles
 */
function plugin_updater_count_available_updates($remote_plugins) {
    if (!is_array($remote_plugins) || empty($remote_plugins)) {
        return 0;
    }

    $installed_plugins = get_plugins();
    $count = 0;

    // Crear lookup array de plugins instalados por slug para mejor performance
    $installed_by_slug = array();
    foreach ($installed_plugins as $plugin_file => $plugin_data) {
        $plugin_slug = dirname($plugin_file);
        $installed_by_slug[$plugin_slug] = $plugin_data;
    }

    foreach ($remote_plugins as $remote_plugin) {
        // Verificar si el plugin está instalado usando lookup O(1)
        if (isset($remote_plugin['slug']) && isset($installed_by_slug[$remote_plugin['slug']])) {
            $plugin_data = $installed_by_slug[$remote_plugin['slug']];
            
            // Comparar versiones
            $installed_version = $plugin_data['Version'];
            $remote_version = isset($remote_plugin['version']) ? $remote_plugin['version'] : '';
            
            if (!empty($remote_version) && version_compare($installed_version, $remote_version, '<')) {
                $count++;
            }
        }
    }

    return $count;
}

/**
 * AJAX: Descartar aviso temporalmente (24 horas)
 */
function plugin_updater_dismiss_notice_ajax() {
    // Verificar nonce
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'plugin_updater_dismiss_notice')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    // Guardar que el aviso fue cerrado por 24 horas
    set_transient('plugin_updater_notice_dismissed', true, 24 * HOUR_IN_SECONDS);
    
    wp_send_json_success();
}
add_action('wp_ajax_plugin_updater_dismiss_notice', 'plugin_updater_dismiss_notice_ajax');

/**
 * Verificar actualizaciones periódicamente en segundo plano
 */
function plugin_updater_schedule_update_check() {
    if (!wp_next_scheduled('plugin_updater_cron_check')) {
        wp_schedule_event(time(), 'twicedaily', 'plugin_updater_cron_check');
    }
}
add_action('wp', 'plugin_updater_schedule_update_check');

/**
 * Ejecutar verificación de actualizaciones programada
 */
function plugin_updater_cron_check_updates() {
    // Forzar actualización del cache de plugins remotos
    delete_transient('plugin_updater_remote_updates_cache');
    
    // Verificar actualizaciones remotas
    if (function_exists('plugin_updater_check_remote_updates')) {
        plugin_updater_check_remote_updates();
    }
}
add_action('plugin_updater_cron_check', 'plugin_updater_cron_check_updates');
