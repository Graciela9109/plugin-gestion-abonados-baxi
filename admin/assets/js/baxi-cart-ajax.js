// BAXI CART AJAX (compatibilidad lógica antigua + nueva productos variables)
document.addEventListener('DOMContentLoaded', function () {
  // Selector para productos variables (nueva lógica)
  document.body.addEventListener('change', function (e) {
    if (e.target.classList.contains('baxi-select-variacion')) {
      const select = e.target;
      const cart_key = select.dataset.cartKey;
      const variation_id = select.value;

      fetch(baxi_tipo_entrada_ajax.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'baxi_actualizar_variacion_cart',
          cart_key: cart_key,
          variation_id: variation_id,
          nonce: baxi_tipo_entrada_ajax.nonce
        })
      })
      .then(r => r.json())
      .then(r => {
        if (r.success) {
          location.reload();
        } else {
          alert(r.data || 'Error');
        }
      });
    }

    // Lógica antigua: para productos simples con selector tipo-entrada
    if (e.target.classList.contains('baxi-tipo-entrada-select')) {
      const select = e.target;
      const cart_key = select.dataset.cartKey;
      const tipo_entrada = select.value;

      fetch(baxi_tipo_entrada_ajax.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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
          location.reload();
        } else {
          alert(r.data || 'Error');
        }
      });
    }
  });
});

/*
 * (OPCIONAL, por compatibilidad legacy con jQuery - puedes eliminar si no usas jQuery)
 */
if (window.jQuery) {
  jQuery(document).on('change', '.baxi-select-variacion', function () {
    var variation_id = jQuery(this).val();
    var key = jQuery(this).data('cart-key');
    jQuery.ajax({
      url: baxi_tipo_entrada_ajax.url,
      method: 'POST',
      data: {
        action: 'baxi_actualizar_variacion_cart',
        cart_key: key,
        variation_id: variation_id,
        nonce: baxi_tipo_entrada_ajax.nonce
      },
      success: function (res) {
        if (res.success) {
          location.reload();
        } else {
          alert(res.data || 'Error');
        }
      }
    });
  });
  jQuery(document).on('change', '.baxi-tipo-entrada-select', function () {
    var tipo = jQuery(this).val();
    var key = jQuery(this).data('cart-key');
    jQuery.ajax({
      url: baxi_tipo_entrada_ajax.url,
      method: 'POST',
      data: {
        action: 'baxi_update_tipo_entrada',
        cart_key: key,
        tipo_entrada: tipo,
        nonce: baxi_tipo_entrada_ajax.nonce
      },
      success: function (res) {
        if (res.success) {
          location.reload();
        } else {
          alert(res.data || 'Error');
        }
      }
    });
  });
}
