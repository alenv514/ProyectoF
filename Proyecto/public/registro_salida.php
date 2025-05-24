<?php
// Inicia la sesión para poder usar variables de sesión
session_start();

// Incluye la clase Parqueo, que contiene las funciones para gestionar las entradas y salidas
require_once 'clases/Parqueo.php';

// Variable que se usará para mostrar mensajes al usuario (como éxito o errores)
$mensaje = '';

// Verifica si el usuario ha iniciado sesión (protección de acceso)
if (!isset($_SESSION['usuario_id'])) {
    // Si no hay sesión, redirige al login para evitar acceso directo sin autenticación
    header("Location: login.php");
    exit(); // Detiene el script después de redirigir
}

// Verifica si el formulario fue enviado usando el método POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtiene la placa del formulario (campo 'placa')
    $placa = $_POST['placa'];

    // Crea una instancia de la clase Parqueo para poder usar sus métodos
    $parqueo = new Parqueo();

    // Llama al método registrarSalida pasando la placa ingresada
    $resultado = $parqueo->registrarSalida($placa);

    // Verifica si el resultado fue exitoso (true) o un mensaje de error
    if ($resultado === true) {
        // Si fue exitoso, guarda el mensaje de confirmación
        $mensaje = "Salida registrada correctamente.";
    } else {
        // Si no, guarda el mensaje de error devuelto por la función
        $mensaje = $resultado;
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Salida</title>
</head>
<body>
    <!-- Título principal de la página -->
    <h2>Registrar Salida de Vehículo</h2>

    <!-- Si hay un mensaje (de éxito o error), se muestra aquí -->
    <?php if ($mensaje): ?>
        <!-- El mensaje se muestra en color verde -->
        <p style="color:green;"><?php echo $mensaje; ?></p>
    <?php endif; ?>

    <!-- Formulario para ingresar la placa del vehículo -->
    <form method="post" action="">
        <!-- Etiqueta y campo para escribir la placa -->
        <label>Placa del vehículo:</label>
        <!-- Campo de texto obligatorio -->
        <input type="text" name="placa" required>
        <!-- Botón que envía el formulario -->
        <button type="submit">Registrar Salida</button>
    </form>

    <!-- Espacio y enlace para volver al panel principal -->
    <br>
    <a href="dashboard.php">Volver al dashboard</a>
</body>
</html>
