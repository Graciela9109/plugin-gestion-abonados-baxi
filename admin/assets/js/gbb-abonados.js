// assets/js/gbb-abonados.js
(function($){
  function renderExtra(personas) {
    const $wr = $('#gbb-extra-members-wrapper'),
          $ct = $('#gbb-extra-members').empty();
    const extras = personas - 1;
    // Leemos la fila del titular
    const titularFila = $('#fila_0').val() || '';
    if (extras > 0) {
      $wr.show();
      for (let i = 1; i <= extras; i++) {
        const par = (i === 1) ? 'Pareja' : 'Niño(a)';
        $ct.append(`
          <fieldset style="border:1px solid #ddd;padding:10px;margin-bottom:10px;">
            <legend>${par}</legend>
            <p><label>Fila: <input type="text" name="fila[]" value="${titularFila}" required class="regular-text"></label></p>
            <p><label>Asiento: <input type="text" name="asiento[]" required class="regular-text"></label></p>
            <p><label>Email: <input type="email" name="email[]" required class="regular-text"></label></p>
            <p><label>Nombre: <input type="text"  name="nombre[]" required class="regular-text"></label></p>
            <p><label>Apellidos: <input type="text"  name="apellidos[]" required class="regular-text"></label></p>
            <p><label>Nº Socio: <input type="text" name="num_socio[]" required class="regular-text"></label></p>
            <p><label>Parentesco: <input type="text" name="parentesco[]" required class="regular-text"></label></p>
          </fieldset>
        `);
      }
    } else {
      $wr.hide();
      $ct.empty();
    }
  }

  $(function(){
    // Cuando cambie el tipo, renderizamos extras
    $('#gbb-tipo-abono').on('change', function(){
      const tipo     = $(this).val(),
            personas = window.gbbTipoAbonos[tipo] || 1;
      renderExtra(personas);
    });
    // También si cambia la fila del titular, actualizamos
    $('#fila_0').on('input change', function(){
      const tipo     = $('#gbb-tipo-abono').val(),
            personas = window.gbbTipoAbonos[tipo] || 1;
      renderExtra(personas);
    });
    // Inicializamos al cargar
    const initTipo     = $('#gbb-tipo-abono').val(),
          initPersonas = window.gbbTipoAbonos[initTipo] || 1;
    renderExtra(initPersonas);
  });
})(jQuery);
