<?php
/**
 * Vista de Abonados - con formulario de alta y listado
 */

global $wpdb;

// Cargar datos para edición si aplica
$modo_edicion = isset($_GET['editar']) && is_numeric($_GET['editar']);
$datos_editar = null;
$extras_editar = [];

if ($modo_edicion) {
    $editar_id = intval($_GET['editar']);
    $datos_editar = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE id = $editar_id");
    $extras_editar = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE grupo_abono = %d", $editar_id));
}

include_once plugin_dir_path(__FILE__) . 'form-abonado.php';

$temporadas = $wpdb->get_results("SELECT DISTINCT temporada FROM {$wpdb->prefix}baxi_abonados ORDER BY temporada DESC");
$gradas = ['Norte', 'Sur'];

$filtro = [
    'busqueda' => $_GET['filtro_nombre'] ?? '',
    'temporada' => $_GET['filtro_temporada'] ?? '',
    'grada' => $_GET['filtro_grada'] ?? ''
];

$where = ["grupo_abono IS NULL"];
if (!empty($filtro['busqueda'])) {
    $like = '%' . esc_sql($filtro['busqueda']) . '%';
    $where[] = "(nombre LIKE '$like' OR apellidos LIKE '$like' OR num_abono LIKE '$like' OR email LIKE '$like')";
}
if (!empty($filtro['temporada'])) {
    $where[] = "temporada = '" . esc_sql($filtro['temporada']) . "'";
}
if (!empty($filtro['grada'])) {
    $where[] = "grada = '" . esc_sql($filtro['grada']) . "'";
}
$where_sql = implode(" AND ", $where);

$abonados = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE $where_sql ORDER BY id DESC");

$renew = $wpdb->get_row(
    "SELECT nombre, inicio_renovacion, fin_renovacion
     FROM {$wpdb->prefix}baxi_temporadas
     WHERE activa = 1
     LIMIT 1",
    ARRAY_A
);

$hoy      = current_time('Y-m-d');
$en_plazo = $renew
           && $hoy >= $renew['inicio_renovacion']
           && $hoy <= $renew['fin_renovacion'];
$active_temp = $renew['nombre'] ?? '';

// ---- Mapeo de tipos y reglas ----
$tipos_abono_precio = [
    'familiar'      => 210,
    'familiar_club' => 140,
    'individual'    => 120,
    'juvenil'       => 60,
];
$precio_nino = 40; // Niños que pagan

// Función para clasificar persona (independiente de nomenclaturas inconsistentes)
function baxi_is_nino($tipo_persona, $parentesco = '') {
    $t = strtolower($tipo_persona);
    $p = strtolower($parentesco);
    return (
        strpos($t, 'nino') !== false ||
        strpos($t, 'niño') !== false ||
        strpos($t, 'hijo') !== false ||
        strpos($t, 'hijo(a)') !== false ||
        $p === 'hijo(a)' ||
        $p === 'hijo'
    );
}
function baxi_is_adulto($tipo_persona, $parentesco = '') {
    $t = strtolower($tipo_persona);
    $p = strtolower($parentesco);
    return (
        strpos($t, 'adulto') !== false ||
        strpos($t, 'conyuge') !== false ||
        strpos($t, 'padre') !== false ||
        strpos($t, 'madre') !== false ||
        strpos($p, 'conyuge') !== false ||
        strpos($p, 'padre') !== false ||
        strpos($p, 'madre') !== false
    );
}
?>

<style>
.baxi-listado-scroll {
    max-height: 480px;
    overflow-y: auto;
    border: 1px solid #ddd;
    background: #fff;
    margin-bottom: 1.5em;
}
.baxi-listado-scroll table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
}
.baxi-listado-scroll th,
.baxi-listado-scroll td {
    padding: 7px 12px;
    border-bottom: 1px solid #eee;
}
.baxi-listado-scroll thead th {
    position: sticky;
    top: 0;
    background: #fafafa;
    z-index: 2;
}
</style>


