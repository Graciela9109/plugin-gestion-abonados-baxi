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

?>


<div class="wrap">

    <?php

    if ( isset( $_GET['renovado'] ) ): ?>
        <div class="notice notice-success is-dismissible">
            <p>✅ Abono(s) renovado(s) correctamente.</p>
        </div>
    <?php elseif ( isset( $_GET['error'] ) && $_GET['error'] === 'renovacion' ): ?>
        <div class="notice notice-error is-dismissible">
            <p>❌ No se pudo completar la renovación.</p>
        </div>
    <?php endif; ?>

    <h1>Gestión de Abonados</h1>

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

    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="baxi-abonados" />
        <input type="text" name="filtro_nombre" placeholder="Buscar por nombre" value="<?= esc_attr($filtro['busqueda']) ?>" />
        <select name="filtro_grada">
            <option value="">Todas las gradas</option>
            <?php foreach ($gradas as $g): ?>
                <option value="<?= $g ?>" <?= selected($filtro['grada'], $g) ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="filtro_temporada" placeholder="Temporada (ej. 2025/26)" value="<?= esc_attr($filtro['temporada']) ?>" />
        <button class="button">Filtrar</button>
    </form>

    <hr>
    <h2>Listado de Abonados</h2>
    <table class="widefat">
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
                <th>PDF Abono</th>
				<th>Renovaciones</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($abonados as $a): ?>
                <tr class="titular" data-id="<?= $a->id ?>">
                    <td><?= esc_html($a->id) ?></td>
                    <td><?= esc_html($a->num_abono) ?></td>
                    <td><?= esc_html($a->num_socio) ?></td>
                    <td><strong><?= esc_html($a->nombre . ' ' . $a->apellidos) ?></strong> <small>(Titular)</small></td>
                    <td><?= esc_html($a->grada) ?></td>
                    <td><?= esc_html($a->fila) ?></td>
                    <td><?= esc_html($a->asiento) ?></td>
                    <td><?= esc_html($a->temporada) ?></td>
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
                </tr>
                <?php
                $extras = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE grupo_abono = {$a->id} ORDER BY id ASC");
                foreach ($extras as $e): ?>
                    <tr class="extra extra-<?= $a->id ?>" style="display: none; background-color: #f9f9f9;">
                        <td><?= esc_html($e->id) ?></td>
                        <td><?= esc_html($e->num_abono) ?></td>
                        <td><?= esc_html($e->num_socio) ?></td>
                        <td style="padding-left: 20px;">↳ <?= esc_html($e->nombre . ' ' . $e->apellidos) ?> <small>(<?= esc_html($e->parentesco) ?>)</small></td>
                        <td><?= esc_html($e->grada) ?></td>
                        <td><?= esc_html($e->fila) ?></td>
                        <td><?= esc_html($e->asiento) ?></td>
                        <td><?= esc_html($e->temporada) ?></td>
						<td>
							<?php $pdf_extra = admin_url( 'admin-post.php?action=baxi_generar_pdf_abono&id=' . $e->id ); ?>
							<a href="<?php echo esc_url( $pdf_extra ); ?>" class="button" target="_blank">📄 PDF</a>
						</td>
						
                        <td>
                            <a href="<?= admin_url('admin.php?page=baxi-abonados&editar=' . $e->id) ?>" class="button">Editar</a>
                            <a href="<?= wp_nonce_url(admin_url('admin-post.php?action=baxi_eliminar_abonado&id=' . $e->id), 'baxi_eliminar_abonado') ?>" class="button button-danger" onclick="return confirm('¿Eliminar este abonado extra?')">Eliminar</a>
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
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
