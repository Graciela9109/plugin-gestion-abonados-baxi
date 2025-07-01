document.addEventListener('DOMContentLoaded', () => {
  const resultDiv = document.getElementById("qr-result");
  const feedbackDiv = document.getElementById("baxi-feedback");
  const okSound = document.getElementById("sound-ok");
  const errorSound = document.getElementById("sound-error");
  const manualBtn = document.getElementById("manual-btn");
  const manualInput = document.getElementById("manual-code");
  let scannerActive = false;

  const qr = new Html5Qrcode("qr-reader");

  function mostrarResultado(ok, mensaje) {
    if (ok) {
      feedbackDiv.innerHTML = `<span style="color:green;">✅ ${mensaje}</span>`;
      okSound.play();
    } else {
      feedbackDiv.innerHTML = `<span style="color:red;">❌ ${mensaje}</span>`;
      errorSound.play();
    }
  }

  function validarCodigo(codigo, reiniciarQR = false) {
    feedbackDiv.innerHTML = `<span style="font-size:28px;color:#666;">Verificando…</span>`;
	const VALIDACION_URL = window.location.origin + '/baxi/baxi/validacion.php';
	fetch(`${VALIDACION_URL}?qr=${encodeURIComponent(codigo)}`)

      .then(r => r.json())
      .then(r => {
        const mensaje = r.message || r.data || 'Respuesta desconocida';
        mostrarResultado(r.success, mensaje);
        if (reiniciarQR) setTimeout(iniciarEscaner, 3000);
      })
      .catch(() => {
        mostrarResultado(false, 'Error de conexión');
        if (reiniciarQR) setTimeout(iniciarEscaner, 3000);
      });
  }

  function iniciarEscaner() {
    if (scannerActive) return;
    scannerActive = true;
    qr.start(
      { facingMode: "environment" },
      { fps: 10, qrbox: 250 },
      qrCodeMessage => {
        scannerActive = false;
        qr.stop();
        resultDiv.innerHTML = `<p><strong>🔍 Código leído:</strong><br>${qrCodeMessage}</p>`;
        validarCodigo(qrCodeMessage, true);
      }
    ).catch(err => {
      resultDiv.innerHTML = `<p style="color:red;">Error al iniciar cámara: ${err}</p>`;
    });
  }

  iniciarEscaner();

  manualBtn?.addEventListener('click', () => {
    const code = manualInput.value.trim();
    if (code) validarCodigo(code);
  });
});
