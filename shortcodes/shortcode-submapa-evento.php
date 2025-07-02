<?php
function baxi_submapa_evento_shortcode($atts) {
    global $wpdb;
    // Atributos: evento_id (ID del evento) y submapa_id (ID del mapa de la zona dentro del evento)
    $atts = shortcode_atts(['evento_id' => 0, 'submapa_id' => 0], $atts);
    $evento_id = intval($atts['evento_id'] ? $atts['evento_id'] : ($_GET['evento'] ?? 0));
    $mapa_id   = intval($atts['submapa_id'] ? $atts['submapa_id'] : ($_GET['submapa'] ?? 0));

    if (!$evento_id || !$mapa_id) {
        return 'Evento o submapa no especificado';
    }

    // Verificar que el evento y el submapa existen
    $evento = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_eventos WHERE id = $evento_id");
    if (!$evento) return 'Evento no encontrado';
    $zona = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE id = $mapa_id");
    if (!$zona) return 'Submapa no encontrado';
    // Comprobar que el submapa pertenezca al mapa del evento
    if (intval($zona->zona_padre_id) !== intval($evento->mapa_id)) {
        return 'El submapa no corresponde al evento';
    }

    // Obtener los asientos del evento para ese submapa
    $asientos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_asientos_evento WHERE evento_id = $evento_id AND mapa_id = $mapa_id");

    ob_start();
    ?>
    <div style="margin: 40px auto; text-align: center;">
        <h2 style="margin-bottom: 0;"><?= esc_html($zona->nombre ?? 'Zona sin nombre') ?></h2>
        <p style="color: #666; margin-top: 5px;"><?= count($asientos) ?> asientos</p>
        <div style="position: relative;">
            <canvas id="submapa-canvas-<?= $evento_id ?>" style="border:1px solid #ccc; background: transparent; display: block;"></canvas>
            <div id="tooltip-evento" style="
                 position: absolute;
                 background: #000;
                 color: #fff;
                 padding: 2px 50px;
                 font-size: 12px;
                 border-radius: 4px;
                 pointer-events: none;
                 opacity: 0;
                 transition: opacity 0.2s;
                 z-index: 10;
            "></div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script>
    {
        const canvasEvt = new fabric.Canvas('submapa-canvas-<?= $evento_id ?>');
        const tooltipEvt = document.getElementById('tooltip-evento');
        const asientosEvt = <?= json_encode($asientos) ?>;
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        let drawn = 0;

        asientosEvt.forEach(a => {
            const x = parseFloat(a.x), y = parseFloat(a.y);
            if (isNaN(x) || isNaN(y)) {
                console.warn('Asiento con coordenadas inválidas:', a);
                return;
            }
            // Acumular extremos para auto-zoom
			const width = a.width ? parseFloat(a.width) : 30;
			const height = a.height ? parseFloat(a.height) : 30;
			minX = Math.min(minX, x);
			minY = Math.min(minY, y);
			maxX = Math.max(maxX, x + width);
			maxY = Math.max(maxY, y + height);

            // Determinar color según estado del asiento
            let fillColor;
            switch(a.estado) {
                case 'ocupado':
                    fillColor = '#00cc00';  // rojo para ocupado/vendido
                    break;
                case 'abonado':
                    fillColor = '#cc0000';  // azul para abonados (reservado)
                    break;
                case 'liberado':
                    fillColor = '#0066cc';  // verde para liberado (disponible para venta)
                    break;
                default:
                    fillColor = '#00cc00';  // verde para libre (disponible)
            }

			const rect = new fabric.Rect({
				left: x,
				top: y,
				width: width,
				height: height,
				fill: fillColor,
                stroke: 'black',
                strokeWidth: 1,
                hasControls: false,
                hasBorders: false,
                selectable: false,
				visible: true,
				opacity: 1
            });
            rect.set({
                metadata: {
                    grada: a.grada,
                    fila: a.fila,
                    asiento: a.asiento,
                    estado: a.estado
                }
            });
            canvasEvt.add(rect);
            drawn++;
        });

        if (drawn === 0) {
            console.error("⚠️ No se ha podido dibujar ningún asiento.");
        } else {
            // Ajustar zoom para mostrar todos los asientos dentro del canvas
            const padding = 50;
            const width = maxX - minX + padding;
            const height = maxY - minY + padding;
            const scaleX = canvasEvt.getWidth() / width;
            const scaleY = canvasEvt.getHeight() / height;
            const scale = Math.min(scaleX, scaleY);
            const offsetX = (canvasEvt.getWidth() - (maxX - minX) * scale) / 2;
            const offsetY = (canvasEvt.getHeight() - (maxY - minY) * scale) / 2;
            canvasEvt.setViewportTransform([scale, 0, 0, scale, -minX * scale + offsetX, -minY * scale + offsetY]);
        }

        canvasEvt.renderAll();
		
        // Tooltip al pasar el ratón por encima de un asiento
        canvasEvt.on('mouse:over', function(opt) {
            const meta = opt.target?.metadata;
            if (meta) {
                tooltipEvt.innerText = `Grada: ${meta.grada} | Fila: ${meta.fila} | Asiento: ${meta.asiento}`;
                tooltipEvt.style.opacity = 1;
            }
        });
        canvasEvt.on('mouse:out', function() {
            tooltipEvt.style.opacity = 0;
        });
        canvasEvt.on('mouse:move', function(opt) {
            if (tooltipEvt.style.opacity === '1') {
                tooltipEvt.style.left = (opt.e.clientX + 15) + 'px';
                tooltipEvt.style.top = (opt.e.clientY - 10) + 'px';
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('baxi_submapa_evento', 'baxi_submapa_evento_shortcode');
?>
