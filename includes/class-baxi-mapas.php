<?php
class Baxi_Mapas {
    public function __construct() {
        add_action('admin_post_baxi_crear_mapa', [$this, 'crear_mapa']);
        add_action('admin_post_baxi_guardar_zonas', [$this, 'guardar_zonas']);
    }

    public function registrar_menu() {
        add_submenu_page(
            'baxi-inicio',
            'Mapas',
            'Mapas',
            'manage_options',
            'baxi-mapas',
            [$this, 'vista_listado_mapas']
        );

// Editores ocultos:
add_submenu_page(
    null,
    'Editor de Mapa',
    '',
    'manage_options',
    'baxi-editar-mapa',
    [ $this, 'vista_editor_mapa' ]
);
add_submenu_page(
    null,
    'Editor de Submapa',
    '',
    'manage_options',
    'baxi-editar-submapa',
    [ $this, 'vista_editor_submapa' ]
);

    }

    public function vista_listado_mapas() {
        require_once BAXI_PATH . 'admin/vistas-mapas.php';
    }

    public function crear_mapa() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        global $wpdb;

        $nombre = sanitize_text_field($_POST['nombre']);
        if (!$nombre) wp_die('Nombre requerido');

        $wpdb->insert(
            $wpdb->prefix . 'baxi_mapas',
            [
                'nombre' => $nombre,
                'es_submapa' => 0,
                'zona_padre_id' => null
            ]
        );

        wp_redirect(admin_url('admin.php?page=baxi-mapas&creado=1'));
        exit;
    }

    public function vista_editor_mapa() {
        require_once BAXI_PATH . 'admin/editor-mapa.php';
    }

    public function vista_editor_submapa() {
    	require_once BAXI_PATH . 'admin/editor-submapa.php';
    }

    public function guardar_zonas() {
    if (!current_user_can('manage_options')) wp_die('Acceso denegado');
    global $wpdb;

    $mapa_id = intval($_POST['mapa_id']);
    $zonas_json = stripslashes($_POST['zonas_json']);
    $zonas = json_decode($zonas_json, true);

    if (!$mapa_id || !is_array($zonas)) wp_die('Datos incorrectos');

    $wpdb->delete($wpdb->prefix . 'baxi_zonas', ['mapa_id' => $mapa_id]);

    foreach ($zonas as $z) {
        $wpdb->insert(
            $wpdb->prefix . 'baxi_zonas',
            [
                'mapa_id'     => $mapa_id,
                'nombre'      => sanitize_text_field($z['nombre']),
                'x'           => intval($z['x']),
                'y'           => intval($z['y']),
                'width'       => intval($z['width']),
                'height'      => intval($z['height']),
                'color'       => sanitize_hex_color($z['color']),
                'submapa_id'  => isset($z['submapa_id']) ? intval($z['submapa_id']) : null
            ]
        );
    }

    wp_redirect(admin_url('admin.php?page=baxi-editar-mapa&id=' . $mapa_id . '&guardado=1'));
    exit;
}


}

new Baxi_Mapas();

function baxi_agregar_campo_submapa_zonas() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'baxi_zonas';

    $column = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'submapa_id'");
    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table_name ADD submapa_id INT DEFAULT NULL");
    }
}
add_action('admin_init', 'baxi_agregar_campo_submapa_zonas');


