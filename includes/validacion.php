<?php
define('WP_USE_THEMES', false);
require_once dirname(__DIR__) . '/../wp-load.php';
header('Content-Type: application/json');
global $wpdb;

/* ---------- código QR recibido ---------- */
$code = trim( sanitize_text_field( $_GET['qr'] ?? '' ) );
if ( ! $code ) {
    wp_send_json_error( 'Código vacío' );
}

/* =================================================
 * ===========  ENTRADAS INDIVIDUALES  =============
 * =================================================*/
if ( strpos( $code, 'EVT:' ) === 0 ) {

    preg_match_all( '/(\w+):([^;]+)/', $code, $m, PREG_SET_ORDER );
    $data       = array_column( $m, 2, 1 );
    $evento_id  = intval( $data['EVT']      ?? 0 );
    $grada      = sanitize_text_field( $data['GRADA'] ?? '' );
    $fila       = sanitize_text_field( $data['FILA']  ?? '' );
    $asiento    = sanitize_text_field( $data['ASIENTO'] ?? '' );
    $codigo     = sanitize_text_field( $data['COD']   ?? '' );

    /* evento debe ser hoy */
    $evento = $wpdb->get_row( $wpdb->prepare(
        "SELECT id,nombre,fecha FROM {$wpdb->prefix}baxi_eventos WHERE id=%d",
        $evento_id
    ) );
	$fecha_evento = wp_date( 'Y-m-d', strtotime( $evento->fecha ) );
	$hoy          = current_time( 'Y-m-d' );

	if ( $fecha_evento !== $hoy ) {
		wp_send_json_error( 'Esta entrada no es para hoy' );
	}


    /* buscar entrada */
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT id,estado,fecha_validacion
           FROM {$wpdb->prefix}baxi_entradas
          WHERE evento_id=%d
            AND grada=%s AND fila=%s AND asiento=%s
            AND qr=%s",
        $evento_id, $grada, $fila, $asiento, $code
    ) );
    if ( ! $row )                  wp_send_json_error( 'Entrada no encontrada' );
    if ( $row->estado === 'validado' || $row->fecha_validacion )
                                   wp_send_json_error( 'Entrada ya utilizada' );

    /* marcar como validada */
    $wpdb->update(
        "{$wpdb->prefix}baxi_entradas",
        [ 'estado' => 'validado', 'fecha_validacion' => current_time( 'mysql' ) ],
        [ 'id' => $row->id ],
        [ '%s','%s' ],
        [ '%d' ]
    );

    /* log opcional */
    $wpdb->insert(
        "{$wpdb->prefix}baxi_validaciones_qr",
        [
            'entrada_id' => $row->id,
            'user_id'    => get_current_user_id(),
            'fecha'      => current_time( 'mysql' ),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? ''
        ],
        [ '%d','%d','%s','%s' ]
    );

    wp_send_json_success( "Entrada válida para el evento: {$evento->nombre}" );
}

/* =================================================
 * ===================  ABONOS  ====================
 * =================================================*/

$params = [];
$query  = parse_url( $code, PHP_URL_QUERY );
if ( $query !== null && $query !== '' ) {
    parse_str( $query, $params );
}

$abono_id  = intval( $params['id']        ?? 0 );
$temporada = sanitize_text_field( $params['temporada'] ?? '' );
if ( ! $abono_id || ! $temporada ) {
    wp_send_json_error( 'QR de abono inválido' );
}

$ab = $wpdb->get_row( $wpdb->prepare(
    "SELECT grada, fila, asiento
       FROM {$wpdb->prefix}baxi_abonados
      WHERE id=%d AND temporada=%s",
    $abono_id, $temporada
) );
if ( ! $ab ) wp_send_json_error( 'Abono no encontrado' );


$evento_id = $wpdb->get_var(
    "SELECT id FROM {$wpdb->prefix}baxi_eventos
      WHERE DATE(fecha)=CURDATE()
      ORDER BY fecha LIMIT 1"
);
if ( ! $evento_id ) wp_send_json_error( 'No hay evento hoy' );


$estado = $wpdb->get_var( $wpdb->prepare(
    "SELECT estado FROM {$wpdb->prefix}baxi_asientos_evento
      WHERE evento_id=%d AND grada=%s AND fila=%s AND asiento=%s",
    $evento_id, $ab->grada, $ab->fila, $ab->asiento
) );
if ( $estado === 'liberado' ) wp_send_json_error( 'Abono liberado para este evento' );
if ( $estado === 'ocupado'  ) wp_send_json_error( 'Este asiento ha sido comprado por otro' );


$wpdb->insert(
    "{$wpdb->prefix}baxi_validaciones_qr",
    [
        'codigo'    => $code,
        'evento_id' => $evento_id,
        'fecha'     => current_time( 'mysql' ),
        'tipo'      => 'abono',
        'resultado' => 'ok',
        'validador' => get_current_user_id()
    ],
    [ '%s','%d','%s','%s','%s','%d' ]
);

wp_send_json_success( 'Abono válido: acceso permitido' );
