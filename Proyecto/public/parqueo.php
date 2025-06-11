<?php
// Intenta conectar al servidor de base de datos
$serverAvailable = true;
try {
  require_once 'clases/Conexion.php';
  $conexion = Conexion::conectar();
  if (!$conexion) {
    $serverAvailable = false;
  }
} catch (Exception $e) {
  $serverAvailable = false;
}

// Si el servidor no está disponible, mostrar solo la pantalla de error
if (!$serverAvailable) {

?>
  <!DOCTYPE html>
  <html lang="es">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicio no disponible</title>
    <style>
      .loading-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #f5f5f5;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        font-family: Arial, sans-serif;
      }

      .spinner {
        border: 5px solid #f3f3f3;
        border-top: 5px solid #3498db;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
      }

      @keyframes spin {
        0% {
          transform: rotate(0deg);
        }

        100% {
          transform: rotate(360deg);
        }
      }

      .message {
        text-align: center;
        max-width: 80%;
        color: #333;
        margin-bottom: 20px;
      }

      .retry-button {
        padding: 10px 20px;
        background-color: #3498db;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        transition: background-color 0.3s;
      }

      .retry-button:hover {
        background-color: #2980b9;
      }
    </style>
  </head>

  <body>
    <div class="loading-screen">
      <div class="spinner"></div>
      <div class="message">
        <h2>Problemas con el servidor</h2>
        <p>Estamos trabajando para solucionarlo. Por favor, intenta recargar la página más tarde.</p>
        <p>La página se actualizará cuando el servicio se restablezca.</p>
      </div>
      <button class="retry-button" onclick="window.location.reload()">Volver a intentar</button>
    </div>
    <script>
      // Intentar reconectar cada 10 segundos
      setInterval(() => {
        fetch('check_server.php')
          .then(response => {
            if (response.ok) {
              return response.text();
            }
            throw new Error('Server error');
          })
          .then(data => {
            if (data === 'OK') {
              window.location.reload();
            }
          })
          .catch(error => {
            console.log('El servidor aún no está disponible');
          });
      }, 10000);
    </script>
  </body>

  </html>
<?php
  exit();
}

session_start();

// Bloqueo de caché para impedir acceso con botón "atrás"
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


// Verificación de sesión activa
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit();
}

// Roles posibles: 'admin', 'usuario', 'invitado'
$rolUsuario = $_SESSION['rol'] ?? 'invitado';

require_once 'clases/Conexion.php';
$conexion = Conexion::conectar();
if (!$conexion) {
  die("Error de conexión a PostgreSQL");
}

// ----------------------------------------------------------------
// 1) Procesar formularios de AGREGAR / ELIMINAR espacios (solo admin)
// ----------------------------------------------------------------
$msgAdmin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rolUsuario === 'admin') {
  // Agregar espacio
  if (isset($_POST['nuevo_espacio'])) {
    $nuevoEsp = trim($_POST['nuevo_espacio']);
    if ($nuevoEsp === '') {
      $msgAdmin = "El nombre del espacio no puede estar vacío.";
    } else {
      $sqlCheck = "SELECT id FROM espacios WHERE nombre = \$1";
      $resCheck = pg_query_params($conexion, $sqlCheck, [$nuevoEsp]);
      if ($resCheck && pg_num_rows($resCheck) > 0) {
        $msgAdmin = "El espacio '$nuevoEsp' ya existe. Elige otro.";
      } else {
        $sqlInsert = "INSERT INTO espacios (nombre) VALUES (\$1)";
        $resInsert = pg_query_params($conexion, $sqlInsert, [$nuevoEsp]);
        if (!$resInsert) {
          $msgAdmin = "Error al agregar '$nuevoEsp': " . pg_last_error($conexion);
        } else {
          $msgAdmin = "Espacio '$nuevoEsp' agregado correctamente.";
        }
      }
    }
  }
  // Eliminar espacio
  if (isset($_POST['eliminar_espacio'])) {
    $delEsp = trim($_POST['eliminar_espacio']);
    if ($delEsp !== '') {
      $sqlCheck2 = "SELECT id FROM parqueos WHERE placa = \$1 AND estado = 'activo' LIMIT 1";
      $resCheck2 = pg_query_params($conexion, $sqlCheck2, [$delEsp]);
      if ($resCheck2 && pg_num_rows($resCheck2) > 0) {
        $msgAdmin = "No se puede eliminar '$delEsp' porque tiene una reserva activa.";
      } else {
        $sqlDel = "DELETE FROM espacios WHERE nombre = \$1";
        $resDel = pg_query_params($conexion, $sqlDel, [$delEsp]);
        if (!$resDel) {
          $msgAdmin = "Error al eliminar '$delEsp': " . pg_last_error($conexion);
        } else {
          $msgAdmin = "Espacio '$delEsp' eliminado correctamente.";
        }
      }
    }
  }
}

