<?php
if (!current_user_can('manage_options')) return;

global $wpdb;

// Obtener el ID del mapa desde la URL
$mapa_id = isset($_GET['mapa_id']) ? intval($_GET['mapa_id']) : 0;
if (!$mapa_id) {
    echo "<div class='notice notice-error'><p>Mapa no válido.</p></div>";
    return;
}

// Obtener nombre del mapa
$mapa = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE id = $mapa_id");
if (!$mapa) {
    echo "<div class='notice notice-error'><p>Mapa no encontrado.</p></div>";
    return;
}
?>

<div class="wrap">
    <h1>Editor de Asientos - <?= esc_html($mapa->nombre) ?></h1>

    <canvas id="canvas" width="1300" height="1300" style="border:1px solid #ccc; background: transparent;"></canvas>

    <div id="tooltip" style="
        position: fixed;
        background: #333;
        color: #fff;
        padding: 4px 8px;
        font-size: 12px;
        border-radius: 4px;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
        z-index: 9999;
    "></div>

    <br>
    <button id="add-seat" class="button">Añadir Asiento</button>
    <button id="save-seats" class="button button-primary">Guardar Asientos</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
const mapaId = <?= intval($mapa_id) ?>;
const canvas = new fabric.Canvas('canvas');
const tooltip = document.getElementById('tooltip');
const canvasElement = document.getElementById('canvas');

// Cargar asientos existentes
fetch(ajaxurl + '?action=baxi_get_asientos&mapa_id=' + mapaId)
    .then(res => res.json())
    .then(asientos => {
        asientos.forEach(a => {
            const rect = new fabric.Rect({
                left: parseInt(a.x),
                top: parseInt(a.y),
                width: 30,
                height: 30,
                fill: a.estado === 'abonado' ? '#ff0000' :
                      a.estado === 'libre' ? '#00cc00' :
                      a.estado === 'liberado' ? '#00cccc' : '#aaaaaa',
                hasControls: false,
                hasBorders: true,
                stroke: 'black',
                strokeWidth: 1
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
        });
    });

// Añadir nuevo asiento
document.getElementById('add-seat').addEventListener('click', () => {
    const grada = prompt("Grada:");
    const fila = prompt("Fila:");
    const asiento = prompt("Número de asiento:");

    if (!grada || !fila || !asiento) {
        alert("Todos los campos son obligatorios.");
        return;
    }

    // Verificar duplicado
    const duplicado = canvas.getObjects().some(obj => {
        const meta = obj.metadata || {};
        return meta.grada === grada && meta.fila === fila && meta.asiento === asiento;
    });

    if (duplicado) {
        alert("Ya existe un asiento con esa grada, fila y número en este mapa.");
        return;
    }

    const left = 50 + Math.random() * 600;
    const top = 50 + Math.random() * 400;

    const rect = new fabric.Rect({
        left: left,
        top: top,
        width: 30,
        height: 30,
        fill: '#00cc00',
        stroke: 'black',
        strokeWidth: 1,
        hasControls: true,
        hasBorders: true,
        selectable: true
    });

    rect.set({
        metadata: {
            grada,
            fila,
            asiento,
            estado: 'libre'
        }
    });

    canvas.add(rect);
});

// Guardar asientos
document.getElementById('save-seats').addEventListener('click', () => {
    const data = canvas.getObjects().map(obj => {
        return {
            x: Math.round(obj.left),
            y: Math.round(obj.top),
            grada: obj.metadata.grada,
            fila: obj.metadata.fila,
            asiento: obj.metadata.asiento,
            estado: obj.metadata.estado
        };
    });

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'baxi_guardar_asientos',
            mapa_id: mapaId,
            asientos: JSON.stringify(data)
        })
    })
    .then(res => res.json())
    .then(res => {
        alert(res.success ? "Asientos guardados correctamente" : "Error al guardar");
    });
});

// Eliminar asiento seleccionado
document.addEventListener('keydown', function(e) {
    if (e.key === 'Delete' || e.key === 'Backspace') {
        const activeObject = canvas.getActiveObject();
        if (activeObject) {
            canvas.remove(activeObject);
        }
    }
});

// Tooltip real
canvas.on('mouse:over', function(opt) {
    const obj = opt.target;
    if (obj && obj.metadata) {
        canvas.hoverCursor = 'pointer';
        const { grada, fila, asiento } = obj.metadata;
        tooltip.innerText = `Grada: ${grada}, Fila: ${fila}, Asiento: ${asiento}`;
        tooltip.style.opacity = 1;
    }
});

canvas.on('mouse:out', function() {
    tooltip.style.opacity = 0;
    canvas.hoverCursor = 'default';
});

canvas.on('mouse:move', function(opt) {
    if (tooltip.style.opacity === "0") return;
    tooltip.style.left = (opt.e.clientX + 15) + 'px';
    tooltip.style.top = (opt.e.clientY - 10) + 'px';
});
</script>
