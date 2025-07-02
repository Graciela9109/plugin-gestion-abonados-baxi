(function($){
  'use strict';

  $(function(){

    var canvas = new fabric.Canvas('gbb-mapa-canvas',{
      selection:false,
      preserveObjectStacking:true
    });

    var zonas = GBBEventMapData.zonas || [];
    var asientos = GBBEventMapData.asientos || [];

    /* --------  dibujar zonas  -------- */
    zonas.forEach(function(z){
      var r = new fabric.Rect({
        left: z.x, top: z.y,
        width: z.width, height: z.height,
        fill: z.color || '#000',
        stroke:'#333', strokeWidth:1,
        selectable:false
      });
      r.set('submapa_id', z.submapa_id);
      canvas.add(r);
    });

    /* --------  dibujar asientos  -------- */
    asientos.forEach(function(s){
      var c = new fabric.Circle({
        left: s.x, top: s.y, radius: 6,
        fill: s.estado === 'abonado' ? 'red' : '#2196f3',
        selectable: false
      });
      canvas.add(c);
    });

    // 🔄 Escalar y centrar canvas al contenedor
    function scaleAndCenterCanvas() {
      const parent = $('#gbb-mapa-canvas').parent();
      const canvasWidth = parent.width();
      const canvasHeight = canvasWidth * 0.66;

      canvas.setWidth(canvasWidth);
      canvas.setHeight(canvasHeight);

      const objs = canvas.getObjects();
      if (!objs.length) return;

      const group = new fabric.Group(objs);
      const bbox = group.getBoundingRect();

      const scaleX = canvasWidth / bbox.width;
      const scaleY = canvasHeight / bbox.height;
      const scale = Math.min(scaleX, scaleY) * 0.9;

      canvas.setZoom(scale);

      const offsetX = (canvasWidth - bbox.width * scale) / 2;
      const offsetY = (canvasHeight - bbox.height * scale) / 2;

      canvas.absolutePan({
        x: -bbox.left * scale + offsetX,
        y: -bbox.top * scale + offsetY
      });

      canvas.renderAll();
    }

    // Inicializar escalado
	scaleAndCenterCanvas();
	$(window).on('resize', _.debounce(scaleAndCenterCanvas, 200));

  });

})(jQuery);
