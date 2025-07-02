<?php
global $wpdb;

// Obtener temporada activa
$temporada_activa = $wpdb->get_var(
    "SELECT nombre FROM {$wpdb->prefix}baxi_temporadas WHERE activa = 1 LIMIT 1"
);
$es_edicion = !empty($datos_editar->id);

// Tipos de abono: se cargan desde la BD para que el value sea el ID y tengan precio real
$tipos_abono = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_tipos_abono ORDER BY id ASC");
$tipos_extra = [
    ['valor' => 'adulto',      'texto' => 'Adulto extra',           'precio' => 0],
    ['valor' => 'nino',        'texto' => 'Niño (5 a 17 años)',     'precio' => 40],
    ['valor' => 'nino_menor',  'texto' => 'Niño menor de 5 años',   'precio' => 0],
    ['valor' => 'nino_club',   'texto' => 'Niño club',              'precio' => 0],
];

// Precargar extras en edición
$extras_editar = [];
if ($es_edicion) {
    $extras_editar = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE grupo_abono = %d ORDER BY id ASC",
        $datos_editar->id
    ));
}
?>
<script>
window.baxiTiposAbono = <?= json_encode(array_map(function($t){
    return [
        'id'     => $t->id,
        'nombre' => $t->nombre,
        'precio' => floatval($t->precio)
    ];
}, $tipos_abono)) ?>;
window.baxiTiposExtra = <?= json_encode($tipos_extra) ?>;
window.baxiExtras = <?= json_encode(array_map(function($e) {
    return [
        'tipo_persona' => $e->tipo_persona ?? '',
        'nombre'      => $e->nombre ?? '',
        'apellidos'   => $e->apellidos ?? '',
        'num_socio'   => $e->num_socio ?? '',
        'grada'       => $e->grada ?? '',
        'fila'        => $e->fila ?? '',
        'asiento'     => $e->asiento ?? '',
        'parentesco'  => $e->parentesco ?? '',
        '_obligatorio'=> property_exists($e, '_obligatorio') ? $e->_obligatorio : false,
    ];
}, $extras_editar)) ?>;
</script>

