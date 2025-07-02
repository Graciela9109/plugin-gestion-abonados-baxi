<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once BAXI_PATH . 'librerias/phpqrcode/qrlib.php';
require_once BAXI_PATH . 'librerias/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

class Baxi_Abonados {

    public function __construct() {
        add_action( 'admin_post_baxi_guardar_abonado',  [ $this, 'guardar_abonado' ] );
        add_action( 'admin_post_baxi_eliminar_abonado', [ $this, 'eliminar_abonado' ] );
		add_action( 'admin_post_baxi_renew_abono', [ $this, 'renew_abonado' ] );
		add_action( 'admin_post_nopriv_baxi_renew_abono', [ $this, 'renew_abonado' ] );
        add_action( 'admin_post_baxi_generar_pdf_abono',        [ $this, 'handler_generar_pdf' ] );
        add_action( 'admin_post_nopriv_baxi_generar_pdf_abono', [ $this, 'handler_generar_pdf' ] );
    }

    public function registrar_menu() {
        add_submenu_page(
            'baxi-ajustes',
            'Abonados',
            'Abonados',
            'manage_options',
            'baxi-abonados',
            [ $this, 'vista_abonados' ]
        );
    }

    public function vista_abonados() {
        require_once BAXI_PATH . 'admin/vistas-abonados.php';
    }


    private function marcar_asiento_abonado( $grada, $fila, $asiento ) {
        global $wpdb;
        $tabla_asientos = $wpdb->prefix . 'baxi_asientos';
        $wpdb->update(
            $tabla_asientos,
            [ 'estado' => 'abonado' ],
            [
                'grada'   => $grada,
                'fila'    => $fila,
                'asiento' => $asiento,
            ],
            [ '%s' ],
            [ '%s','%s','%s' ]
        );
    }


