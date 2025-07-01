<?php

class Baxi_Ajustes {

    public function __construct() {
        add_action('admin_post_baxi_guardar_tipo_abono', [$this, 'guardar_tipo_abono']);
        add_action('admin_post_baxi_eliminar_tipo_abono', [$this, 'eliminar_tipo_abono']);
    }

    /**
     * Registra el menú principal y el submenú Ajustes
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

        $nombre = sanitize_text_field($_POST['nombre']);
        $precio = floatval($_POST['precio']);
        $numero_personas = intval($_POST['personas']);
		$wc_product_id = isset($_POST['wc_product_id']) ? intval($_POST['wc_product_id']) : null;

        if (empty($nombre) || $precio <= 0 || $numero_personas < 1) {
            wp_die('Por favor completa todos los campos correctamente.');
        }

        $datos = [
            'nombre' => $nombre,
            'precio' => $precio,
            'numero_personas' => $numero_personas,
			'wc_product_id' => $wc_product_id
        ];

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
