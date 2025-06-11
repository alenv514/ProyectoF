<?php
require_once 'clases/Conexion.php';

header('Content-Type: application/json');

if (Conexion::verificarServidor()) {
    echo json_encode(['status' => 'online']);
} else {
    http_response_code(503);
    echo json_encode(['status' => 'offline']);
}
?>