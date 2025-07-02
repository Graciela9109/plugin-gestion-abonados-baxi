<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Baxi_Temporadas {

    public function __construct() {
        add_action( 'admin_post_guardar_temporada',  [ $this, 'guardar_temporada' ] );
    }

    public function registrar_menu() {
        add_submenu_page(
            'baxi-ajustes',
            'Temporadas',
            'Temporadas',
            'manage_options',
            'baxi-temporadas',
            [ $this, 'vista_temporadas' ]
        );
    }
	
    /**
     * Handler para guardar (insert o update) una temporada
     */
        public function guardar_temporada() {
        if ( ! current_user_can( 'manage_options' ) )  wp_die( 'No autorizado.' );
        if ( empty($_POST['baxi_guardar_temporada_nonce']) ||
             ! wp_verify_nonce($_POST['baxi_guardar_temporada_nonce'],'baxi_guardar_temporada') )
            wp_die('Nonce inválido.');

        global $wpdb;
        $tbl = "{$wpdb->prefix}baxi_temporadas";

        $id         = isset($_POST['temporada_id']) ? intval($_POST['temporada_id']) : 0;
        $nombre_raw = sanitize_text_field( $_POST['nombre'] ?? '' );

        /* ── Formateo / validación del nombre ── */
        if ( ! preg_match('/^Temporada\s\d{4}\/\d{4}$/', $nombre_raw) ) {
            if ( preg_match('/(\d{4}).?(\d{2,4})/', $nombre_raw, $m) ) {
                $anio2  = strlen($m[2]) === 2 ? '20'.$m[2] : $m[2];
                $nombre = 'Temporada '.$m[1].'/'.$anio2;
            } else {
                add_settings_error(
                    'baxi_temporadas',
                    'formato_nombre',
                    '❌ Formato de nombre de temporada no válido. Usa "Temporada 2025/2026".',
                    'error'
                );
                set_transient( 'settings_errors', get_settings_errors(), 30 );
                wp_redirect( admin_url('admin.php?page=baxi-temporadas') );
                exit;
            }
        } else {
            $nombre = $nombre_raw;
        }

        /* Datos */
        $data = [
            'nombre'            => $nombre,
            'inicio_alta'       => sanitize_text_field($_POST['inicio_alta']       ?? ''),
            'fin_alta'          => sanitize_text_field($_POST['fin_alta']          ?? ''),
            'inicio_renovacion' => sanitize_text_field($_POST['inicio_renovacion'] ?? ''),
            'fin_renovacion'    => sanitize_text_field($_POST['fin_renovacion']    ?? ''),
            'activa'            => isset($_POST['activa']) ? 1 : 0,
        ];
        $format = ['%s','%s','%s','%s','%s','%d'];

        /* Si se activa ⇒ desactivar las demás */
        if ( $data['activa'] )  $wpdb->update( $tbl, ['activa'=>0], ['activa'=>1], ['%d'], ['%d'] );

        /* Insert / Update */
        if ( $id )
            $wpdb->update( $tbl, $data, ['id'=>$id], $format, ['%d'] );
        else
            $wpdb->insert( $tbl, $data, $format );

        wp_redirect( admin_url('admin.php?page=baxi-temporadas&guardado=1') );
        exit;
    }

    /**
     * Renderiza la pantalla de gestión de temporadas
     */
    public function vista_temporadas() {
        global $wpdb;
        $tabla      = "{$wpdb->prefix}baxi_temporadas";
        $temporadas = $wpdb->get_results( "SELECT * FROM {$tabla} ORDER BY activa DESC, id DESC" );
        $edita      = isset( $_GET['id'] ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE id=%d", intval($_GET['id']) ) ) : null;
        ?>
        <div class="wrap">
            <h1>Gestión de Temporadas</h1>

            <?php if ( isset( $_GET['guardado'] ) ): ?>
                <div class="notice notice-success is-dismissible"><p>✅ Temporada guardada correctamente.</p></div>
            <?php endif; ?>

            <?php if ( isset( $_GET['activada'] ) ): ?>
                <div class="notice notice-success is-dismissible"><p>✅ Nueva temporada activada.</p></div>
            <?php endif; ?>

            <h2><?php echo $edita ? 'Editar Temporada' : 'Crear nueva temporada'; ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <input type="hidden" name="action" value="guardar_temporada">
                <?php wp_nonce_field( 'baxi_guardar_temporada', 'baxi_guardar_temporada_nonce' ); ?>
                <?php if ( $edita ): ?>
                    <input type="hidden" name="temporada_id" value="<?php echo esc_attr( $edita->id ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th><label for="nombre">Nombre</label></th>
                        <td>
                            <input name="nombre" type="text" id="nombre" class="regular-text"
                                   value="<?= esc_attr($edita->nombre ?? ''); ?>"
                                   placeholder="Temporada 2025/2026"
                                   pattern="^Temporada\s\d{4}/\d{4}$"
                                   title="Formato requerido: Temporada 2025/2026"
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inicio_alta">Inicio Alta</label></th>
                        <td>
                            <input name="inicio_alta" type="date" id="inicio_alta"
                                   value="<?php echo esc_attr( $edita->inicio_alta ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fin_alta">Fin Alta</label></th>
                        <td>
                            <input name="fin_alta" type="date" id="fin_alta"
                                   value="<?php echo esc_attr( $edita->fin_alta ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="inicio_renovacion">Inicio Renovación</label></th>
                        <td>
                            <input name="inicio_renovacion" type="date" id="inicio_renovacion"
                                   value="<?php echo esc_attr( $edita->inicio_renovacion ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fin_renovacion">Fin Renovación</label></th>
                        <td>
                            <input name="fin_renovacion" type="date" id="fin_renovacion"
                                   value="<?php echo esc_attr( $edita->fin_renovacion ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="activa">Temporada activa</label></th>
                        <td>
                            <input name="activa" type="checkbox" id="activa" value="1"
                                <?php checked( $edita->activa ?? 0, 1 ); ?>>
                        </td>
                    </tr>
                </table>

                <?php submit_button( $edita ? 'Actualizar Temporada' : 'Guardar Temporada' ); ?>
            </form>

            <?php if ( $temporadas ): ?>
                <hr>
                <h2>Temporadas registradas</h2>
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
                            <td><?php echo esc_html( $temp->inicio_alta ) . ' → ' . esc_html( $temp->fin_alta ); ?></td>
                            <td><?php echo esc_html( $temp->inicio_renovacion ) . ' → ' . esc_html( $temp->fin_renovacion ); ?></td>
                            <td><?php echo $temp->activa
                                ? '<strong style="color:green;">Activa</strong>'
                                : 'Inactiva'; ?></td>

                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

new Baxi_Temporadas();