// Cargar asientos por AJAX
add_action('wp_ajax_baxi_get_asientos', 'baxi_get_asientos');
function baxi_get_asientos() {
    global $wpdb;
    $mapa_id = intval($_GET['mapa_id']);
    $pref = $wpdb->prefix;

    // Reset: marcar todos los asientos de este mapa como 'libre'
    $wpdb->update(
        "{$pref}baxi_asientos",
        [ 'estado' => 'libre' ],
        [ 'mapa_id' => $mapa_id ],
        [ '%s' ],
        [ '%d' ]
    );

    // Obtenemos todos los asientos del mapa (ahora todos en 'libre')
    $asientos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, mapa_id, grada, fila, asiento, x, y
               FROM {$pref}baxi_asientos
              WHERE mapa_id = %d",
            $mapa_id
        ),
        ARRAY_A
    );

    // Averiguamos la temporada activa
    $temporada_activa = $wpdb->get_var(
        "SELECT nombre
           FROM {$pref}baxi_temporadas
          WHERE activa = 1
          LIMIT 1"
    );

    // Cargamos los abonados de esa temporada
    $abonados = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT grada, fila, asiento
               FROM {$pref}baxi_abonados
              WHERE temporada = %s",
            $temporada_activa
        ),
        ARRAY_A
    );

    // Índice rápido de ocupados
    $ocupados = [];
    foreach ($abonados as $a) {
        $ocupados["{$a['grada']}_{$a['fila']}_{$a['asiento']}"] = true;
    }

    // Asignamos estado y lo volcamos también en la BD
    foreach ($asientos as &$seat) {
        $clave = "{$seat['grada']}_{$seat['fila']}_{$seat['asiento']}";
        $nuevo_estado = isset($ocupados[$clave]) ? 'abonado' : 'libre';

        // Si el estado en memoria difiere del que acabamos de calcular, actualizamos la BD
        if ($seat['estado'] ?? '' !== $nuevo_estado) {
            $wpdb->update(
                "{$pref}baxi_asientos",
                ['estado' => $nuevo_estado],
                ['id'     => intval($seat['id'])],
                ['%s'],
                ['%d']
            );
        }

        // Reflejamos en el array para el JSON
        $seat['estado'] = $nuevo_estado;
    }

    wp_send_json($asientos);
}

// Guardar asientos por AJAX
add_action('wp_ajax_baxi_guardar_asientos', 'baxi_guardar_asientos');
function baxi_guardar_asientos() {
    global $wpdb;
    $mapa_id = intval($_POST['mapa_id']);
    $asientos = json_decode(stripslashes($_POST['asientos']), true);

    if (!$mapa_id || !is_array($asientos)) {
        wp_send_json_error('Datos inválidos');
        return;
    }

    // Validar duplicados antes de insertar
    $combinaciones = [];
    foreach ($asientos as $a) {
        $clave = strtolower(trim($a['grada'])) . '_' . strtolower(trim($a['fila'])) . '_' . strtolower(trim($a['asiento']));
        if (in_array($clave, $combinaciones)) {
            wp_send_json_error("Asiento duplicado detectado: {$a['grada']} - {$a['fila']} - {$a['asiento']}");
            return;
        }
        $combinaciones[] = $clave;
    }

    // Borrar anteriores
    $wpdb->delete("{$wpdb->prefix}baxi_asientos", ['mapa_id' => $mapa_id]);

    // Insertar nuevos
    foreach ($asientos as $a) {
        $wpdb->insert("{$wpdb->prefix}baxi_asientos", [
            'mapa_id' => $mapa_id,
            'grada' => sanitize_text_field($a['grada']),
            'fila' => sanitize_text_field($a['fila']),
            'asiento' => sanitize_text_field($a['asiento']),
            'x' => intval($a['x']),
            'y' => intval($a['y']),
            'estado' => sanitize_text_field($a['estado'])
        ]);
    }

    wp_send_json_success();
}

// AJAX: Crear submapa automáticamente desde el editor
add_action('wp_ajax_baxi_crear_submapa', function() {
    global $wpdb;

    $nombre = sanitize_text_field($_POST['nombre']);
    $padre_id = intval($_POST['padre_id']); // <- nuevo

    $wpdb->insert("{$wpdb->prefix}baxi_mapas", [
        'nombre' => $nombre,
        'es_submapa' => 1,
        'zona_padre_id' => $padre_id
    ]);

    $submapa_id = $wpdb->insert_id;

    wp_send_json_success(['submapa_id' => $submapa_id]);
});

