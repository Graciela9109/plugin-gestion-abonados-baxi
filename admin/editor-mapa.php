<?php
if (!current_user_can('manage_options')) wp_die('No autorizado');

$mapa_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$mapa_id) wp_die('ID de mapa no válido');

global $wpdb;
$mapa = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE id = $mapa_id");
if (!$mapa) wp_die('Mapa no encontrado');

$zonas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_zonas WHERE mapa_id = $mapa_id");
?>
<div class="wrap">
    <h1>Editor del mapa: <?= esc_html($mapa->nombre) ?></h1>

    <?php if (isset($_GET['guardado'])): ?>
        <div class="notice notice-success"><p>Zonas guardadas correctamente.</p></div>
    <?php endif; ?>

    <canvas id="canvas" width="800" height="600" style="border:1px solid #ccc; background: transparent; "></canvas>

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

    <form id="form_zonas" method="post" action="<?= admin_url('admin-post.php') ?>">
        <input type="hidden" name="action" value="baxi_guardar_zonas">
        <input type="hidden" name="mapa_id" value="<?= $mapa_id ?>">
        <input type="hidden" name="zonas_json" id="zonas_json">
        <p><button type="submit" class="button button-primary">Guardar zonas</button></p>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
const canvas = new fabric.Canvas('canvas');
const tooltip = document.getElementById('tooltip');
const zonas = <?= json_encode($zonas) ?>;

// Cargar zonas
zonas.forEach(z => {
    const rect = new fabric.Rect({
        left: parseInt(z.x),
        top: parseInt(z.y),
        width: parseInt(z.width),
        height: parseInt(z.height),
        fill: z.color,
        stroke: 'black',
        strokeWidth: 1,
        hasControls: true,
        hasBorders: true
    });

    rect.set({
        metadata: {
            nombre: z.nombre,
            color: z.color,
            submapa_id: z.submapa_id
        }
    });

    canvas.add(rect);
});

// Crear zona con submapa
canvas.on('mouse:dblclick', async function(opt) {
    const pointer = canvas.getPointer(opt.e);
    const nombre = prompt("Nombre de la zona:");
    if (!nombre) return;

    const color = prompt("Color (hex):", "#cccccc");

    const form = new FormData();
    form.append('action', 'baxi_crear_submapa');
    form.append('nombre', nombre);
    form.append('padre_id', <?= $mapa_id ?>);

    const submapa_id = await fetch(ajaxurl, {
        method: 'POST',
        body: form
    }).then(r => r.json()).then(res => res.data.submapa_id);

    const rect = new fabric.Rect({
        left: pointer.x,
        top: pointer.y,
        width: 100,
        height: 60,
        fill: color,
        stroke: 'black',
        strokeWidth: 1,
        hasControls: true,
        hasBorders: true
    });

    rect.set({
        metadata: {
            nombre,
            color,
            submapa_id
        }
    });

    canvas.add(rect);
});

// Guardar zonas
document.getElementById('form_zonas').addEventListener('submit', function(e) {
    const zonas = canvas.getObjects('rect').map(r => {
        const meta = r.metadata || {};
        return {
            nombre: meta.nombre || '',
            color: meta.color || '#cccccc',
            submapa_id: meta.submapa_id || null,
            x: r.left,
            y: r.top,
            width: r.width * r.scaleX,
            height: r.height * r.scaleY
        };
    });
    document.getElementById('zonas_json').value = JSON.stringify(zonas);
});

// Redirigir a submapa
canvas.on('mouse:up', function(opt) {
    const obj = opt.target;
    if (obj?.metadata?.submapa_id) {
        if (confirm(`¿Deseas ir al editor de asientos de la zona "${obj.metadata.nombre}"?`)) {
            window.location.href = `admin.php?page=baxi-editar-submapa&mapa_id=${obj.metadata.submapa_id}`;
        }
    }
});

// Tooltip flotante
canvas.on('mouse:over', function(opt) {
    const obj = opt.target;
    if (obj?.metadata?.nombre) {
        canvas.hoverCursor = 'pointer';
        tooltip.innerText = `Zona: ${obj.metadata.nombre}`;
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
