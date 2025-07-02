/* admin/assets/js/baxi-selector.js */
document.addEventListener('DOMContentLoaded', () => {

  /* ───────────────── VARIABLES GLOBALES ───────────────── */
  const data = (typeof baxiMapData !== 'undefined') ? baxiMapData : null;
  if (!data) { console.error('baxiMapData no definido'); return; }

  const zonasCanvas   = new fabric.Canvas('mapa-zonas');
  const submapaCanvas = new fabric.Canvas('mapa-submapa');

  const selectedSeats = new Set();   // ← una sola instancia
  let   buyBtn = null;               // ← se crea una sola vez

  zonasCanvas.selection   = false;
  submapaCanvas.selection = false;

  /* ─── FONDO DEL MAPA BASE ───────────────────────────── */
  if (data.zonas_bg_url) {
    fabric.Image.fromURL(data.zonas_bg_url, bg => {
      zonasCanvas.setWidth(bg.width);
      zonasCanvas.setHeight(bg.height);
      zonasCanvas.setBackgroundImage(
        bg, zonasCanvas.renderAll.bind(zonasCanvas),
        { originX:'left', originY:'top', crossOrigin:'anonymous' }
      );
    });
  }

  /* ───────────────── FUNCIÓN AJUSTAR ZOOM ───────────────── */
  function fitCanvasToObjects(canvas) {
    const objs = canvas.getObjects();
    if (!objs.length) return;
    const g = new fabric.Group(objs);
    const b = g.getBoundingRect();
    const cw = canvas.getWidth(), ch = canvas.getHeight();
    const zoom = Math.min(cw / b.width, ch / b.height) * 0.95;
    canvas.setZoom(zoom);
    canvas.absolutePan({
      x:(cw - b.width  * zoom)/6,
      y:(ch - b.height * zoom)/6
    });
  }

  /* ───────────────── DIBUJAR ZONAS ───────────────── */
  (data.zonas || []).forEach(z => {
    const r = new fabric.Rect({
      left:+z.x, top:+z.y, width:+z.width, height:+z.height,
      fill:z.color || '#ccc', stroke:'black', strokeWidth:1,
      selectable:true, evented:true,
      hasControls:false, hasBorders:false,
      lockMovementX:true, lockMovementY:true,
      lockScalingX:true, lockScalingY:true,
      lockRotation:true, excludeFromExport:true
    });
    r.set({ metadata:{ submapa_id:+z.submapa_id, nombre:z.nombre } });
    zonasCanvas.add(r);
  });
  fitCanvasToObjects(zonasCanvas);

  /* ───────────────── CLICK EN ZONA ───────────────── */
  zonasCanvas.on('mouse:up', e => {
    const zona = e.target;
    if (zona?.metadata?.submapa_id) cargarSubmapa(zona.metadata.submapa_id);
  });

  /* ───────────────── BOTÓN VOLVER ───────────────── */
  const backBtn = document.getElementById('baxi-btn-back');
  backBtn?.addEventListener('click', () => {
    document.getElementById('mapa-submapa-container').style.display = 'none';
    document.getElementById('mapa-zonas-container').style.display    = 'block';
    submapaCanvas.clear();
    selectedSeats.clear();
    if (buyBtn) buyBtn.style.display = 'none';
  });

  /* ───────────────── CARGAR SUBMAPA ───────────────── */
  function cargarSubmapa(id) {

    document.getElementById('mapa-zonas-container').style.display   = 'none';
    document.getElementById('mapa-submapa-container').style.display = 'block';
    submapaCanvas.clear();
    selectedSeats.clear();

    const url = `${data.ajaxUrl}?action=baxi_get_asientos_evento&evento_id=${data.eventoId}&submapa_id=${id}&nonce=${data.nonce}`;

    fetch(url)
      .then(r => r.json())
      .then(r => {
        if (!r.success || !Array.isArray(r.data)) return;

        r.data.forEach(a => {
          const seat = new fabric.Rect({
            left:+a.x, top:+a.y,
            width:+a.width, height:+a.height,
            fill: colorEstado(a.estado),
            stroke:'black', strokeWidth:1,
            selectable: esSeleccionable(a.estado), evented:true,
            hasControls:false, hasBorders:true,
            lockMovementX:true, lockMovementY:true,
            lockScalingX:true, lockScalingY:true,
            lockRotation:true, excludeFromExport:true
          });
          seat.set({ metadata:a });
          submapaCanvas.add(seat);
        });

        fitCanvasToObjects(submapaCanvas);

        /* ---------- BOTÓN AÑADIR (una sola vez) ---------- */
        if (!buyBtn) {
          buyBtn = document.createElement('button');
          buyBtn.id          = 'baxi-buy-seats';
          buyBtn.textContent = 'Añadir al carrito';
          buyBtn.className   = 'button button-primary';
          buyBtn.style.display = 'none';
          document.getElementById('mapa-submapa-container').prepend(buyBtn);

          buyBtn.addEventListener('click', () => {
            if (!selectedSeats.size) return;

            const ids = Array.from(selectedSeats);
            buyBtn.disabled = true;
            buyBtn.textContent = 'Añadiendo…';

            fetch(data.ajaxUrl, {
              method :'POST',
              headers :{'Content-Type':'application/x-www-form-urlencoded'},
              body    :new URLSearchParams({
                action :'baxi_add_seats_to_cart',
                nonce  : data.nonce,
                ids    : ids.join(','),
                evento : data.eventoId
              })
            })
            .then(r => r.json())
            .then(r => {
              if (!r.success) { alert(r.data||'Error'); return; }
              window.location.href = r.data;   // → carrito
            })
            .finally(() => {
              buyBtn.disabled   = false;
              buyBtn.textContent = 'Añadir al carrito';
            });
          });
        }
        buyBtn.style.display = 'none';
      })
      .catch(err => console.error('JSON error', err));
  }

  /* ─────────────── SELECCIÓN / DESELECCIÓN ────────────── */
  submapaCanvas.on('mouse:down', e => {
    const obj = e.target;
    if (!obj?.metadata) return;

    const key = String(obj.metadata.id);
    if (selectedSeats.has(key)) {
      selectedSeats.delete(key);
      obj.set({ stroke:'black', strokeWidth:1 });
    } else {
      selectedSeats.add(key);
      obj.set({ stroke:'red', strokeWidth:5 });
    }
    submapaCanvas.requestRenderAll();

    if (buyBtn) buyBtn.style.display = selectedSeats.size ? 'inline-block' : 'none';
  });

  /* ───────────────── AUXILIARES ───────────────── */
  function colorEstado(s){
    switch (s) {
      case 'libre'   : return '#238C00';
      case 'liberado': return '#2693FF';
      case 'abonado' : return '#013277';
      case 'ocupado' : return '#F00C7D';
      case 'reservado': return '#666'; 
      default        : return '#aaa';
    }
  }
  function esSeleccionable(s){ return s === 'libre' || s === 'liberado'; }

});