// ----------------------------------------------------------------
// 2) Verificar si el usuario normal ya tiene una reserva activa
//    Solo aplicable a rol 'usuario'; invitados no reservan.
// ----------------------------------------------------------------
$miReservada = null;
if ($rolUsuario === 'usuario') {
  $usuario_id = (int) $_SESSION['usuario_id'];
  $sql_active = "
        SELECT placa
          FROM parqueos
         WHERE usuario_id = \$1
           AND estado = 'activo'
         LIMIT 1
    ";
  $res_active = pg_query_params($conexion, $sql_active, [$usuario_id]);
  if ($res_active === false) {
    die("Error en la consulta de reserva: " . pg_last_error($conexion));
  }
  if (pg_num_rows($res_active) > 0) {
    $fila = pg_fetch_assoc($res_active);
    $miReservada = $fila['placa'];
  }
}

// ----------------------------------------------------------------
// 3) Obtener todas las placas ocupadas (parqueos activos)
// ----------------------------------------------------------------
$ocupadas = [];
$sql_all_active = "SELECT placa FROM parqueos WHERE estado = 'activo'";
$res_all = pg_query($conexion, $sql_all_active);
if ($res_all !== false) {
  while ($f = pg_fetch_assoc($res_all)) {
    $ocupadas[] = $f['placa'];
  }
}

// ----------------------------------------------------------------
// 4) Obtener lista dinámica de espacios desde la tabla 'espacios'
//    Orden natural numérico usando SUBSTRING(nombre FROM 2) AS INTEGER
// ----------------------------------------------------------------
$espacios = [];
$sqlEsp = "
  SELECT id, nombre
    FROM espacios
   ORDER BY CAST(SUBSTRING(nombre FROM 2) AS INTEGER)
";
$resEsp = pg_query($conexion, $sqlEsp);
if ($resEsp !== false) {
  while ($e = pg_fetch_assoc($resEsp)) {
    $espacios[] = $e['nombre'];
  }
}

