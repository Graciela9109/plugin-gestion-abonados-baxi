<?php
if (!defined('ABSPATH')) exit;

class BAXI_Gestion_Situ {

    public function __construct() {
        add_action('wp_ajax_baxi_get_asientos_evento', [$this, 'ajax_cargar_asientos']);
        add_action('wp_ajax_baxi_cambiar_estado_asiento', [$this, 'ajax_cambiar_estado']);
    }

    public function add_menu() {
        add_submenu_page(
            'baxi-inicio',
            'Gestión In Situ',
            'Gestión In Situ',
            'manage_options',
            'baxi-gestion-situ',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        global $wpdb;
        $eventos = $wpdb->get_results("SELECT id, nombre FROM {$wpdb->prefix}baxi_eventos ORDER BY fecha DESC");

        echo '<div class="wrap"><h1>Gestión In Situ de Asientos</h1>';
        echo '<select id="baxi-select-evento">';
        echo '<option value="">Selecciona un evento</option>';
        foreach ($eventos as $ev) {
            echo '<option value="' . esc_attr($ev->id) . '">' . esc_html($ev->nombre) . '</option>';
        }
        echo '</select>';
        echo '<div id="baxi-listado-asientos" style="margin-top:2em"></div>';
        echo '</div>';

        wp_enqueue_script(
            'baxi-gestion-situ',
            plugins_url('admin/assets/js/baxi-gestion-situ.js', BAXI_PLUGIN_FILE),
            ['jquery'], null, true
        );
        wp_localize_script('baxi-gestion-situ', 'baxiInSitu', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('baxi_admin_nonce')
        ]);
    }

    public function ajax_cargar_asientos() {
        check_ajax_referer('baxi_admin_nonce', 'nonce');

        $evento = intval($_POST['evento'] ?? 0);
        if (!$evento) wp_send_json_error('Evento inválido');

        global $wpdb;
        $asientos = $wpdb->get_results("SELECT id, zona, fila, asiento, estado FROM {$wpdb->prefix}baxi_asientos_evento WHERE evento_id = $evento ORDER BY zona, fila, asiento");

        wp_send_json_success($asientos);
    }

    public function ajax_cambiar_estado() {
        check_ajax_referer('baxi_admin_nonce', 'nonce');

        $id = intval($_POST['id'] ?? 0);
        $estado = sanitize_text_field($_POST['estado'] ?? '');
        if (!$id || !in_array($estado, ['libre', 'ocupado'])) {
            wp_send_json_error('Datos inválidos');
        }

        global $wpdb;
        $wpdb->update("{$wpdb->prefix}baxi_asientos_evento", ['estado' => $estado], ['id' => $id]);

        wp_send_json_success('Estado actualizado');
    }
}
