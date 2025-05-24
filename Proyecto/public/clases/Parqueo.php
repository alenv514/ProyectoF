<?php
require_once 'Conexion.php';

class Parqueo {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::conectar();
        if (!$this->conexion) {
            die("Error de conexión: " . pg_last_error());
        }
    }

    public function registrarEntrada($usuario_id, $placa) {
        // Validación básica
        if (empty($placa)) {
            return "La placa no puede estar vacía";
        }

        $sql_verificar = "SELECT id FROM parqueos WHERE placa = $1 AND estado = 'activo'";
        $params = [$placa];
        $res = pg_query_params($this->conexion, $sql_verificar, $params);

        if (!$res) {
            return "Error al verificar: " . pg_last_error($this->conexion);
        }

        if (pg_num_rows($res) > 0) {
            return "Vehículo ya registrado";
        }

        $sql_insertar = "INSERT INTO parqueos (usuario_id, placa, hora_entrada, estado) VALUES ($1, $2, NOW(), 'activo')";
        $params = [$usuario_id, $placa];
        $result = pg_query_params($this->conexion, $sql_insertar, $params);

        if (!$result) {
            return "Error al registrar: " . pg_last_error($this->conexion);
        }

        return true;
    }

    public function registrarSalida($placa) {
        if (empty($placa)) {
            return "La placa no puede estar vacía";
        }

        $sql = "UPDATE parqueos SET hora_salida = NOW(), estado = 'salido' WHERE placa = $1 AND estado = 'activo' RETURNING id";
        $params = [$placa];
        $result = pg_query_params($this->conexion, $sql, $params);

        if (!$result) {
            return "Error en consulta: " . pg_last_error($this->conexion);
        }

        return (pg_affected_rows($result) > 0) 
            ? true 
            : "No se encontró vehículo activo con esa placa";
    }
}
?>