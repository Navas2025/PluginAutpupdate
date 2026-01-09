<?php
if (!defined('ABSPATH')) exit;

// Opciones de White Label
$white_label_logo = get_option('plugin_updater_white_label_logo', 'https://gplreposit.online/wp-content/uploads/2025/01/Nexus-Digital-Solutions-1.png');
$white_label_name = get_option('plugin_updater_white_label_name', 'Activa tu API Key');
$api_key = get_option('plugin_updater_api_key', '');

// Verificar si hay API Key activa
if (empty($api_key)) {
    echo '<div class="notice notice-error"><p>Necesitas activar una API Key primero. <a href="' . admin_url('admin.php?page=plugin-updater-client') . '">Activar API Key</a></p></div>';
    return;
}

// Verificar si la API Key ha caducado
$expiry_date = get_option('plugin_updater_expiry', '');
if (!empty($expiry_date)) {
    $expiry_timestamp = strtotime($expiry_date);
    if ($expiry_timestamp && $expiry_timestamp < time()) {
    echo '<div class="notice notice-error"><p>Tu API Key ha caducado. Por favor, renueva tu licencia o contacta con soporte.</p></div>';
    return;
    }
}

// Borrar caché para forzar la petición
delete_transient('plugin_updater_available_themes');

// Obtener temas disponibles directamente del servidor
$server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';
$response = wp_remote_get(
    $server_url . 'get-themes.php?apiKey=' . urlencode($api_key),
    array(
    'timeout' => 15,
    'headers' => array(
    'User-Agent' => 'WordPress/' . get_bloginfo('version')
    )
    )
);

if (is_wp_error($response)) {
    echo '<div class="notice notice-error"><p>Error al conectar con el servidor: ' . esc_html($response->get_error_message()) . '</p></div>';
    return;
}

$body = wp_remote_retrieve_body($response);

// Limpiar la respuesta de caracteres no válidos
$body = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $body);

// Intentar decodificar el JSON
$themes = json_decode($body);

// Si falla, intentar con un enfoque más agresivo
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Error al decodificar JSON: ' . json_last_error_msg());
    
    // Intentar extraer solo la parte JSON válida
    if (preg_match('/{.*}/', $body, $matches)) {
    $body = $matches[0];
    $themes = json_decode($body);
    }
    
    // Si aún falla, mostrar un mensaje de error
    if (json_last_error() !== JSON_ERROR_NONE) {
    echo '<div class="notice notice-error"><p>Error al procesar la respuesta del servidor. Por favor, contacta con soporte.</p></div>';
    if (current_user_can('manage_options')) {
    echo '<div class="notice notice-info"><p>Información de depuración:</p>';
    echo '<p>Error: ' . json_last_error_msg() . '</p>';
    echo '<p>Respuesta: ' . esc_html(substr($body, 0, 255)) . (strlen($body) > 255 ? '...' : '') . '</p>';
    echo '</div>';
    }
    return;
    }
}

// Verificar si la respuesta es válida y contiene temas
if (
    empty($themes) ||
    !is_array($themes) ||
    (isset($themes->error) && !empty($themes->error)) ||
    (is_string($themes) && !empty($themes))
) {
    // Si el servidor devuelve un mensaje de error, mostrarlo
    $error_message = '';
    if (is_object($themes) && isset($themes->error)) {
    $error_message = esc_html($themes->error);
    } elseif (is_string($themes) && !empty($themes)) {
    $error_message = esc_html($themes);
    } else {
    $error_message = 'No se encontraron temas disponibles. Por favor, verifica tu API Key o contacta con soporte.';
    }
    echo '<div class="notice notice-warning"><p>' . $error_message . '</p></div>';
    return;
}

// Obtener temas instalados
$installed_themes = wp_get_themes();

// Preparar array para comparar slugs (normalizado)
$installed_slugs = array();
foreach ($installed_themes as $theme_slug => $theme_data) {
    $slug = strtolower(trim($theme_slug));
    $installed_slugs[$slug] = array(
    'slug' => $theme_slug,
    'version' => trim($theme_data->get('Version'))
    );
}

// Obtener temas con auto-actualización activada
$auto_update_themes = (array) get_site_option('auto_update_themes', []);
?>

