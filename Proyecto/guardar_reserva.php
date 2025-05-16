<?php
session_start();
require_once 'clases/Conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    echo "NO_AUTORIZADO";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $espacio = $_POST['espacio'];
    $usuario_id = $_SESSION['usuario_id'];

    $conexion = Conexion::conectar();

    // Verifica si el espacio ya está reservado
    $verificar = $conexion->prepare("SELECT id FROM reservas WHERE espacio = ?");
    $verificar->bind_param("s", $espacio);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows > 0) {
        echo "YA_RESERVADO";
    } else {
        $stmt = $conexion->prepare("INSERT INTO reservas (usuario_id, espacio) VALUES (?, ?)");
        $stmt->bind_param("is", $usuario_id, $espacio);
        if ($stmt->execute()) {
            echo "OK";
        } else {
            echo "ERROR_AL_GUARDAR";
        }
    }
}
?>
