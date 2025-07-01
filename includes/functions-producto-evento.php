<?php
/**
 * Funciones específicas para productos-evento
 *
 * 1) Inyectar el mapa del evento en la ficha de producto (pestaña Descripción).
 * 2) Ocultar el botón estándar “Añadir al carrito” excepto en nuestro AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -------------------------------------------------------------------------
 * Inyectar el mapa del evento en la pestaña Descripción
 * ------------------------------------------------------------------------- */
add_filter( 'woocommerce_product_tabs', 'baxi_reemplazar_descripcion_con_mapa', 98 );
function baxi_reemplazar_descripcion_con_mapa( $tabs ) {
    if ( ! is_product() ) {
        return $tabs;
    }

    global $post, $wpdb;
    $product_id = $post->ID;

    // Relación producto → evento
    $evento_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT evento_id
           FROM {$wpdb->prefix}baxi_eventos_productos
          WHERE producto_id = %d
          LIMIT 1",
        $product_id
    ) );
    if ( ! $evento_id ) {
        return $tabs;
    }

    $tabs['description']['title']    = __( 'Selecciona tus asientos', 'baxi' );
    $tabs['description']['callback'] = function() use ( $evento_id, $post ) {
        echo '<div class="baxi-mapa-producto">';
        echo do_shortcode( '[baxi_mapa_evento evento_id="' . intval( $evento_id ) . '"]' );
        echo '</div>';
    };
    return $tabs;
}

/* -------------------------------------------------------------------------
 * Deshabilitar compra directa (oculta el botón estándar)
 * ------------------------------------------------------------------------- 
add_filter( 'woocommerce_is_purchasable', 'baxi_product_evento_no_purchasable', 10, 2 );
function baxi_product_evento_no_purchasable( $purchasable, $product ) {

    if ( is_admin() ) {
        return $purchasable;
    }

    if ( defined( 'DOING_AJAX' ) && DOING_AJAX
      && isset( $_POST['action'] ) && $_POST['action'] === 'baxi_add_seats_to_cart'
    ) {
        return true;
    }

    global $wpdb;
    $evento_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT evento_id
           FROM {$wpdb->prefix}baxi_eventos_productos
          WHERE producto_id = %d
          LIMIT 1",
        $product->get_id()
    ) );
    return $evento_id ? false : $purchasable;
}


add_action( 'wp_enqueue_scripts', function() {
    if ( is_product() ) {
        wp_add_inline_style(
            'woocommerce-inline',
            '.single-product .single_add_to_cart_button,
             .single-product form.cart {display:none !important;}'
        );
    }
}, 20 ); */

add_action( 'woocommerce_single_product_summary', function() {
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
}, 1 ); 
