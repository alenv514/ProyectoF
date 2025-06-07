<?php
session_start();
require_once 'clases/Conexion.php';

if (!isset($_SESSION['usuario_id']) || empty($_POST['espacio'])) {
    echo "SIN_SESION_O_ESPACIO";
    exit();
}

$usuario_id = (int) $_SESSION['usuario_id'];
$placa      = trim($_POST['espacio']); // “espacio” proveniente del front-end

// 1) Conectar a la BD
$conexion = Conexion::conectar();
if (!$conexion) {
    echo "ERROR_EN_CONEXION";
    exit();
}

// 2) Verificar si ese mismo usuario ya tiene una reserva activa
$sql_check_user = "
    SELECT id 
      FROM parqueos 
     WHERE usuario_id = \$1 
       AND estado = 'activo' 
     LIMIT 1
";
$res_check_user = pg_query_params($conexion, $sql_check_user, [$usuario_id]);
if ($res_check_user === false) {
    echo "ERROR_EN_VERIFICACION_USUARIO";
    exit();
}
if (pg_num_rows($res_check_user) > 0) {
    // Ya hay una reserva activa para este usuario
    echo "YA_TIENE_RESERVA";
    exit();
}

// 3) Verificar si esa placa (espacio) ya está reservada (estado = 'activo')
$sql_check_placa = "
    SELECT id 
      FROM parqueos 
     WHERE placa = \$1 
       AND estado = 'activo' 
     LIMIT 1
";
$res_check_placa = pg_query_params($conexion, $sql_check_placa, [$placa]);
if ($res_check_placa === false) {
    echo "ERROR_EN_VERIFICACION_PLACA";
    exit();
}
if (pg_num_rows($res_check_placa) > 0) {
    // Ya hay otro usuario con esa placa activa
    echo "ESPACIO_OCUPADO";
    exit();
}

// 4) Si todo está bien, insertar la nueva reserva en “parqueos”
$sql_insert = "
    INSERT INTO parqueos (usuario_id, placa, hora_entrada, estado) 
    VALUES (\$1, \$2, NOW(), 'activo')
";
$res_insert = pg_query_params($conexion, $sql_insert, [$usuario_id, $placa]);

if ($res_insert === false) {
    echo "ERROR_EN_INSERCION";
    exit();
}

// 5) Si llegó hasta aquí, todo salió OK
echo "OK";
exit();
?>