<div class="wrap plugin-updater-wrap">
    <h1><?php echo esc_html($white_label_name); ?> Temas Plugins-Wp</h1>
    <div class="plugin-updater-info">
    <p>Desde aquí puedes instalar y actualizar los temas disponibles con tu API Key.</p>
    </div>
    
    <div class="plugin-updater-container">
    <table class="plugin-updater-table">
    <thead>
    <tr>
    <th>Tema</th>
    <th>Versión</th>
    <th>Estado</th>
    <th>Acción</th>
    <th>Auto-Actualizar</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($themes as $theme): ?>
    <?php
    // Verificar que $theme sea un objeto válido con las propiedades necesarias
    if (!is_object($theme) || !isset($theme->slug) || !isset($theme->version)) {
    continue;
    }
    
    // Procesar datos del tema de forma segura
    $theme_slug = isset($theme->slug) ? (string)$theme->slug : '';
    $server_slug = strtolower(trim($theme_slug));
    $is_installed = isset($installed_slugs[$server_slug]);
    $installed_version = $is_installed ? $installed_slugs[$server_slug]['version'] : '';
    $server_version = isset($theme->version) ? trim((string)$theme->version) : '';
    $theme_name = isset($theme->name) ? (string)$theme->name : 'Sin nombre';
    $download_url = isset($theme->download_url) ? (string)$theme->download_url : '';
    
    // Determinar estado y acciones
    $needs_update = $is_installed && version_compare($installed_version, $server_version, '<');
    $action_text = $is_installed ? ($needs_update ? 'Actualizar' : 'Instalado') : 'Instalar';
    $action_class = $is_installed ? ($needs_update ? 'update-now' : 'button-disabled') : 'install-now';
    $status_class = $is_installed ? ($needs_update ? 'status-update' : 'status-installed') : 'status-not-installed';
    $is_auto_update = $is_installed && in_array($theme_slug, $auto_update_themes);
    ?>
    <tr>
    <td class="plugin-name"><strong><?php echo esc_html($theme_name); ?></strong></td>
    <td class="plugin-version"><?php echo esc_html($server_version); ?></td>
    <td class="plugin-status">
    <span class="status-badge <?php echo esc_attr($status_class); ?>">
    <?php if ($is_installed): ?>
    <?php if ($needs_update): ?>
    Actualización disponible (<?php echo esc_html($installed_version); ?> → <?php echo esc_html($server_version); ?>)
    <?php else: ?>
    Instalado (<?php echo esc_html($installed_version); ?>)
    <?php endif; ?>
    <?php else: ?>
    No instalado
    <?php endif; ?>
    </span>
    </td>
    <td class="plugin-action">
    <?php if ($action_text !== 'Instalado'): ?>
    <a href="#" class="button <?php echo esc_attr($action_class); ?>"
    data-slug="<?php echo esc_attr($theme_slug); ?>"
    data-name="<?php echo esc_attr($theme_name); ?>"
    data-action="<?php echo $is_installed ? 'update' : 'install'; ?>"
    data-download-url="<?php echo esc_url($download_url); ?>">
    <?php echo esc_html($action_text); ?>
    </a>
    <?php else: ?>
    <span class="button button-disabled">Instalado</span>
    <?php endif; ?>
    </td>
    <td class="plugin-auto-update">
    <?php if ($is_installed): ?>
    <button class="button toggle-auto-update-theme" 
    data-theme="<?php echo esc_attr($theme_slug); ?>"
    data-status="<?php echo $is_auto_update ? 'on' : 'off'; ?>">
    <?php echo $is_auto_update ? 'Desactivar' : 'Activar'; ?>
    </button>
    <?php else: ?>
    <span class="button button-disabled">-</span>
    <?php endif; ?>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Actualizar/instalar tema (con timeout largo)
    $('.install-now, .update-now').on('click', function(e) {
    e.preventDefault();
    var $button = $(this);
    var slug = $button.data('slug');
    var action = $button.data('action');
    var downloadUrl = $button.data('download-url');
    
    $button.text('Procesando...').prop('disabled', true);
    
    var data = {
    'action': 'plugin_updater_manage_theme',
    'slug': slug,
    'theme_action': action,
    'download_url': downloadUrl,
    'security': '<?php echo wp_create_nonce("plugin_updater_nonce"); ?>'
    };
    
    $.ajax({
    url: ajaxurl,
    type: 'POST',
    data: data,
    timeout: 60000, // 10 minutos
    success: function(response) {
    if (response.success) {
    $button.text('¡Completado!');
    setTimeout(function() { location.reload(); }, 1000);
    } else {
    $button.text('Error');
    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Error desconocido'));
    setTimeout(function() {
    $button.text(action === 'install' ? 'Instalar' : 'Actualizar').prop('disabled', false);
    }, 1000);
    }
    },
    error: function(xhr, textStatus, errorThrown) {
    $button.text('Error');
    console.error('Error AJAX:', textStatus, errorThrown);
    alert('Error de conexión. Por favor, inténtalo de nuevo.');
    setTimeout(function() {
    $button.text(action === 'install' ? 'Instalar' : 'Actualizar').prop('disabled', false);
    }, 1000);
    }
    });
    });

    // Activar/desactivar auto-actualización de temas
    $('.toggle-auto-update-theme').on('click', function(e) {
    e.preventDefault();
    var $btn = $(this);
    var theme = $btn.data('theme');
    var status = $btn.data('status');
    
    $btn.text('Procesando...').prop('disabled', true);
    
    $.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
    action: 'plugin_updater_toggle_auto_update_theme',
    theme: theme,
    enable: status === 'off' ? 1 : 0,
    security: '<?php echo wp_create_nonce("plugin_updater_nonce"); ?>'
    },
    success: function(response) {
    if (response.success) {
    $btn.text(response.data.enabled ? 'Desactivar' : 'Activar');
    $btn.data('status', response.data.enabled ? 'on' : 'off');
    } else {
    $btn.text(status === 'off' ? 'Activar' : 'Desactivar');
    alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Error desconocido'));
    }
    $btn.prop('disabled', false);
    },
    error: function() {
    $btn.text(status === 'off' ? 'Activar' : 'Desactivar').prop('disabled', false);
    alert('Error de conexión.');
    }
    });
    });
});
</script>

