<?php
// se conecta a la base
require_once 'Conexion.php';

class Usuario {
    private $conexion;

    public function __construct() {
        $this->conexion = Conexion::conectar();
    }

    // resgitra los usuarios
    public function registrar($email, $password, $rol = 'usuario') {
        $password_simple = $password;

        $verificar = $this->conexion->query("SELECT id FROM usuarios WHERE email = '$email'");
        if ($verificar->num_rows > 0) {
            return "Correo ya existe";
        }

        $sql = "INSERT INTO usuarios (email, password, rol) VALUES ('$email', '$password_simple', '$rol')";
        if ($this->conexion->query($sql)) {
            return true;
        } else {
            return "Error al registrar";
        }
    }

    public function login($email, $password) {
        $sql = "SELECT * FROM usuarios WHERE email = '$email'";
        $res = $this->conexion->query($sql);

        if ($res->num_rows == 1) {
            $user = $res->fetch_assoc();

            if ($password === $user['password']) {
                $this->conexion->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = " . $user['id']);

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
}
?>
