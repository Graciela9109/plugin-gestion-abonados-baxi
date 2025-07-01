<?php
// includes/generar-entradas.php

use Dompdf\Dompdf;
use Dompdf\Options;
if ( ! class_exists( 'QRcode' ) ) {
    require_once dirname( __DIR__ ) . '/librerias/phpqrcode/qrlib.php';
}

add_action('woocommerce_order_status_completed', 'baxi_generar_pdfs_para_pedido');

function baxi_generar_pdfs_para_pedido($order_id) {
    global $wpdb;

    $order = wc_get_order($order_id);
    if (! $order) return;

    $upload_dir = wp_upload_dir();

    foreach ($order->get_items() as $item) {
        $seat_id   = $item->get_meta('seat_id', true);
        $evento_id = $item->get_meta('evento_id', true);
        $nombre    = $item->get_meta('Nombre');

        if (! $seat_id || ! $evento_id) continue;

        // Obtener datos del asiento
        $asiento = $wpdb->get_row($wpdb->prepare(
            "SELECT grada, fila, asiento FROM {$wpdb->prefix}baxi_asientos_evento WHERE id = %d",
            $seat_id
        ));
        if (! $asiento) continue;

        // Obtener nombre del evento
        $evento_nombre = $wpdb->get_var($wpdb->prepare(
            "SELECT nombre FROM {$wpdb->prefix}baxi_eventos WHERE id = %d",
            $evento_id
        ));
        if (! $evento_nombre) continue;

        $grada   = $asiento->grada;
        $fila    = $asiento->fila;
        $numero  = $asiento->asiento;
		
		$item->update_meta_data('Tipo de entrada', ucfirst($item->get_meta('tipo_entrada')));
        $item->update_meta_data('Grada', $grada);
        $item->update_meta_data('Fila', $fila);
        $item->update_meta_data('Asiento', $numero);
        $item->update_meta_data('Evento', $evento_nombre);
		$item->save();

        // Generar contenido del QR
        $codigo_unico = strtoupper(uniqid());
		$qr_text = "EVT:$evento_id;GRADA:$grada;FILA:$fila;ASIENTO:$numero;COD:$codigo_unico";
		$item->update_meta_data('Código de entrada', $codigo_unico);
		$item->update_meta_data('Texto QR', $qr_text);
		$item->save();

		$wpdb->insert(
			$wpdb->prefix.'baxi_entradas',
			[
				'evento_id'      => $evento_id,
				'pedido_id'      => $order_id,
				'user_id'        => $order->get_user_id(),
				'grada'          => $grada,
				'fila'           => $fila,
				'asiento'        => $numero,
				'tipo_entrada'   => $item->get_meta('tipo_entrada'),
				'qr'             => $qr_text,
				'codigo_manual'  => $codigo_unico,     // opcional, pero útil
				'estado'         => 'ocupado',
				'fecha_validacion'=> null,
			],
			[ '%d','%d','%d','%s','%s','%s','%s','%s','%s','%s' ]
		);


        // Crear imagen QR temporal
		ob_start();
		QRcode::png($qr_text, null, 'L', 4, 2);
		$image_data = ob_get_clean();
		$qr_base64 = 'data:image/png;base64,' . base64_encode($image_data);

        // HTML del PDF
        ob_start();
        ?>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 14px; padding: 20px; }
                h2 { margin-bottom: 10px; }
                p { margin: 5px 0; }
                .qr { margin-top: 20px; }
            </style>
        </head>
        <body>
            <h2>Entrada para el evento</h2>
            <p><strong>Evento:</strong> <?= esc_html($evento_nombre) ?></p>
            <p><strong>Grada:</strong> <?= esc_html($grada) ?></p>
            <p><strong>Fila:</strong> <?= esc_html($fila) ?></p>
            <p><strong>Asiento:</strong> <?= esc_html($numero) ?></p>
            <?php if ($nombre): ?>
                <p><strong>Nombre:</strong> <?= esc_html($nombre) ?></p>
            <?php endif; ?>
            <div class="qr">
                <img src="<?= $qr_base64 ?>" width="150" height="150">
            </div>
			<p><strong>Código manual:</strong> <?= esc_html($codigo_unico) ?></p>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // Configurar DomPDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        // Guardar PDF
        $filename = 'entrada_baxi_' . $order_id . '_' . uniqid() . '.pdf';
		$subdir    = '/baxi-entradas/' . $evento_id;
		$full_path = $upload_dir['basedir'] . $subdir;
		if ( ! file_exists($full_path) ) {
			wp_mkdir_p($full_path);
		}
		$file_path = $full_path . '/' . $filename;
		$file_url  = $upload_dir['baseurl'] . $subdir . '/' . $filename;


        if (file_put_contents($file_path, $dompdf->output())) {
            error_log("✅ PDF guardado en: $file_path");
            $item->update_meta_data('_entrada_pdf_url', $file_url);
			$item->update_meta_data('_entrada_pdf_path', $file_path); 
            $item->save();
        } else {
            error_log("❌ No se pudo guardar el PDF: $file_path");
        }
    }
}

add_filter('woocommerce_email_attachments', 'baxi_adjuntar_pdfs_email', 10, 3);

function baxi_adjuntar_pdfs_email($attachments, $email_id, $order) {
    if ($email_id !== 'customer_completed_order') return $attachments;

    if (! $order instanceof WC_Order) return $attachments;

    foreach ( $order->get_items() as $item ) {
        $pdf_path = $item->get_meta('_entrada_pdf_path');
        if ($pdf_path && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }
    }

    return $attachments;
}



