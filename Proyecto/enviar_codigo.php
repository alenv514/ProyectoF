<?php
session_start();
require_once './clases/Conexion.php'; // Asegúrate de tener esto
require_once './clases/Usuario.php';  
function generarCodigo($longitud = 4) {
    return str_pad(rand(0, 9999), $longitud, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emailinst']) && isset($_POST['emailrecover'])) {
    $emailinst = $_POST['emailinst'];
    $emailrecover = $_POST['emailrecover'];
      $usuario = new Usuario();
    if (!$usuario->existeEmail($emailinst)) {
        echo json_encode([
            'success' => false,
            'message' => 'El correo no está registrado.'
        ]);
        exit;
    }

    $codigo = generarCodigo();

    $_SESSION['codigo_verificacion'] = $codigo;
    $_SESSION['emailinst'] = $emailinst;

    // API Key de Resend (reemplaza con tu clave)
    $apiKey = 're_iyh7ybv9_N4eFFgUp96YLKWWaS6bN9Wb8';

    $data = [
        'from' => 'onboarding@resend.dev',
        'to' => [$emailrecover],
        'subject' => 'Código de recuperación de contraseña',
        'html' => "<p>Tu código de verificación es: <strong>{$codigo}</strong></p>"
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer {$apiKey}"
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 || $status === 202) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo.']);
    }
}
?>
