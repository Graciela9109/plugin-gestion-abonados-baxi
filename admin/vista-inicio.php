<div class="wrap">
    <h1>Gestión Integral BAXI</h1>
    <div style="max-width: 300px; margin-bottom: 20px;">
        <img src="https://indigacompany.com/baxi/wp-content/uploads/2025/06/logotipo-baxi.png" alt="Logotipo BAXI" style="width: 100%;">
    </div>

    <div class="wrap baxi-manual-app">
  <h1>📘 Manual de Uso – APP BAXI</h1>
  <p>Esta guía te acompañará por cada apartado de la Gestión BAXI, indicándote cómo acceder, qué parámetros configurar y qué resultados obtendrás.</p>

  <!-- Inicio -->
  <details open>
    <summary><strong>1. Inicio</strong></summary>
    <ol>
      <li>En el menú principal verás “Gestión BAXI” con el icono rosa.</li>
      <li>Haz clic en él para acceder al <em>Dashboard</em> (página de bienvenida).</li>
      <li>Desde aquí tienes un resumen de cómo utilizar el plugin personalizado.</li>
      <li>Utilidad: punto de partida para navegar al resto de módulos.</li>
    </ol>
  </details>

  <!-- Abonados -->
  <details>
    <summary><strong>2. Abonados</strong></summary>
    <ol>
      <li>En “Gestión BAXI” → “Abonados”.</li>
      <li>Verás la tabla con todos los abonados registrados.</li>
      <li>Botones disponibles:
        <ul>
          <li><strong>Campos abonado</strong>: abre el formulario para dar de alta un titular y sus extras.</li>
          <li><strong>Editar</strong>: modifica datos, el QR permanece fijo durante toda la temporada.</li>
          <li><strong>Eliminar</strong>: borra titular y extras, y elimina sus QR de disco.</li>
        </ul>
      </li>
      <li>Al guardar:
        <ul>
          <li>Se genera un QR con nombre <code>ID-numAbono-numSocio.png</code>.</li>
          <li>Si editas, no se regenera ya que el QR original se mantiene.</li>
        </ul>
      </li>
      <li>Utilidad: gestión centralizada de abonos, emisión y mantenimiento de QR, extras y datos de asiento.</li>
    </ol>
  </details>

  <!-- Eventos -->
  <details>
    <summary><strong>3. Eventos</strong></summary>
    <ol>
      <li>En “Gestión BAXI” → “Eventos”.</li>
      <li>Lista de eventos con:
        <ul>
          <li>ID, nombre, fecha, temporada y mapa asociado.</li>
          <li>Botón “Editar” para modificar. Al editar interpretará que es otro evento, por lo que la recomendación es crearlos bien siempre desde el principio, ya que clona el mapa base cada vez que se crea uno.</li>
        </ul>
      </li>
      <li>Formulario de creación/edición:
        <ul>
          <li><strong>Nombre</strong>: título del evento.</li>
          <li><strong>Fecha y hora</strong>: formato <code>YYYY-MM-DDThh:mm</code>. Es importante siempre añadir la hora del evento al crearlo.</li>
          <li><strong>Temporada</strong>: seleccionar temporada activa o futura (preactivada).</li>
          <li><strong>Mapa base</strong>: selecciona el plano de asiento (preactivado).</li>
          <li><strong>Productos</strong>: items de WooCommerce asociados.</li>
        </ul>
      </li>
      <li>Al guardar:
        <ul>
          <li>Se clona el <em>mapa general</em> y sus submapas (zonas).</li>
          <li>Se clonan también todos los asientos (tabla <code>baxi_asientos_evento</code>) con estado inicial:
            <ul>
              <li><code>abonado</code> si ya existen abonados asignados.</li>
              <li><code>libre</code> en otro caso.</li>
            </ul>
          </li>
        </ul>
      </li>
      <li>Utilidad: preparar cada evento con su propio plano, preservar históricos y poder liberar/revertir asientos.</li>
    </ol>
  </details>

  <!-- Mapas -->
  <details>
    <summary><strong>4. Mapas</strong></summary>
    <ol>
      <li>En “Gestión BAXI” → “Mapas”.</li>
      <li>Se muestra el listado de mapas generales.</li>
      <li>Botones:
        <ul>
          <li><strong>Nuevo Mapa</strong>: da de alta un nuevo plano vacío.</li>
          <li><strong>Editar</strong>: abre el editor gráfico (he utilizado Fabric.js).</li>
          <li><strong>Eliminar</strong>: borra mapa, zonas y asientos asociados.</li>
        </ul>
      </li>
      <li>Editor de Mapa:
        <ul>
          <li>Dibuja zonas como rectángulos, mueve/reescala y define <code>nombre</code>, <code>color</code>.</li>
          <li>Al guardar se regraba la tabla <code>baxi_zonas</code>.</li>
        </ul>
      </li>
      <li>Editor de Submapa (por zona):
        <ul>
          <li>Genera asientos manual o por bloque (grada, fila, nº asientos, separaciones).</li>
          <li>Define metadatos: <code>grada</code>, <code>fila</code>, <code>asiento</code>.</li>
          <li>Al guardar, tabla <code>baxi_asientos</code>.</li>
        </ul>
      </li>
      <li>Utilidad: crear y mantener planos detallados para luego clonar en eventos.</li>
    </ol>
  </details>

  <!-- Temporadas -->
  <details>
    <summary><strong>5. Temporadas</strong></summary>
    <ol>
      <li>En “Gestión BAXI” → “Temporadas”.</li>
      <li>Administra años deportivos o ciclos (p.ej. “2025/2026”).</li>
      <li>Marca una como <em>activa</em> (solo una a la vez) para filtrar en abonos y renovaciones.</li>
    </ol>
  </details>

  <!-- Estadísticas -->
  <details>
    <summary><strong>6. Estadísticas</strong></summary>
    <ol>
      <li>En “Gestión BAXI” → “Estadísticas”.</li>
      <li>Muestra por evento:
        <ul>
          <li>Nombre, temporada, nº abonados asignados.</li>
          <li>Entradas vendidas (<code>ocupado</code>), liberadas (<code>liberado</code>), total.</li>
          <li>Porcentaje de venta.</li>
        </ul>
      </li>
      <li>Útil para medir ocupación y rendimiento de cada evento.</li>
    </ol>
  </details>

  <!-- Ajustes -->
  <details>
    <summary><strong>7. Ajustes</strong></summary>
    <ol>
      <li>En “Gestión BAXI” → “Ajustes”.</li>
      <li>Define parámetros globales:
        <ul>
          <li>Creación de tipos de abono</li>
        </ul>
      </li>
      <li>Influye en comportamiento de todos los módulos (abonos, renovaciones, generación de QR).</li>
    </ol>
  </details>

</div>


    <p style="margin-top: 20px;"><em>Este plugin ha sido desarrollado por Indiga Company para el Club BAXI Ferrol.</em></p>
</div>
