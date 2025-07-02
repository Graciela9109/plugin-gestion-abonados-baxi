<?php
global $wpdb;

// Obtener temporada activa
$temporada_activa = $wpdb->get_var(
    "SELECT nombre FROM {$wpdb->prefix}baxi_temporadas WHERE activa = 1 LIMIT 1"
);
$es_edicion = ! empty( $datos_editar->id );
?>

<form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ); ?>">
<div class="baxi-formulario">
    <?php wp_nonce_field( 'baxi_guardar_abonado', 'baxi_guardar_abonado_nonce' ); ?>
    <input type="hidden" name="action" value="baxi_guardar_abonado">
    <?php if ( $es_edicion ): ?>
        <input type="hidden" name="editar_id" value="<?= esc_attr( $datos_editar->id ); ?>">
    <?php endif; ?>

    <h2><?= $es_edicion ? 'Editar Abonado' : 'Nuevo Abonado'; ?></h2>
    
	<table class="form-table">
        <tr>
            <th><label for="num_abono">Nº de Abono</label></th>
            <td>
              <input type="text" name="num_abono" id="num_abono" required
                     value="<?= esc_attr( $datos_editar->num_abono ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="num_socio">Nº de Socio</label></th>
            <td>
              <input type="text" name="num_socio" id="num_socio"
                     value="<?= esc_attr( $datos_editar->num_socio ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="nombre">Nombre</label></th>
            <td>
              <input type="text" name="nombre" id="nombre" required
                     value="<?= esc_attr( $datos_editar->nombre ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="apellidos">Apellidos</label></th>
            <td>
              <input type="text" name="apellidos" id="apellidos" required
                     value="<?= esc_attr( $datos_editar->apellidos ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="email">Email</label></th>
            <td>
              <input type="email" name="email" id="email" required
                     value="<?= esc_attr( $datos_editar->email ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="grada">Grada</label></th>
            <td>
                <select name="grada" id="grada" required>
                    <option value="">Seleccionar</option>
                    <option value="Norte" <?= selected( $datos_editar->grada ?? '', 'Norte', false ); ?>>Norte</option>
                    <option value="Sur"   <?= selected( $datos_editar->grada ?? '', 'Sur',   false ); ?>>Sur</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="fila">Fila</label></th>
            <td>
              <input type="text" name="fila" id="fila" required
                     value="<?= esc_attr( $datos_editar->fila ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="asiento">Asiento</label></th>
            <td>
              <input type="text" name="asiento" id="asiento" required
                     value="<?= esc_attr( $datos_editar->asiento ?? '' ); ?>">
            </td>
        </tr>
        <tr>
            <th><label for="parentesco">Parentesco</label></th>
            <td>
                <select name="parentesco" id="parentesco">
                    <?php
                    $parentesco_actual = $datos_editar->parentesco ?? 'Titular';
                    foreach ( [ 'Titular', 'Padre/Madre', 'Hijo(a)', 'Cónyuge', 'Hermano(a)', 'Sobrino(a)' ] as $op ) :
                    ?>
                        <option value="<?= esc_attr( $op ); ?>"
                            <?= selected( $parentesco_actual, $op, false ); ?>>
                            <?= esc_html( $op ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="temporada">Temporada</label></th>
            <td>
              <input type="text" name="temporada" id="temporada" readonly
                     value="<?= esc_attr( $temporada_activa ); ?>"
                     style="background:#eee;">
            </td>
        </tr>
        <tr>
            <th><label for="tipo_abono">Tipo de Abono</label></th>
            <td>
                <select name="tipo_abono" id="tipo_abono" required <?= empty( $datos_editar ) ? 'disabled' : ''; ?>>
                    <option value="">Seleccionar tipo</option>
                    <?php
                    $tipos = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}baxi_tipos_abono ORDER BY id ASC" );
                    foreach ( $tipos as $tipo ) :
                        $sel = ( isset( $datos_editar->tipo_abono ) && $datos_editar->tipo_abono == $tipo->id )
                             ? 'selected' : '';
                    ?>
                        <option value="<?= esc_attr( $tipo->id ); ?>"
                                data-personas="<?= esc_attr( $tipo->numero_personas ); ?>"
                                <?= $sel; ?>>
                            <?= esc_html( $tipo->nombre ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <div id="contenedor_extras"></div>

    <p>
      <input type="submit" class="button button-primary"
             value="<?= $es_edicion ? 'Actualizar Abonado' : 'Guardar Abonado'; ?>">
    </p>
</form>

<script>
// Tu script de extras dinámicos tal cual lo tenías
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipo_abono');
    const numAbono   = document.getElementById('num_abono');
    const grada      = document.getElementById('grada');
    const fila       = document.getElementById('fila');

    function habilitarTipo() {
        tipoSelect.disabled = !(numAbono.value && grada.value && fila.value);
    }

    [numAbono, grada, fila].forEach(el => {
        if (el) {
            el.addEventListener('input', habilitarTipo);
            el.addEventListener('change', habilitarTipo);
        }
    });
    habilitarTipo();

    tipoSelect.addEventListener('change', function () {
        const container = document.getElementById('contenedor_extras');
        container.innerHTML = '';
        const personas = parseInt(this.selectedOptions[0].dataset.personas || '1', 10);
        if (personas <= 1) return;

        for (let i = 1; i < personas; i++) {
            const div = document.createElement('div');
            div.classList.add('extra-abonado');
            div.innerHTML = `
                <h3>Abonado Extra ${i}</h3>
                <table class="form-table">
                    <tr><th><label>Nombre</label></th>
                        <td><input type="text" name="extras[${i}][nombre]" required></td></tr>
                    <tr><th><label>Apellidos</label></th>
                        <td><input type="text" name="extras[${i}][apellidos]" required></td></tr>
                    <tr><th><label>Nº de Socio</label></th>
                        <td><input type="text" name="extras[${i}][num_socio]"></td></tr>
                    <tr><th><label>Asiento</label></th>
                        <td><input type="text" name="extras[${i}][asiento]" required></td></tr>
                    <tr><th><label>Parentesco</label></th>
                        <td>
                          <select name="extras[${i}][parentesco]" required>
                              <option value="Padre/Madre">Padre/Madre</option>
                              <option value="Hijo(a)">Hijo(a)</option>
                              <option value="Cónyuge">Cónyuge</option>
                              <option value="Hermano(a)">Hermano(a)</option>
                              <option value="Sobrino(a)">Sobrino(a)</option>
                          </select>
                        </td></tr>
                    <tr><th><label>Nº de Abono</label></th>
                        <td><input type="text" name="extras[${i}][num_abono]"
                                   value="${numAbono.value}" readonly style="background:#eee;"></td></tr>
                    <tr><th><label>Grada</label></th>
                        <td><input type="text" name="extras[${i}][grada]"
                                   value="${grada.value}" readonly style="background:#eee;"></td></tr>
                    <tr><th><label>Fila</label></th>
                        <td><input type="text" name="extras[${i}][fila]"
                                   value="${fila.value}" readonly style="background:#eee;"></td></tr>
                </table><hr>`;
            container.appendChild(div);
        }
    });
});
</script>
