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

    /**
     * Verifica si el usuario tiene una reserva activa (estado = 'activo').
     * Retorna true si existe, false si no.
     */
    private function tieneReservaActiva(int $usuario_id): bool {
        $sql = "SELECT 1 
                  FROM parqueos 
                 WHERE usuario_id = $1 
                   AND estado = 'activo' 
                 LIMIT 1";
        $params = [$usuario_id];
        $res = pg_query_params($this->conexion, $sql, $params);
        if (!$res) {
            // En caso de error, asumimos que no hay reserva activa
            return false;
        }
        return (pg_num_rows($res) > 0);
    }

    /**
     * Registra una nueva entrada (reserva) para el usuario con la placa dada,
     * pero solo si no tiene ya una reserva activa y la placa no está en uso.
     * Retorna true en éxito, o cadena de error.
     */
    public function registrarEntrada(int $usuario_id, string $placa) {
        // 1) Validación básica
        if (empty(trim($placa))) {
            return "La placa no puede estar vacía";
        }

        // 2) Verificar si el usuario ya tiene una reserva activa
        if ($this->tieneReservaActiva($usuario_id)) {
            return "Ya tienes una reserva activa. Primero desocupa tu puesto.";
        }

        // 3) Verificar si la misma placa ya está registrada como 'activo' (otro usuario)
        $sql_verificar_placa = "SELECT id 
                                  FROM parqueos 
                                 WHERE placa = $1 
                                   AND estado = 'activo' 
                                 LIMIT 1";
        $params_placa = [$placa];
        $res_placa = pg_query_params($this->conexion, $sql_verificar_placa, $params_placa);
        if (!$res_placa) {
            return "Error al verificar placa: " . pg_last_error($this->conexion);
        }
        if (pg_num_rows($res_placa) > 0) {
            return "Ese vehículo ya está registrado en otro puesto activo.";
        }

        // 4) Insertar la nueva reserva en estado 'activo'
        $sql_insertar = "
            INSERT INTO parqueos (usuario_id, placa, hora_entrada, estado) 
            VALUES ($1, $2, NOW(), 'activo')
        ";
        $params_insertar = [$usuario_id, $placa];
        $result = pg_query_params($this->conexion, $sql_insertar, $params_insertar);

        if (!$result) {
            return "Error al registrar entrada: " . pg_last_error($this->conexion);
        }

        return true;
    }

    /**
     * Registra la salida (cancela la reserva activa) para la placa dada.
     * Solo afecta si existe un registro activo con esa placa.
     * Retorna true en éxito, o cadena de error.
     */
    public function registrarSalida(string $placa) {
        if (empty(trim($placa))) {
            return "La placa no puede estar vacía";
        }

        $sql = "
            UPDATE parqueos 
               SET hora_salida = NOW(), estado = 'salido' 
             WHERE placa = $1 
               AND estado = 'activo'
             RETURNING id
        ";
        $params = [$placa];
        $result = pg_query_params($this->conexion, $sql, $params);

        if (!$result) {
            return "Error al registrar salida: " . pg_last_error($this->conexion);
        }

        if (pg_affected_rows($result) > 0) {
            return true;
        }

        return "No se encontró un vehículo activo con esa placa.";
    }

    /**
     * Cancela (desocupa) la reserva activa del usuario.
     * Retorna true en éxito; si no tenía reserva, retorna mensaje.
     */
    public function cancelarReservaPorUsuario(int $usuario_id) {
        // 1) Verificar si hay reserva activa para el usuario
        $sql_buscar = "
            SELECT id 
              FROM parqueos 
             WHERE usuario_id = $1 
               AND estado = 'activo' 
             LIMIT 1
        ";
        $params_buscar = [$usuario_id];
        $res_buscar = pg_query_params($this->conexion, $sql_buscar, $params_buscar);

        if (!$res_buscar) {
            return "Error al verificar reserva activa: " . pg_last_error($this->conexion);
        }

        if (pg_num_rows($res_buscar) === 0) {
            return "No tienes ninguna reserva activa para desocupar.";
        }

        $fila = pg_fetch_assoc($res_buscar);
        $reserva_id = $fila['id'];

        // 2) Actualizar el registro a estado 'salido'
        $sql_update = "
            UPDATE parqueos 
               SET hora_salida = NOW(), estado = 'salido' 
             WHERE id = $1
        ";
        $params_update = [$reserva_id];
        $res_update = pg_query_params($this->conexion, $sql_update, $params_update);

        if (!$res_update) {
            return "Error al cancelar la reserva: " . pg_last_error($this->conexion);
        }

        return true;
    }

    /**
     * Retorna un array con todas las reservas activas (para listado general, si se requiere).
     */
    public function listarReservasActivas(): array {
        $sql = "
            SELECT id, usuario_id, placa, hora_entrada 
              FROM parqueos 
             WHERE estado = 'activo'
             ORDER BY hora_entrada DESC
        ";
        $res = pg_query($this->conexion, $sql);

        if (!$res) {
            return [];
        }

        $lista = [];
        while ($fila = pg_fetch_assoc($res)) {
            $lista[] = $fila;
        }
        return $lista;
    }
}
?>
