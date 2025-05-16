<?php
session_start();

// Verifica si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit(); // debe ir aquí, antes de cargar nada más
}

// Conexión (si realmente es necesaria aquí)
require_once 'clases/Conexion.php';

// Redirige automáticamente al sistema visual del parqueo
header("Location: parqueo.php");
exit();
?>
