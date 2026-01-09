<?php
if (!defined('ABSPATH')) {
    exit; // Proteger acceso directo
}

// Iniciar sesión al principio para la sección (esto se usará en White Label)
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Página principal para activar la API Key.
 */
function plugin_updater_client_page() {
    if (isset($_POST['api_key'])) {
        $apiKey = sanitize_text_field($_POST['api_key']);
        $siteUrl = get_site_url();

        $response = wp_remote_post(PLUGIN_UPDATER_SERVER . 'validate-key.php', [
            'body' => [
                'apiKey'  => $apiKey,
                'siteUrl' => $siteUrl,
            ],
        ]);

        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>Error al conectar con el servidor: ' . $response->get_error_message() . '</p></div>';
            return;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['success']) && $data['success']) {
            update_option('plugin_updater_api_key', $apiKey);
            update_option('plugin_updater_expiry', $data['data']['expiry_date']);
            echo '<div class="notice notice-success"><p>API Key activada correctamente. Redirigiendo a la página de plugins instalados...</p></div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "' . admin_url('plugins.php') . '";
                }, 2000);
                </script>';
            return;
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($data['message']) . '</p></div>';
            return;
        }
    }

    $api_key = get_option('plugin_updater_api_key', '');
    $expiry_date = get_option('plugin_updater_expiry', 'No registrada');
    // Enmascaramos la API key (mostrando asteriscos)
    $display_key = $api_key ? str_repeat('*', strlen($api_key)) : '';

    // Incluir la plantilla que muestra el formulario de activación de API Key.
    // Nota: Ahora la carpeta de plantillas está dentro de includes/templates/
    include plugin_dir_path(__FILE__) . 'templates/admin-page-template.php';
}

/**
 * Página de ajustes de White Label protegida por contraseña (opcional).
 * Permite modificar el Plugin Name, Plugin Author y Logo.
 *
 * - Si se deja el campo "Clave de Protección" vacío, se accede sin protección.
 * - Si se ingresa una clave y se guarda, en futuros accesos se solicitará esa clave.
 * - La contraseña se guarda en texto plano en la opción "plugin_updater_white_label_password".
 * - Se utiliza sesión para recordar la autenticación durante la sesión actual.
 * - Se añade un ícono (👁️) para mostrar/ocultar el campo de la clave.
 */