	public function generate_and_save_qr( $id, $num_abono, $num_socio, $temporada ) {
		global $wpdb;
		$tabla = $wpdb->prefix . 'baxi_abonados';

		$upload    = wp_upload_dir();
		$temp_slug = sanitize_title( $temporada );
		$qr_dir    = trailingslashit( $upload['basedir'] ) . "baxi_qrcodes/{$temp_slug}/";
		if ( ! file_exists( $qr_dir ) ) {
			wp_mkdir_p( $qr_dir );
		}

		$safe_name = sanitize_file_name( "{$id}-{$num_abono}-{$num_socio}.png" );
		$filepath  = $qr_dir . $safe_name;
		$qr_data   = home_url( "/validar/?id={$id}&temporada=" . urlencode( $temporada ) );
		\QRcode::png( $qr_data, $filepath, QR_ECLEVEL_L, 5, 2 );

		$qr_url = trailingslashit( $upload['baseurl'] ) . "baxi_qrcodes/{$temp_slug}/{$safe_name}";
		$wpdb->update( $tabla, [ 'qr_code' => $qr_url ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
	}


    public function guardar_abonado() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No autorizado.' );
    }
    if ( empty( $_POST['baxi_guardar_abonado_nonce'] )
      || ! wp_verify_nonce( $_POST['baxi_guardar_abonado_nonce'], 'baxi_guardar_abonado' ) ) {
        wp_die( 'Error de seguridad (nonce inválido).' );
    }

    // Campos obligatorios
    $required = [
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
    foreach ( $required as $field => $label ) {
        if ( empty( trim( $_POST[ $field ] ?? '' ) ) ) {
            wp_redirect( admin_url( "admin.php?page=baxi-abonados&error=missing_{$field}" ) );
            exit;
        }
    }

    global $wpdb;
    $tabla      = $wpdb->prefix . 'baxi_abonados';
    $tabla_base = $wpdb->prefix . 'baxi_asientos';

    // Sanitizar claves
    $abonado_id = intval( $_POST['editar_id'] ?? 0 );
    $num_abono  = sanitize_text_field( $_POST['num_abono'] );
    $num_socio  = sanitize_text_field( $_POST['num_socio'] );
    $nombre     = sanitize_text_field( $_POST['nombre'] );
    $apellidos  = sanitize_text_field( $_POST['apellidos'] );
    $email      = sanitize_email( $_POST['email'] );
    $temporada  = sanitize_text_field( $_POST['temporada'] );
    $grada      = sanitize_text_field( $_POST['grada'] );
    $fila       = sanitize_text_field( $_POST['fila'] );
    $asiento    = sanitize_text_field( $_POST['asiento'] );
    $parentesco = sanitize_text_field( $_POST['parentesco'] );
    $tipo_abono = intval( $_POST['tipo_abono'] ?? 0 );

    // Verificar que el asiento existe en el mapa base
    $existe = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tabla_base}
         WHERE grada=%s AND fila=%s AND asiento=%s",
        $grada, $fila, $asiento
    ) );
    if ( ! $existe ) {
        wp_redirect( admin_url( 'admin.php?page=baxi-abonados&error=asiento' ) );
        exit;
    }

    // Verificar email válido
    if ( ! is_email( $email ) ) {
        wp_redirect( admin_url( 'admin.php?page=baxi-abonados&error=email' ) );
        exit;
    }

    // Verificar email único (excluyendo edición)
    $count_email = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) 
		  FROM {$tabla}
		 WHERE email = %s
		   AND grupo_abono IS NULL" . ( $abonado_id ? " AND id <> %d" : '' ),
		$abonado_id
			? [ $email, $abonado_id ]
			: [ $email ]
	) );
	if ( $count_email > 0 ) {
		wp_redirect( admin_url( 'admin.php?page=baxi-abonados&error=email' ) );
		exit;
	}

    // Verificar asiento libre en esta temporada (titular o extras)
    $count_asiento = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tabla}
         WHERE temporada=%s AND grada=%s AND fila=%s AND asiento=%s" 
         . ( $abonado_id ? " AND id<>%d" : '' ),
        $abonado_id
            ? [ $temporada, $grada, $fila, $asiento, $abonado_id ]
            : [ $temporada, $grada, $fila, $asiento ]
    ) );
    if ( $count_asiento > 0 ) {
        wp_redirect( admin_url( 'admin.php?page=baxi-abonados&error=asiento' ) );
        exit;
    }

    // Conservar grupo_abono existente
    $existing_group = null;
    if ( $abonado_id ) {
        $existing_group = $wpdb->get_var(
            $wpdb->prepare( "SELECT grupo_abono FROM {$tabla} WHERE id=%d", $abonado_id )
        );
    }

    // Preparar datos comunes
    $data = [
        'num_abono'  => $num_abono,
        'num_socio'  => $num_socio,
        'nombre'     => $nombre,
        'apellidos'  => $apellidos,
        'email'      => $email,
        'temporada'  => $temporada,
        'grada'      => $grada,
        'fila'       => $fila,
        'asiento'    => $asiento,
        'parentesco' => $parentesco,
        'tipo_abono' => $tipo_abono,
        'grupo_abono'=> $existing_group,
    ];
    $format = array_fill( 0, count( $data ), '%s' );
    $format[ count( $data ) - 1 ] = '%d';

    // Insertar o actualizar
    $is_new = false;
    if ( $abonado_id ) {
        $wpdb->update( $tabla, $data, [ 'id' => $abonado_id ], $format );
    } else {
        $wpdb->insert( $tabla, $data, $format );
        $abonado_id = $wpdb->insert_id;
        $is_new     = true;
    }

    // Recuperar QR e información previa
    $old = $wpdb->get_row( $wpdb->prepare(
        "SELECT qr_code, temporada FROM {$tabla} WHERE id=%d",
        $abonado_id
    ), ARRAY_A );
    $old_qr    = $old['qr_code']   ?? '';
    $old_temp  = $old['temporada'] ?? '';

    // Determinar si regenerar QR
    $active_temp = $wpdb->get_var(
        "SELECT nombre FROM {$wpdb->prefix}baxi_temporadas WHERE activa=1 LIMIT 1"
    );
    $should_qr = $is_new || ( $old_temp !== $temporada ) || ( $abonado_id && $old_temp !== $active_temp );
    if ( $should_qr ) {
        $this->generate_and_save_qr( $abonado_id, $num_abono, $num_socio, $temporada );
    }

    // Marcar asiento como abonado
    $this->marcar_asiento_abonado( $grada, $fila, $asiento );

    // Extras (borrar e insertar)
    if ( isset( $_POST['extras'] ) && is_array( $_POST['extras'] ) ) {
        $wpdb->delete( $tabla, [ 'grupo_abono' => $abonado_id ], [ '%d' ] );
        foreach ( $_POST['extras'] as $extra ) {
            if ( empty( $extra['nombre'] ) && empty( $extra['num_socio'] ) ) {
                continue;
            }
            $e = array_map( 'sanitize_text_field', $extra );
            // Validar existencia asiento
            $cnt = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla_base}
                 WHERE grada=%s AND fila=%s AND asiento=%s",
                $grada, $fila, $e['asiento']
            ) );
            if ( ! $cnt ) {
                wp_redirect( admin_url( 'admin.php?page=baxi-abonados&error=asiento' ) );
                exit;
            }
            // Insertar extra
            $e_data = [
                'num_abono'  => $num_abono,
                'num_socio'  => $e['num_socio'],
                'nombre'     => $e['nombre'],
                'apellidos'  => $e['apellidos'],
                'email'      => $email,
                'temporada'  => $temporada,
                'grada'      => $grada,
                'fila'       => $fila,
                'asiento'    => $e['asiento'],
                'parentesco' => $e['parentesco'],
                'tipo_abono' => $tipo_abono,
                'grupo_abono'=> $abonado_id,
            ];
            $wpdb->insert( $tabla, $e_data, $format );
			$extra_id = $wpdb->insert_id;
            $this->marcar_asiento_abonado( $grada, $fila, $e['asiento'] );
            $this->generate_and_save_qr( $extra_id, $num_abono, $e['num_socio'], $temporada );
        }
    }

    // Redirigir
    wp_redirect( admin_url( 'admin.php?page=baxi-abonados&guardado=1' ) );
    exit;
}


