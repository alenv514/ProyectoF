<?php
session_start();

// 1) Verificar que haya sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'clases/Conexion.php';

// 2) Obtener el usuario logueado
$usuario_id = (int) $_SESSION['usuario_id'];

// 3) Conectar a la base de datos
$conexion = Conexion::conectar();
if (!$conexion) {
    die("Error de conexión a PostgreSQL");
}

// 4) “Cancelar” la reserva activa de este usuario: 
//    marcamos estado='salido' sólo donde usuario_id coincida y estado='activo'
$sql = "
    UPDATE parqueos
       SET hora_salida = NOW(),
           estado      = 'salido'
     WHERE usuario_id = \$1
       AND estado = 'activo'
";
$res = pg_query_params($conexion, $sql, [$usuario_id]);

// 5) Una vez finalizada la operación, redirigimos de vuelta a parqueo.php
header("Location: parqueo.php");
exit();
?>
