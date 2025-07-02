<?php
/**
 * Shortcode [baxi_mis_abonos]
 */
function baxi_shortcode_mis_abonos() {
    if ( ! is_user_logged_in() ) {
        return '<p>Debes iniciar sesión para ver tus abonos.</p>';
    }

    global $wpdb;
    $user      = wp_get_current_user();
    $email     = $user->user_email;
    $hoy       = date('Y-m-d');
    $t_activa  = $wpdb->get_var( "SELECT nombre FROM {$wpdb->prefix}baxi_temporadas WHERE activa = 1" );
    $nonce     = wp_create_nonce( 'baxi_liberar_asiento' );
    $ajax_url  = admin_url( 'admin-ajax.php' );

    // 1) Titulares
    $titulares = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}baxi_abonados
         WHERE email = %s AND grupo_abono IS NULL
         ORDER BY id DESC",
        $email
    ) );
    if ( ! $titulares ) {
        return '<p>No se encontraron abonos asociados a tu cuenta.</p>';
    }

// → 1.A) Obtenemos temporada activa y fechas de renovación
$renew = $wpdb->get_row( "
    SELECT nombre   AS temporada_activa,
           inicio_renovacion,
           fin_renovacion
    FROM {$wpdb->prefix}baxi_temporadas
    WHERE activa = 1
    LIMIT 1
", ARRAY_A );

$active_temp = $renew['temporada_activa'] ?? '';
$hoy         = current_time( 'Y-m-d' );
$en_plazo    = $renew
             && $hoy >= $renew['inicio_renovacion']
             && $hoy <= $renew['fin_renovacion'];



    ob_start();
    ?>
    <h2>Mis abonos</h2>
    <p>Temporada activa: <strong><?php echo esc_html( $t_activa ); ?></strong></p>

    <?php foreach ( $titulares as $T ):
        // enlace al PDF de este abonado
        $pdf_url = admin_url( 'admin-post.php?action=baxi_generar_pdf_abono&id=' . intval( $T->id ) );
    ?>
    <div class="baxi-abono-block" style="border:1px solid #ccc;padding:1em;margin-bottom:1em;">
        <p>
            <strong><?php echo esc_html("{$T->nombre} {$T->apellidos}"); ?></strong>
            (Abono Nº <?php echo esc_html( $T->num_abono ); ?>)
            (Socio Nº <?php echo esc_html( $T->num_socio ); ?>)
        </p>
        <p>
            Grada: <?php echo esc_html( $T->grada ); ?>
            | Fila: <?php echo esc_html( $T->fila ); ?>
            | Asiento: <?php echo esc_html( $T->asiento ); ?>
        </p>
        <p>Última temporada: <?php echo esc_html( $T->temporada ); ?>

		<?php
		// ——— Botón RENOVAR para el TITULAR ———
		$product_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT wc_product_id
			 FROM {$wpdb->prefix}baxi_tipos_abono    /* <- aquí */
			 WHERE id = %d
			 LIMIT 1",
			$T->tipo_abono
		) );

		if ( $T->temporada === $active_temp ) {
			// Ya está en temporada activa: no renovar
			echo '<span style="color:gray">Temporada en curso. Este bono todavía no puede ser renovado.</span>';
		}
		elseif ( ! $en_plazo ) {
			// Fuera de ventana de renovación
			echo '<button class="button" disabled>Renovar abono (fuera de plazo)</button>';
		}
		elseif ( ! $product_id ) {
			// No hay producto configurado
			echo '<p style="color:orange">Producto no configurado para este tipo de abono.</p>';
		}
		else {
			// En plazo, distinto a activa: botón funcional
			$add_url = wc_get_cart_url()
					 . '?add-to-cart=' . $product_id
					 . '&baxi_abonado_id=' . $T->id;
			echo '<p><a href="' . esc_url( $add_url ) . '" class="button">🔄 Renovar mi abono para la nueva temporada</a></p>';
		}

		?>
	
		</p>
		
		
        <?php if ( ! empty( $T->qr_code ) ): ?>
            <p><img src="<?php echo esc_url( $T->qr_code ); ?>" style="max-width:120px;"></p>
        <?php endif; ?>

        <p>
          <a href="<?php echo esc_url( $pdf_url ); ?>" class="button" target="_blank">
            📄 Descargar PDF
          </a>
        </p>

