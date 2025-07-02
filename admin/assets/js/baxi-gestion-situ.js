jQuery(document).ready(function($){
    $('#baxi-select-evento').on('change', function(){
        const evento = $(this).val();
        $('#baxi-listado-asientos').html('Cargando…');

        $.post(baxiInSitu.ajaxurl, {
            action: 'baxi_get_asientos_evento',
            nonce: baxiInSitu.nonce,
            evento: evento
        }, function(res){
            if (!res.success) return $('#baxi-listado-asientos').html('Error al cargar');

            const html = ['<table class="wp-list-table widefat striped"><thead><tr><th>Zona</th><th>Fila</th><th>Asiento</th><th>Estado</th><th>Acción</th></tr></thead><tbody>'];
            res.data.forEach(a => {
                const btn = (a.estado === 'ocupado')
                    ? `<button class="baxi-cambiar-estado" data-id="${a.id}" data-estado="libre">Liberar</button>`
                    : `<button class="baxi-cambiar-estado" data-id="${a.id}" data-estado="ocupado">Marcar como vendido</button>`;
                html.push(`<tr><td>${a.zona}</td><td>${a.fila}</td><td>${a.asiento}</td><td>${a.estado}</td><td>${btn}</td></tr>`);
            });
            html.push('</tbody></table>');
            $('#baxi-listado-asientos').html(html.join(''));
        });
    });

    $(document).on('click', '.baxi-cambiar-estado', function(){
        const id = $(this).data('id');
        const estado = $(this).data('estado');

        $.post(baxiInSitu.ajaxurl, {
            action: 'baxi_cambiar_estado_asiento',
            nonce: baxiInSitu.nonce,
            id: id,
            estado: estado
        }, function(res){
            if (res.success) {
                $('#baxi-select-evento').trigger('change');
            } else {
                alert('Error: ' + res.data);
            }
        });
    });
});
