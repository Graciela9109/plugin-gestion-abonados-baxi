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
      var r=new fabric.Rect({
        left: z.x, top: z.y,
        width: z.width, height: z.height,
        fill: z.color||'#000',
        stroke:'#333', strokeWidth:1,
        selectable:false
      });
      r.set('submapa_id', z.submapa_id);
      canvas.add(r);
    });

    /* --------  dibujar asientos  -------- */
    asientos.forEach(function(s){
      var c=new fabric.Circle({
        left:s.x, top:s.y, radius:6,
        fill: s.estado==='abonado' ? 'red' : '#2196f3',
        selectable:false
      });
      canvas.add(c);
    });


    // 3) Responsivo
    function resizeCanvas(){
      var parent = $('#gbb-mapa-canvas').parent();
      canvas.setWidth( parent.width() );
      canvas.setHeight( parent.width() * 0.66 );
      if ( canvas.backgroundImage ){
        var bg = canvas.backgroundImage;
        bg.set({
          scaleX: canvas.width / bg.width,
          scaleY: canvas.height / bg.height
        });
      }
      canvas.renderAll();
    }

    // Inicializamos
    drawZones();
    drawasientos();
    resizeCanvas();
    $(window).on('resize', _.debounce(resizeCanvas, 200));

    // TODO: aquí puedes añadir handlers para drag/drop, guardar cambios, etc.
  });
})(jQuery);
