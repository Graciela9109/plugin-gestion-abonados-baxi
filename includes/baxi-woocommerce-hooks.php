<?php
/* Mostrar datos de asiento en la línea del carrito / pedido */
add_filter( 'woocommerce_get_item_data', function( $d, $item ) {
    global $wpdb;

    // Asiento
    if ( isset( $item['seat_id'] ) ) {
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT grada, fila, asiento FROM {$wpdb->prefix}baxi_asientos_evento WHERE id=%d", $item['seat_id']
        ) );

        if ( $row ) {
            $d[] = ['name' => 'Grada', 'value' => $row->grada];
            $d[] = ['name' => 'Fila', 'value' => $row->fila];
            $d[] = ['name' => 'Asiento', 'value' => $row->asiento];
        }
    }

    // Evento
    if ( isset( $item['evento_id'] ) ) {
        $evento_nombre = $wpdb->get_var( $wpdb->prepare(
            "SELECT nombre FROM {$wpdb->prefix}baxi_eventos WHERE id=%d", $item['evento_id']
        ) );

        if ( $evento_nombre ) {
            $d[] = ['name' => 'Evento', 'value' => $evento_nombre];
        }
    }

    // Tipo de entrada (solo informativo, Woo ya muestra la variación)
    if ( isset( $item['tipo_entrada'] ) ) {
        $d[] = [
            'name'  => 'Tipo de entrada',
            'value' => ucfirst( $item['tipo_entrada'] )
        ];
    }

    return $d;
}, 10, 2 );

/* Quita el selector manual de tipo de entrada en el carrito.
   WooCommerce mostrará el selector de variación si el producto es variable.
   Así, el usuario puede cambiar la variación directamente en el carrito,
   y Woo se encarga del precio.
*/


/* Actualizar el estado de los asientos al completar el pedido */
add_action( 'woocommerce_order_status_completed', function( $order_id ){
    global $wpdb;
    $tbl = "{$wpdb->prefix}baxi_asientos_evento";
    $order = wc_get_order( $order_id );
    foreach ( $order->get_items() as $it ) {
        $sid = intval( $it->get_meta( 'seat_id', true ) );
        if ( $sid ) {
            $wpdb->update( $tbl, ['estado'=>'ocupado'], ['id'=>$sid], ['%s'], ['%d'] );
        }
    }
} );

/* Liberar asientos si el pedido se cancela o reembolsa */
add_action( 'woocommerce_order_status_cancelled', 'baxi_release_seats' );
add_action( 'woocommerce_order_status_refunded',  'baxi_release_seats' );
function baxi_release_seats( $order_id ){
    global $wpdb;
    $tbl = "{$wpdb->prefix}baxi_asientos_evento";
    $order = wc_get_order( $order_id );
    foreach ( $order->get_items() as $it ) {
        $sid = intval( $it->get_meta( 'seat_id', true ) );
        if ( $sid ) {
            $wpdb->update( $tbl, ['estado'=>'libre'], ['id'=>$sid,'estado'=>'reservado'], ['%s'], ['%d','%s'] );
        }
    }
}

add_action( 'woocommerce_remove_cart_item', function ( $cart_item_key, $cart ) {
    $item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
    if ( ! $item || empty( $item['seat_id'] ) ) return;

    global $wpdb;
    $wpdb->update(
        "{$wpdb->prefix}baxi_asientos_evento",
        [ 'estado' => 'libre' ],
        [ 'id' => intval($item['seat_id']), 'estado' => 'reservado' ],
        [ '%s' ], [ '%d', '%s' ]
    );
}, 10, 2 );


/* ------------------------------------------------------------------
 * CRON: liberar asientos 'reservado' con >10 min de antigüedad
 * -----------------------------------------------------------------*/
if ( ! wp_next_scheduled( 'baxi_purge_reserved_seats' ) ) {
    wp_schedule_event( time() + 600, 'five_minutes', 'baxi_purge_reserved_seats' );
}
// frecuencia personalizada (5 min)
add_filter( 'cron_schedules', function ( $s ) {
    $s['five_minutes'] = [ 'interval' => 300, 'display' => 'Cada 5 min' ];
    return $s;
});

add_action( 'baxi_purge_reserved_seats', function () {
    global $wpdb;
    $sql = "
        UPDATE {$wpdb->prefix}baxi_asientos_evento
        SET estado = 'libre'
        WHERE estado = 'reservado'
          AND TIMESTAMPDIFF(MINUTE, updated_at, NOW()) >= 10
    ";
    $wpdb->query( $sql );
});

/* ------------------------------------------------------------------
 * Liberar asientos que se eliminan del carrito
 * -----------------------------------------------------------------*/
add_action( 'woocommerce_remove_cart_item', function( $cart_key, $cart ) {
    if ( isset( $cart->removed_cart_contents[ $cart_key ]['seat_id'] ) ) {
        global $wpdb;
        $sid = intval( $cart->removed_cart_contents[ $cart_key ]['seat_id'] );
        $wpdb->update(
            "{$wpdb->prefix}baxi_asientos_evento",
            [ 'estado' => 'libre', 'updated_at' => current_time('mysql') ],
            [ 'id' => $sid, 'estado' => 'reservado' ],
            [ '%s','%s' ], [ '%d','%s' ]
        );
    }
}, 10, 2 );


/* -----------------------------------------------------------------
 * Al marcar el pedido como COMPLETADO → asientos a "ocupado"
 * ----------------------------------------------------------------- */
add_action( 'woocommerce_order_status_completed', 'baxi_ocupar_asientos_evento' );

function baxi_ocupar_asientos_evento( $order_id ) {

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	global $wpdb;
	$tabla = "{$wpdb->prefix}baxi_asientos_evento";

	foreach ( $order->get_items() as $item ) {

		$seat_id   = intval( $item->get_meta( 'seat_id', true ) );
		$evento_id = intval( $item->get_meta( 'evento_id', true ) );

		if ( ! $seat_id || ! $evento_id ) {
			continue;               // sin meta → ignoramos
		}

		$wpdb->update(
			$tabla,
			[ 'estado' => 'ocupado', 'updated_at' => current_time( 'mysql' ) ],
			[ 'id'     => $seat_id,  'evento_id'  => $evento_id, 'estado' => 'reservado' ],
			[ '%s', '%s' ],
			[ '%d', '%d', '%s' ]
		);
	}
}


/* -----------------------------------------------------------
 * Copiar metadatos de asiento del carrito → línea de pedido
 * ----------------------------------------------------------- */
add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_item_key, $cart_item, $order ) {

		// Sólo si son asientos (tienen seat_id)
		if ( empty( $cart_item['seat_id'] ) ) {
			return;
		}

		$item->add_meta_data( 'seat_id',   intval( $cart_item['seat_id'] ),   true );
		$item->add_meta_data( 'evento_id', intval( $cart_item['evento_id'] ), true );

		
		if ( ! empty( $cart_item['tipo_entrada'] ) ) {
			$item->add_meta_data( 'tipo_entrada', sanitize_text_field( $cart_item['tipo_entrada'] ), true );
		}
	},
	10, 4
);

add_filter( 'woocommerce_order_again_button_enabled', '__return_false' );

add_filter( 'woocommerce_order_item_hidden_meta_keys', function( $hidden_keys ) {
    return array_merge( $hidden_keys, [
        'seat_id',
        'evento_id',
        'tipo_entrada',
        '_entrada_pdf_path',
    ]);
});