// ----------------------------------------------------------------
// 5) Dividir espacios en fila A y fila B (por prefijo “A” o “B”)
// ----------------------------------------------------------------
$espaciosA = [];
$espaciosB = [];
foreach ($espacios as $esp_to) {
  if (stripos($esp_to, 'A') === 0) {
    $espaciosA[] = $esp_to;
  } else {
    $espaciosB[] = $esp_to;
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Reserva de Estacionamiento</title>
  <style>
    /* ----------------------------------------
       Estilos generales
    ---------------------------------------- */
    body {
      font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
      background-color: #f5f5f5;
      margin: 0;
      padding: 20px;
    }

    .container {
      max-width: 900px;
      margin: auto;
      background-color: #fff;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      padding: 20px;
    }

    .logout {
      text-align: right;
      margin-bottom: 10px;
    }

    .logout button {
      background-color: #333;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }

    h1 {
      text-align: center;
      color: #333;
      margin-bottom: 20px;
    }

    .legend {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-bottom: 20px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .legend-color {
      width: 20px;
      height: 20px;
      border: 1px solid #333;
    }

    .legend-available {
      background-color: #e0e0e0;
    }

    .legend-reserved {
      background-color: #ff6b6b;
    }

    .info {
      background-color: #f0f0f0;
      padding: 10px 15px;
      border-radius: 6px;
      text-align: center;
      margin-bottom: 20px;
      color: #333;
    }

    .alert {
      background-color: #f8d7da;
      color: #721c24;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 20px;
      text-align: center;
    }

    .msgAdmin {
      background-color: #f8d7da;
      color: #721c24;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 20px;
      text-align: center;
    }

    .successAdmin {
      background-color: #d4edda;
      color: #155724;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 20px;
      text-align: center;
    }

    /* ----------------------------------------
       Sección ADMIN: Agregar / Eliminar espacios
    ---------------------------------------- */
    .admin-section {
      border: 2px dashed #ccc;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 6px;
      background-color: #fafafa;
    }

    .admin-section h2 {
      margin-top: 0;
      margin-bottom: 10px;
      font-size: 1.2em;
    }

    .form-inline {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 15px;
    }

    .form-inline input[type="text"] {
      padding: 6px;
      width: 150px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .form-inline button {
      background-color: #007BFF;
      color: white;
      padding: 8px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .form-inline button:hover {
      background-color: #0069d9;
    }

    /* Contenedor con scroll para la lista de espacios */
    .espacios-list-container {
      max-height: 250px;
      /* Ajusta la altura a tu gusto */
      overflow-y: auto;
      /* Scroll vertical cuando hay muchas filas */
      border: 1px solid #ccc;
      border-radius: 4px;
      margin-top: 10px;
      background-color: #fff;
    }

    .espacios-table {
      width: 100%;
      border-collapse: collapse;
    }

    .espacios-table th,
    .espacios-table td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: left;
    }

    .espacios-table th {
      background-color: #f0f0f0;
    }

    .btn-delete {
      background-color: #f44336;
      color: white;
      border: none;
      padding: 6px 10px;
      border-radius: 4px;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .btn-delete:hover {
      background-color: #d32f2f;
    }

    /* ----------------------------------------
       TABLERO DE ESPACIOS:
       - Fila A
       - DIV “ENTRADA / SALIDA”
       - Fila B
       - Orden natural numérico gracias a SUBSTRING(nombre FROM 2)
    ---------------------------------------- */

    /* Fila de espacios (A ó B) en un solo renglón, con scroll horizontal si es necesario */
    .parking-lot {
      display: flex;
      gap: 5px;
      overflow-x: auto;
      /* Aparece scroll horizontal si excede ancho */
      padding-bottom: 10px;
      /* Espacio para la barra de scroll */
      margin-bottom: 10px;
      /* Separar de la siguiente sección */
    }

    /* Estilado de scrollbar (opcional) */
    .parking-lot::-webkit-scrollbar {
      height: 8px;
    }

    .parking-lot::-webkit-scrollbar-thumb {
      background-color: rgba(0, 0, 0, 0.2);
      border-radius: 4px;
    }

    .parking-lot::-webkit-scrollbar-track {
      background-color: rgba(0, 0, 0, 0.05);
    }

    /* Cada casilla de estacionamiento */
    .space {
      flex: 0 0 80px;
      /* Ancho fijo: 80px */
      aspect-ratio: 1/1.5;
      /* Alto proporcional */
      background-color: #e0e0e0;
      border: 1px solid #ccc;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      cursor: pointer;
      user-select: none;
      border-radius: 4px;
      transition: background-color 0.2s, transform 0.1s;
    }

    .space:hover {
      background-color: #d4d4d4;
      transform: scale(1.02);
    }

    .reserved {
      background-color: #ff6b6b;
      color: white;
      cursor: not-allowed;
    }

    .selected {
      border: 2px solid #4CAF50;
      box-sizing: border-box;
      transform: none;
      /* Evitar agrandar en hover si está seleccionado */
    }

    /* DIV “calle” (driveway) entre fila A y B */
    .driveway {
      width: 100%;
      /* Ocupa todo el ancho (genera “nueva fila”) */
      background-color: #888;
      color: white;
      text-align: center;
      padding: 10px;
      margin: 10px 0;
      font-weight: bold;
      border-radius: 4px;
    }

    /* ----------------------------------------
       CONTROLES de reserva / cancelar / reiniciar
    ---------------------------------------- */
    .controls {
      display: flex;
      justify-content: space-between;
      margin-top: 15px;
    }

    button {
      padding: 10px 15px;
      border: none;
      border-radius: 4px;
      font-size: 14px;
      cursor: pointer;
      color: white;
      transition: background-color 0.2s;
    }

    .reserve-btn {
      background-color: #4CAF50;
    }

    .reserve-btn:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }

    .cancel-btn {
      background-color: #f44336;
    }

    .cancel-btn:hover {
      background-color: #d32f2f;
    }

    .reset-btn {
      background-color: #f44336;
    }

    .reset-btn:hover {
      background-color: #d32f2f;
    }

    /* =======================================================
       ESTILOS PARA VENTANA MODAL PERSONALIZADA
       ======================================================= */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    .modal-content {
      background-color: #fff;
      padding: 20px 25px;
      border-radius: 8px;
      max-width: 350px;
      width: 90%;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
      text-align: center;
    }

    .modal-content p {
      margin: 0 0 20px;
      font-size: 1rem;
      color: #333;
    }

    .modal-content span {
      font-weight: bold;
      color: #b30000;
    }

    .modal-buttons {
      display: flex;
      justify-content: center;
      gap: 15px;
    }

    .btn-confirm,
    .btn-cancel {
      padding: 8px 16px;
      border: none;
      border-radius: 4px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .btn-confirm {
      background-color: #d32f2f;
      color: white;
    }

    .btn-confirm:hover {
      background-color: #b71c1c;
    }

    .btn-cancel {
      background-color: #ccc;
      color: #333;
    }

    .btn-cancel:hover {
      background-color: #aaa;
    }

    /* Pantalla de carga cuando el servidor falla */
    .server-error-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 5, 5, 0.95);
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      font-family: Arial, sans-serif;
    }

    .server-error-overlay .spinner {
      border: 5px solid #f3f3f3;
      border-top: 5px solid #3498db;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      animation: spin 1s linear infinite;
      margin-bottom: 20px;
    }

    .server-error-overlay .message {
      text-align: center;
      max-width: 80%;
      color: #333;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    /* ======================================================= */
  </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.42.3/dist/umd/supabase.min.js"></script>
<script>
  const supabaseClient = supabase.createClient(
    'https://nyjlglghxxpaypzmffvj.supabase.co',
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im55amxnbGdoeHhwYXlwem1mZnZqIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDgxMDQ4MzAsImV4cCI6MjA2MzY4MDgzMH0.LIt2xJKgjBnkwJRd3JgqfQW-OEG1-AswshT3iPbrazc'
  );

  document.addEventListener('DOMContentLoaded', () => {
    let selected = null;
    const btnReservar = document.getElementById("reserveBtn");
    const info = document.querySelector('.info');

    if (!btnReservar) {
      console.warn('Botón "Reservar" no encontrado en el DOM');
    }

    // Obtener datos del usuario desde PHP
    const userData = {
      rol: <?= json_encode($rolUsuario, JSON_HEX_TAG) ?>,
      reserva: <?= json_encode($miReservada, JSON_HEX_TAG) ?>
    };

    function deselectCurrent() {
      if (selected) {
        selected.classList.remove('selected');
        selected = null;
        if (btnReservar) btnReservar.disabled = true;
      }
    }

    function asignarEventosEspacios() {
      const espaciosDiv = document.querySelectorAll(".space:not(.reserved)");
      espaciosDiv.forEach(divEspacio => {
        divEspacio.addEventListener("click", () => {
          document.querySelectorAll('.space').forEach(s => s.classList.remove('selected'));
          divEspacio.classList.add('selected');
          selected = divEspacio;
          if (btnReservar) btnReservar.disabled = false;
        });
      });
    }

    // Aquí ocupadas la definimos para usarla
    let ocupadas = [];

    function actualizarEspaciosUI(espaciosA, espaciosB) {
      console.log('Actualizando UI con:', espaciosA, espaciosB, ocupadas);

      const renderFila = (containerId, espacios) => {
        const container = document.getElementById(containerId);
        if (!container) {
          console.error(`Contenedor ${containerId} no encontrado`);
          return;
        }

        container.innerHTML = '';

        espacios.forEach(esp => {
          const espacioDiv = document.createElement('div');
          espacioDiv.className = 'space';
          espacioDiv.dataset.id = esp;
          espacioDiv.textContent = esp;

          // Marcar reservado si está en ocupadas o si es la reserva actual del usuario
          if (userData.reserva === esp || ocupadas.includes(esp)) {
            espacioDiv.classList.add('reserved');
          }

          container.appendChild(espacioDiv);
        });
      };

      renderFila('fila-a', espaciosA);
      renderFila('fila-b', espaciosB);

      if (userData.rol === 'usuario') {
        asignarEventosEspacios();
      }
    }

    supabaseClient
      .channel('parqueos')
      .on(
        'postgres_changes', {
          event: '*',
          schema: 'public',
          table: 'parqueos',
        },
        (payload) => {
          console.log('Cambio recibido:', payload);

          const eventType = payload.eventType || payload.event;
          let espacio;

          if (eventType === 'DELETE') {
            espacio = payload.old.placa;
          } else {
            espacio = payload.new?.placa;
          }

          if (!espacio) return;

          const espacioDiv = document.querySelector(`.space[data-id='${espacio}']`);
          if (!espacioDiv) return;

          if (eventType === 'INSERT' || (eventType === 'UPDATE' && payload.new.estado === 'activo')) {
            espacioDiv.classList.add('reserved');
            espacioDiv.classList.remove('selected');

            if (selected && selected.dataset.id === espacio) {
              if (info) info.textContent = `El espacio ${espacio} fue reservado por otro usuario.`;
              deselectCurrent();
            }
          } else if (eventType === 'DELETE' || (eventType === 'UPDATE' && payload.new.estado !== 'activo')) {
            espacioDiv.classList.remove('reserved');
          }
        }
      )
      .subscribe();

    supabaseClient
      .channel('espacios')
      .on(
        'postgres_changes', {
          event: '*',
          schema: 'public',
          table: 'espacios',
        },
        async (payload) => {
          console.log('Cambio en espacios:', payload);

          try {
            const response = await fetch('actualizar_espacios.php?_=' + Date.now());
            if (!response.ok) throw new Error('Error en la respuesta');

            const data = await response.json();
            console.log('Datos actualizados:', data);

            if (data.espaciosA && data.espaciosB && data.ocupadas) {
              ocupadas = data.ocupadas; // actualizamos el array global
              actualizarEspaciosUI(data.espaciosA, data.espaciosB);
            } else {
              console.error('Datos incompletos en la respuesta:', data);
            }
          } catch (err) {
            console.error('Error al actualizar espacios:', err);
          }
        }
      )
      .subscribe();

    if (userData.rol === 'usuario') {
      asignarEventosEspacios();

      if (btnReservar) {
        btnReservar.addEventListener("click", () => {
          if (!selected) return;

          fetch("guardar_reserva.php", {
              method: "POST",
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `espacio=${encodeURIComponent(selected.dataset.id)}`
            })
            .then(res => res.text())
            .then(txt => {
              txt = txt.trim();
              if (txt === "OK") {
                window.location.reload();
              } else if (txt === "YA_TIENE_RESERVA") {
                alert("Ya tienes una reserva activa. Primero cancélala para seleccionar otro espacio.");
              } else if (txt === "ESPACIO_OCUPADO") {
                alert("Ese espacio ya está ocupado. Elige otro.");
              } else {
                alert("Error al reservar: " + txt);
              }
            });
        });
      }
    }
  });

  //---------------------------------------------------------------------
  //SERVIDOOOR MANEJO DE ERRORES
  //-----------------------------------------------------------------------
  // Estado de conexión mejorado
  const connectionState = {
    isOnline: navigator.onLine,
    retryCount: 0,
    maxRetries: 10,
    checking: false,
    lastChecked: null,
    serverDown: false,
    lastSuccess: new Date() // <-- fecha de última conexión exitosa
  };

  // Añadir estilos para el spinner
  const spinnerStyle = document.createElement('style');
  spinnerStyle.textContent = `
@keyframes spinner-border {
  to { transform: rotate(360deg); }
}
.spinner-border {
  display: inline-block;
  width: 1rem;
  height: 1rem;
  vertical-align: text-bottom;
  border: 0.25em solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spinner-border .75s linear infinite;
}
`;
  document.head.appendChild(spinnerStyle);

  // Interceptar errores de carga de página
  window.addEventListener('error', (event) => {
    if (event.message.includes('Failed to fetch') ||
      event.message.includes('NetworkError') ||
      event.message.includes('ERR_CONNECTION_TIMED_OUT')) {

      // Calcular diferencia en minutos entre ahora y última conexión exitosa
      const now = new Date();
      const diffMinutes = (now - connectionState.lastSuccess) / 1000 / 60;

      // Solo mostrar error si han pasado 5 minutos (o más)
      if (diffMinutes >= 5) {
        event.preventDefault();
        if (!document.getElementById('fullscreen-error')) {
          showFullscreenError('connection');
        }
      } else {
        console.log(`Error detectado, pero la pantalla de error se muestra después de 5 minutos sin conexión. Tiempo actual sin conexión: ${diffMinutes.toFixed(2)} minutos.`);
      }
    }
  }, true);

  // Interceptar respuestas fetch fallidas
  const originalFetch = window.fetch;
  window.fetch = async function(...args) {
    try {
      const response = await originalFetch.apply(this, args);
      if (!response.ok) {
        throw new Error('Failed to fetch');
      }
      return response;
    } catch (error) {
      if (!document.getElementById('fullscreen-error')) {
        showFullscreenError(connectionState.serverDown ? 'server' : 'connection');
      }
      throw error;
    }
  };

  // Función para mostrar la pantalla completa de error
  function showFullscreenError(type) {
    // Ocultar cualquier contenido de la aplicación
    const appContent = document.getElementById('app-content');
    if (appContent) appContent.style.display = 'none';
    document.documentElement.style.overflow = 'hidden'; // Prevenir scroll

    // Eliminar cualquier pantalla de error existente
    hideFullscreenError();

    const errorScreen = document.createElement('div');
    errorScreen.id = 'fullscreen-error';
    errorScreen.style.position = 'fixed';
    errorScreen.style.top = '0';
    errorScreen.style.left = '0';
    errorScreen.style.width = '100vw';
    errorScreen.style.height = '100vh';
    errorScreen.style.backgroundColor = '#f8f9fa';
    errorScreen.style.display = 'flex';
    errorScreen.style.flexDirection = 'column';
    errorScreen.style.justifyContent = 'center';
    errorScreen.style.alignItems = 'center';
    errorScreen.style.zIndex = '9999';
    errorScreen.style.padding = '20px';
    errorScreen.style.textAlign = 'center';

    if (type === 'connection') {
      errorScreen.innerHTML = `
    <div style="max-width: 500px;">
      <div style="font-size: 80px; margin-bottom: 20px;">📶</div>
      <h1 style="color: #dc3545; margin-bottom: 15px;">Problemas de conexión</h1>
      <p style="margin-bottom: 25px; font-size: 18px;">No se pudo establecer conexión con internet. Por favor, verifica tu conexión de red.</p>
      <button id="retry-button" style="padding: 12px 30px; background: #dc3545; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s;">
        Reintentar conexión
      </button>
      <div id="retry-message" style="margin-top: 15px; color: #6c757d; display: none;"></div>
    </div>
  `;
    } else if (type === 'server') {
      errorScreen.innerHTML = `
    <div style="max-width: 500px;">
      <div style="font-size: 80px; margin-bottom: 20px;">⚠️</div>
      <h1 style="color: #dc3545; margin-bottom: 15px;">Servidor no disponible</h1>
      <p style="margin-bottom: 25px; font-size: 18px;">Estamos experimentando problemas con nuestro servidor. Por favor, intenta nuevamente más tarde.</p>
      <button id="retry-button" style="padding: 12px 30px; background: #dc3545; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.3s;">
        Reintentar conexión
      </button>
      <div id="retry-message" style="margin-top: 15px; color: #6c757d; display: none;"></div>
    </div>
  `;
    }

    document.body.appendChild(errorScreen);

    // Agregar evento al botón
    document.getElementById('retry-button').addEventListener('click', async function() {
      const button = this;
      const retryMessage = document.getElementById('retry-message');

      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando...';
      button.style.background = '#a0a0a0';

      retryMessage.style.display = 'block';
      retryMessage.textContent = 'Verificando conexión...';

      try {
        const isOnline = await checkConnection();

        if (isOnline) {
          retryMessage.textContent = '¡Conexión restablecida! Recargando...';
          setTimeout(() => location.reload(), 1000);
        } else {
          button.disabled = false;
          button.innerHTML = 'Reintentar conexión';
          button.style.background = '#dc3545';
          retryMessage.textContent = connectionState.serverDown ?
            'El servidor sigue sin estar disponible. Por favor, inténtalo más tarde.' :
            'No se pudo establecer conexión. Verifica tu red e intenta nuevamente.';
        }
      } catch (error) {
        button.disabled = false;
        button.innerHTML = 'Reintentar conexión';
        button.style.background = '#dc3545';
        retryMessage.textContent = 'Error al verificar la conexión. Intenta nuevamente.';
      }
    });
  }

  // Función para ocultar la pantalla de error
  function hideFullscreenError() {
    const errorScreen = document.getElementById('fullscreen-error');
    if (errorScreen) {
      errorScreen.style.transition = 'opacity 0.5s ease';
      errorScreen.style.opacity = '0';
      setTimeout(() => {
        errorScreen.remove();
        const appContent = document.getElementById('app-content');
        if (appContent) appContent.style.display = 'block';
        document.documentElement.style.overflow = ''; // Restaurar scroll
      }, 500);
    }
  }

  // Verificación de conexión mejorada
  async function checkConnection() {
    try {
      // Verificación básica de conexión
      if (!navigator.onLine) {
        connectionState.serverDown = false;
        return false;
      }

      // Verificación del servidor con timeout
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 5000);

      const response = await fetch(window.location.href, {
        method: 'HEAD',
        cache: 'no-store',
        signal: controller.signal
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        connectionState.serverDown = true;
        return false;
      }

      // Verificación de Supabase
      const {
        error
      } = await supabaseClient.from('espacios').select('id').limit(1).single();

      if (error) {
        connectionState.serverDown = false;
        throw error;
      }

      connectionState.serverDown = false;
      return true;
    } catch (error) {
      console.error('Error al verificar conexión:', error);

      if (error.message.includes('Failed to fetch') || error.name === 'AbortError') {
        connectionState.serverDown = true;
      }

      return false;
    }
  }

  // Actualización del estado de conexión
  async function updateConnectionStatus() {
    if (connectionState.checking) return;

    connectionState.checking = true;
    connectionState.lastChecked = new Date();

    const wasOnline = connectionState.isOnline;
    const isNowOnline = await checkConnection();
    connectionState.isOnline = isNowOnline;

    if (isNowOnline) {
      connectionState.lastSuccess = new Date(); // <-- Actualiza al momento actual
    }

    if (!wasOnline && isNowOnline) {
      // Conexión restablecida
      hideFullscreenError();
      connectionState.retryCount = 0;
      setTimeout(() => location.reload(), 1000);
    } else if (wasOnline && !isNowOnline) {
      // Conexión perdida
      if (connectionState.serverDown) {
        showFullscreenError('server');
      } else {
        showFullscreenError('connection');
      }

      // Reintentos automáticos
      if (connectionState.retryCount < connectionState.maxRetries) {
        connectionState.retryCount++;
        const delay = Math.min(30000, 1000 * Math.pow(2, connectionState.retryCount));
        setTimeout(updateConnectionStatus, delay);
      }
    }

    connectionState.checking = false;
  }

  // Inicialización del monitoreo
  function initConnectionMonitoring() {
    // Asegurarse de que el contenido de la app tenga un ID
    const mainContent = document.querySelector('main') || document.body;
    if (!mainContent.id) {
      mainContent.id = 'app-content';
    }

    // Eventos de navegador
    window.addEventListener('online', () => {
      console.log("Conexión restablecida (navegador)");
      updateConnectionStatus();
    });

    window.addEventListener('offline', () => {
      console.log("Conexión perdida (navegador)");
      connectionState.isOnline = false;
      connectionState.serverDown = false;
      showFullscreenError('connection');
    });

    // Verificación periódica
    setInterval(updateConnectionStatus, 30000);

    // Verificación inicial
    updateConnectionStatus();
  }

  // Iniciar cuando el DOM esté listo
  document.addEventListener('DOMContentLoaded', initConnectionMonitoring);

  // Verificación adicional al cargar la página
  window.addEventListener('load', function() {
    if (!document.body || performance.navigation.type === performance.navigation.TYPE_RELOAD) {
      setTimeout(updateConnectionStatus, 2000);
    }
  });
</script>


<body>
  <div class="container">
    <!-- Botón Cerrar Sesión -->
    <div class="logout">
      <form action="logout.php" method="POST">
        <button type="submit">Cerrar sesión</button>
      </form>
    </div>

    <h1>Sistema de Reserva de Estacionamiento</h1>

    <!-- ----------------------------------------------------------
      SECCIÓN ADMINISTRADOR: Agregar/Eliminar espacios
      (solo si rol='admin')
    ---------------------------------------------------------- -->
    <?php if ($rolUsuario === 'admin'): ?>
      <div class="admin-section">
        <h2>Panel de Espacios (Modo Administrador)</h2>

        <?php if (!empty($msgAdmin)): ?>
          <div class="<?= (strpos($msgAdmin, 'correctamente') !== false) ? 'successAdmin' : 'msgAdmin' ?>">
            <?= htmlspecialchars($msgAdmin) ?>
          </div>
        <?php endif; ?>

        <!-- 1) Formulario para AGREGAR un nuevo espacio -->
        <form method="POST" class="form-inline">
          <label for="nuevo_espacio">Nombre de nuevo espacio:</label>
          <input type="text" id="nuevo_espacio" name="nuevo_espacio" placeholder="Ej. C1" required>
          <button type="submit">Agregar espacio</button>
        </form>

        <!-- 2) Tabla con la lista de espacios y botones Eliminar -->
        <div class="espacios-list-container">
          <table class="espacios-table">
            <thead>
              <tr>
                <th>Espacio</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($espacios) === 0): ?>
                <tr>
                  <td colspan="2" style="text-align:center; padding:10px;">No hay espacios registrados.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($espacios as $esp): ?>
                  <tr>
                    <td><?= htmlspecialchars($esp) ?></td>
                    <td>
                      <form method="POST" class="form-delete" data-espacio="<?= htmlspecialchars($esp) ?>" style="display:inline-block;">
                        <input type="hidden" name="eliminar_espacio" value="<?= htmlspecialchars($esp) ?>">
                        <button type="button" class="btn-delete">Eliminar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
    <!-- ----------------------------------------------------------
      FIN SECCIÓN ADMINISTRADOR
    ---------------------------------------------------------- -->

    <!-- ----------------------------------------------------------
      MENSAJE E INFORMACIÓN PARA USUARIOS NORMALES O INVITADO
    ---------------------------------------------------------- -->
    <?php if ($rolUsuario === 'usuario'): ?>
      <?php if ($miReservada !== null): ?>
        <!-- Usuario normal con reserva activa -->
        <div class="alert">
          Ya tienes un espacio reservado: <strong><?= htmlspecialchars($miReservada) ?></strong>.<br>
          Si quieres seleccionar otro espacio, primero debes cancelar tu reserva actual.
        </div>
      <?php else: ?>
        <!-- Usuario normal sin reserva -->
        <div class="info">
          Selecciona un espacio para reservar
        </div>
      <?php endif; ?>
    <?php elseif ($rolUsuario === 'invitado'): ?>
      <!-- Invitado: solo visualización -->
      <div class="info">
        Modo invitado: solo puedes ver el estacionamiento.
      </div>
    <?php endif; ?>

    <!-- ----------------------------------------------------------
      TABLERO DE ESPACIOS EN 3 SECCIONES:
      1) Fila A (orden natural numérico)
      2) DIV “ENTRADA / SALIDA”
      3) Fila B (orden natural numérico)
    ---------------------------------------------------------- -->

    <!-- 1) FILA A -->
    <div class="parking-lot" id="fila-a">
      <?php
      foreach ($espaciosA as $espA) {
        $esOcupadoA = in_array($espA, $ocupadas, true);
        // Si es invitado y está ocupado, marcamos reserved pero sin puntero
        $claseA = 'space';
        if ($esOcupadoA) {
          $claseA = 'space reserved';
        }
        // Si invitado, agregamos clase extra para deshabilitar puntero
        if ($rolUsuario === 'invitado') {
          $claseA .= ' no-interact';
        }
        echo "<div class=\"$claseA\" data-id=\"$espA\">$espA</div>";
      }
      ?>
    </div>

    <!-- 2) Calle / Driveway -->
    <div class="driveway">ENTRADA / SALIDA</div>

    <!-- 3) FILA B -->
    <div class="parking-lot" id="fila-b">
      <?php
      foreach ($espaciosB as $espB) {
        $esOcupadoB = in_array($espB, $ocupadas, true);
        $claseB = 'space';
        if ($esOcupadoB) {
          $claseB = 'space reserved';
        }
        if ($rolUsuario === 'invitado') {
          $claseB .= ' no-interact';
        }
        echo "<div class=\"$claseB\" data-id=\"$espB\">$espB</div>";
      }
      ?>
    </div>

    <!-- ----------------------------------------------------------
  CONTROLES: Reservar / Cancelar mi reserva (solo para 'usuario')
  Reiniciar todas las reservas (solo para 'admin')
