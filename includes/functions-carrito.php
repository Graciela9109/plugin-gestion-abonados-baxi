<?php
/**
 * Funciones BAXI – selector de tipo de entrada por asiento en el carrito
 */

/* -------------------------------------------------------------
   MOSTRAR SELECTOR “TIPO DE ENTRADA” EN CADA LÍNEA DEL CARRITO
   ------------------------------------------------------------- */
add_action( 'woocommerce_after_cart_item_name', 'baxi_selector_tipo_entrada', 10, 2 );
function baxi_selector_tipo_entrada( $cart_item, $cart_item_key ) {

	$producto_entradas_id = 123; // 
	if ( $cart_item['product_id'] != $producto_entradas_id ) {
		return;
	}

	$tipos = [
		'adulto' => 'Adulto',
		'nino'   => 'Niño',
	];

	$selected = isset( $cart_item['tipo_entrada'] ) ? $cart_item['tipo_entrada'] : '';

	echo '<div class="baxi-tipo-entrada">';
	echo '<label for="tipo-entrada-' . esc_attr( $cart_item_key ) . '">Tipo&nbsp;</label>';
	echo '<select name="tipo_entrada[' . esc_attr( $cart_item_key ) . ']" id="tipo-entrada-' . esc_attr( $cart_item_key ) . '">';
	foreach ( $tipos as $value => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $selected, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
	echo '</div>';
}

/* -------------------------------------------------------------
   GUARDAR LA SELECCIÓN AL ACTUALIZAR EL CARRITO
   ------------------------------------------------------------- */
add_action( 'woocommerce_cart_loaded_from_session', 'baxi_cargar_tipo_entrada_desde_sesion' );
function baxi_cargar_tipo_entrada_desde_sesion( $cart ) {
	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
		if ( isset( $_POST['tipo_entrada'][ $cart_item_key ] ) ) {
			$cart_item['tipo_entrada'] = sanitize_text_field( $_POST['tipo_entrada'][ $cart_item_key ] );
		}
	}
}

add_action( 'woocommerce_after_cart_item_quantity_update', 'baxi_guardar_tipo_entrada_al_actualizar', 10, 3 );
function baxi_guardar_tipo_entrada_al_actualizar( $cart_item_key, $quantity, $cart ) {
	if ( isset( $_POST['tipo_entrada'][ $cart_item_key ] ) ) {
		$cart->cart_contents[ $cart_item_key ]['tipo_entrada'] = sanitize_text_field( $_POST['tipo_entrada'][ $cart_item_key ] );
		WC()->session->set( 'cart', $cart->get_cart_for_session() ); 
	}
}

/* -------------------------------------------------------------
   RECALCULAR EL PRECIO SEGÚN EL TIPO
   ------------------------------------------------------------- */
add_action( 'woocommerce_before_calculate_totals', 'baxi_ajustar_precio_segun_tipo' );
function baxi_ajustar_precio_segun_tipo( $cart ) {

	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}

	foreach ( $cart->get_cart() as $cart_item ) {

		if ( ! isset( $cart_item['tipo_entrada'] ) ) {
			continue; 
		}

		$tipo = $cart_item['tipo_entrada'];
		$product = $cart_item['data'];

		$precio_base_adulto = 15; // €
		$precio_base_nino   = 10; // €

		switch ( $tipo ) {
			case 'adulto':
				$product->set_price( $precio_base_adulto );
				break;
			case 'nino':
				$product->set_price( $precio_base_nino );
				break;
		}
	}
}
