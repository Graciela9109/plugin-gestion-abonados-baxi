<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Baxi_Eventos {

    public function __construct() {
        add_action( 'admin_post_guardar_evento',[ $this, 'guardar_evento' ] );
        add_action( 'admin_enqueue_scripts',    [ $this, 'enqueue_event_map' ] );
		add_action( 'wp_ajax_baxi_get_asientos_evento',        [ $this, 'ajax_get_asientos_evento' ] );
		add_action( 'wp_ajax_nopriv_baxi_get_asientos_evento', [ $this, 'ajax_get_asientos_evento' ] );
	}

    /* ----------  MENÚ  ---------- */
    public function registrar_menu() {
        add_submenu_page(
            'baxi-ajustes',
            'Eventos',
            'Eventos',
            'manage_options',
            'baxi-eventos',
            [ $this, 'vista_eventos' ]
        );
    }

    public function vista_eventos() {
        require_once BAXI_PATH . 'admin/vista-eventos.php';
    }

    /* ----------  FABRIC EN EL EDITOR DE EVENTOS  ---------- */
    public function enqueue_event_map( $hook ) {
        if ( $hook !== 'admin.php'
          || empty( $_GET['page'] )
          || $_GET['page'] !== 'baxi-eventos'
          || empty( $_GET['id'] )
        ) {
            return;
        }

        wp_enqueue_script(
            'fabric',
            'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'gbb-event-map',
            BAXI_URL . 'admin/assets/js/gbb-event-map.js',
            [ 'jquery', 'underscore', 'fabric' ],
            null,
            true
        );

        global $wpdb;
        $evt_id  = intval( $_GET['id'] );
        $mapa_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT mapa_id FROM {$wpdb->prefix}baxi_eventos WHERE id=%d",
            $evt_id
        ) );

        $zonas = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}baxi_zonas WHERE mapa_id=%d", $mapa_id ),
            ARRAY_A
        );

        $asientos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, grada, fila, asiento, estado, x, y
                 FROM {$wpdb->prefix}baxi_asientos_evento
                 WHERE evento_id = %d",
                $evt_id
            ),
            ARRAY_A
        );

        wp_localize_script( 'gbb-event-map', 'GBBEventMapData', [
            'zonas' => $zonas,
            'asientos' => $asientos,
        ] );
    }

    /* ----------  CREAR / ACTUALIZAR EVENTO  ---------- */
    public function guardar_evento() {

        // Seguridad
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        if ( empty( $_POST['baxi_guardar_evento_nonce'] )
          || ! wp_verify_nonce( $_POST['baxi_guardar_evento_nonce'], 'baxi_guardar_evento' )
        ) {
            wp_die( 'Nonce inválido.' );
        }

        global $wpdb;
        $tb_evt  = "{$wpdb->prefix}baxi_eventos";
        $tb_map  = "{$wpdb->prefix}baxi_mapas";
        $tb_zon  = "{$wpdb->prefix}baxi_zonas";
        $tb_asb  = "{$wpdb->prefix}baxi_asientos";
        $tb_ase  = "{$wpdb->prefix}baxi_asientos_evento";
        $tb_prod = "{$wpdb->prefix}baxi_eventos_productos";

        // Datos del formulario
        $evento_id    = intval( $_POST['evento_id'] ?? 0 );
        $nombre       = sanitize_text_field( $_POST['nombre'] );
        $fecha        = date( 'Y-m-d H:i:s', strtotime( sanitize_text_field( $_POST['fecha'] ) ) );
        $temporada_id = intval( $_POST['temporada_id'] );
        $mapa_base_id = intval( $_POST['mapa_id'] );
        $productos    = array_map( 'intval', $_POST['productos_evento'] ?? [] );
		$producto_id  = $productos[0] ?? 0; 
        // Clonar Mapa General
        $wpdb->insert( $tb_map, [
            'nombre'        => $nombre . ' – Mapa',
            'es_submapa'    => 0,
            'zona_padre_id' => null,
        ], [ '%s','%d','%d' ] );
        $nuevo_mapa_id = $wpdb->insert_id;

        // Crear o actualizar Evento
        $data_evt = [
            'nombre'       => $nombre,
            'fecha'        => $fecha,
            'mapa_id'      => $nuevo_mapa_id,
            'temporada_id' => $temporada_id,
			'producto_id' => $producto_id,
        ]; 
		$format_evt = [ '%s','%s','%d','%d','%d' ];
		if ( $evento_id ) {
			$wpdb->update( $tb_evt, $data_evt, [ 'id' => $evento_id ], $format_evt, [ '%d' ] );
		} else {
			$wpdb->insert( $tb_evt, $data_evt, $format_evt );
			$evento_id = $wpdb->insert_id;
		}

        // Clonar Zonas y Submapas (solo una vez cada submapa)
        $submapa_mapeo = []; // [old_id => new_id]
        $zonas_base    = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$tb_zon} WHERE mapa_id=%d", $mapa_base_id )
        );

        foreach ( $zonas_base as $z ) {
            // submapa
            $nuevo_sub = null;
            if ( $z->submapa_id ) {
                if ( ! isset( $submapa_mapeo[ $z->submapa_id ] ) ) {
                    $wpdb->insert( $tb_map, [
                        'nombre'        => $z->nombre . ' – Submapa',
                        'es_submapa'    => 1,
                        'zona_padre_id' => $nuevo_mapa_id,
                    ], [ '%s','%d','%d' ] );
                    $submapa_mapeo[ $z->submapa_id ] = $wpdb->insert_id;
                }
                $nuevo_sub = $submapa_mapeo[ $z->submapa_id ];
            }
            // zona
            $wpdb->insert( $tb_zon, [
                'mapa_id'    => $nuevo_mapa_id,
                'nombre'     => $z->nombre,
                'x'          => $z->x,
                'y'          => $z->y,
                'width'      => $z->width,
                'height'     => $z->height,
                'color'      => $z->color,
                'submapa_id' => $nuevo_sub,
            ], [ '%d','%s','%d','%d','%d','%d','%s','%d' ] );
        }

        // Clonar Asientos (limpiar antes)
        $wpdb->delete( $tb_ase, [ 'evento_id' => $evento_id ], [ '%d' ] );

        $insert_asiento = function( $row, $dest_mapa_id ) use ( $wpdb, $tb_ase, $evento_id ) {
            $es_abonado = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}baxi_abonados
                 WHERE grada=%s AND fila=%s AND asiento=%s",
                $row->grada, $row->fila, $row->asiento
            ) );
            static $tiene_dim = null;
            if ( $tiene_dim === null ) {
                $cols = $wpdb->get_col( "DESC {$tb_ase}", 0 );
                $tiene_dim = in_array( 'width', $cols, true ) && in_array( 'height', $cols, true );
            }
            $data = [
                'evento_id' => $evento_id,
                'mapa_id'   => $dest_mapa_id,
                'grada'     => $row->grada,
                'fila'      => $row->fila,
                'asiento'   => $row->asiento,
                'estado'    => $es_abonado ? 'abonado' : 'libre',
                'x'         => $row->x,
                'y'         => $row->y,
            ];
            if ( $tiene_dim ) {
                $data['width']  = $row->width  ?? 30;
                $data['height'] = $row->height ?? 30;
            }
            $wpdb->insert( $tb_ase, $data );
        };

        // Asientos del mapa general
        $as_gen = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tb_asb} WHERE mapa_id=%d", $mapa_base_id
        ) );
        foreach ( $as_gen as $a ) {
            $insert_asiento( $a, $nuevo_mapa_id );
        }
        // Asientos de cada submapa
        foreach ( $submapa_mapeo as $old_id => $new_id ) {
            $as_sub = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$tb_asb} WHERE mapa_id=%d", $old_id
            ) );
            foreach ( $as_sub as $a ) {
                $insert_asiento( $a, $new_id );
            }
        }

        // Productos relacionados
		$wpdb->delete( $tb_prod, [ 'evento_id' => $evento_id ], [ '%d' ] );

		if ( $producto_id ) {
			$wpdb->insert( $tb_prod, [
				'evento_id'   => $evento_id,
				'producto_id' => $producto_id,
			], [ '%d','%d' ] );
		}

        // Redirección
        wp_redirect( admin_url( 'admin.php?page=baxi-eventos&guardado=1' ) );
        exit;
    }
	
	
			public function ajax_get_asientos_evento() {
    check_ajax_referer( 'baxi_selector', 'nonce' );

    $evento_id  = intval( $_GET['evento_id'] ?? 0 );
    $submapa_id = intval( $_GET['submapa_id'] ?? 0 );
    if ( ! $evento_id || ! $submapa_id ) {
        wp_send_json_error( 'Faltan parámetros.' );
    }

    global $wpdb;
    $tbl = "{$wpdb->prefix}baxi_asientos_evento";
    $as  = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, grada, fila, asiento, estado, x, y,
                COALESCE(width,30)  AS width,
                COALESCE(height,30) AS height
         FROM $tbl
         WHERE evento_id=%d AND mapa_id=%d",
        $evento_id, $submapa_id
    ), ARRAY_A );

    wp_send_json_success( $as );   
}
}


