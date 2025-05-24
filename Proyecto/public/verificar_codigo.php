<?php
session_start();
require_once './clases/Usuario.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $codigoIngresado = $_POST['codigo'];
    if (isset($_SESSION['codigo_verificacion']) && $_SESSION['codigo_verificacion'] === $codigoIngresado) {
        $usuario = new Usuario();
        $nuevaPassword = $_POST['password'] ?? '';
        $email = $_SESSION['emailinst'] ?? null;
        
        $resultado = $usuario->actualizarPassword($email, $nuevaPassword);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
