<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Adjuntar PDFs al email de pedido completado
 */
add_filter( 'woocommerce_email_attachments', function ( $attachments, $email_id, $order, $email ) {
    if ( $email_id !== 'customer_completed_order' || ! is_a( $order, 'WC_Order' ) ) return $attachments;

    foreach ( $order->get_items() as $item ) {
        $url = $item->get_meta( '_entrada_pdf_url' );
        if ( $url ) {
            $path = str_replace( content_url(), WP_CONTENT_DIR, $url );
            if ( file_exists( $path ) ) {
                $attachments[] = $path;
            } else {
                error_log("❌ PDF no encontrado para adjuntar: $path");
            }
        }
    }

    return $attachments;
}, 10, 4 );


/**
 * Mostrar botón de descarga de la entrada (PDF) en la cuenta del cliente
 */
add_action( 'woocommerce_order_item_meta_end', function ( $item_id, $item, $order ) {
    if ( ! is_user_logged_in() || ! is_account_page() ) return;

    $url = $item->get_meta( '_entrada_pdf_url' );
    if ( $url ) {
        echo '<p><a href="' . esc_url( $url ) . '" target="_blank" class="button">Descargar entrada (PDF)</a></p>';
    }
}, 10, 3 );