/**
 * AJAX: Añade uno o varios asientos al carrito WooCommerce
 * Recibe: ids (comma separated, ej: 123,124), evento (id numérico)
 */
function baxi_add_seats_to_cart() {
    if ( ! defined('DOING_AJAX') || ! DOING_AJAX ) {
        wp_send_json_error('Acceso denegado');
    }

    $ids    = isset($_POST['ids']) ? array_filter(array_map('intval', explode(',', $_POST['ids']))) : [];
    $evento = isset($_POST['evento']) ? intval($_POST['evento']) : 0;

    if ( empty($ids) || ! $evento ) {
        wp_send_json_error('Parámetros incompletos');
    }

    global $wpdb;

    $product_id = baxi_get_evento_product($evento);
    if ( ! $product_id ) {
        wp_send_json_error('Producto no encontrado para el evento');
    }

    // Comprobar estado de los asientos
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $query = $wpdb->prepare(
        "SELECT id, estado
         FROM {$wpdb->prefix}baxi_asientos_evento
         WHERE id IN ($placeholders) AND evento_id = %d",
        array_merge($ids, [ $evento ])
    );
    $asientos = $wpdb->get_results($query);

    $errores = [];
    foreach ( $asientos as $asiento ) {
        if ( $asiento->estado !== 'libre' && $asiento->estado !== 'liberado' ) {
            $errores[] = $asiento->id;
        }
    }
    if ( count($errores) ) {
        wp_send_json_error('Uno o más asientos ya no están disponibles');
    }

    // Añadir al carrito y marcar como reservado
    $añadidos = 0;
    foreach ( $ids as $sid ) {
        $ya_en_carrito = false;
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if (
                isset($cart_item['seat_id']) &&
                intval($cart_item['seat_id']) === $sid &&
                isset($cart_item['evento_id']) &&
                intval($cart_item['evento_id']) === $evento
            ) {
                $ya_en_carrito = true;
                break;
            }
        }
        if ( $ya_en_carrito ) continue;

        WC()->cart->add_to_cart(
            $product_id,
            1,
            0,
            [],
            [
				'seat_id'      => $sid,
				'evento_id'    => $evento,
				'tipo_entrada' => 'adulto',
				'grada'        => $asiento->grada,
				'fila'         => $asiento->fila,
				'num_asiento'  => $asiento->asiento,
				'evento_name'  => $evento_nombre,
            ]
        );

        $wpdb->update(
            "{$wpdb->prefix}baxi_asientos_evento",
            [
                'estado'     => 'reservado',
                'updated_at' => current_time('mysql')
            ],
            [ 'id' => $sid, 'evento_id' => $evento ],
            [ '%s', '%s' ], [ '%d', '%d' ]
        );
        $añadidos++;
    }

    if ( ! $añadidos ) {
        wp_send_json_error('No se pudo añadir ningún asiento (ya en carrito)');
    }

    // ✅ Redirección al carrito
    wp_send_json_success( wc_get_cart_url() );
}



/* =========================================================
 *  AJAX: Añadir los asientos seleccionados al carrito
 * ======================================================= */
add_action( 'wp_ajax_baxi_add_seats_to_cart',        'baxi_add_seats_to_cart' );
add_action( 'wp_ajax_nopriv_baxi_add_seats_to_cart', 'baxi_add_seats_to_cart' );

function baxi_get_evento_product( $evento_id ){
    global $wpdb;
    return $wpdb->get_var( $wpdb->prepare(
        "SELECT producto_id
         FROM {$wpdb->prefix}baxi_eventos_productos
         WHERE evento_id = %d
         LIMIT 1",
        $evento_id
    ) );
}

new Baxi_Eventos();
