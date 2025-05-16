<?php
session_start();
require_once 'clases/Parqueo.php';

$mensaje = '';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $placa = $_POST['placa'];
    $usuario_id = $_SESSION['usuario_id'];

    $parqueo = new Parqueo();
    $resultado = $parqueo->registrarEntrada($usuario_id, $placa);

    if ($resultado === true) {
        $mensaje = "Entrada registrada con éxito.";
    } else {
        $mensaje = $resultado; // mensaje de error si ya está activo u otro problema
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Entrada</title>
</head>
<body>
    <h2>Registrar Entrada de Vehículo</h2>

    <?php if ($mensaje): ?>
        <p style="color:blue;"><?php echo $mensaje; ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <label>Placa del vehículo:</label>
        <input type="text" name="placa" required>
        <button type="submit">Registrar Entrada</button>
    </form>

    <br>
    <a href="dashboard.php">Volver al dashboard</a>
</body>
</html>
