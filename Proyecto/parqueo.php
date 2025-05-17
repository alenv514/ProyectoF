<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'clases/Conexion.php';

$conexion = Conexion::conectar();
$reservas = [];

$sql = "SELECT espacio FROM reservas";
$resultado = $conexion->query($sql);

while ($fila = $resultado->fetch_assoc()) {
    $reservas[] = $fila['espacio'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistema de Reserva de Estacionamiento</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #f5f5f5;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 20px;
    }

    h1 {
      text-align: center;
      color: #333;
      margin-bottom: 30px;
    }

    .parking-lot {
      display: grid;
      grid-template-columns: repeat(9, 1fr);
      gap: 5px;
      margin-bottom: 20px;
      position: relative;
    }

    .space {
      aspect-ratio: 1/1.5;
      background-color: #e0e0e0;
      border: 1px solid #999;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s;
      font-weight: bold;
      position: relative;
    }

    .space:hover {
      background-color: #d0d0d0;
    }

    .reserved {
      background-color: #ff6b6b;
      color: white;
    }

    .reserved:hover {
      background-color: #ff5252;
    }

    .space.disabled {
      background-color: #aaa;
      cursor: not-allowed;
    }

    .driveway {
      background-color: #a0a0a0;
      grid-column: span 9;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }

    .controls {
      margin-top: 20px;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    .space-info {
      padding: 15px;
      background-color: #f0f0f0;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    button {
      background-color: #4CAF50;
      color: white;
      border: none;
      padding: 10px 15px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: background-color 0.3s;
    }

    button:hover {
      background-color: #45a049;
    }

    button:disabled {
      background-color: #cccccc;
      cursor: not-allowed;
    }

    .reset-btn {
      background-color: #f44336;
    }

    .reset-btn:hover {
      background-color: #e53935;
    }

    .legend {
      display: flex;
      justify-content: center;
      margin: 20px 0;
      gap: 20px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .legend-color {
      width: 20px;
      height: 20px;
      border: 1px solid #999;
    }

    .legend-available {
      background-color:rgb(0, 249, 108);
    }

    .legend-reserved {
      background-color:rgb(234, 0, 0);
    }

    @media (max-width: 768px) {
      .parking-lot {
        grid-template-columns: repeat(4, 1fr);
      }

      .driveway {
        grid-column: span 4;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Sistema de Reserva de Estacionamiento</h1>

    <div class="legend">
      <div class="legend-item">
        <div class="legend-color legend-available"></div>
        <span>Disponible</span>
      </div>
      <div class="legend-item">
        <div class="legend-color legend-reserved"></div>
        <span>Reservado</span>
      </div>
    </div>

    <div id="spaceInfo" class="space-info">
      Seleccione un espacio para reservar
    </div>

    <div class="parking-lot">
      <?php
      $espacios = [
        'A1','A2','A3','A4','A5','A6','A7','A8',
        'B1','B2','B3','B4','B5','B6','B7','B8','B9'
      ];
      $puntoMedio = 8;

      foreach ($espacios as $i => $espacio) {
          if ($i == $puntoMedio) {
              echo '<div class="driveway">ENTRADA / SALIDA</div>';
          }
          $clase = in_array($espacio, $reservas) ? 'space reserved' : 'space';
          echo "<div class=\"$clase\" data-id=\"$espacio\">$espacio</div>";
      }
      ?>
    </div>

    <div class="controls">
      <button id="reserveBtn" disabled>Reservar espacio</button>
      <button id="resetBtn" class="reset-btn">Reiniciar todas las reservas</button>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      let selectedSpace = null;
      const reserveBtn = document.getElementById('reserveBtn');
      const resetBtn = document.getElementById('resetBtn');
      const spaceInfo = document.getElementById('spaceInfo');
      const spaces = document.querySelectorAll('.space:not(.disabled)');

      spaces.forEach(space => {
        space.addEventListener('click', function() {
          if (this.classList.contains('reserved')) {
            spaceInfo.textContent = `El espacio ${this.dataset.id} ya está reservado.`;
            selectedSpace = null;
            reserveBtn.disabled = true;
            return;
          }

          if (selectedSpace) {
            selectedSpace.classList.remove('selected');
          }

          selectedSpace = this;
          spaceInfo.textContent = `Espacio seleccionado: ${this.dataset.id}`;
          reserveBtn.disabled = false;
        });
      });

      reserveBtn.addEventListener('click', function() {
        if (selectedSpace) {
          const xhr = new XMLHttpRequest();
          xhr.open("POST", "guardar_reserva.php", true);
          xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
          xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
              if (xhr.responseText.trim() === "ok") {
                selectedSpace.classList.add('reserved');
                spaceInfo.textContent = `¡Espacio ${selectedSpace.dataset.id} reservado exitosamente!`;
                selectedSpace = null;
                reserveBtn.disabled = true;
              } else {
                alert(xhr.responseText);
              }
            }
          };
          xhr.send("espacio=" + encodeURIComponent(selectedSpace.dataset.id));
        }
      });

      resetBtn.addEventListener('click', function() {
        window.location.href = "resetear_reservas.php";
      });
    });
  </script>
</body>
</html>
