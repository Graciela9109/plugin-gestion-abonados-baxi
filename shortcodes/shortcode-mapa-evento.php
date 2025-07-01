<?php
/**
 * Shortcode  [baxi_mapa_evento id=123]
 *
 * Dibuja el mapa general de un evento (zonas) y,
 * al hacer clic, carga el submapa con los asientos.
 */
function baxi_shortcode_mapa_evento( $atts ) {

    global $wpdb;

    // Acepta tanto id como evento_id por compatibilidad
    $atts = shortcode_atts( [
        'id'         => 0,
        'evento_id'  => 0
    ], $atts );

    // Prioriza evento_id si está presente
    $eventoID = intval( $atts['evento_id'] ?: $atts['id'] );
    if ( ! $eventoID ) return 'Evento no válido';


    // ── 2) Datos del evento y su mapa ─────────────────────────
    $mapaID = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT mapa_id FROM {$wpdb->prefix}baxi_eventos WHERE id = %d",
            $eventoID
        )
    );
    if ( ! $mapaID ) return 'Mapa no encontrado para este evento';

    // Zonas del mapa general
    $zonas = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}baxi_zonas WHERE mapa_id = %d",
            $mapaID
        ),
        ARRAY_A
    );

    // ── 3) Salida HTML + Fabric ────────────────────────────────
    ob_start(); 	?>
	
<div class="baxi-mapa-wrapper">
  <!-- CONTENEDOR ZONAS -->
  <div id="mapa-zonas-container" class="baxi-canvas-container">
    <canvas id="mapa-zonas" width="900" height="650"></canvas>
  </div>

  <!-- CONTENEDOR SUBMAPA -->
  <div id="mapa-submapa-container" class="baxi-canvas-container" style="display:none">
    <button id="baxi-btn-back" class="button" style="margin: 10px auto; display: block;">← Volver a zonas</button>
    <canvas id="mapa-submapa" width="900" height="650"></canvas>
  </div>
</div>

      <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
      <script>
		  var baxiMapData = {
			  eventoId : <?php echo esc_js( $eventoID ); ?>,
			  zonas    : <?php echo wp_json_encode( $zonas ); ?>,
			  nonce    : '<?php echo wp_create_nonce( "baxi_selector" ); ?>',
			  ajaxUrl  : '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>'
		  };
	</script>
      <!-- ¡IMPORTANTE! Asegúrate de que esta ruta coincide con tu cambio -->
      <script src="<?php echo esc_url( BAXI_URL . 'admin/assets/js/baxi-selector.js' ); ?>" defer></script>
    <?php
	
	    wp_enqueue_style(
        'baxi-public-map',
        BAXI_URL . 'shortcodes/css/baxi-style.css',
        [],
        '1.0'
    );
	
    return ob_get_clean();
}
add_shortcode( 'baxi_mapa_evento', 'baxi_shortcode_mapa_evento' );
