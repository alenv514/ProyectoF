<?php
class Conexion {
    private static $host = "localhost";
    private static $usuario = "root";
    private static $password = "2805";
    private static $bd = "sistema_estacionamiento";

    public static function conectar() {
        $conexion = new mysqli(self::$host, self::$usuario, self::$password, self::$bd);
        if ($conexion->connect_error) {
            die("Error de conexión: " . $conexion->connect_error);
        }
        $conexion->set_charset("utf8");
        return $conexion;
    }
}
?>
