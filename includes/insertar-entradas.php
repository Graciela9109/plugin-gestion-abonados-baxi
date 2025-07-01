<?php
if (!defined('ABSPATH')) exit;

/**
 * Inserta cada asiento del pedido en la tabla wp_baxi_entradas al completar pedido
 */
add_action('woocommerce_order_status_completed', 'baxi_insertar_entradas_qr', 20);

function baxi_insertar_entradas_qr($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    global $wpdb;

    foreach ($order->get_items() as $item) {
        $seat_id   = intval($item->get_meta('seat_id', true));
        $evento_id = intval($item->get_meta('evento_id', true));
        $tipo      = strtolower($item->get_meta('tipo_entrada', true) ?: 'adulto');
        $qr_text   = $item->get_meta('Texto QR', true);
        $codigo    = $item->get_meta('Código de entrada', true);

        if (!$seat_id || !$evento_id || !$qr_text || !$codigo) continue;

        // Datos del asiento
        $asiento = $wpdb->get_row($wpdb->prepare(
            "SELECT grada, fila, asiento FROM {$wpdb->prefix}baxi_asientos_evento WHERE id = %d",
            $seat_id
        ));
        if (!$asiento) continue;

        $wpdb->insert(
            $wpdb->prefix . 'baxi_entradas',
            [
                'evento_id'     => $evento_id,
                'pedido_id'     => $order_id,
                'user_id'       => $order->get_user_id(),
                'grada'         => $asiento->grada,
                'fila'          => $asiento->fila,
                'asiento'       => $asiento->asiento,
                'tipo_entrada'  => $tipo,
                'qr'            => $qr_text,
                'codigo_manual' => $codigo,
                'estado'        => 'ocupado',
                'fecha_validacion' => null
            ],
            ['%d','%d','%d','%s','%s','%s','%s','%s','%s','%s']
        );
    }
}
