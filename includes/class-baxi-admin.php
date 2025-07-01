<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Baxi_Admin {
    private $abonados;
    private $eventos;
    private $mapas;
    private $ajustes;
    private $temporadas;

    public function __construct() {

        $this->abonados    = new Baxi_Abonados();
        $this->eventos     = new Baxi_Eventos();
        $this->mapas       = new Baxi_Mapas();
        $this->ajustes     = new Baxi_Ajustes();
        $this->temporadas  = new Baxi_Temporadas();

        // AJAX: liberar/revertir asiento
        add_action( 'wp_ajax_baxi_liberar_asiento', [ $this, 'ajax_liberar_asiento' ] );
        add_action( 'wp_ajax_baxi_revert_asiento',  [ $this, 'ajax_revert_asiento' ] );
		add_action( 'admin_head', [ $this, 'admin_custom_styles' ] );
    }

	 
	public function admin_custom_styles() {

		?>
		<style>
      /* Seleccionamos únicamente el toplevel page de BAXI */
      #adminmenu li#toplevel_page_baxi-inicio > a {
        background-color: #F00C7D !important;
        color: #ffffff !important;
      }
      /* Al hacer hover sobre el toplevel */
      #adminmenu li#toplevel_page_baxi-inicio:hover > a {
        background-color: #F00C7D !important;
      }
      /* Cuando está abierto/activo */
      #adminmenu li#toplevel_page_baxi-inicio.wp-has-current-submenu > a,
      #adminmenu li#toplevel_page_baxi-inicio.wp-menu-open > a {
        background-color: #F00C7D !important;
        color: #ffffff !important;
      }
      /* Fondo de su submenú */
      #adminmenu li#toplevel_page_baxi-inicio .wp-submenu {
        background-color: #013277 !important;
      }
      /* Links del submenú */
      #adminmenu li#toplevel_page_baxi-inicio .wp-submenu a {
        color: #ffffff !important;
      }
      /* Hover en submenú */
      #adminmenu li#toplevel_page_baxi-inicio .wp-submenu a:hover {
        background-color: #F00C7D !important;
      }
	  
	  /* Submenú: ítem activo (li.current > a) */
	  #adminmenu li#toplevel_page_baxi-inicio .wp-submenu li.current > a,
	  #adminmenu li#toplevel_page_baxi-inicio .wp-submenu a.current {
	  background-color: #F00C7D !important;
	  color: #ffffff !important;
	  }

    </style>
		<?php
	}

    public function registrar_menus() {
        // Menú principal
        add_menu_page(
            'Gestión BAXI',
            'Gestión BAXI',
            'manage_options',
            'baxi-inicio',
            [ $this, 'vista_inicio' ],
            'dashicons-tickets-alt',
            30
        );

        // Submenús
		add_submenu_page(
			'baxi-inicio',                           
			'Manual APP Baxi',                                
			'Manual APP Baxi',                                
			'manage_options',                        
			'baxi-inicio',                           
			[ $this, 'vista_inicio' ]                
		);
		add_submenu_page(
			'baxi-inicio',
			'Abonados',
			'Abonados',
			'manage_options',
			'baxi-abonados', 
			[ $this->abonados, 'vista_abonados' ]
		);	
	    add_submenu_page(
            'baxi-inicio',
            'Mapas',
            'Mapas',
            'manage_options',
            'baxi-mapas',
            [ $this->mapas, 'vista_listado_mapas' ]
        );

		// Editor de Mapa (oculto, pero con capability)
		add_submenu_page(
			'baxi-inicio',       
			'',    
			'',                  
			'manage_options',    
			'baxi-editar-mapa',  
			[ $this->mapas,      
			  'vista_editor_mapa'
			]
		);

		// Editor de Submapa (oculto)
		add_submenu_page(
			'baxi-inicio',
			'',
			'',
			'manage_options',
			'baxi-editar-submapa',
			[ $this->mapas,
			  'vista_editor_submapa'
			]
		);

        add_submenu_page(
            'baxi-inicio',
            'Eventos',
            'Eventos',
            'manage_options',
            'baxi-eventos',
            [ $this->eventos, 'vista_eventos' ]
        );
	    add_submenu_page(
            'baxi-inicio',
            'Temporadas',
            'Temporadas',
            'manage_options',
            'baxi-temporadas',
            [ $this->temporadas, 'vista_temporadas' ]
        );
        add_submenu_page(
            'baxi-inicio',
            'Estadísticas',
            'Estadísticas',
            'manage_options',
            'baxi-estadisticas',
            [ $this, 'vista_estadisticas' ]
        );

        add_submenu_page(
            'baxi-inicio',
            'Ajustes',
            'Ajustes',
            'manage_options',
            'baxi-ajustes',
            [ $this, 'mostrar_ajustes' ]
        );
    }


    public function vista_inicio() {
        echo '<div class="wrap">';
        require BAXI_PATH . 'admin/vista-inicio.php';
        echo '</div>';
    }


    public function mostrar_ajustes() {
        $this->ajustes->vista_ajustes();
    }


    public function vista_estadisticas() {
        global $wpdb;
        $p = $wpdb->prefix;

        $eventos = $wpdb->get_results(
            "
            SELECT
              e.id,
              e.nombre,
              t.nombre    AS temporada,
			  e.fecha AS fecha_evento,
              SUM(a.estado = 'abonado')   AS num_abonados,
              SUM(a.estado = 'ocupado')    AS vendidos,
              SUM(a.estado = 'liberado')   AS liberados,
              COUNT(a.id)                  AS total
            FROM {$p}baxi_eventos e
            LEFT JOIN {$p}baxi_temporadas t
              ON e.temporada_id = t.id
            LEFT JOIN {$p}baxi_asientos_evento a
              ON a.evento_id = e.id
            GROUP BY e.id
            ORDER BY e.fecha DESC
            "
        );

        echo '<div class="wrap"><h1>📊 Estadísticas de Ventas</h1>';
        echo '<table class="widefat fixed striped"><thead><tr>
                <th>Evento</th>
				<th>Fecha</th>
                <th>Temporada</th>
                <th>NºAbonados</th>
                <th>Entradas vendidas</th>
                <th>Asientos Liberados</th>
                <th>Total</th>
                <th>% Venta</th>
              </tr></thead><tbody>';

        foreach ( $eventos as $ev ) {
            $pct = $ev->total
                 ? round(100 * $ev->vendidos / $ev->total, 1)
                 : 0;

            echo '<tr>'
               . '<td>' . esc_html( $ev->nombre       ) . '</td>'
			   . '<td>' . esc_html( date_i18n( 'd/m/Y', strtotime( $ev->fecha_evento ) ) ) . '</td>'
               . '<td>' . esc_html( $ev->temporada    ) . '</td>'
               . '<td>' . intval    ( $ev->num_abonados ) . '</td>'
               . '<td>' . intval    ( $ev->vendidos     ) . '</td>'
               . '<td>' . intval    ( $ev->liberados    ) . '</td>'
               . '<td>' . intval    ( $ev->total        ) . '</td>'
               . '<td>' . esc_html( $pct . '%'         ) . '</td>'
               . '</tr>';
        }

        echo '</tbody></table></div>';
    }


    public function ajax_liberar_asiento() {
        check_ajax_referer( 'baxi_liberar_asiento', '_ajax_nonce' );

        $evento  = intval( $_POST['evento_id'] ?? 0 );
        $grada   = sanitize_text_field( $_POST['grada']   ?? '' );
        $fila    = sanitize_text_field( $_POST['fila']    ?? '' );
        $asiento = sanitize_text_field( $_POST['asiento'] ?? '' );
        if ( ! $evento || ! $grada || ! $fila || ! $asiento ) {
            wp_send_json_error( 'Parámetros inválidos.' );
        }

        global $wpdb;
        $tbl = "{$wpdb->prefix}baxi_asientos_evento";

        $estado = $wpdb->get_var( $wpdb->prepare(
            "SELECT estado FROM {$tbl} 
             WHERE evento_id=%d AND grada=%s AND fila=%s AND asiento=%s",
            $evento, $grada, $fila, $asiento
        ) );
        if ( 'abonado' !== $estado ) {
            wp_send_json_error( 'Solo un asiento con estado “abonado” puede liberarse.' );
        }

        $ok = $wpdb->update(
            $tbl,
            [ 'estado' => 'liberado' ],
            [ 'evento_id'=>$evento,'grada'=>$grada,'fila'=>$fila,'asiento'=>$asiento ],
            [ '%s' ],
            [ '%d','%s','%s','%s' ]
        );
        if ( false === $ok ) {
            wp_send_json_error( 'Error al liberar el asiento.' );
        }
        wp_send_json_success();
    }


    public function ajax_revert_asiento() {
        check_ajax_referer( 'baxi_liberar_asiento', '_ajax_nonce' );

        $evento  = intval( $_POST['evento_id'] ?? 0 );
        $grada   = sanitize_text_field( $_POST['grada']   ?? '' );
        $fila    = sanitize_text_field( $_POST['fila']    ?? '' );
        $asiento = sanitize_text_field( $_POST['asiento'] ?? '' );
        if ( ! $evento || ! $grada || ! $fila || ! $asiento ) {
            wp_send_json_error( 'Parámetros inválidos.' );
        }

        global $wpdb;
        $tbl = "{$wpdb->prefix}baxi_asientos_evento";

        $estado = $wpdb->get_var( $wpdb->prepare(
            "SELECT estado FROM {$tbl} 
             WHERE evento_id=%d AND grada=%s AND fila=%s AND asiento=%s",
            $evento, $grada, $fila, $asiento
        ) );
        if ( 'liberado' !== $estado ) {
            wp_send_json_error( 'Solo un asiento con estado “liberado” puede revertirse.' );
        }

        $ok = $wpdb->update(
            $tbl,
            [ 'estado' => 'abonado' ],
            [ 'evento_id'=>$evento,'grada'=>$grada,'fila'=>$fila,'asiento'=>$asiento ],
            [ '%s' ],
            [ '%d','%s','%s','%s' ]
        );
        if ( false === $ok ) {
            wp_send_json_error( 'Error al revertir el asiento.' );
        }
        wp_send_json_success();
    }
}