public function renew_abonado() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('No autorizado.');
    }
    $id    = intval( $_GET['id']   ?? 0 );
    $nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
    if ( ! wp_verify_nonce( $nonce, 'baxi_renew_abono_' . $id ) ) {
        wp_die('Nonce inválido.');
    }

    global $wpdb;
    $p = $wpdb->prefix;

    // Obtenemos la temporada activa
    $nueva_temp = $wpdb->get_var(
        "SELECT nombre FROM {$p}baxi_temporadas WHERE activa=1 LIMIT 1"
    );
    if ( ! $nueva_temp ) {
        wp_die('No hay temporada activa.');
    }
	$wpdb->query("UPDATE {$p}baxi_asientos SET estado = 'libre'");

    // Cogemos IDs de titular + extras
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$p}baxi_abonados WHERE id=%d OR grupo_abono=%d",
        $id, $id
    ) );
    if ( empty( $ids ) ) {
        wp_die('Abonado no encontrado.');
    }

    // Actualizamos temporada y regeneramos QR
    $BA = new Baxi_Abonados();
    foreach ( $ids as $aid ) {
        $ok = $wpdb->update(
            "{$p}baxi_abonados",
            [ 'temporada' => $nueva_temp ],
            [ 'id'        => $aid ],
            [ '%s' ], [ '%d' ]
        );

        // Recuperamos datos para el QR
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT num_abono, num_socio FROM {$p}baxi_abonados WHERE id=%d",
            $aid
        ) );

        // Regeneramos QR
		$this->generate_and_save_qr(
			$aid,
			$row->num_abono,
			$row->num_socio,
			$nueva_temp
        );
    }

    // Redirect con mensaje de éxito o fallo
   
    wp_redirect( admin_url( 'admin.php?page=baxi-abonados&renovado=1' ) );
    exit;
}

