<?php
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
      max-height: 250px;      /* Ajusta la altura a tu gusto */
      overflow-y: auto;       /* Scroll vertical cuando hay muchas filas */
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
      overflow-x: auto;       /* Aparece scroll horizontal si excede ancho */
      padding-bottom: 10px;   /* Espacio para la barra de scroll */
      margin-bottom: 10px;    /* Separar de la siguiente sección */
    }
    /* Estilado de scrollbar (opcional) */
    .parking-lot::-webkit-scrollbar {
      height: 8px;
    }
    .parking-lot::-webkit-scrollbar-thumb {
      background-color: rgba(0,0,0,0.2);
      border-radius: 4px;
    }
    .parking-lot::-webkit-scrollbar-track {
      background-color: rgba(0,0,0,0.05);
    }

    /* Cada casilla de estacionamiento */
    .space {
      flex: 0 0 80px;       /* Ancho fijo: 80px */
      aspect-ratio: 1/1.5;  /* Alto proporcional */
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
      transform: none; /* Evitar agrandar en hover si está seleccionado */
    }

    /* DIV “calle” (driveway) entre fila A y B */
    .driveway {
      width: 100%;           /* Ocupa todo el ancho (genera “nueva fila”) */
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
      background-color: rgba(0,0,0,0.5);
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
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
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

    .btn-confirm, .btn-cancel {
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
    /* ======================================================= */
  </style>
</head>

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
    <div class="parking-lot">
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
    <div class="parking-lot">
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
      CONTROLES: Reservar / Cancelar mi reserva / Reiniciar todas
      Solo para rol 'usuario' o 'admin'. Invitado no ve nada.
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

        <!-- Botón “Reiniciar todas las reservas” (solo admin valida en script) -->
        <button id="resetBtn" class="reset-btn">Reiniciar todas las reservas</button>
      </div>
    <?php elseif ($rolUsuario === 'admin'): ?>
      <div class="controls" style="justify-content: flex-end;">
        <!-- Solo mostrar “Reiniciar todas las reservas” -->
        <button id="resetBtn" class="reset-btn">Reiniciar todas las reservas</button>
      </div>
    <?php endif; ?>
  </div>

  <!-- ======================================================
       VENTANA MODAL DE CONFIRMACIÓN (Oculta por defecto)
       ====================================================== -->
  <div id="modalOverlay" class="modal-overlay">
    <div class="modal-content">
      <p>¿Eliminar espacio <span id="modalEspacio"></span>?</p>
      <div class="modal-buttons">
        <button id="btnConfirmar" class="btn-confirm">Aceptar</button>
        <button id="btnCancelar"  class="btn-cancel">Cancelar</button>
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
