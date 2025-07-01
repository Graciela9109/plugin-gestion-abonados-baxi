<?php
function baxi_submapa_shortcode() {
    if (!isset($_GET['submapa'])) return '';

    global $wpdb;
    $mapa_id = intval($_GET['submapa']);
    if (!$mapa_id) return 'Submapa no válido';

    $zona = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE id = $mapa_id");
    $asientos = $wpdb->get_results("SELECT * FROM wp_baxi_asientos_evento WHERE mapa_id = $mapa_id");
	if (empty($asientos)) {
		// No hay asientos en tabla de eventos, intentar cargar de tabla base
		$asientos = $wpdb->get_results("SELECT * FROM wp_baxi_asientos WHERE mapa_id = $mapa_id");
	}

    ob_start();
    ?>
    <div style="max-width: 900px; margin: 40px auto; text-align: center;">
        <h2 style="margin-bottom: 0;">
            <?= esc_html($zona ? $zona->nombre : 'Zona sin nombre') ?>
        </h2>
        <p style="color: #666; margin-top: 5px;">
            <?= count($asientos) ?> asientos
        </p>

        <div style="position: relative;">
            <canvas id="submapa-canvas" width="800" height="600" style="border:1px solid #ccc; background: #fafafa;"></canvas>
            <div id="tooltip" style="
                position: absolute;
                background: #222;
                color: #fff;
                padding: 4px 8px;
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
    const canvas = new fabric.Canvas('submapa-canvas');
    const tooltip = document.getElementById('tooltip');
    const asientos = <?= json_encode($asientos) ?>;

    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    let dibujados = 0;

    asientos.forEach(a => {
        const x = parseFloat(a.x);
        const y = parseFloat(a.y);

        if (isNaN(x) || isNaN(y)) {
            console.warn('Asiento con coordenadas inválidas:', a);
            return;
        }

        minX = Math.min(minX, x);
        minY = Math.min(minY, y);
        maxX = Math.max(maxX, x + 30);
        maxY = Math.max(maxY, y + 30);

        const rect = new fabric.Rect({
            left: x,
            top: y,
            width: 30,
            height: 30,
            fill: '#00cc00',
            stroke: 'black',
            strokeWidth: 1,
            hasControls: false,
            hasBorders: false,
            selectable: false
        });

        rect.set({
            metadata: {
                grada: a.grada,
                fila: a.fila,
                asiento: a.asiento,
                estado: a.estado
            }
        });

        canvas.add(rect);
        dibujados++;
    });

    canvas.renderAll();

    if (dibujados === 0) {
        console.error("⚠️ No se ha podido dibujar ningún asiento.");
    }

    // Zoom automático al contenido
    if (dibujados > 0) {
        const padding = 50;
        const width = maxX - minX + padding;
        const height = maxY - minY + padding;
        const scaleX = canvas.getWidth() / width;
        const scaleY = canvas.getHeight() / height;
        const scale = Math.min(scaleX, scaleY);
        const offsetX = (canvas.getWidth() - (maxX - minX) * scale) / 2;
        const offsetY = (canvas.getHeight() - (maxY - minY) * scale) / 2;

        canvas.setViewportTransform([scale, 0, 0, scale, -minX * scale + offsetX, -minY * scale + offsetY]);
    }

    // Tooltip flotante
    canvas.on('mouse:over', function(opt) {
        const meta = opt.target?.metadata;
        if (meta) {
            tooltip.innerText = `Grada: ${meta.grada} | Fila: ${meta.fila} | Asiento: ${meta.asiento}`;
            tooltip.style.opacity = 1;
        }
    });

    canvas.on('mouse:out', function() {
        tooltip.style.opacity = 0;
    });

    canvas.on('mouse:move', function(opt) {
        if (tooltip.style.opacity === '1') {
            tooltip.style.left = (opt.e.clientX + 15) + 'px';
            tooltip.style.top = (opt.e.clientY - 10) + 'px';
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('baxi_submapa', 'baxi_submapa_shortcode');