<style>
/* Estilos NARANJA/BEIGE - Idénticos a plugins */
.plugin-updater-wrap {
    max-width: 1200px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    background: #FFF4E6;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #FFE0B3;
}

.plugin-updater-info {
    background-color: #fff;
    border-left: 4px solid #FF9800;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    margin: 20px 0;
    padding: 12px 15px;
    border-radius: 3px;
}

.plugin-updater-container {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin-top: 20px;
}

.plugin-updater-table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
}

.plugin-updater-table th,
.plugin-updater-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
}

.plugin-updater-table th {
    background-color: #f9f9f9;
    font-weight: 600;
    color: #23282d;
    font-size: 14px;
}

.plugin-updater-table tr:hover {
    background-color: #f9f9f9;
}

.plugin-updater-table tr:last-child td {
    border-bottom: none;
}

.plugin-name {
    font-size: 15px;
    color: #23282d;
}

.plugin-version {
    font-size: 14px;
    color: #50575e;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.status-installed {
    background-color: #edfaef;
    color: #2a9d3f;
}

.status-update {
    background-color: #fff8e5;
    color: #d67b0c;
}

.status-not-installed {
    background-color: #f0f0f1;
    color: #50575e;
}

.button {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    border: none;
    transition: all 0.2s ease;
}

.install-now {
    background-color: #2271b1;
    color: #fff;
}

.install-now:hover {
    background-color: #135e96;
    color: #fff;
}

.update-now {
    background-color: #d67b0c;
    color: #fff;
}

.update-now:hover {
    background-color: #b35900;
    color: #fff;
}

.button-disabled {
    background-color: #f0f0f1;
    color: #a7aaad;
    cursor: not-allowed;
}

.button-disabled:hover {
    background-color: #f0f0f1;
}

/* Responsive */
@media screen and (max-width: 782px) {
    .plugin-updater-table th,
    .plugin-updater-table td {
    padding: 12px;
    }
    
    .status-badge {
    padding: 4px 8px;
    font-size: 12px;
    }
    
    .button {
    padding: 6px 12px;
    font-size: 12px;
    }
}
</style>