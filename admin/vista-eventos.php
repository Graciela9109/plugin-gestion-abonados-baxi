<?php
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
global $wpdb;


if ( isset( $_POST['baxi_eliminar_evento'] )
     && check_admin_referer( 'baxi_eliminar_evento_nonce' ) ) {

    $evento_id = intval( $_POST['baxi_eliminar_evento'] );

    $evento = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT mapa_id FROM {$wpdb->prefix}baxi_eventos WHERE id = %d",
            $evento_id
        )
    );

    if ( $evento && $evento->mapa_id ) {
        $mapa_id = intval( $evento->mapa_id );

        // Eliminar zonas del mapa clonado
        $wpdb->delete( "{$wpdb->prefix}baxi_zonas", [ 'mapa_id' => $mapa_id ], [ '%d' ] );

        // Eliminar asientos del mapa clonado
        $wpdb->delete( "{$wpdb->prefix}baxi_asientos", [ 'mapa_id' => $mapa_id ], [ '%d' ] );

        // Eliminar submapas asociados
		 $submapas = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id
				 FROM {$wpdb->prefix}baxi_mapas
				 WHERE es_submapa = 1
				   AND zona_padre_id = %d",
				$mapa_id
			)
		);

		foreach ( $submapas as $sub_id ) {
			// zonas y asientos de cada submapa
			$wpdb->delete( "{$wpdb->prefix}baxi_zonas",   [ 'mapa_id' => $sub_id ], [ '%d' ] );
			$wpdb->delete( "{$wpdb->prefix}baxi_asientos", [ 'mapa_id' => $sub_id ], [ '%d' ] );
			// submapa en sí
			$wpdb->delete( "{$wpdb->prefix}baxi_mapas",    [ 'id' => $sub_id ],      [ '%d' ] );
		}

				// Eliminar el mapa clonado principal
				$wpdb->delete( "{$wpdb->prefix}baxi_mapas", [ 'id' => $mapa_id ], [ '%d' ] );
			}

    // Eliminar asientos clónicos de ese evento
    $wpdb->delete(
        "{$wpdb->prefix}baxi_asientos_evento",
        [ 'evento_id' => $evento_id ],
        [ '%d' ]
    );

    // Eliminar relación producto-evento (si existe)
    $wpdb->delete(
        "{$wpdb->prefix}baxi_eventos_productos",
        [ 'evento_id' => $evento_id ],
        [ '%d' ]
    );

    // Eliminar el propio evento
    $wpdb->delete(
        "{$wpdb->prefix}baxi_eventos",
        [ 'id' => $evento_id ],
        [ '%d' ]
    );

    echo '<div class="notice notice-success is-dismissible"><p>✅ Evento, mapa clonado y datos relacionados eliminados correctamente.</p></div>';
}


// Datos de evento para editar
$evento_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$evento    = $evento_id
           ? $wpdb->get_row( $wpdb->prepare(
                 "SELECT * FROM {$wpdb->prefix}baxi_eventos WHERE id = %d",
                 $evento_id
             ) )
           : null;

// Listados para el formulario
$temporadas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_temporadas ORDER BY nombre DESC");
$productos  = get_posts([ 'post_type' => 'product', 'numberposts' => -1 ]);

// Temporada activa (única posible)
$temporada_activa = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_temporadas WHERE activa = 1 LIMIT 1");

// Mapa general (único posible)
$mapa_general = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE nombre = 'MAPA GENERAL PABELLON' AND es_submapa = 0 LIMIT 1");


// Mensaje de guardado
if ( isset( $_GET['creado'] ) ) {
    echo '<div class="notice notice-success is-dismissible"><p>✅ Evento guardado correctamente.</p></div>';
}
?>