<div class="wrap">

    <?php if ( isset( $_GET['renovado'] ) ): ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ Abono(s) renovado(s) correctamente.</p>
        </div>
    <?php elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'renovacion' ): ?>
        <div class="notice notice-error is-dismissible">
            <p>❌ No se pudo completar la renovación.</p>
        </div>
    <?php endif; ?>

    <h1>Búsqueda rápida de Abonados</h1>

    <?php if (isset($_GET['guardado'])): ?>
        <div class="notice notice-success"><p>Abonado guardado correctamente.</p></div>
    <?php elseif (isset($_GET['eliminado'])): ?>
        <div class="notice notice-success"><p>Abonado eliminado correctamente.</p></div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'asiento'): ?>
        <div class="notice notice-error"><p>Error: Este asiento está ocupado o no existe.</p></div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'email'): ?>
        <div class="notice notice-error"><p>Error: El email del titular es obligatorio.</p></div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'temporada'): ?>
        <div class="notice notice-error"><p>Error: La temporada es obligatoria.</p></div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'num_abono'): ?>
        <div class="notice notice-error"><p>Error: Ya existe un abonado con ese número de abono en esta temporada.</p></div>
    <?php endif; ?>

	<?php if ( isset($_GET['error']) && strpos($_GET['error'], 'missing_') === 0 ): 
		  $campo = substr( $_GET['error'], 8 );
		  $labels = [
			'num_abono' => 'Nº de abono',
			'num_socio' => 'Nº de socio',
			'nombre'    => 'Nombre',
			'apellidos' => 'Apellidos',
			'email'     => 'Email',
			'temporada' => 'Temporada',
			'grada'     => 'Grada',
			'fila'      => 'Fila',
			'asiento'   => 'Asiento',
		  ];
	?>
	  <div class="notice notice-error">
		<p>Error: El campo <strong><?php echo esc_html( $labels[ $campo ] ?? $campo ); ?></strong> es obligatorio.</p>
	  </div>
	<?php endif; ?>
	<input type="text" id="baxi-filtrar-abonados" placeholder="Filtra por nombre, abono, socio, email..." style="min-width:240px;margin-bottom:10px;">
    <hr>
    <h2>Listado de Abonados</h2>
	<div class="baxi-listado-scroll">
    <table id="tabla-abonados" class="widefat">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nº Abono</th>
				<th>Nº Socio</th>
                <th>Nombre</th>
                <th>Grada</th>
                <th>Fila</th>
                <th>Asiento</th>
                <th>Temporada</th>
                <th>Precio abono (€)</th>
                <th>Nº Miembros</th>
                <th>Nº Niños</th>
                <th>PDF Abono</th>
				<th>Renovaciones</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($abonados as $a): ?>
                <?php
                // ------ Cálculos de precio y recuentos ------
                // 1. Resolver tipo_abono como string (id->nombre)
                $tipo_abono_valor = $a->tipo_abono;
                if (is_numeric($tipo_abono_valor)) {
                    $row = $wpdb->get_row("SELECT nombre FROM {$wpdb->prefix}baxi_tipos_abono WHERE id = {$tipo_abono_valor}");
                    $tipo_abono_valor = $row ? sanitize_title($row->nombre) : '';
                }
                $precio = $tipos_abono_precio[$tipo_abono_valor] ?? 0;

                // 2. Recoger extras de este titular
                $extras = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE grupo_abono = {$a->id} ORDER BY id ASC");

                // 3. Contadores usando funciones robustas
				$num_adultos = 1; // titular siempre es 1 adulto
				$num_ninos = 0;
				foreach ($extras as $e) {
					if (baxi_is_adulto($e->tipo_persona, $e->parentesco)) $num_adultos++;
					if (baxi_is_nino($e->tipo_persona, $e->parentesco))   $num_ninos++;
				}

                // 4. Calcular precio final según reglas
                if ($tipo_abono_valor === 'familiar') {
                    // Primer niño gratis, el resto pagan
                    $niños_pago = max(0, $num_ninos - 1);
                    $precio += $niños_pago * $precio_nino;
                } elseif ($tipo_abono_valor === 'familiar_club') {
                    // Niños club gratis, otros pagan
                    foreach ($extras as $e) {
                        if (baxi_is_nino($e->tipo_persona, $e->parentesco) && stripos($e->tipo_persona, 'club') === false) {
                            $precio += $precio_nino;
                        }
                    }
                } elseif ($tipo_abono_valor === 'individual' || $tipo_abono_valor === 'juvenil') {
                    foreach ($extras as $e) {
                        if (baxi_is_nino($e->tipo_persona, $e->parentesco) && stripos($e->tipo_persona, 'club') === false && stripos($e->tipo_persona, 'menor') === false) {
                            $precio += $precio_nino;
                        }
                    }
                }
                ?>
                <tr class="titular" data-id="<?= $a->id ?>" data-search="<?= strtolower( $a->num_abono . ' ' . $a->num_socio . ' ' . $a->nombre); ?>">
                    <td><?= esc_html($a->id) ?></td>
                    <td><?= esc_html($a->num_abono) ?></td>
                    <td><?= esc_html($a->num_socio) ?></td>
                    <td><strong><?= esc_html($a->nombre . ' ' . $a->apellidos) ?></strong> <small>(Titular)</small></td>
                    <td><?= esc_html($a->grada) ?></td>
                    <td><?= esc_html($a->fila) ?></td>
                    <td><?= esc_html($a->asiento) ?></td>
                    <td><?= esc_html($a->temporada) ?></td>
                    <td><?= number_format($precio, 2, ',', '.') ?> €</td>
                    <td><?= esc_html($num_adultos) ?></td>
                    <td><?= esc_html($num_ninos) ?></td>
					<td><?php
					$pdf_url = admin_url( 'admin-post.php?action=baxi_generar_pdf_abono&id=' . $a->id );
					?>
					<a href="<?php echo esc_url( $pdf_url ); ?>"
					   class="button"
					   target="_blank">
						📄 PDF
					</a>
					</td>
                    <td>
                        <a href="<?= admin_url('admin.php?page=baxi-abonados&editar=' . $a->id) ?>" class="button">Editar</a>
                        <a href="<?= wp_nonce_url(admin_url('admin-post.php?action=baxi_eliminar_abonado&id=' . $a->id), 'baxi_eliminar_abonado') ?>" class="button button-danger" onclick="return confirm('¿Eliminar abonado y sus extras?')">Eliminar</a>
                        <button type="button" class="button toggle-extras" data-id="<?= $a->id ?>">Ver/Ocultar extras</button>
                    </td>
					<td>
					  <?php
					  // Sólo para filas TITULAR (grupo_abono IS NULL)
					  if ( $a->grupo_abono === null ) :

						// Si ya está en la temporada activa, no muestra botón
						if ( $a->temporada === $active_temp ) :
						  echo '<span style="color:gray">—</span>';

						// Si estamos fuera de plazo, muestra botón desactivado
						elseif ( ! $en_plazo ) :
						  echo '<button class="button" disabled>Renovar</button>';

						// En plazo y distinto a activa: botón funcional
						else :
						  $nonce = wp_create_nonce( 'baxi_renew_abono_' . $a->id );
						  $url   = add_query_arg( [
							'action'   => 'baxi_renew_abono',
							'id'       => $a->id,
							'_wpnonce' => $nonce
						  ], admin_url('admin-post.php') );
						  echo '<a href="'. esc_url($url) .'" class="button">Renovar</a>';
						endif;

					  // para los extras, dejo la celda vacía
					  endif;
					  ?>
					</td>
                </tr>
                <?php
                foreach ($extras as $e): ?>
                    <tr class="extra extra-<?= $a->id ?>" style="display: none; background-color: #f9f9f9;" data-search="<?= strtolower($e->num_abono . ' ' . $e->num_socio . ' ' . $e->nombre . ' ' . $e->apellidos); ?>">
                        <td><?= esc_html($e->id) ?></td>
                        <td><?= esc_html($e->num_abono) ?></td>
                        <td><?= esc_html($e->num_socio) ?></td>
                        <td style="padding-left: 20px;">↳ <?= esc_html($e->nombre . ' ' . $e->apellidos) ?> <small>(<?= esc_html($e->parentesco) ?>)</small></td>
                        <td><?= esc_html($e->grada) ?></td>
                        <td><?= esc_html($e->fila) ?></td>
                        <td><?= esc_html($e->asiento) ?></td>
                        <td><?= esc_html($e->temporada) ?></td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
						<td>
							<?php $pdf_extra = admin_url( 'admin-post.php?action=baxi_generar_pdf_abono&id=' . $e->id ); ?>
							<a href="<?php echo esc_url( $pdf_extra ); ?>" class="button" target="_blank">📄 PDF</a>
						</td>
                        <td>
                            <a href="<?= admin_url('admin.php?page=baxi-abonados&editar=' . $e->id) ?>" class="button">Editar</a>
                            <a href="<?= wp_nonce_url(admin_url('admin-post.php?action=baxi_eliminar_abonado&id=' . $e->id), 'baxi_eliminar_abonado') ?>" class="button button-danger" onclick="return confirm('¿Eliminar este abonado extra?')">Eliminar</a>
                        </td>
						
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtrado en vivo
    const input = document.getElementById('baxi-filtrar-abonados');
    if (input) {
      input.addEventListener('input', function() {
        const val = input.value.trim().toLowerCase();
        document.querySelectorAll('.baxi-listado-scroll tbody tr').forEach(tr => {
          const text = tr.getAttribute('data-search') || '';
          tr.style.display = text.includes(val) ? '' : 'none';
        });
      });
    }

    // Ver/Ocultar extras
    document.querySelectorAll('.toggle-extras').forEach(btn => {
        btn.addEventListener('click', function () {
            const grupo = this.dataset.id;
            document.querySelectorAll('.extra-' + grupo).forEach(row => {
                row.style.display = row.style.display === 'none' ? '' : 'none';
            });
        });
    });
});
</script>
