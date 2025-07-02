<div class="wrap">
    <?php settings_errors('baxi_temporadas'); ?>
    <h1>Gestión de Temporadas</h1>
	
	<?php if ( isset($_GET['error']) && $_GET['error'] === 'formato_nombre' ): ?>
    <div class="notice notice-error is-dismissible">
        <p>❌ Formato de nombre de temporada no válido. Usa "Temporada 2025/2026".</p>
    </div>
	<?php endif; ?>

    <?php if ( isset( $_GET['guardado'] ) ): ?>
        <div class="updated notice"><p>✅ Temporada guardada correctamente.</p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['activada'] ) ): ?>
        <div class="updated notice"><p>✅ Nueva temporada activada. Las anteriores han sido desactivadas.</p></div>
    <?php endif; ?>

    <h2>Crear nueva temporada</h2>
    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <input type="hidden" name="action" value="guardar_temporada">
        <?php wp_nonce_field( 'baxi_guardar_temporada', 'baxi_guardar_temporada_nonce' ); ?>

        <table class="form-table">
			<tr>
			  <th><label for="nombre">Nombre</label></th>
			  <td>
				<input name="nombre" type="text" id="nombre"
					   placeholder="Temporada 2025/2026"
					   pattern="^Temporada\s\d{4}/\d{4}$"
					   title="Formato: Temporada 2025/2026"
					   required />
			  </td>
			</tr>
            <tr>
                <th scope="row"><label for="inicio_alta">Inicio Alta</label></th>
                <td>
                    <input name="inicio_alta" type="date" id="inicio_alta"
                        value="<?php echo esc_attr( $temporada->inicio_alta ?? '' ); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fin_alta">Fin Alta</label></th>
                <td>
                    <input name="fin_alta" type="date" id="fin_alta"
                        value="<?php echo esc_attr( $temporada->fin_alta ?? '' ); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="inicio_renovacion">Inicio Renovación</label></th>
                <td>
                    <input name="inicio_renovacion" type="date" id="inicio_renovacion"
                        value="<?php echo esc_attr( $temporada->inicio_renovacion ?? '' ); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="fin_renovacion">Fin Renovación</label></th>
                <td>
                    <input name="fin_renovacion" type="date" id="fin_renovacion"
                        value="<?php echo esc_attr( $temporada->fin_renovacion ?? '' ); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>
            <tr>
                <th><label for="activa">Temporada activa</label></th>
                <td>
                    <input name="activa" type="checkbox" id="activa" value="1" />
                </td>
            </tr>
        </table>

        <p>
            <input type="submit" class="button-primary" value="Guardar Temporada">
        </p>
    </form>

    <hr>

    <h2>Temporadas registradas</h2>
    <?php
    global $wpdb;
    $tabla      = $wpdb->prefix . 'baxi_temporadas';
    $temporadas = $wpdb->get_results( "SELECT * FROM $tabla ORDER BY activa DESC" );
    ?>

    <?php if ( $temporadas ): ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Alta</th>
                    <th>Renovación</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $temporadas as $temp ): ?>
                    <tr>
                        <td><?php echo esc_html( $temp->nombre ); ?></td>
                        <td>
                            <?php
                            echo esc_html( $temp->inicio_alta ) . ' → ' .
                                 esc_html( $temp->fin_alta );
                            ?>
                        </td>
                        <td>
                            <?php
                            echo esc_html( $temp->inicio_renovacion ) . ' → ' .
                                 esc_html( $temp->fin_renovacion );
                            ?>
                        </td>
                        <td>
                            <?php echo $temp->activa
                                ? '<strong style="color:green;">Activa</strong>'
                                : 'Inactiva'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay temporadas registradas.</p>
    <?php endif; ?>
</div>
