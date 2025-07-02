<?php

class Baxi_Ajustes {

    public function __construct() {
        add_action('admin_post_baxi_guardar_tipo_abono', [$this, 'guardar_tipo_abono']);
        add_action('admin_post_baxi_eliminar_tipo_abono', [$this, 'eliminar_tipo_abono']);
    }

    /**
     * Registra el submenú Ajustes
     */
    public function registrar_menu() {
        add_submenu_page(
            'baxi-ajustes',
            'Ajustes BAXI',
            'Ajustes',
            'manage_options',
            'baxi-ajustes',
            [$this, 'vista_ajustes']
        );
    }

    /**
     * Muestra la vista principal de ajustes
     */
    public function vista_ajustes() {
        require_once BAXI_PATH . 'admin/vistas-ajustes.php';
    }

    /**
     * Guarda un tipo de abono (nuevo o edición)
     */
    public function guardar_tipo_abono() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes');
        }

        global $wpdb;

        $nombre           = sanitize_text_field($_POST['nombre'] ?? '');
        $numero_personas  = intval($_POST['personas'] ?? 1);
        $wc_product_id    = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : null;
        $config           = isset($_POST['config']) && is_array($_POST['config']) ? $_POST['config'] : [];
		$precio_titular = isset($config['precio_titular']) ? floatval($config['precio_titular']) : 0.0;

        // Precio base (es obligatorio para todos)
        $precio = isset($config['precio_adulto']) && is_numeric($config['precio_adulto'])
            ? floatval($config['precio_adulto']) : 0;

        // Sanitización y forzado de tipos numéricos para los precios
        foreach ($config as $k => &$v) {
            if (is_numeric($v)) $v = floatval($v);
            elseif ($v === 'on' || $v === '1') $v = 1;
            elseif ($v === '') $v = null;
        }
        unset($v);

        $datos = [
            'nombre'           => $nombre,
            'numero_personas'  => $numero_personas,
            'precio'           => $precio_titular,
            'wc_product_id'    => $wc_product_id,
            'config'           => json_encode($config, JSON_UNESCAPED_UNICODE)
        ];

        if (empty($nombre) || $numero_personas < 1) {
            wp_die('Por favor completa todos los campos correctamente.');
        }

        // Bloquea que se pueda guardar un tipo "niño" como abono principal
        $prohibidos = ['niño', 'niño menor', 'niño club', 'nino', 'nino_menor', 'nino_club'];
        if (in_array(mb_strtolower($nombre), $prohibidos)) {
            wp_die('Este tipo de abono solo puede ser usado como extra, no como titular.');
        }

        if (!empty($_POST['id'])) {
            // Editar
            $wpdb->update("{$wpdb->prefix}baxi_tipos_abono", $datos, ['id' => intval($_POST['id'])]);
        } else {
            // Nuevo
            $wpdb->insert("{$wpdb->prefix}baxi_tipos_abono", $datos);
        }

        wp_redirect(admin_url('admin.php?page=baxi-ajustes&success=1'));
        exit;
    }

    /**
     * Elimina un tipo de abono
     */
    public function eliminar_tipo_abono() {
        if (!current_user_can('manage_options') || !check_admin_referer('baxi_eliminar_tipo_abono')) {
            wp_die('Acceso denegado');
        }

        global $wpdb;
        $id = intval($_GET['id']);
        if ($id > 0) {
            $wpdb->delete("{$wpdb->prefix}baxi_tipos_abono", ['id' => $id]);
        }

        wp_redirect(admin_url('admin.php?page=baxi-ajustes&eliminado=1'));
        exit;
    }
}
