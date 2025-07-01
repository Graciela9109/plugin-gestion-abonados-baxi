<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Baxi_Loader {

	public static function init() {

		require_once BAXI_PATH . 'includes/class-baxi-admin.php';
		require_once BAXI_PATH . 'includes/class-baxi-ajustes.php';
		require_once BAXI_PATH . 'includes/class-baxi-abonados.php';
		require_once BAXI_PATH . 'includes/class-baxi-eventos.php';
		require_once BAXI_PATH . 'includes/class-baxi-mapas.php';
		require_once BAXI_PATH . 'includes/class-baxi-temporadas.php';
		require_once BAXI_PATH . 'admin/class-baxi-gestion-situ.php';

		// Hooks para la generación de PDF (handler estático)
		add_action( 'admin_post_nopriv_baxi_generar_pdf_abono', [ 'Baxi_Abonados', 'handler_generar_pdf' ] );
		add_action( 'admin_post_baxi_generar_pdf_abono',        [ 'Baxi_Abonados', 'handler_generar_pdf' ] );

		// Instanciamos siempre Baxi_Abonados (admin_post guardar/eliminar)
		new Baxi_Abonados();

		// En admin, instanciamos la lógica que tiene menú y AJAX de asientos
		if ( is_admin() ) {
			$eventos = new Baxi_Eventos();
			$mapas   = new Baxi_Mapas();
			$admin   = new Baxi_Admin();
			$gestion_in_situ = new BAXI_Gestion_Situ();

			// Menús
			add_action( 'admin_menu', [ $admin, 'registrar_menus' ] );

			// AJAX para liberar/revertir asientos (sobre la instancia)
			add_action( 'wp_ajax_baxi_liberar_asiento', [ $admin, 'ajax_liberar_asiento' ] );
			add_action( 'wp_ajax_baxi_revert_asiento',  [ $admin, 'ajax_revert_asiento' ] );
		}
	}

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Crear tabla de eventos
        $sql_evt = "
            CREATE TABLE IF NOT EXISTS {$wpdb->prefix}baxi_eventos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(255),
                fecha DATETIME,
                mapa_id INT,
                producto_id INT
            ) $charset_collate;
        ";
        dbDelta( $sql_evt );

        // Tablas de mapas/zonas/asientos
        if ( function_exists( 'baxi_crear_tablas_mapa' ) ) {
            ob_start();
            baxi_crear_tablas_mapa();
            ob_end_clean();
        }
    }
}

// Enganchar init y activate
add_action( 'plugins_loaded',       [ 'Baxi_Loader', 'init' ] );
register_activation_hook( __FILE__, [ 'Baxi_Loader', 'activate' ] );
