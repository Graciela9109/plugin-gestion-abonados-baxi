<?php
// Mostrar el selector en el carrito
add_filter('woocommerce_cart_item_name', function($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['seat_id'])) {
        $tipo = isset($cart_item['tipo_entrada']) ? $cart_item['tipo_entrada'] : 'adulto';
        $select = '<select class="baxi-tipo-entrada" data-key="' . esc_attr($cart_item_key) . '">'
                . '<option value="adulto"'   . selected($tipo, 'adulto',   false) . '>Adulto + de 15</option>'
                . '<option value="infantil"' . selected($tipo, 'infantil', false) . '>Niño + de 6</option>'
                . '<option value="vip"'      . selected($tipo, 'vip',      false) . '>Niño - de 6</option>'
                . '</select>';
        $name .= '<br><small>Tipo de entrada: ' . $select . '</small>';
    }
    return $name;
}, 10, 3);

// AJAX para guardar el tipo de entrada en el carrito
add_action('wp_ajax_baxi_update_tipo_entrada', 'baxi_update_tipo_entrada');
add_action('wp_ajax_nopriv_baxi_update_tipo_entrada', 'baxi_update_tipo_entrada');
function baxi_update_tipo_entrada() {
    if (empty($_POST['cart_item_key']) || empty($_POST['tipo_entrada'])) {
        wp_send_json_error('Datos incompletos');
    }
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $tipo = sanitize_text_field($_POST['tipo_entrada']);

    foreach (WC()->cart->get_cart() as $key => $item) {
        if ($key === $cart_item_key) {
            WC()->cart->cart_contents[$key]['tipo_entrada'] = $tipo;
            WC()->cart->set_session(); // guarda
            WC()->cart->calculate_totals();
            wp_send_json_success();
        }
    }
    wp_send_json_error('Ítem no encontrado');
}
function baxi_get_tipos_entrada() {
    return [
        'adulto'   => ['label'=>'Adulto',   'ajuste'=>0],
        'infantil' => ['label'=>'Infantil', 'ajuste'=>-5],
        'vip'      => ['label'=>'VIP',      'ajuste'=>10],
    ];
}
// Cambiar el precio según tipo de entrada
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['tipo_entrada'])) {
            $base_price = (float)$cart_item['data']->get_regular_price();
            switch ($cart_item['tipo_entrada']) {
                case 'infantil':
                    $cart_item['data']->set_price($base_price * 0.5); // 50%
                    break;
                case 'vip':
                    $cart_item['data']->set_price($base_price * 2);   // 200%
                    break;
                default:
                    $cart_item['data']->set_price($base_price);
            }
        }
    }
});

// Mostrar y guardar el tipo de entrada en el pedido
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['tipo_entrada'])) {
        $item->add_meta_data('Tipo de entrada', ucfirst($values['tipo_entrada']));
    }
}, 10, 4);
