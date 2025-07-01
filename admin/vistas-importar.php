<?php
if (!current_user_can('manage_options')) wp_die('Acceso denegado');

if (isset($_POST['baxi_importar_abonados']) && isset($_FILES['archivo_abonados'])) {
    if ($_FILES['archivo_abonados']['error'] === UPLOAD_ERR_OK) {
        $archivo_tmp = $_FILES['archivo_abonados']['tmp_name'];
        $extension = pathinfo($_FILES['archivo_abonados']['name'], PATHINFO_EXTENSION);

        if (in_array(strtolower($extension), ['xlsx', 'csv'])) {
            require_once BAXI_PATH . 'includes/importador-abonados.php';
            $resultados = baxi_importar_abonados($archivo_tmp, $extension);
        } else {
            echo '<div class="notice notice-error"><p>Formato no soportado. Usa un archivo .xlsx o .csv</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>Error al subir el archivo.</p></div>';
    }
}
?>

<div class="wrap">
    <h1>Importar Abonados desde Excel</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="archivo_abonados" accept=".xlsx,.csv" required>
        <p class="submit">
            <input type="submit" name="baxi_importar_abonados" class="button-primary" value="Importar">
        </p>
    </form>

    <?php if (!empty($resultados)): ?>
        <hr>
        <h2>Resultado de la importación</h2>
        <p><strong>Importados:</strong> <?= intval($resultados['importados']) ?></p>
        <p><strong>Omitidos:</strong> <?= intval($resultados['omitidos']) ?></p>
        <?php if (!empty($resultados['errores'])): ?>
            <h3>Errores detectados:</h3>
            <ul>
                <?php foreach ($resultados['errores'] as $error): ?>
                    <li><?= esc_html($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>