function plugin_updater_white_label_settings_page() {
    // Asegurarse de que se cargue el cargador de medios.
    wp_enqueue_media();

    // Recuperar la contraseña almacenada (si existe)
    $stored_password = get_option('plugin_updater_white_label_password', '');
    $password_required = !empty($stored_password);
    $authenticated = isset($_SESSION['white_label_authenticated']) && $_SESSION['white_label_authenticated'] === true;
    
    // Si se requiere contraseña y el usuario aún no está autenticado, procesar el formulario de acceso.
    if ($password_required && !$authenticated) {
        if (isset($_POST['white_label_access'])) {
            $access = sanitize_text_field($_POST['white_label_access']);
            if ($access === $stored_password) {
                $_SESSION['white_label_authenticated'] = true;
                $authenticated = true;
            } else {
                echo '<div class="notice notice-error"><p>Clave incorrecta. Acceso denegado.</p></div>';
            }
        }
        if (!$authenticated) {
            ?>
            <div class="wrap">
                <h1>Acceso Protegido - White Label Settings</h1>
                <form method="post">
                    <label for="white_label_access">Introduce la clave de protección:</label>
                    <input type="password" name="white_label_access" id="white_label_access" class="regular-text" />
                    <input type="submit" value="Acceder" class="button button-primary" />
                    <span id="toggle_password" style="cursor:pointer; margin-left:5px;">👁️</span>
                </form>
            </div>
            <script type="text/javascript">
            jQuery(document).ready(function($){
                $("#toggle_password").on("click", function(){
                    var input = $("#white_label_access");
                    if (input.attr("type") === "password") {
                        input.attr("type", "text");
                    } else {
                        input.attr("type", "password");
                    }
                });
            });
            </script>
            <?php
            return;
        }
    }
    
    // Procesar el formulario de ajustes.
    if (isset($_POST['white_label_settings_nonce']) && wp_verify_nonce($_POST['white_label_settings_nonce'], 'save_white_label_settings')) {
        $white_label_name   = isset($_POST['white_label_name']) ? sanitize_text_field($_POST['white_label_name']) : '';
        $white_label_logo   = isset($_POST['white_label_logo']) ? esc_url_raw($_POST['white_label_logo']) : '';
        $white_label_author = isset($_POST['white_label_author']) ? sanitize_text_field($_POST['white_label_author']) : '';
        update_option('plugin_updater_white_label_name', $white_label_name);
        update_option('plugin_updater_white_label_logo', $white_label_logo);
        update_option('plugin_updater_white_label_author', $white_label_author);
        // Guardar la nueva clave si se ingresa; si se deja vacío, se borra la protección.
        $new_password = isset($_POST['white_label_new_password']) ? trim($_POST['white_label_new_password']) : '';
        update_option('plugin_updater_white_label_password', $new_password);
        if (!empty($new_password)) {
            $_SESSION['white_label_authenticated'] = false; // Forzar reautenticación si se cambia la clave.
        }
        echo '<div class="notice notice-success"><p>Ajustes guardados correctamente.</p></div>';
    }
    
    // Obtener los valores actuales.
    $white_label_name   = get_option('plugin_updater_white_label_name', 'Activa tu API Key');
    $white_label_logo   = get_option('plugin_updater_white_label_logo', 'https://gplreposit.online/wp-content/uploads/2025/01/Nexus-Digital-Solutions-1.png');
    $white_label_author = get_option('plugin_updater_white_label_author', 'Navas');
    $current_password   = get_option('plugin_updater_white_label_password', '');
    ?>
    <div class="wrap">
        <h1>White Label Settings</h1>
        <form method="post">
            <?php wp_nonce_field('save_white_label_settings', 'white_label_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="white_label_name">Plugin Name</label></th>
                    <td><input type="text" name="white_label_name" id="white_label_name" value="<?php echo esc_attr($white_label_name); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="white_label_author">Plugin Author</label></th>
                    <td><input type="text" name="white_label_author" id="white_label_author" value="<?php echo esc_attr($white_label_author); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="white_label_logo">Logo</label></th>
                    <td>
                        <input type="text" name="white_label_logo" id="white_label_logo" value="<?php echo esc_attr($white_label_logo); ?>" class="regular-text" />
                        <input type="button" id="upload_logo_button" class="button" value="Upload Logo" />
                        <p class="description">Selecciona o sube un logo para el plugin.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="white_label_new_password">Clave de Protección (opcional)</label></th>
                    <td>
                        <input type="password" name="white_label_new_password" id="white_label_new_password" value="<?php echo esc_attr($current_password); ?>" class="regular-text" />
                        <span id="toggle_new_password" style="cursor:pointer; margin-left:5px;">👁️</span>
                        <p class="description">Si se ingresa una clave, se protegerá la configuración en futuros accesos. Déjalo en blanco para no usar protección.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Guardar Ajustes'); ?>
        </form>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#upload_logo_button').on('click', function(e) {
            e.preventDefault();
            var mediaUploader = wp.media({
                title: 'Selecciona un logo',
                button: { text: 'Usar este logo' },
                multiple: false
            });
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#white_label_logo').val(attachment.url);
            });
            mediaUploader.open();
        });
        $('#toggle_new_password').on('click', function(){
            var input = $('#white_label_new_password');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
            } else {
                input.attr('type', 'password');
            }
        });
    });
    </script>
    <?php
}

/**
 * Página para mostrar y gestionar plugins disponibles.
 */
function plugin_updater_plugins_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    include plugin_dir_path(__FILE__) . 'templates/plugins-list-template.php';
}

/**
 * Página para mostrar y gestionar temas disponibles.
 */
function plugin_updater_themes_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    include plugin_dir_path(__FILE__) . 'templates/themes-list-template.php';
}

/**
 * Registrar el menú y submenús.
 */
add_action('admin_menu', function () {
    add_menu_page(
        'Activa tu API Key',
        'Activa tu API Key',
        'manage_options',
        'plugin-updater-client',
        'plugin_updater_client_page',
        'dashicons-admin-network',
        26
    );
    add_submenu_page(
        'plugin-updater-client',
        'Activa tu API Key',
        'Activar API Key',
        'manage_options',
        'plugin-updater-client',
        'plugin_updater_client_page'
    );
    add_submenu_page(
        'plugin-updater-client',
        'Plugins AutoUpdate',
        'Plugins AutoUpdate GplReposit',
        'manage_options',
        'plugin-updater-plugins',
        'plugin_updater_plugins_page'
    );
    add_submenu_page(
        'plugin-updater-client',
        'Temas AutoUpdate',
        'Temas AutoUpdate',
        'manage_options',
        'plugin-updater-themes',
        'plugin_updater_themes_page'
    );
    add_submenu_page(
        'plugin-updater-client',
        'White Label Settings',
        'White Label',
        'manage_options',
        'plugin-updater-white-label',
        'plugin_updater_white_label_settings_page'
    );
});

/**
 * Encolar el script de medios solo en las páginas de nuestro plugin.
 */
function plugin_updater_enqueue_media($hook) {
    $allowed_pages = array(
        'toplevel_page_plugin-updater-client',
        'plugin-updater_page_plugin-updater-white-label'
    );
    if (in_array($hook, $allowed_pages)) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'plugin_updater_enqueue_media');