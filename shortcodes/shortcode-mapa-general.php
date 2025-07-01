<?php
function baxi_mapa_general_shortcode($atts) {
    ob_start();
    global $wpdb;
    $atts = shortcode_atts(['id' => 0], $atts);
    $mapa_id = intval($atts['id']);
    if (!$mapa_id) return 'Mapa no válido';
    $mapa = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE id = $mapa_id");
    if (!$mapa) return 'Mapa no encontrado';

    $zonas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_zonas WHERE mapa_id = $mapa_id");
    // Determinar si este mapa corresponde a un evento para incluir el ID de evento en el enlace
    $evento_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}baxi_eventos WHERE mapa_id = $mapa_id");
    ?>
    <canvas id="mapa-general-<?= $mapa_id ?>" width="800" height="600" style="border:1px solid #ccc;"></canvas>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script>
    {
        const canvas = new fabric.Canvas('mapa-general-<?= $mapa_id ?>');
        const zonas = <?= json_encode($zonas) ?>;
        const eventId = <?= $evento_id ? $evento_id : 'null' ?>;

        zonas.forEach(z => {
            const rect = new fabric.Rect({
                left: parseInt(z.x),
                top: parseInt(z.y),
                width: parseInt(z.width),
                height: parseInt(z.height),
                fill: z.color,
                stroke: 'black',
                strokeWidth: 1,
                hasControls: false,
                hasBorders: false,
                selectable: true
            });
            rect.set({
                metadata: {
                    nombre: z.nombre,
                    submapa_id: z.submapa_id
                }
            });
            canvas.add(rect);
        });

        canvas.on('mouse:up', function(opt) {
            const obj = opt.target;
            if (obj?.metadata?.submapa_id) {
                // Si es un mapa de evento, incluir el ID de evento en la URL
                if (eventId) {
                    window.location.href = '?evento=' + eventId + '&submapa=' + obj.metadata.submapa_id;
                } else {
                    window.location.href = '?submapa=' + obj.metadata.submapa_id;
                }
            }
        });

        canvas.on('mouse:over', function(opt) {
            if (opt.target?.metadata?.nombre) {
                canvas.hoverCursor = 'pointer';
            }
        });

        canvas.on('mouse:out', function() {
            canvas.hoverCursor = 'default';
        });
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('baxi_mapa_general', 'baxi_mapa_general_shortcode');
?>
