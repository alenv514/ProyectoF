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

    // resgitra los usuarios
    public function registrar($email, $password, $rol = 'usuario')
    {
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

    public function actualizarPassword($email, $nuevaPassword)
    {
        $stmt = $this->conexion->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $nuevaPassword, $email);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return "Error al actualizar la contraseña";
        }
    }


    public function login($email, $password)
    {
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


    public function existeEmail($email)
    {
        $conexion = Conexion::conectar();
        $sql = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;

        // Cierra la conexión y el statement
        $stmt->close();
        $conexion->close();

        return $existe;
    }

    // Método para generar y guardar token de recuperación
    public function generarTokenRecuperacion($email)
    {
        $token = bin2hex(random_bytes(32)); // Token seguro
        $expiracion = date("Y-m-d H:i:s", strtotime("+1 hour")); // Expira en 1 hora

        $conexion = Conexion::conectar();
        $sql = "UPDATE usuarios SET token_recuperacion = ?, token_expiracion = ? WHERE email = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sss", $token, $expiracion, $email);

        if ($stmt->execute()) {
            return $token;
        } else {
            return false;
        }
    }

    // Método para actualizar la contraseña


    public function existeTokenValido($token)
    {
        $conexion = Conexion::conectar();
        $sql = "SELECT id FROM usuarios WHERE token_recuperacion = ? AND token_expiracion > NOW()";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }
}
