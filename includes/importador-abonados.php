<?php
use PhpOffice\PhpSpreadsheet\IOFactory;

function baxi_importar_abonados($archivo_tmp, $extension) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'baxi_abonados';
    $importados = 0;
    $omitidos = 0;
    $errores = [];

    require_once ABSPATH . 'wp-content/plugins/gestion-abonados-baxi/vendor/autoload.php';

    $spreadsheet = IOFactory::load($archivo_tmp);
    $hoja = $spreadsheet->getActiveSheet();
    $filas = $hoja->toArray(null, true, true, true);

    // Agrupar por num_abono
    $grupos = [];
    foreach ($filas as $i => $fila) {
        if ($i === 1) continue; // Saltar encabezado
        $num_abono = trim($fila['A']);
        if (!$num_abono) continue;
        $grupos[$num_abono][] = $fila;
    }

    foreach ($grupos as $num_abono => $grupo) {
        $titular = null;
        foreach ($grupo as $fila) {
            if (!empty($fila['E'])) { // Email presente
                $titular = $fila;
                break;
            }
        }

        if (!$titular) {
            $omitidos++;
            $errores[] = "Grupo con Nº de abono $num_abono sin titular (no tiene email)";
            continue;
        }

        $datos = preparar_abonado($titular);
        if (!$datos['email']) {
            $omitidos++;
            $errores[] = "Titular $datos[nombre] $datos[apellidos] sin email.";
            continue;
        }

        if (existe_abonado($datos)) {
            $omitidos++;
            $errores[] = "El asiento {$datos['grada']}-{$datos['fila']}-{$datos['asiento']} ya está ocupado.";
            continue;
        }

        $datos['grupo_abono'] = null;
        $datos['qr_code'] = uniqid('qr_', true);
        $wpdb->insert($tabla, $datos);
        $id_titular = $wpdb->insert_id;
        $importados++;

        // Extras
        foreach ($grupo as $fila) {
            if ($fila === $titular) continue;
            $extra = preparar_abonado($fila);
            $extra['grupo_abono'] = $id_titular;
            $extra['qr_code'] = uniqid('qr_', true);
            $wpdb->insert($tabla, $extra);
            $importados++;
        }
    }

    return [
        'importados' => $importados,
        'omitidos' => $omitidos,
        'errores' => $errores
    ];
}

function preparar_abonado($fila) {
    return [
        'num_abono'   => trim($fila['A']),
        'num_socio'   => trim($fila['B']),
        'nombre'      => trim($fila['C']),
        'apellidos'   => trim($fila['D']),
        'email'       => sanitize_email(trim($fila['E'])),
        'grada'       => trim($fila['F']),
        'fila'        => trim($fila['G']),
        'asiento'     => trim($fila['H']),
        'parentesco'  => trim($fila['I']),
        'tipo_abono'  => intval($fila['J']),
        'temporada'   => trim($fila['K'])
    ];
}

function existe_abonado($datos) {
    global $wpdb;
    $tabla = $wpdb->prefix . 'baxi_abonados';
    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tabla WHERE grada = %s AND fila = %s AND asiento = %s AND temporada = %s",
        $datos['grada'], $datos['fila'], $datos['asiento'], $datos['temporada']
    ));
    return $existe > 0;
}
