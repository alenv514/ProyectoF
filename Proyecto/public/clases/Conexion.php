<?php
class Conexion {
    // Configuración para producción o Supabase
    private static $host     = "aws-0-us-east-2.pooler.supabase.com";
    private static $usuario  = "postgres.nyjlglghxxpaypzmffvj";
    private static $password = "CGEC19@a123";
    private static $bd       = "postgres";

    public static function conectar() {
        $conn_string = 
            "host="     . self::$host     . " " .
            "dbname="   . self::$bd       . " " .
            "user="     . self::$usuario  . " " .
            "password=" . self::$password;

        $conexion = @pg_connect($conn_string . " connect_timeout=5");

        if (!$conexion) {
            error_log("[" . date("Y-m-d H:i:s") . "] Error de conexión a PostgreSQL");
            return false;
        }

        pg_set_client_encoding($conexion, "UTF8");
        return $conexion;
    }

    // Método para verificar el estado del servidor
    public static function verificarServidor() {
        $conn = self::conectar();
        
        if (!$conn) {
            return false;
        }
        
        // Intentar una consulta simple para verificar que la conexión funciona
        $result = @pg_query($conn, "SELECT 1");
        $status = ($result !== false);
        
        pg_close($conn);
        return $status;
    }
}
?>