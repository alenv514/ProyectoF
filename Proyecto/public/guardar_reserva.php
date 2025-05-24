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
    $sql_verificar = "SELECT id FROM reservas WHERE espacio = $1";
    $params_verificar = [$espacio];
    $res_verificar = pg_query_params($conexion, $sql_verificar, $params_verificar);

    if (!$res_verificar) {
        echo "ERROR_EN_VERIFICACION";
        exit();
    }

    if (pg_num_rows($res_verificar) > 0) {
        echo "YA_RESERVADO";
    } else {
        $sql_insertar = "INSERT INTO reservas (usuario_id, espacio) VALUES ($1, $2)";
        $params_insertar = [$usuario_id, $espacio];
        $res_insertar = pg_query_params($conexion, $sql_insertar, $params_insertar);

        if ($res_insertar) {
            echo "OK";
        } else {
            echo "ERROR_AL_GUARDAR";
        }
    }
}
?>