<div class="wrap">
  <h1><?php echo $evento ? 'Editar Evento' : 'Crear Evento'; ?></h1>
  <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
    <?php wp_nonce_field( 'baxi_guardar_evento', 'baxi_guardar_evento_nonce' ); ?>
    <input type="hidden" name="action" value="guardar_evento">
    <?php if ( $evento ): ?>
      <input type="hidden" name="evento_id" value="<?php echo esc_attr( $evento->id ); ?>">
    <?php endif; ?>

    <table class="form-table">
      <tr>
        <th><label for="nombre">Nombre</label></th>
        <td>
          <input type="text" name="nombre" id="nombre" class="regular-text" required
            value="<?php echo esc_attr( $evento->nombre ?? '' ); ?>">
        </td>
      </tr>
      <tr>
        <th><label for="fecha">Fecha y hora</label></th>
        <td>
          <input type="datetime-local" name="fecha" id="fecha" class="regular-text" required
            value="<?php
              if ( $evento && ! empty( $evento->fecha ) ) {
                echo esc_attr( date('Y-m-d\TH:i', strtotime($evento->fecha)) );
              }
            ?>">
        </td>
      </tr>
	<tr>
	  <th><label for="temporada_id">Temporada</label></th>
	  <td>
		<input type="hidden" name="temporada_id" value="<?php echo esc_attr($temporada_activa->id); ?>">
		<input type="text" class="regular-text" disabled value="<?php echo esc_html($temporada_activa->nombre); ?>">
	  </td>
	</tr>
      <tr>
	  <th><label for="mapa_id">Mapa base</label></th>
	  <td>
		<input type="hidden" name="mapa_id" value="<?php echo esc_attr($mapa_general->id); ?>">
		<input type="text" class="regular-text" disabled value="<?php echo esc_html($mapa_general->nombre); ?>">
	  </td>
	</tr>
	<tr>
	  <th><label for="productos_evento">Producto</label></th>
	  <td>
		<select name="productos_evento[]" id="productos_evento" required>
		  <option value="">Selecciona un producto</option>
		  <?php foreach ( $productos as $p ): ?>
			<option value="<?php echo esc_attr($p->ID); ?>"
			  <?php echo in_array($p->ID, $wpdb->get_col(
				  $wpdb->prepare("SELECT producto_id FROM {$wpdb->prefix}baxi_eventos_productos WHERE evento_id = %d", $evento_id)
				)) ? 'selected' : ''; ?>>
			  <?php echo esc_html($p->post_title); ?>
			</option>
		  <?php endforeach; ?>
		</select>
	  </td>
	</tr>
    </table>

    <?php submit_button( $evento ? 'Actualizar Evento' : 'Crear Evento' ); ?>
  </form>

  <hr>
  <h2>Listado de Eventos</h2>
  <?php
    $tabla_evt = "{$wpdb->prefix}baxi_eventos";
    $eventos   = $wpdb->get_results(
      "SELECT e.*, t.nombre AS temporada, m.nombre AS mapa
       FROM {$tabla_evt} e
       LEFT JOIN {$wpdb->prefix}baxi_temporadas t ON e.temporada_id = t.id
       LEFT JOIN {$wpdb->prefix}baxi_mapas m ON e.mapa_id = m.id
       ORDER BY e.fecha DESC"
    );
  ?>

<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 6px;">
  <table class="widefat fixed striped">
    <thead>
      <tr>
        <th>ID</th><th>Nombre</th><th>Fecha</th><th>Temporada</th><th>Mapa</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if ( $eventos ): foreach ( $eventos as $ev ): ?>
        <tr>
          <td><?php echo esc_html($ev->id); ?></td>
          <td><?php echo esc_html($ev->nombre); ?></td>
          <td><?php echo esc_html($ev->fecha); ?></td>
          <td><?php echo esc_html($ev->temporada); ?></td>
          <td><?php echo esc_html($ev->mapa); ?></td>
			<td style="display:flex;gap:6px;">

			  <a href="<?php echo esc_url( admin_url("admin.php?page=baxi-eventos&id={$ev->id}") ); ?>"
				 class="button">Editar</a>

			  <form method="post" onsubmit="return confirm('¿Eliminar definitivamente este evento?');">
				  <?php wp_nonce_field( 'baxi_eliminar_evento_nonce' ); ?>
				  <input type="hidden" name="baxi_eliminar_evento" value="<?php echo esc_attr( $ev->id ); ?>">
				  <button type="submit" class="button">Eliminar</button>
			  </form>

			</td>

        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6">No hay eventos registrados.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</div>
