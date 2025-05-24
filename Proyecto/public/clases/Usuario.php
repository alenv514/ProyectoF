<?php
// se conecta a la base
require_once 'Conexion.php';

class Usuario
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = Conexion::conectar();
    }

    // registra los usuarios
    public function registrar($email, $password, $rol = 'usuario')
    {
        // Verificar si el email existe
        $verificar = pg_query_params($this->conexion, "SELECT id FROM usuarios WHERE email = $1", [$email]);
        if (pg_num_rows($verificar) > 0) {
            return "Correo ya existe";
        }

        $sql = "INSERT INTO usuarios (email, password, rol) VALUES ($1, $2, $3)";
        $res = pg_query_params($this->conexion, $sql, [$email, $password, $rol]);
        if ($res) {
            return true;
        } else {
            return "Error al registrar";
        }
    }

    public function actualizarPassword($email, $nuevaPassword)
    {
        $sql = "UPDATE usuarios SET password = $1 WHERE email = $2";
        $res = pg_query_params($this->conexion, $sql, [$nuevaPassword, $email]);
        if ($res) {
            return true;
        } else {
            return "Error al actualizar la contraseña";
        }
    }

    public function login($email, $password)
    {
        $sql = "SELECT * FROM usuarios WHERE email = $1";
        $res = pg_query_params($this->conexion, $sql, [$email]);

        if (pg_num_rows($res) == 1) {
            $user = pg_fetch_assoc($res);

            if ($password === $user['password']) {
                $updateSql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = $1";
                pg_query_params($this->conexion, $updateSql, [$user['id']]);

                return [
                    "ok" => true,
                    "id" => $user['id'],
                    "email" => $user['email'],
                    "rol" => $user['rol']
                ];
            } else {
                return "Contraseña incorrecta";
            }
        } else {
            return "Usuario no encontrado";
        }
    }

    public function existeEmail($email)
    {
        $sql = "SELECT id FROM usuarios WHERE email = $1";
        $res = pg_query_params($this->conexion, $sql, [$email]);
        return pg_num_rows($res) > 0;
    }

    // Método para generar y guardar token de recuperación
    public function generarTokenRecuperacion($email)
    {
        $token = bin2hex(random_bytes(32)); // Token seguro
        $expiracion = date("Y-m-d H:i:s", strtotime("+1 hour")); // Expira en 1 hora

        $sql = "UPDATE usuarios SET token_recuperacion = $1, token_expiracion = $2 WHERE email = $3";
        $res = pg_query_params($this->conexion, $sql, [$token, $expiracion, $email]);

        if ($res) {
            return $token;
        } else {
            return false;
        }
    }

    public function existeTokenValido($token)
    {
        $sql = "SELECT id FROM usuarios WHERE token_recuperacion = $1 AND token_expiracion > NOW()";
        $res = pg_query_params($this->conexion, $sql, [$token]);

        return pg_num_rows($res) > 0;
    }
}
