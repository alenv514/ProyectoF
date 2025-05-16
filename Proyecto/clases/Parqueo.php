<?php
require_once 'Conexion.php';

class Parqueo {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::conectar();
    }

    public function registrarEntrada($usuario_id, $placa) {
        $sql_verificar = "SELECT id FROM parqueos WHERE placa = '$placa' AND estado = 'activo'";
        $res = $this->conexion->query($sql_verificar);

        if ($res->num_rows > 0) {
            return "Este vehículo ya está registrado como activo.";
        }

        $sql_insertar = "INSERT INTO parqueos (usuario_id, placa, hora_entrada, estado) VALUES ($usuario_id, '$placa', NOW(), 'activo')";
        if ($this->conexion->query($sql_insertar)) {
            return true;
        } else {
            return "Error al registrar entrada.";
        }
    }

    public function registrarSalida($placa) {
        $sql = "UPDATE parqueos SET hora_salida = NOW(), estado = 'salido' WHERE placa = '$placa' AND estado = 'activo'";
        if ($this->conexion->query($sql) && $this->conexion->affected_rows > 0) {
            return true;
        } else {
            return "No se encontró un vehículo activo con esa placa.";
        }
    }
}
?>
