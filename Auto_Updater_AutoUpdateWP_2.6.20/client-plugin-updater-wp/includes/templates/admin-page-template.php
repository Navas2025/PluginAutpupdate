<?php
// Obtener opciones de White Label con valores por defecto
$white_label_logo = get_option('plugin_updater_white_label_logo', 'https://gplreposit.online/wp-content/uploads/2025/01/Nexus-Digital-Solutions-1.png');
$white_label_name = get_option('plugin_updater_white_label_name', 'Activa tu API Key');

// Obtener la API key
$api_key = get_option('plugin_updater_api_key', '');

// Inicializar variables
$display_key = '';
$expiry_date = '';
$expiry_message = '';
$expiry_timestamp = 0;

// Si hay una API key, formatearla para mostrar
if (!empty($api_key)) {
    // Mostrar solo parte de la API key por seguridad
    $display_key = substr($api_key, 0, 5) . '...' . substr($api_key, -5);
    
    // Obtener fecha de expiración si existe
    $expiry_date = get_option('plugin_updater_expiry', '');
    
    // Formatear mensaje de expiración
    if (!empty($expiry_date)) {
        $expiry_timestamp = strtotime($expiry_date);
        if ($expiry_timestamp && $expiry_timestamp < time()) {
            // Si la fecha ya pasó, solo se muestra "API Key caducada"
            $expiry_message = "API Key caducada";
        } else {
            // Si aún no ha pasado, se muestra la fecha y el aviso
            $expiry_message = "Fecha de caducidad: <strong>" . esc_html($expiry_date) . "</strong>. Este día ya no podrás usar la API Key.";
        }
    } else {
        $expiry_message = "No hay fecha de caducidad establecida.";
    }
}

// Determinar si la API Key está activa y no caducada
$is_active = !empty($api_key) && (empty($expiry_date) || ($expiry_timestamp && $expiry_timestamp > time()));
?>
<div class="wrap" style="text-align: center; margin-top: 50px; background: linear-gradient(135deg, #f44336, #9c27b0); color: #fff; padding: 30px; border-radius: 10px; border: 2px solid #f44336;">
    <img src="<?php echo esc_url($white_label_logo); ?>" alt="Logo" style="max-width: 150px; margin-bottom: 20px; border-radius: 50%; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
    <h1 style="color: #fff; font-size: 28px; margin-bottom: 20px; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"><?php echo esc_html($white_label_name); ?></h1>
    <p style="font-size: 16px; margin-bottom: 30px; color: rgba(255,255,255,0.9);">Introduce tu API Key para habilitar las actualizaciones automáticas de tus plugins.</p>
    
    <?php settings_errors('plugin_updater_messages'); ?>
    
    <form method="post" action="" style="margin: auto; max-width: 400px; background: rgba(255, 255, 255, 0.15); padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); border: 1px solid rgba(244, 67, 54, 0.5);">
        <?php wp_nonce_field('plugin_updater_nonce', 'plugin_updater_nonce'); ?>
        <label for="api_key" style="display: block; font-weight: bold; margin-bottom: 5px; color: #fff;">API Key:</label>
        <input type="text" name="api_key" id="api_key" value="<?php echo esc_attr($display_key); ?>" style="width: 100%; padding: 10px; border: 1px solid #f44336; border-radius: 5px; background: rgba(255, 255, 255, 0.9); color: #333;">
        <?php if (!empty($expiry_message)): ?>
            <p style="margin: 20px 0; font-size: 14px; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);"><?php echo $expiry_message; ?></p>
        <?php endif; ?>
        
        <button type="submit" id="api_key_button" class="button-primary api-key-red" style="margin-top: 20px; width: 100%; padding: 10px; font-size: 16px; border-radius: 5px; border-color: #f44336; color: #fff;">
            <?php echo $is_active ? 'API Key Activada' : 'Activar API Key'; ?>
        </button>
    </form>
</div>

<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . '../../assets/admin-theme.css'; ?>">
<style>
.api-key-red {
    background: #f44336 !important;
    border-color: #f44336 !important;
    color: #fff !important;
    opacity: 1 !important;
    transition: background 0.2s, opacity 0.2s;
}
.api-key-purple {
    background: #9c27b0 !important;
    border-color: #9c27b0 !important;
    color: #fff !important;
    opacity: 1 !important;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var isActive = <?php echo $is_active ? 'true' : 'false'; ?>;
    var displayKey = '<?php echo esc_js($display_key); ?>';
    var apiKeyField = $('#api_key');
    var apiKeyButton = $('#api_key_button');
    
    function updateButton() {
        var fieldValue = apiKeyField.val().trim();
        if (fieldValue === '') {
            apiKeyButton.text('Activar API Key');
            apiKeyButton.removeClass('api-key-purple').addClass('api-key-red');
        } else if (isActive && fieldValue === displayKey) {
            apiKeyButton.text('API Key Activada');
            apiKeyButton.removeClass('api-key-red').addClass('api-key-purple');
        } else {
            apiKeyButton.text('Activar API Key');
            apiKeyButton.removeClass('api-key-purple').addClass('api-key-red');
        }
    }
    apiKeyField.on('input', updateButton);
    updateButton();
});
</script>