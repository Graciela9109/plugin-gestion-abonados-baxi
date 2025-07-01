<?php
/**
 * Plugin Name: Gestión Integral de Abonados BAXI
 * Description: Plugin personalizado para gestión de abonados, entradas y mapas del pabellón.
 * Version:     1.0
 * Author:      Graciela – Indiga Company
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manejador para silenciar solo los avisos deprecados específicos
 * de strpos()/str_replace()/strip_tags() pasándoles null.
 */
set_error_handler( function( $errno, $errstr, $file, $line ) {
    if ( ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED)
      && (
           str_contains( $errstr, 'strpos(): Passing null' )
        || str_contains( $errstr, 'str_replace(): Passing null' )
        || str_contains( $errstr, 'strip_tags(): Passing null' )
      )
    ) {
        // Silencia estos avisos
        return true;
    }
    // Deja pasar cualquier otro error
    return false;
}, E_DEPRECATED | E_USER_DEPRECATED );

define( 'BAXI_PATH', plugin_dir_path( __FILE__ ) );
define( 'BAXI_URL',  plugin_dir_url(  __FILE__ ) );
define( 'BAXI_PLUGIN_FILE', __FILE__ );



// **Solo** cargamos el Loader
require_once BAXI_PATH . 'includes/class-baxi-loader.php';

// Shortcodes (siempre se cargan aquí)
require_once BAXI_PATH . 'shortcodes/shortcode-area-privada.php';
require_once BAXI_PATH . 'shortcodes/shortcode-mapa-general.php';
require_once BAXI_PATH . 'shortcodes/shortcode-submapa.php';
require_once BAXI_PATH . 'shortcodes/shortcode-submapa-evento.php';
require_once BAXI_PATH . 'shortcodes/shortcode-mapa-evento.php';
require_once BAXI_PATH . 'includes/baxi-woocommerce-hooks.php';
require_once __DIR__ . '/includes/functions-tipo-entrada.php';
require_once __DIR__ . '/includes/generar-entradas.php';
require_once __DIR__ . '/includes/emails-entradas.php';
require_once __DIR__ . '/librerias/dompdf/autoload.inc.php';
require_once __DIR__ . '/librerias/phpqrcode/qrlib.php';
require_once plugin_dir_path(__FILE__) . 'shortcodes/shortcode-validador.php';
require_once __DIR__ . '/admin/class-baxi-gestion-situ.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions-producto-evento.php';
require_once __DIR__ . '/includes/insertar-entradas.php';


/**
 * Al activar el plugin, limpiamos cualquier output y:
 * - Creamos tablas de Baxi_Loader::activate()
 * - Creamos tablas de mapas/zonas/asientos
 */
register_activation_hook( __FILE__, 'baxi_plugin_activate' );
function baxi_plugin_activate() {
    ob_start();
    Baxi_Loader::activate();
    baxi_crear_tablas_mapa();
    ob_end_clean();
}

/**
 * Crea o actualiza las tablas de mapas, zonas y asientos
 */
function baxi_crear_tablas_mapa() {
    global $wpdb;
    $cs = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = [];

    // Mapas
    $sql[] = "
    CREATE TABLE {$wpdb->prefix}baxi_mapas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nombre VARCHAR(255) NOT NULL,
      es_submapa BOOLEAN DEFAULT FALSE,
      zona_padre_id INT DEFAULT NULL,
      fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (zona_padre_id) REFERENCES {$wpdb->prefix}baxi_mapas(id) ON DELETE CASCADE
    ) $cs;
    ";

    // Zonas
    $sql[] = "
    CREATE TABLE {$wpdb->prefix}baxi_zonas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      mapa_id INT NOT NULL,
      nombre VARCHAR(255) NOT NULL,
      x INT NOT NULL, y INT NOT NULL,
      width INT NOT NULL, height INT NOT NULL,
      color VARCHAR(10) DEFAULT '#cccccc',
      FOREIGN KEY (mapa_id) REFERENCES {$wpdb->prefix}baxi_mapas(id) ON DELETE CASCADE
    ) $cs;
    ";

    // Asientos generales
    $sql[] = "
    CREATE TABLE {$wpdb->prefix}baxi_asientos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      mapa_id INT NOT NULL,
      grada VARCHAR(100) NOT NULL,
      fila VARCHAR(100) NOT NULL,
      asiento VARCHAR(100) NOT NULL,
      x INT NOT NULL, y INT NOT NULL,
      estado ENUM('libre','abonado','ocupado','liberado') DEFAULT 'libre',
      FOREIGN KEY (mapa_id) REFERENCES {$wpdb->prefix}baxi_mapas(id) ON DELETE CASCADE
    ) $cs;
    ";

    // Asientos por evento
    $sql[] = "
    CREATE TABLE {$wpdb->prefix}baxi_asientos_evento (
      id INT AUTO_INCREMENT PRIMARY KEY,
      evento_id INT NOT NULL,
      mapa_id INT NOT NULL,
      grada VARCHAR(100) NOT NULL,
      fila VARCHAR(100) NOT NULL,
      asiento VARCHAR(100) NOT NULL,
      estado ENUM('libre','abonado','ocupado','liberado') DEFAULT 'libre',
      x INT NOT NULL, y INT NOT NULL,
      FOREIGN KEY (evento_id) REFERENCES {$wpdb->prefix}baxi_eventos(id) ON DELETE CASCADE,
      FOREIGN KEY (mapa_id)   REFERENCES {$wpdb->prefix}baxi_mapas(id)  ON DELETE CASCADE
    ) $cs;
    ";

    foreach ( $sql as $query ) {
        dbDelta( $query );
    }
}
add_action( 'init', function() {
    if ( class_exists( 'WooCommerce' ) && WC()->session === null ) {
        WC()->initialize_session();
    }
}, 1 );

/**
 * Arrancamos el Loader de forma única.
 */
add_action( 'plugins_loaded', [ 'Baxi_Loader', 'init' ] );


/**
 * Encola el validador SOLO en páginas con el shortcode [baxi_validador]
 */
function baxi_enqueue_validador_condicional() {
    if ( is_singular() && has_shortcode( get_post()->post_content, 'baxi_validador' ) ) {
        wp_enqueue_script(
            'baxi-validador',
            plugins_url( 'admin/assets/js/baxi-validador.js', BAXI_PLUGIN_FILE ),
            [ 'jquery' ],
            null,
            true
        );
        wp_localize_script( 'baxi-validador', 'baxiQR', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'baxi_qr_nonce' )
        ] );
    }
}
add_action( 'wp_enqueue_scripts', 'baxi_enqueue_validador_condicional' );

add_action('wp_enqueue_scripts', function(){
    if (is_cart()) {
        wp_enqueue_script('baxi-cart-ajax', plugins_url('admin/assets/js/baxi-cart-ajax.js', __FILE__), [], '1.0', true);
        wp_localize_script('baxi-cart-ajax', 'baxi_tipo_entrada_ajax', [
            'url'   => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('baxi_tipo_entrada'),
        ]);
    }
});

