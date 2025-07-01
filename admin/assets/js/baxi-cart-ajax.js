document.addEventListener('DOMContentLoaded', function() {
  document.body.addEventListener('change', function(e) {
    if (e.target.classList.contains('baxi-tipo-entrada-select')) {
      const select = e.target;
      const cart_key = select.dataset.cartKey;
      const tipo_entrada = select.value;
      fetch(baxi_tipo_entrada_ajax.url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'baxi_update_tipo_entrada',
          cart_key: cart_key,
          tipo_entrada: tipo_entrada,
          nonce: baxi_tipo_entrada_ajax.nonce
        })
      })
      .then(r => r.json())
      .then(r => {
        if (r.success) {
          // Refresca el carrito para ver nuevos precios
          location.reload();
        } else {
          alert(r.data || 'Error');
        }
      });
    }
  });
});

jQuery(document).on('change', '.baxi-tipo-entrada-select', function () {
    var tipo = jQuery(this).val();
    var key = jQuery(this).data('cart-key');

    jQuery.ajax({
        url: baxi_cart_ajax.ajaxurl,
        method: 'POST',
        data: {
            action: 'baxi_update_tipo_entrada',
            cart_key: key,
            tipo_entrada: tipo
        },
        success: function (res) {
            if (res.success) {
                // Opcional: actualizar precios visualmente
                location.reload();
            }
        }
    });
});