<form method="post" action="<?= esc_url(admin_url('admin-post.php')); ?>">
<div class="baxi-formulario">
    <?php wp_nonce_field('baxi_guardar_abonado', 'baxi_guardar_abonado_nonce'); ?>
    <input type="hidden" name="action" value="baxi_guardar_abonado">
    <?php if ($es_edicion): ?>
        <input type="hidden" name="editar_id" value="<?= esc_attr($datos_editar->id); ?>">
    <?php endif; ?>

    <h2><?= $es_edicion ? 'Editar Abonado' : 'Nuevo Abonado'; ?></h2>
    <table class="form-table">
        <!-- ... campos de abonado ... -->
        <tr>
            <th><label for="num_abono">Nº de Abono</label></th>
            <td>
              <input type="text" name="num_abono" id="num_abono" required
                     value="<?= esc_attr($datos_editar->num_abono ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="num_socio">Nº de Socio</label></th>
            <td>
              <input type="text" name="num_socio" id="num_socio"
                     value="<?= esc_attr($datos_editar->num_socio ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="nombre">Nombre</label></th>
            <td>
              <input type="text" name="nombre" id="nombre" required
                     value="<?= esc_attr($datos_editar->nombre ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="apellidos">Apellidos</label></th>
            <td>
              <input type="text" name="apellidos" id="apellidos" required
                     value="<?= esc_attr($datos_editar->apellidos ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="email">Email</label></th>
            <td>
              <input type="email" name="email" id="email" required
                     value="<?= esc_attr($datos_editar->email ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="grada">Grada</label></th>
            <td>
                <select name="grada" id="grada" required>
                    <option value="">Seleccionar</option>
                    <option value="Norte" <?= selected($datos_editar->grada ?? '', 'Norte', false); ?>>Norte</option>
                    <option value="Sur"   <?= selected($datos_editar->grada ?? '', 'Sur',   false); ?>>Sur</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="fila">Fila</label></th>
            <td>
              <input type="text" name="fila" id="fila" required
                     value="<?= esc_attr($datos_editar->fila ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="asiento">Asiento</label></th>
            <td>
              <input type="text" name="asiento" id="asiento" required
                     value="<?= esc_attr($datos_editar->asiento ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <th>Tipo de abono</th>
            <td>
			<?php
			$tipos_db = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_tipos_abono ORDER BY id ASC");
			?>
			<select name="tipo_abono" id="tipo_abono" required>
				<option value="">Seleccionar</option>
				<?php foreach ($tipos_db as $tipo): ?>
					<option value="<?= esc_attr($tipo->id) ?>" <?= (isset($datos_editar->tipo_abono) && $datos_editar->tipo_abono==$tipo->id)?'selected':'' ?>>
						<?= esc_html($tipo->nombre) ?>
					</option>
				<?php endforeach ?>
			</select>
            </td>
        </tr>
        <tr>
            <th>Tipo de persona</th>
            <td>
                <input type="text" value="Adulto titular" readonly style="background:#eee;">
                <input type="hidden" name="tipo_persona" value="adulto_titular">
            </td>
        </tr>
        <tr>
            <th>Parentesco</th>
            <td>
                <input type="text" value="Titular" readonly style="background:#eee;">
                <input type="hidden" name="parentesco" value="Titular">
            </td>
        </tr>
        <tr>
            <th><label for="temporada">Temporada</label></th>
            <td>
              <input type="text" name="temporada" id="temporada" readonly
                     value="<?= esc_attr($temporada_activa); ?>"
                     style="background:#eee;">
            </td>
        </tr>
    </table>

    <hr>
    <h3>Integrantes adicionales (extras)</h3>
    <div id="contenedor_extras"></div>
    <button type="button" id="add-extra-btn">Añadir niño</button>
    <div id="baxi_total_abono" style="margin:1em 0;font-weight:bold;"></div>

    <p>
      <input type="submit" class="button button-primary"
             value="<?= $es_edicion ? 'Actualizar Abonado' : 'Guardar Abonado'; ?>">
    </p>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoAbono = document.getElementById('tipo_abono');
    const container = document.getElementById('contenedor_extras');
    const addBtn = document.getElementById('add-extra-btn');
    const totalDiv = document.getElementById('baxi_total_abono');
    let tiposAbono = window.baxiTiposAbono || [];
    let preciosBase = {};
    tiposAbono.forEach(a=>{preciosBase[a.id]=a.precio;});
    let preciosExtra = (window.baxiTiposExtra||[]).reduce((acc,a)=>{acc[a.valor]=a.precio;return acc;}, {});
    let extras = window.baxiExtras || [];

    function renderExtras() {
        container.innerHTML = '';
        let tipos = window.baxiTiposExtra || [];
        let parentescos = [
            { value: '',            text: 'Seleccionar' },
            { value: 'Cónyuge',     text: 'Cónyuge' },
            { value: 'Padre/Madre', text: 'Padre/Madre' },
            { value: 'Hijo(a)',     text: 'Hijo(a)' },
            { value: 'Hermano(a)',  text: 'Hermano(a)' },
            { value: 'Sobrino(a)',  text: 'Sobrino(a)' }
        ];
        extras.forEach((extra, idx) => {
            let tiposOpciones = tipos.map(tp =>
                `<option value="${tp.valor}" ${extra.tipo_persona===tp.valor?'selected':''}>${tp.texto}</option>`
            ).join('');
            let parentescoOptions = parentescos.map(op =>
                `<option value="${op.value}" ${extra.parentesco===op.value?'selected':''}>${op.text}</option>`
            ).join('');
            container.innerHTML += `
                <div style="border:1px solid #ccc;padding:7px;margin-bottom:9px;">
                  <strong>${extra.tipo_persona === 'adulto' ? 'Adulto extra' : 'Niño ' + (extras.filter(e=>e.tipo_persona!=='adulto').indexOf(extra)+1)}</strong>
                  ${extra._obligatorio ? '<span style="color:#888;font-size:12px;">(obligatorio)</span>' : ''}
                  ${!extra._obligatorio ? `<button type="button" class="remove-extra" data-idx="${idx}" style="float:right;">Eliminar</button>` : ''}
                  <table>
                  <tr>
                    <th>Tipo:</th>
                    <td>
                        <select name="extras[${idx}][tipo_persona]" class="tipo-extra" ${extra._obligatorio && extra.tipo_persona==='adulto'?'disabled':''}>
                            ${tiposOpciones}
                        </select>
                        ${extra._obligatorio && extra.tipo_persona==='adulto' ? '<input type="hidden" name="extras['+idx+'][tipo_persona]" value="adulto">' : ''}
                    </td>
                  </tr>
                  <tr>
                    <th>Nombre</th>
                    <td><input type="text" name="extras[${idx}][nombre]" value="${extra.nombre||''}" required></td>
                  </tr>
                  <tr>
                    <th>Apellidos</th>
                    <td><input type="text" name="extras[${idx}][apellidos]" value="${extra.apellidos||''}" required></td>
                  </tr>
                  <tr>
                    <th>Parentesco</th>
                    <td>
                      <select name="extras[${idx}][parentesco]">
                        ${parentescoOptions}
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <th>Nº de Socio</th>
                    <td><input type="text" name="extras[${idx}][num_socio]" value="${extra.num_socio||''}"></td>
                  </tr>
                  <tr>
                    <th>Grada</th>
                    <td>
                        <select name="extras[${idx}][grada]">
                          <option value="">Seleccionar</option>
                          <option value="Norte" ${extra.grada==='Norte'?'selected':''}>Norte</option>
                          <option value="Sur" ${extra.grada==='Sur'?'selected':''}>Sur</option>
                        </select>
                    </td>
                  </tr>
                  <tr>
                    <th>Fila</th>
                    <td><input type="text" name="extras[${idx}][fila]" value="${extra.fila||''}"></td>
                  </tr>
                  <tr>
                    <th>Asiento</th>
                    <td><input type="text" name="extras[${idx}][asiento]" value="${extra.asiento||''}"></td>
                  </tr>
                  </table>
                </div>
            `;
        });
        // Botones de eliminar
        container.querySelectorAll('.remove-extra').forEach(btn=>{
            btn.onclick=function(){
                extras.splice(parseInt(this.dataset.idx),1);
                renderExtras(); calcTotal();
            }
        });
        calcTotal();
    }

    function calcTotal() {
        let tipo = tipoAbono.value;
        let total = preciosBase[tipo] || 0;
        // Familiar: segundo adulto (obligatorio) y primer niño gratis (obligatorio), resto niños pagan
        if (tipo && tiposAbono.find(t=>t.id==tipo)?.nombre?.toLowerCase().includes('familiar')) {
            extras.forEach((e, idx) => {
                if (e.tipo_persona === 'adulto') return; // segundo adulto, sin recargo
                if (idx === 1) return; // primer niño, gratis
                let precio = preciosExtra[e.tipo_persona] || 0;
                total += parseFloat(precio);
            });
        }
        // Familiar club: segundo adulto (obligatorio), niños club gratis, resto pagan
        else if (tipo && tiposAbono.find(t=>t.id==tipo)?.nombre?.toLowerCase().includes('club')) {
            extras.forEach(e => {
                if (e.tipo_persona === 'adulto') return;
                if (e.tipo_persona === 'nino_club') return; // niño club gratis
                let precio = preciosExtra[e.tipo_persona] || 0;
                total += parseFloat(precio);
            });
        }
        // Otros: suman todos los extras según tipo
        else {
            extras.forEach(e => {
                let precio = preciosExtra[e.tipo_persona] || 0;
                total += parseFloat(precio);
            });
        }
        totalDiv.innerHTML = '<b>Total estimado: ' + total.toFixed(2) + ' €</b>';
    }

    addBtn.onclick = function() {
        extras.push({
            tipo_persona: window.baxiTiposExtra[1].valor, // por defecto niño
            nombre: '', apellidos: '', num_socio: '', grada: '', fila: '', asiento: '', parentesco: ''
        });
        renderExtras();
    };

    container.addEventListener('change', function(e){
        if(e.target.classList.contains('tipo-extra')){
            calcTotal();
        }
    });

    tipoAbono.addEventListener('change', function(){
        let tipo = tipoAbono.value;
        extras = [];
        // Detecta el tipo de abono (por ID)
        let nombreTipo = '';
        let tipoRow = tiposAbono.find(t=>t.id==tipo);
        if (tipoRow) nombreTipo = tipoRow.nombre.toLowerCase();
        if (nombreTipo.includes('familiar')) {
            // Extra 1: Segundo adulto (obligatorio)
            extras.push({
                tipo_persona: 'adulto',
                nombre: '', apellidos: '', num_socio: '', grada: '', fila: '', asiento: '', parentesco: 'Cónyuge',
                _obligatorio: true
            });
            // Extra 2: Primer niño (obligatorio, gratis)
            extras.push({
                tipo_persona: 'nino',
                nombre: '', apellidos: '', num_socio: '', grada: '', fila: '', asiento: '', parentesco: 'Hijo(a)',
                _obligatorio: true
            });
        } else if (nombreTipo.includes('club')) {
            // Extra 1: Segundo adulto (obligatorio)
            extras.push({
                tipo_persona: 'adulto',
                nombre: '', apellidos: '', num_socio: '', grada: '', fila: '', asiento: '', parentesco: 'Cónyuge',
                _obligatorio: true
            });
        }
        renderExtras();
    });

    // Precarga extras en edición
    if (window.baxiExtras && window.baxiExtras.length) {
        extras = window.baxiExtras;
    }
    renderExtras();
});
</script>
