<?php
session_start();
require_once 'clases/Usuario.php';

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = new Usuario();
    $resultado = $usuario->registrar($_POST['email'], $_POST['password'], $_POST['rol']);

    if ($resultado === true) {
        $_SESSION['mensaje'] = "Registro exitoso. Inicia sesión.";
        header("Location: login.php");
        exit();
    } else {
        $mensaje = $resultado;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Usuario - UTA</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #ffffff;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .main-container {
      display: flex;
      background: #fff;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      border-radius: 12px;
      overflow: hidden;
      max-width: 800px;
      width: 90%;
    }
    .left-side {
      background-color: #fff;
      padding: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 50%;
    }
    .left-side img {
      max-width: 100%;
      height: auto;
    }
    .right-side {
      padding: 40px;
      width: 50%;
    }
    .right-side h2 {
      margin-bottom: 20px;
      font-size: 22px;
      color: #b30000;
      text-align: center;
    }
    label {
      display: block;
      text-align: left;
      margin-bottom: 5px;
      font-size: 13px;
      color: #800000;
    }
    input[type="email"], input[type="password"], select {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #f4c2c2;
      border-radius: 4px;
      box-sizing: border-box;
    }
    .btn-submit {
      background-color: #b30000;
      color: white;
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 4px;
      font-size: 15px;
      margin-top: 10px;
      cursor: pointer;
    }
    .btn-submit:hover {
      background-color: #990000;
    }
    .links {
      margin-top: 15px;
      font-size: 12px;
      text-align: center;
    }
    .links a {
      color: #cc0000;
      text-decoration: none;
    }
    .links a:hover {
      text-decoration: underline;
    }
    .mensaje {
      text-align: center;
      color: red;
      margin-bottom: 10px;
      font-size: 14px;
    }
    @media (max-width: 768px) {
      .main-container {
        flex-direction: column;
      }
      .left-side, .right-side {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="main-container">
    <div class="left-side">
      <img src="https://obest.uta.edu.ec/wp-content/uploads/2024/12/logo-uta.png" alt="Logo UTA">
    </div>
    <div class="right-side">
      <h2>Registro de Usuario</h2>

      <?php if ($mensaje): ?>
        <div class="mensaje"><?php echo $mensaje; ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <label for="email">Correo:</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required>

        <label for="rol">Rol:</label>
        <select id="rol" name="rol" required>
          <option value="usuario">Usuario</option>
          <option value="invitado">Invitado</option>
          <option value="admin">Administrador</option>
        </select>

        <button type="submit" class="btn-submit">Registrarse</button>
      </form>

      <div class="links">
        <a href="login.php">¿Ya tienes cuenta? Inicia sesión</a>
      </div>
    </div>
  </div>
</body>
</html>
