<?php
global $wpdb;

$modo_edicion = false;
$tipo_actual = null;

if (isset($_GET['editar_tipo'])) {
    $modo_edicion = true;
    $id = intval($_GET['editar_tipo']);
    $tipo_actual = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_tipos_abono WHERE id = $id");
}

$cfg = [];
if ($tipo_actual && $tipo_actual->config) {
    $cfg = json_decode($tipo_actual->config, true);
}
function v($cfg, $k, $def='') { return isset($cfg[$k]) ? esc_attr($cfg[$k]) : $def; }
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
            <td>
                <select name="nombre" required>
                    <option value="">Seleccionar</option>
                    <?php
                    $titulares = [
                        'Familiar',
                        'Familiar club',
                        'Individual',
                        'Juvenil',
                    ];
                    foreach ($titulares as $nombre) {
                        $selected = (isset($tipo_actual->nombre) && $tipo_actual->nombre == $nombre) ? 'selected' : '';
                        echo "<option value='".esc_attr($nombre)."' $selected>".esc_html($nombre)."</option>";
                    }
                    ?>
                </select>
                <small><em>Solo los tipos principales de abono (los niños van como extra)</em></small>
            </td>
        </tr>
        <tr>
            <th><label for="personas">Nº Personas base</label></th>
            <td>
                <input name="personas" type="number" min="1" required value="<?php echo esc_attr($tipo_actual->numero_personas ?? '') ?>">
                <small><em>Número mínimo de personas por defecto en este abono</em></small>
            </td>
        </tr>
        <tr>
            <th><label for="wc_product_id">Producto WooCommerce</label></th>
            <td>
                <?php $productos = wc_get_products([ 'limit' => -1 ]); ?>
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
        <tr>
            <th colspan="2"><h4>Precios y restricciones por integrante extra</h4></th>
        </tr>
        <tr>
            <th><label>Precio abono principal (€)</label></th>
            <td>
                <input name="config[precio_titular]" type="number" step="0.01" required value="<?php
                    // Si viene de edición, lo toma del config, si no existe, de la columna
                    echo v($cfg, 'precio_titular', ($tipo_actual->precio ?? ''));
                ?>">
                <small>
                    <em>
                        Familiar: 210€<br>
                        Familiar club: 140€<br>
                        Individual: 120€<br>
                        Juvenil: 60€
                    </em>
                </small>
            </td>
        </tr>
        <tr>
            <th><label>Precio niño extra (5-17 años) (€)</label></th>
            <td><input name="config[precio_nino]" type="number" step="0.01" value="<?php echo v($cfg,'precio_nino', 40); ?>"> <small>(Por cada niño añadido, excepto el primero en Familiar)</small></td>
        </tr>
        <tr>
            <th><label>Precio niño menor de 5 años (€)</label></th>
            <td><input name="config[precio_nino_menor]" type="number" step="0.01" value="<?php echo v($cfg,'precio_nino_menor', 0); ?>"> <small>(Siempre 0€)</small></td>
        </tr>
        <tr>
            <th><label>Precio niño club (€)</label></th>
            <td><input name="config[precio_nino_club]" type="number" step="0.01" value="<?php echo v($cfg,'precio_nino_club', 0); ?>"> <small>(Siempre 0€)</small></td>
        </tr>
        <tr>
            <th><label>Nº máximo de niños extra</label></th>
            <td><input name="config[max_ninos]" type="number" min="0" value="<?php echo v($cfg,'max_ninos'); ?>"></td>
        </tr>
        <tr>
            <th><label>Permitir añadir niños club</label></th>
            <td>
                <input type="checkbox" name="config[permite_nino_club]" value="1" <?php echo v($cfg,'permite_nino_club') ? 'checked' : ''; ?>> Sí
            </td>
        </tr>
        <tr>
            <th><label>Permitir añadir niños menor de 5</label></th>
            <td>
                <input type="checkbox" name="config[permite_nino_menor]" value="1" <?php echo v($cfg,'permite_nino_menor') ? 'checked' : ''; ?>> Sí
            </td>
        </tr>
        <!-- Puedes añadir más controles o notas según necesites -->
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
                <th>Precio abono principal</th>
                <th>Nº Personas base</th>
                <th>Producto WooCommerce</th>
                <th>Precios niños</th>
                <th>Restricciones</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tipos as $tipo): 
                $cfg = $tipo->config ? json_decode($tipo->config, true) : [];
                // El precio principal siempre se muestra de la columna
                $precio_base = isset($tipo->precio) && $tipo->precio > 0 ? $tipo->precio : (isset($cfg['precio_titular']) ? $cfg['precio_titular'] : '—');
            ?>
                <tr>
                    <td><?php echo $tipo->id ?></td>
                    <td><?php echo esc_html($tipo->nombre) ?></td>
                    <td><?php echo is_numeric($precio_base) ? number_format($precio_base, 2, ',', '.') . ' €' : '—'; ?></td>
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
                        Niño: <?php echo isset($cfg['precio_nino']) ? number_format($cfg['precio_nino'], 2, ',', '.') . ' €' : '—'; ?><br>
                        Niño menor: <?php echo isset($cfg['precio_nino_menor']) ? number_format($cfg['precio_nino_menor'], 2, ',', '.') . ' €' : '—'; ?><br>
                        Niño club: <?php echo isset($cfg['precio_nino_club']) ? number_format($cfg['precio_nino_club'], 2, ',', '.') . ' €' : '—'; ?>
                    </td>
                    <td>
                        Máx niños: <?php echo isset($cfg['max_ninos']) ? esc_html($cfg['max_ninos']) : '—'; ?><br>
                        Club: <?php echo !empty($cfg['permite_nino_club']) ? 'Sí' : 'No'; ?><br>
                        Menor 5: <?php echo !empty($cfg['permite_nino_menor']) ? 'Sí' : 'No'; ?>
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