public function eliminar_abonado() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No autorizado.' );
    }
    if ( ! isset( $_REQUEST['_wpnonce'] ) ||
         ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'baxi_eliminar_abonado' ) ) {
        wp_die( 'Nonce inválido.' );
    }
    if ( empty( $_GET['id'] ) || ! is_numeric( $_GET['id'] ) ) {
        wp_die( 'ID no válido.' );
    }
    $id = intval( $_GET['id'] );

    global $wpdb;
    $tabla_ab = $wpdb->prefix . 'baxi_abonados';
    $tabla_as = $wpdb->prefix . 'baxi_asientos';

    // Recuperar todos los asientos (titular + extras)
    $filas = $wpdb->get_results( $wpdb->prepare(
        "SELECT grada, fila, asiento
         FROM {$tabla_ab}
         WHERE id = %d OR grupo_abono = %d",
        $id, $id
    ), ARRAY_A );

    // Marcar cada asiento de vuelta a 'libre'
    foreach ( $filas as $f ) {
        if ( empty( $f['grada'] ) || empty( $f['fila'] ) || empty( $f['asiento'] ) ) {
            continue;
        }
        $wpdb->update(
            $tabla_as,
            [ 'estado' => 'libre' ],
            [
                'grada'   => $f['grada'],
                'fila'    => $f['fila'],
                'asiento' => $f['asiento'],
            ],
            [ '%s' ],
            [ '%s','%s','%s' ]
        );
    }

    // Borrar QR y ficheros (titular + extras)
    $rows   = $wpdb->get_results( $wpdb->prepare(
        "SELECT qr_code FROM {$tabla_ab} WHERE id = %d OR grupo_abono = %d",
        $id, $id
    ), ARRAY_A );
    $upload = wp_upload_dir();
    foreach ( $rows as $r ) {
        if ( ! empty( $r['qr_code'] ) ) {
            $file = str_replace(
                trailingslashit( $upload['baseurl'] ),
                trailingslashit( $upload['basedir'] ),
                $r['qr_code']
            );
            if ( is_file( $file ) ) {
                @unlink( $file );
            }
        }
    }

    // Borrar registros de extras y titular
    $wpdb->delete( $tabla_ab, [ 'grupo_abono' => $id ], [ '%d' ] );
    $wpdb->delete( $tabla_ab, [ 'id'          => $id ], [ '%d' ] );

    // Redirigir
    wp_redirect( admin_url( 'admin.php?page=baxi-abonados&eliminado=1' ) );
    exit;
}


    public function handler_generar_pdf() {
        if ( ! is_user_logged_in() || empty( $_GET['id'] ) ) {
            wp_die( 'Acceso no autorizado' );
        }

        $id = intval( $_GET['id'] );
        global $wpdb;
        $tabla = $wpdb->prefix . 'baxi_abonados';
        $a     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE id = %d", $id ) );
        if ( ! $a ) {
            wp_die( 'Abonado no encontrado' );
        }

        // Obtener QR
        $upload  = wp_upload_dir();
        $qr_file = str_replace(
            trailingslashit( $upload['baseurl'] ),
            trailingslashit( $upload['basedir'] ),
            $a->qr_code
        );
        if ( ! file_exists( $qr_file ) ) {
            wp_die( 'QR no encontrado' );
        }
        $qr_data = base64_encode( file_get_contents( $qr_file ) );
        $qr_img  = "<img src='data:image/png;base64,{$qr_data}' style='width:150px;height:150px;'>";

        $html = "
          <table width='100%' border='1' cellpadding='10' cellspacing='0'>
            <tr><td colspan='2'><strong>Abono:</strong> {$a->temporada}</td></tr>
            <tr>
              <td>
                <p><strong>Nombre:</strong> {$a->nombre} {$a->apellidos}</p>
                <p><strong>Nº Abono:</strong> {$a->num_abono}</p>
                <p><strong>Nº Socio:</strong> {$a->num_socio}</p>
                <p><strong>Grada:</strong> {$a->grada}</p>
                <p><strong>Fila:</strong> {$a->fila}</p>
                <p><strong>Asiento:</strong> {$a->asiento}</p>
              </td>
              <td align='center'>{$qr_img}</td>
            </tr>
          </table>
        ";

        $dompdf = new Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A6', 'landscape' );
        $dompdf->render();
        $dompdf->stream( "Abono-{$a->num_abono}-{$a->num_socio}.pdf", [ 'Attachment' => true ] );
        exit;
    }
}

new Baxi_Abonados();

