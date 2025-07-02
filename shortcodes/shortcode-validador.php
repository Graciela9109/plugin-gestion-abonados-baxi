<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('baxi_validador', function() {
    ob_start();
    ?>
    <div id="baxi-validador" style="max-width:600px;margin:auto;text-align:center;padding:1em">
        <h2>Validador de Entradas / Abonos</h2>

        <!-- Lector QR -->
        <div id="qr-reader" style="width:100%;max-width:400px;margin:auto;"></div>
        <div id="qr-result" style="margin-top:20px; font-size:18px;"></div>

        <!-- Validación manual -->
        <hr style="margin:2em 0">
        <h3>Validación manual</h3>
        <input type="text" id="manual-code" placeholder="Introduce código" style="width:100%;padding:0.5em;font-size:16px">
        <button id="manual-btn" class="button button-primary" style="margin-top:1em">Validar</button>

        <!-- Resultado -->
        <div id="baxi-feedback" style="font-size:48px; margin-top:20px;"></div>

        <!-- Sonidos -->
        <audio id="sound-ok" src="<?= plugins_url('admin/assets/audio/success.mp3', BAXI_PLUGIN_FILE) ?>"></audio>
        <audio id="sound-error" src="<?= plugins_url('admin/assets/audio/error.mp3', BAXI_PLUGIN_FILE) ?>"></audio>
    </div>

    <!-- Scripts -->
	<script src="<?= plugins_url('admin/assets/js/html5-qrcode.min.js', BAXI_PLUGIN_FILE); ?>"></script>
    <script src="<?= plugins_url('admin/assets/js/baxi-validador.js', BAXI_PLUGIN_FILE); ?>"></script>
    <?php
    return ob_get_clean();
});
