<?php
require_once 'clases/Conexion.php';
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$conexion = Conexion::conectar();
if (!$conexion) {
    die(json_encode([
        'success' => false,
        'error' => 'Error de conexión a PostgreSQL',
        'espacios' => [],
        'espaciosA' => [],
        'espaciosB' => [],
        'ocupadas' => []
    ]));
}

try {
    // Obtener todos los espacios ordenados
    $sqlEsp = "SELECT nombre FROM espacios ORDER BY nombre";
    $resEsp = pg_query($conexion, $sqlEsp);

    if ($resEsp === false) {
        throw new Exception('Error al ejecutar la consulta de espacios');
    }

    $espacios = [];
    $espaciosA = [];
    $espaciosB = [];

    while ($e = pg_fetch_assoc($resEsp)) {
        $nombre = trim($e['nombre']);
        $espacios[] = $nombre;
        
        if (strtoupper(substr($nombre, 0, 1)) === 'A') {
            $espaciosA[] = $nombre;
        } else {
            $espaciosB[] = $nombre;
        }
    }

    // Ordenar numéricamente (A1, A2,... B1, B2,...)
    usort($espaciosA, function($a, $b) {
        return substr($a, 1) - substr($b, 1);
    });
    
    usort($espaciosB, function($a, $b) {
        return substr($a, 1) - substr($b, 1);
    });

    // Obtener espacios ocupados (reservas activas)
    $sqlOcupadas = "SELECT placa FROM parqueos WHERE estado = 'activo'";
    $resOcupadas = pg_query($conexion, $sqlOcupadas);

    $ocupadas = [];
    if ($resOcupadas !== false) {
        while ($row = pg_fetch_assoc($resOcupadas)) {
            $ocupadas[] = trim($row['placa']);
        }
    }

    echo json_encode([
        'success' => true,
        'espacios' => $espacios,
        'espaciosA' => $espaciosA,
        'espaciosB' => $espaciosB,
        'ocupadas' => $ocupadas,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'espacios' => [],
        'espaciosA' => [],
        'espaciosB' => [],
        'ocupadas' => []
    ]);
}
?>
