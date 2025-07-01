<?php
function baxi_selector_evento_shortcode($atts) {
    ob_start();

    global $wpdb;

    $atts = shortcode_atts([
        'evento_id' => 0,
    ], $atts);

    $evento_id = intval($atts['evento_id']);
    if (!$evento_id) return 'Evento no válido';

    // Obtener mapa del evento
    $mapa_id = $wpdb->get_var($wpdb->prepare(
        "SELECT mapa_id FROM {$wpdb->prefix}baxi_eventos WHERE id = %d", $evento_id
    ));
    if (!$mapa_id) return 'Mapa del evento no encontrado';

    // Obtener zonas del mapa general
    $zonas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}baxi_zonas WHERE mapa_id = %d", $mapa_id
    ));

    wp_enqueue_script('fabric', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js', [], null, true);
    wp_enqueue_script('baxi-selector', plugins_url('admin/assets/js/baxi-selector.js', BAXI_PLUGIN_FILE), ['fabric'], null, true);
    wp_localize_script('baxi-selector', 'baxiMapData', [
        'ajaxUrl'    => admin_url('admin-ajax.php'),
        'eventoId'   => $evento_id,
        'zonas'      => $zonas,
        'mapaId'     => $mapa_id,
        'nonce'      => wp_create_nonce('baxi_selector_nonce'),
	'zonas_bg_url' => plugins_url( 'admin/assets/fondo-zonas.png', __FILE__ ),
    ]);

    ?>
    <div id="baxi-selector-wrap">
        <div id="mapa-zonas-container">
            <canvas id="mapa-zonas" width="800" height="600" style="border:1px solid #ccc; background-color: transparent;"></canvas>
        </div>

        <div id="mapa-submapa-container" style="display:none;">
            <button id="volver-a-zonas" class="button">← Volver a zonas</button>
            <canvas id="mapa-submapa" width="800" height="600" style="border:1px solid #ccc; margin-top:10px; background-color: transparent;"></canvas>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('baxi_selector_evento', 'baxi_selector_evento_shortcode');