<?php
        // 3) Eventos para liberar asiento (solo hoy o mañana)
        $eventos = $wpdb->get_results( $wpdb->prepare(
            "SELECT ev.id, ev.nombre, ev.fecha
             FROM {$wpdb->prefix}baxi_asientos_evento ea
             JOIN {$wpdb->prefix}baxi_eventos ev ON ea.evento_id = ev.id
             WHERE ea.grada   = %s
               AND ea.fila    = %s
               AND ea.asiento = %s
               AND DATE(ev.fecha) BETWEEN %s AND %s
             ORDER BY ev.fecha ASC",
            $T->grada,
            $T->fila,
            $T->asiento,
            $hoy,
            date('Y-m-d', strtotime('+1 day'))
        ) );
        if ( $eventos ): ?>
            <h4>Liberar asiento en evento:</h4>
            <select class="baxi-event-select"
                    data-grada="<?php echo esc_attr( $T->grada ); ?>"
                    data-fila="<?php echo esc_attr( $T->fila ); ?>"
                    data-asiento="<?php echo esc_attr( $T->asiento ); ?>">
                <option value="">-- Selecciona evento --</option>
                <?php foreach ( $eventos as $ev ): ?>
                    <option value="<?php echo esc_attr( $ev->id ); ?>">
                        <?php echo esc_html("{$ev->nombre} ({$ev->fecha})"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button baxi-liberar-btn">Liberar asiento</button>
        <?php endif; ?>
		
        <?php
        // 2) Extras de este titular
        $extras = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}baxi_abonados
             WHERE grupo_abono = %d
             ORDER BY id ASC",
            $T->id
        ) );
        if ( $extras ):
            echo '<h4>Extras:</h4>';
            foreach ( $extras as $E ):
                $pdf_extra = admin_url( 'admin-post.php?action=baxi_generar_pdf_abono&id=' . intval( $E->id ) );
            ?>
            <div style="margin-left:20px;border:1px dashed #aaa;padding:0.5em;margin-bottom:0.5em;">
                <p>
                    ↳ <strong><?php echo esc_html("{$E->nombre} {$E->apellidos}"); ?></strong>
                    (Abono Nº <?php echo esc_html( $E->num_abono ); ?>)
                    (Socio Nº <?php echo esc_html( $E->num_socio ); ?>)
                </p>
                <p>
                    Grada: <?php echo esc_html( $E->grada ); ?>
                    | Fila: <?php echo esc_html( $E->fila ); ?>
                    | Asiento: <?php echo esc_html( $E->asiento ); ?>
                </p>

                <?php if ( ! empty( $E->qr_code ) ): ?>
                    <p><img src="<?php echo esc_url( $E->qr_code ); ?>" style="max-width:100px;"></p>
                <?php endif; ?> 

                <p>
                  <a href="<?php echo esc_url( $pdf_extra ); ?>" class="button small" target="_blank">
                    📄 Descargar PDF
                  </a>
                </p>
				
				<?php
				// Eventos para liberar asiento del extra
				$eventos_extra = $wpdb->get_results( $wpdb->prepare(
					"SELECT ev.id, ev.nombre, ev.fecha
					 FROM {$wpdb->prefix}baxi_asientos_evento ea
					 JOIN {$wpdb->prefix}baxi_eventos ev ON ea.evento_id = ev.id
					 WHERE ea.grada   = %s
					   AND ea.fila    = %s
					   AND ea.asiento = %s
					   AND DATE(ev.fecha) BETWEEN %s AND %s
					 ORDER BY ev.fecha ASC",
					$E->grada,
					$E->fila,
					$E->asiento,
					$hoy,
					date('Y-m-d', strtotime('+1 day'))
				) );
				if ( $eventos_extra ): ?>
					<h4>Liberar asiento en evento:</h4>
					<select class="baxi-event-select"
							data-grada="<?php echo esc_attr( $E->grada ); ?>"
							data-fila="<?php echo esc_attr( $E->fila ); ?>"
							data-asiento="<?php echo esc_attr( $E->asiento ); ?>">
						<option value="">-- Selecciona evento --</option>
						<?php foreach ( $eventos_extra as $ev ): ?>
							<option value="<?php echo esc_attr( $ev->id ); ?>">
								<?php echo esc_html("{$ev->nombre} ({$ev->fecha})"); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button class="button baxi-liberar-btn">Liberar asiento</button>
				<?php endif; ?>

            </div>
            <?php
            endforeach;
        endif;
        ?>

    </div>
    <?php endforeach; ?>

    <script type="text/javascript">
