<?php
if (!is_user_logged_in()) return;

$current_user = wp_get_current_user();
$user_id = get_current_user_id();
global $wpdb;

$abonado = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE user_id = $user_id AND es_titular = 1 ORDER BY id DESC LIMIT 1");
if (!$abonado) {
    echo '<p>No se ha encontrado tu abono actual.</p>';
    return;
}

$temporada_actual = get_option('baxi_temporada_activa');
$renovado = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM {$wpdb->prefix}baxi_abonados 
    WHERE user_id = %d AND temporada = %s
", $user_id, $temporada_actual));

echo "<h2>Tu abono</h2>";
echo "<p><strong>Nombre:</strong> " . esc_html($abonado->nombre) . "</p>";
echo "<p><strong>Asiento:</strong> Fila " . esc_html($abonado->fila) . ", Asiento " . esc_html($abonado->asiento) . " (" . esc_html($abonado->grada) . ")</p>";
echo "<p><strong>Temporada:</strong> " . esc_html($abonado->temporada) . "</p>";

$pdf_url = plugin_dir_url(__FILE__) . "generar-pdf-abono.php?abonado_id=" . $abonado->id;
echo "<p><a href='$pdf_url' target='_blank' class='button'>Descargar abono en PDF</a></p>";

// Extras asociados
$extras = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_abonados WHERE id_titular = {$abonado->id}");

if ($extras) {
    echo "<h3>Abonos de tus acompañantes</h3><ul>";
    foreach ($extras as $extra) {
        $url_extra = plugin_dir_url(__FILE__) . "generar-pdf-abono.php?abonado_id=" . $extra->id;
        echo "<li>" . esc_html($extra->nombre) . " - <a class='button' href='$url_extra' target='_blank'>Descargar PDF</a></li>";
    }
    echo "</ul>";
}

// Mostrar estado de renovación
if ($renovado) {
    echo "<p style='color: green; font-weight: bold;'>Tu abono ya ha sido renovado para la temporada $temporada_actual.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Tu abono aún no ha sido renovado.</p>";
    echo do_shortcode('[baxi_renovar_abono]');
}
?>
