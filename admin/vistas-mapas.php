<?php
if ( ! current_user_can('manage_options') ) wp_die('No autorizado');

global $wpdb;
$mapas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}baxi_mapas ORDER BY id DESC");
?>
<div class="wrap">
    <h1>Mapas del Pabellón</h1>

    <?php if ( isset($_GET['creado']) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Mapa creado correctamente.</p></div>
    <?php endif; ?>

    <h2>Listado completo de mapas actuales:</h2>

    <!-- Estilos para colapsar -->
    <style>
        tr.submapa       { display:none; }
        tr.mapa-principal{ cursor:pointer; }
    </style>

    <table id="baxi-mapas-table" class="widefat striped">
        <thead>
            <tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php foreach ( $mapas as $mapa ) : ?>
                <?php if ( ! $mapa->es_submapa ) : ?>
                    <!-- Fila MAPA PRINCIPAL -->
                    <tr class="mapa-principal" data-mapa="<?= esc_attr( $mapa->id ); ?>">
                        <td><?= $mapa->id; ?></td>
                        <td><span class="caret">▶</span> <strong><?= esc_html( $mapa->nombre ); ?></strong></td>
                        <td>
                            <a href="<?= admin_url( "admin.php?page=baxi-editar-mapa&id={$mapa->id}" ); ?>" class="button">Editar</a>
                        </td>
                    </tr>

                    <!-- Sub-mapas -->
                    <?php
                    $submapas = $wpdb->get_results(
                        $wpdb->prepare(
                          "SELECT * FROM {$wpdb->prefix}baxi_mapas WHERE zona_padre_id = %d ORDER BY id ASC",
                          $mapa->id
                        )
                    );
                    foreach ( $submapas as $sub ) : ?>
                        <tr class="submapa" data-parent="<?= esc_attr( $mapa->id ); ?>">
                            <td><?= $sub->id; ?></td>
                            <td style="padding-left:30px;">↳ <?= esc_html( $sub->nombre ); ?></td>
                            <td>
                                <a href="<?= admin_url( "admin.php?page=baxi-editar-submapa&mapa_id={$sub->id}" ); ?>" class="button">Editar Asientos</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- JS para alternar ▶ / ▼ y mostrar sub-filas -->
<script>
jQuery(function($){
    $('#baxi-mapas-table').on('click', 'tr.mapa-principal', function(){
        const id   = $(this).data('mapa');
        const hijos= $('tr.submapa[data-parent="'+id+'"]');
        hijos.toggle();

        const caret = $(this).find('.caret');
        caret.text( hijos.is(':visible') ? '▼' : '▶' );
    });
});
</script>
