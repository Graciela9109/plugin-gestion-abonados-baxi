<?php
/**
 * Funciones BAXI – selector de tipo de entrada por asiento en el carrito (ahora para productos variables)
 */

/* -------------------------------------------------------------
   MOSTRAR SELECTOR DE VARIACIÓN EN CADA LÍNEA DEL CARRITO
   ------------------------------------------------------------- */
add_action( 'woocommerce_after_cart_item_name', 'baxi_selector_variacion_entrada', 10, 2 );
function baxi_selector_variacion_entrada( $cart_item, $cart_item_key ) {
	$product = wc_get_product( $cart_item['product_id'] );

	// Solo para productos variables (asociados a entradas de evento)
	if ( ! $product || ! $product->is_type( 'variable' ) ) return;

	$variations = $product->get_available_variations();
	$selected_variation_id = $cart_item['variation_id'] ?? 0;

	echo '<div class="baxi-selector-variacion">';
	echo '<label for="baxi-var-' . esc_attr($cart_item_key) . '">Tipo de entrada:&nbsp;</label>';
	echo '<select class="baxi-select-variacion" id="baxi-var-' . esc_attr($cart_item_key) . '" data-cart-key="'.esc_attr($cart_item_key).'">';
	foreach ($variations as $v) {
		// Asegúrate de poner el atributo correcto aquí
		$attr = '';
		foreach($v['attributes'] as $att_key => $att_val) {
			$attr = $att_val; // Suele ser 'adulto', 'nino', etc.
		}
		$label = ucfirst($attr);
		$id = $v['variation_id'];
		$sel = selected( $selected_variation_id, $id, false );
		echo "<option value='$id' $sel>$label ({$v['display_price']} €)</option>";
	}
	echo '</select></div>';
}

/* -------------------------------------------------------------
   AJAX: ACTUALIZAR VARIACIÓN DESDE EL CARRITO
   ------------------------------------------------------------- */
add_action('wp_ajax_baxi_actualizar_variacion_cart', 'baxi_actualizar_variacion_cart');
add_action('wp_ajax_nopriv_baxi_actualizar_variacion_cart', 'baxi_actualizar_variacion_cart');
function baxi_actualizar_variacion_cart() {
	check_ajax_referer('baxi_tipo_entrada', 'nonce');
	$cart_key = sanitize_text_field($_POST['cart_key']);
	$variation_id = intval($_POST['variation_id']);

	$cart = WC()->cart;
	if( !isset($cart->cart_contents[$cart_key]) ) {
		wp_send_json_error('No se encuentra el producto en el carrito.');
	}

	$cart_item = $cart->cart_contents[$cart_key];
	$product_id = $cart_item['product_id'];
	$qty = $cart_item['quantity'];

	// Conserva los metadatos importantes, como asiento, fila, grada, etc.
	$meta = [];
	foreach($cart_item as $k => $v) {
		if (in_array($k, ['asiento','fila','grada','evento_id','baxi_abonado_id'])) {
			$meta[$k] = $v;
		}
	}

	// Elimina la línea anterior
	$cart->remove_cart_item($cart_key);

	// Añade el producto con la nueva variación
	$added_key = $cart->add_to_cart($product_id, $qty, $variation_id, [], $meta);

	if ($added_key) {
		wp_send_json_success();
	} else {
		wp_send_json_error('Error al actualizar el tipo de entrada.');
	}
}

/* -------------------------------------------------------------
   DESACTIVA LÓGICA ANTIGUA DE PRECIOS MANUALES PARA PRODUCTOS DE ENTRADA
   ------------------------------------------------------------- */
/*
add_action( 'woocommerce_before_calculate_totals', 'baxi_ajustar_precio_segun_tipo' );
function baxi_ajustar_precio_segun_tipo( $cart ) {
	// Lógica desactivada: todo el precio debe venir de WooCommerce (producto y variación)
}
*/

/* -------------------------------------------------------------
   (Opcional) Si quieres seguir mostrando el selector para productos simples antiguos,
   puedes dejar la función anterior y adaptarla solo para esos casos, pero para productos variables
   se usa el sistema de variaciones.
   ------------------------------------------------------------- */