---------------------------------------------------------- -->
    <?php if ($rolUsuario === 'usuario'): ?>
      <div class="controls">
        <?php if ($miReservada !== null): ?>
          <!-- Botón “Cancelar mi reserva” -->
          <form action="cancelar_reserva.php" method="POST" style="margin-right: auto;">
            <input type="hidden" name="placa" value="<?= htmlspecialchars($miReservada) ?>">
            <button type="submit" class="cancel-btn">Cancelar mi reserva</button>
          </form>
        <?php else: ?>
          <!-- Botón “Reservar espacio” -->
          <button id="reserveBtn" class="reserve-btn" disabled>Reservar espacio</button>
        <?php endif; ?>
      </div>
    <?php elseif ($rolUsuario === 'admin'): ?>
      <div class="controls" style="justify-content: flex-end;">
        <!-- Solo mostrar “Reiniciar todas las reservas” -->
        <button id="resetBtn" class="reset-btn">Reiniciar todas las reservas</button>
      </div>
    <?php endif; ?>


    <!-- ======================================================
       VENTANA MODAL DE CONFIRMACIÓN (Oculta por defecto)
       ====================================================== -->
    <div id="modalOverlay" class="modal-overlay">
      <div class="modal-content">
        <p>¿Eliminar espacio <span id="modalEspacio"></span>?</p>
        <div class="modal-buttons">
          <button id="btnConfirmar" class="btn-confirm">Aceptar</button>
          <button id="btnCancelar" class="btn-cancel">Cancelar</button>
        </div>
      </div>
    </div>

    <!-- ======================================================
       SCRIPT PARA MANEJAR EL MODAL Y LAS RESERVAS
       ====================================================== -->
    <script>
      // Referencias a elementos del modal
      const modalOverlay = document.getElementById('modalOverlay');
      const modalEspacio = document.getElementById('modalEspacio');
      const btnConfirmar = document.getElementById('btnConfirmar');
      const btnCancelar = document.getElementById('btnCancelar');

      // Variable para guardar el formulario a enviar
      let formAEliminar = null;

      // Asignar evento a cada formulario .form-delete (solo admin ve estos formularios)
      document.querySelectorAll('.form-delete').forEach(form => {
        const btnDel = form.querySelector('button.btn-delete');
        btnDel.addEventListener('click', (e) => {
          e.preventDefault();
          formAEliminar = form;
          const nombreEspacio = form.dataset.espacio;
          modalEspacio.textContent = nombreEspacio;
          modalOverlay.style.display = 'flex';
        });
      });

      // Si presiona “Cancelar” en el modal, ocultar modal
      btnCancelar.addEventListener('click', () => {
        modalOverlay.style.display = 'none';
        formAEliminar = null;
      });

      // Si presiona “Aceptar” en el modal, enviar el formulario guardado
      btnConfirmar.addEventListener('click', () => {
        if (formAEliminar) {
          formAEliminar.submit();
        }
      });

      // Si hace clic fuera del cuadro (en el overlay), cerrar modal
      modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) {
          modalOverlay.style.display = 'none';
          formAEliminar = null;
        }
      });

      // Lógica de selección y reserva (solo rol 'usuario')
      const rolUsuario = <?= json_encode($rolUsuario, JSON_HEX_TAG) ?>;
      const miReservada = <?= json_encode($miReservada, JSON_HEX_TAG) ?>;

      if (rolUsuario === 'usuario') {
        let selected = null;
        const btnReservar = document.getElementById("reserveBtn");
        const espaciosDiv = document.querySelectorAll(".space:not(.reserved)");

        espaciosDiv.forEach(divEspacio => {
          divEspacio.addEventListener("click", () => {
            espaciosDiv.forEach(e => e.classList.remove("selected"));
            divEspacio.classList.add("selected");
            selected = divEspacio;
            btnReservar.disabled = false;
          });
        });

        btnReservar.addEventListener("click", () => {
          if (!selected) return;
          fetch("guardar_reserva.php", {
              method: "POST",
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `espacio=${encodeURIComponent(selected.dataset.id)}`
            })
            .then(res => res.text())
            .then(txt => {
              txt = txt.trim();
              if (txt === "OK") {
                window.location.reload();
              } else if (txt === "YA_TIENE_RESERVA") {
                alert("Ya tienes una reserva activa. Primero cancélala para seleccionar otro espacio.");
              } else if (txt === "ESPACIO_OCUPADO") {
                alert("Ese espacio ya está ocupado. Elige otro.");
              } else {
                alert("Error al reservar: " + txt);
              }
            });
        });
      }

      // Botón “Reiniciar todas las reservas” (rol 'usuario' o 'admin')
      const resetBtn = document.getElementById("resetBtn");
      if (resetBtn) {
        resetBtn.addEventListener("click", () => {
          window.location.href = "resetear_reservas.php";
        });
      }

      // Si accede con botón atrás, validar sesión
      if (performance.navigation.type === 2) {
        fetch("check_session.php")
          .then(res => res.text())
          .then(data => {
            if (data.trim() === "NO_SESSION") {
              window.location.href = "login.php";
            }
          });
      }
    </script>


</body>

</html>