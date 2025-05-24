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

require_once 'clases/Conexion.php';

$conexion = Conexion::conectar();
$reservas = [];
$sql = "SELECT espacio FROM reservas";

$resultado = pg_query($conexion, $sql);
$reservas = [];

while ($fila = pg_fetch_assoc($resultado)) {
  $reservas[] = $fila['espacio'];
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Reserva de Estacionamiento</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma;
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
    }

    .legend {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin: 20px 0;
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

    .space-info {
      background-color: #f0f0f0;
      padding: 10px;
      border-radius: 6px;
      text-align: center;
      margin-bottom: 20px;
    }

    .parking-lot {
      display: grid;
      grid-template-columns: repeat(9, 1fr);
      gap: 5px;
      margin-bottom: 20px;
    }

    .space {
      aspect-ratio: 1/1.5;
      background-color: #e0e0e0;
      border: 1px solid #ccc;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      cursor: pointer;
    }

    .reserved {
      background-color: #ff6b6b;
      color: white;
    }

    .driveway {
      grid-column: span 9;
      background-color: #888;
      text-align: center;
      padding: 10px;
      color: white;
      font-weight: bold;
    }

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
    }

    .reserve-btn {
      background-color: #4CAF50;
    }

    .reserve-btn:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }

    .reset-btn {
      background-color: #f44336;
    }

    .reset-btn:hover {
      background-color: #e53935;
    }
  </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.42.3/dist/umd/supabase.min.js"></script>
<script>
  const supabaseClient = supabase.createClient(
    'https://nyjlglghxxpaypzmffvj.supabase.co',
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im55amxnbGdoeHhwYXlwem1mZnZqIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDgxMDQ4MzAsImV4cCI6MjA2MzY4MDgzMH0.LIt2xJKgjBnkwJRd3JgqfQW-OEG1-AswshT3iPbrazc'
  );

  supabaseClient.channel('reservas').on('postgres_changes', {
      event: '*',
      schema: 'public',
      table: 'reservas'
    }, payload => {
      const espacio = payload.new.espacio;
      const div = document.querySelector(`.space[data-id='${espacio}']`);
      if (div) {
        div.classList.add('reserved');
        div.classList.remove('selected');
        if (selected && selected.dataset.id === espacio) {
          info.textContent = `El espacio ${espacio} fue reservado por otro usuario.`;
          selected = null;
          btn.disabled = true;
        }
      }
    })
    .subscribe();
</script>

<body>
  <div class="container">
    <!-- Cerrar sesión -->
    <div class="logout">
      <form action="logout.php" method="POST">
        <button type="submit">Cerrar sesión</button>
      </form>
    </div>

    <h1>Sistema de Reserva de Estacionamiento</h1>

    <div class="legend">
      <div class="legend-item">
        <div class="legend-color legend-available"></div> Disponible
      </div>
      <div class="legend-item">
        <div class="legend-color legend-reserved"></div> Reservado
      </div>
    </div>

    <div id="spaceInfo" class="space-info">Seleccione un espacio para reservar</div>

    <div class="parking-lot">
      <?php
      $espacios = [
        'A1',
        'A2',
        'A3',
        'A4',
        'A5',
        'A6',
        'A7',
        'A8',
        'A9',
        'ENTRADA',
        'B1',
        'B2',
        'B3',
        'B4',
        'B5',
        'B6',
        'B7',
        'B8',
        'B9'
      ];

      foreach ($espacios as $espacio) {
        if ($espacio === 'ENTRADA') {
          echo '<div class="driveway">ENTRADA / SALIDA</div>';
          continue;
        }
        $clase = in_array($espacio, $reservas) ? 'space reserved' : 'space';
        echo "<div class=\"$clase\" data-id=\"$espacio\">$espacio</div>";
      }
      ?>
    </div>

    <div class="controls">
      <button id="reserveBtn" class="reserve-btn" disabled>Reservar espacio</button>
      <button id="resetBtn" class="reset-btn">Reiniciar todas las reservas</button>
    </div>
  </div>

  <script>
    let selected = null;
    const btn = document.getElementById("reserveBtn");
    const resetBtn = document.getElementById("resetBtn");
    const info = document.getElementById("spaceInfo");

    document.querySelectorAll(".space").forEach(s => {
      s.addEventListener("click", () => {
        if (s.classList.contains("reserved")) {
          info.textContent = `El espacio ${s.dataset.id} ya está reservado.`;
          selected = null;
          btn.disabled = true;
          return;
        }
        document.querySelectorAll(".space").forEach(el => el.classList.remove("selected"));
        s.classList.add("selected");
        selected = s;
        info.textContent = `Espacio seleccionado: ${s.dataset.id}`;
        btn.disabled = false;
      });
    });

    btn.addEventListener("click", () => {
      if (!selected) return;
      fetch("guardar_reserva.php", {
          method: "POST",
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: `espacio=${selected.dataset.id}`
        })
        .then(res => res.text())
        .then(txt => {
          if (txt.trim() === "OK") {
            selected.classList.add("reserved");
            info.textContent = `¡Reserva exitosa en ${selected.dataset.id}!`;
            selected = null;
            btn.disabled = true;
          } else {
            info.textContent = `Error: ${txt}`;
          }
        });
    });

    resetBtn.addEventListener("click", () => {
      window.location.href = "resetear_reservas.php";
    });

    // Si accede con el botón atras cierra la verificación de sesión
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