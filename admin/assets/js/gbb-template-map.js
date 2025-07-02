jQuery(function($){
  if ( typeof fabric === 'undefined' || typeof GBBTemplateMapData === 'undefined' ){
    console.warn('Falta Fabric.js o GBBTemplateMapData');
    return;
  }

  var canvas      = new fabric.Canvas('gbb-template-canvas');
  var mapaActual  = parseInt( $('#mapa_id').val(), 10 ) || null;

  function drawZones(){
    canvas.clear();
    GBBTemplateMapData.zonas.forEach(function(z){
      if ( parseInt(z.mapa_id,10) !== mapaActual ) return;
      var rect = new fabric.Rect({
        left:       z.x,
        top:        z.y,
        width:      z.width,
        height:     z.height,
        fill:       z.color,
        selectable: true
      });
      rect.set({ zona_id: z.id, submapa_id: z.submapa_id });
      canvas.add(rect);
    });
    canvas.renderAll();
  }

  function loadAndDrawAsientos(){
    if ( ! mapaActual ) return;
    $.getJSON( GBBTemplateMapData.ajaxUrl, {
      action:      'baxi_get_asientos',
      mapa_id:     mapaActual,
      _ajax_nonce: GBBTemplateMapData.nonce
    }).done(function(asientos){
      canvas.getObjects('circle').forEach(c=>canvas.remove(c));
      asientos.forEach(function(a){
        var circle = new fabric.Circle({
          left: a.x, top: a.y, radius: 5,
          fill: a.estado==='abonado' ? 'red' : 'blue',
          selectable: false
        });
        circle.set('asiento_id', a.id);
        canvas.add(circle);
      });
      canvas.renderAll();
    });
  }

  // Al iniciar
  drawZones();
  loadAndDrawAsientos();

  // Doble clic para ir a submapa
  canvas.on('mouse:down', function(opt){
    var t = opt.target;
    if ( t && t.submapa_id ){
      mapaActual = t.submapa_id;
      drawZones();
      loadAndDrawAsientos();
    }
  });
});