jQuery(function($){
  const ajaxUrl = '<?php echo esc_js( $ajax_url ); ?>';
  const nonce   = '<?php echo esc_js( $nonce ); ?>';

  // Liberar asiento
  $(document).on('click', '.baxi-liberar-btn', function(e){
    e.preventDefault();
    const btn    = $(this);
    const sel    = btn.prev('select.baxi-event-select');
    const evento = sel.val();
    const grada  = sel.data('grada');
    const fila   = sel.data('fila');
    const asien  = sel.data('asiento');

    if ( ! evento ) {
      alert('Selecciona un evento.');
      return;
    }
    if ( ! confirm('¿Seguro que quieres liberar el asiento para este evento?') ) {
      return;
    }

    btn.prop('disabled', true).text('Liberando…');

    $.post( ajaxUrl, {
      action:      'baxi_liberar_asiento',
      _ajax_nonce: nonce,
      evento_id:   evento,
      grada:       grada,
      fila:        fila,
      asiento:     asien
    })
    .done(function(res){
      if ( res.success ) {
        btn.replaceWith('<span style="color:green;font-weight:bold;">Asiento liberado</span>');
      } else {
        alert('Error: ' + (res.data||''));
        btn.prop('disabled',false).text('Liberar asiento');
      }
    })
    .fail(function(jqXHR, textStatus){
      console.error('AJAX error', textStatus, jqXHR.responseText);
      alert('Se ha producido un error al comunicarse con el servidor.');
      btn.prop('disabled',false).text('Liberar asiento');
    });
  });

  // Revertir liberación
  $(document).on('click', '.baxi-revert-btn', function(e){
    e.preventDefault();
    const btn    = $(this);
    const sel    = btn.closest('.baxi-abono-block').find('select.baxi-event-select');
    const evento = sel.val();
    const grada  = sel.data('grada');
    const fila   = sel.data('fila');
    const asien  = sel.data('asiento');

    if ( ! confirm('¿Revertir la liberación de este asiento?') ) {
      return;
    }

    btn.prop('disabled', true).text('Revirtiendo…');

    $.post( ajaxUrl, {
      action:      'baxi_revert_asiento',
      _ajax_nonce: nonce,
      evento_id:   evento,
      grada:       grada,
      fila:        fila,
      asiento:     asien
    })
    .done(function(res){
      if ( res.success ) {
        btn.replaceWith('<span style="color:orange;font-weight:bold;">Liberación revertida</span>');
      } else {
        alert('Error: ' + (res.data||''));
        btn.prop('disabled',false).text('Revertir');
      }
    })
    .fail(function(jqXHR, textStatus){
      console.error('AJAX error', textStatus, jqXHR.responseText);
      alert('Se ha producido un error al comunicarse con el servidor.');
      btn.prop('disabled',false).text('Revertir');
    });
  });
});
</script>

    <?php

    return ob_get_clean();
}
add_shortcode( 'baxi_mis_abonos', 'baxi_shortcode_mis_abonos' );
