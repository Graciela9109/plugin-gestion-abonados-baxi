<?php
global $wpdb;

$modo_edicion = false;
$tipo_actual = null;

if (isset($_GET['editar_tipo'])) {
    $modo_edicion = true;
    $id = intval($_GET['editar_tipo']);
    $tipo_actual = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_tipos_abono WHERE id = $id");
}
?>

<h2><?php echo $modo_edicion ? 'Editar Tipo de Abono' : 'Añadir Nuevo Tipo de Abono' ?></h2>

<?php if (isset($_GET['success'])): ?>
    <div class="notice notice-success"><p>Cambios guardados correctamente.</p></div>
<?php elseif (isset($_GET['eliminado'])): ?>
    <div class="notice notice-success"><p>Tipo de abono eliminado correctamente.</p></div>
<?php endif; ?>

<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <input type="hidden" name="action" value="baxi_guardar_tipo_abono">
    <?php if ($modo_edicion): ?>
        <input type="hidden" name="id" value="<?php echo $tipo_actual->id ?>">
    <?php endif; ?>
    <table class="form-table">
        <tr>
            <th><label for="nombre">Nombre</label></th>
            <td><input name="nombre" type="text" class="regular-text" required value="<?php echo esc_attr($tipo_actual->nombre ?? '') ?>"></td>
        </tr>
        <tr>
            <th><label for="precio">Precio (€)</label></th>
            <td><input name="precio" type="number" step="0.01" required value="<?php echo esc_attr($tipo_actual->precio ?? '') ?>"></td>
        </tr>
        <tr>
            <th><label for="personas">Nº Personas</label></th>
            <td><input name="personas" type="number" min="1" required value="<?php echo esc_attr($tipo_actual->numero_personas ?? '') ?>"></td>
        </tr>
		<tr>
			<th><label for="entradas">Entradas asociadas</label></th>
			<td><?php $productos = wc_get_products([ 'limit' => -1 ]); ?>
				<select name="wc_product_id">
					<option value="">— Ninguno —</option>
					<?php foreach ( $productos as $p ): ?>
						<option value="<?= $p->get_id() ?>" <?= ($tipo_actual->wc_product_id ?? '') == $p->get_id() ? 'selected' : '' ?>>
							<?= esc_html($p->get_name()) ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
    </table>
    <p><input type="submit" class="button button-primary" value="<?php echo $modo_edicion ? 'Guardar cambios' : 'Añadir tipo de abono' ?>"></p>
</form>

<hr>

<h3>Listado de Tipos de Abono</h3>

<?php
$tipos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_tipos_abono ORDER BY id DESC");
?>

<?php if (empty($tipos)): ?>
    <p>No hay tipos de abono registrados todavía.</p>
<?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Precio (€)</th>
                <th>Nº Personas</th>
				<th>Entradas asociadas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tipos as $tipo): ?>
                <tr>
                    <td><?php echo $tipo->id ?></td>
                    <td><?php echo esc_html($tipo->nombre) ?></td>
                    <td><?php echo number_format($tipo->precio, 2, ',', '.') ?></td>
                    <td><?php echo esc_html($tipo->numero_personas) ?></td>
					<td>
						<?php
						if ($tipo->wc_product_id) {
							$producto = wc_get_product($tipo->wc_product_id);
							echo esc_html($producto ? $producto->get_name() : '(no encontrado)');
						} else {
							echo '—';
						}
						?>
					</td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=baxi-ajustes&editar_tipo=' . $tipo->id) ?>">Editar</a> |
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=baxi_eliminar_tipo_abono&id=' . $tipo->id), 'baxi_eliminar_tipo_abono') ?>"
                           onclick="return confirm('¿Seguro que deseas eliminar este tipo de abono?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
