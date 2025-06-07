<?php
class Conexion {
    // Comenta o borra los valores de Supabase:
    private static $host     = "aws-0-us-east-2.pooler.supabase.com";
    private static $usuario  = "postgres.nyjlglghxxpaypzmffvj";
    private static $password = "CGEC19@a123";
    private static $bd       = "postgres";

    // Configura estos valores para tu instalación local:
   // private static $host     = "localhost";
//    private static $usuario  = "postgres";          // o el usuario que configuraste
  //  private static $password = "1234";     // reemplázala con tu clave real
  //  private static $bd       = "parqueo_db";        // nombre exacto de tu base creación

//private static $host = "localhost";
   // private static $usuario = "root";
    //private static $password = "2805";
    //private static $bd = "sistema_estacionamiento";
    //public static function conectar() {
       // $conexion = new mysqli(self::$host, self::$usuario, self::$password, self::$bd);
       // if ($conexion->connect_error) {
         //   die("Error de conexión: " . $conexion->connect_error);
        //}
        //$conexion->set_charset("utf8");
       // return $conexion;
    //}

    public static function conectar() {
        $conn_string = 
            "host="     . self::$host   . " " .
            "dbname="   . self::$bd     . " " .
            "user="     . self::$usuario . " " .
            "password=" . self::$password;

        $conexion = pg_connect($conn_string);
        if (!$conexion) {
            die("Error de conexión a PostgreSQL");
        }
        // Configurar la codificación a UTF-8
        pg_set_client_encoding($conexion, "UTF8");
        return $conexion;
    }
}
?>


