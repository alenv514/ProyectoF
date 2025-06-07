<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo "Acceso denegado. Solo admin puede reiniciar reservas.";
    exit();
}
require_once 'clases/Conexion.php';

$conexion = Conexion::conectar();
$sql = "
    UPDATE parqueos
       SET hora_salida = NOW(),
           estado      = 'salido'
     WHERE estado = 'activo'
";
pg_query($conexion, $sql);
header("Location: parqueo.php");
exit();
?>
