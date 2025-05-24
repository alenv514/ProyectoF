<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recuperar contraseña - UTA</title>
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

        h2 {
            margin-bottom: 20px;
            font-size: 22px;
            color: #b30000;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #800000;
        }

        input {
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
            cursor: pointer;
        }

        .btn-submit:hover {
            background-color: #990000;
        }

        #codigoModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 450px;
            width: 100%;
        }

        #codigoError {
            color: red;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }

            .left-side,
            .right-side {
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
            <h2>Recuperar contraseña</h2>
            <form onsubmit="return enviarCorreo();">
                <label for="emailinst">Correo Institucional:</label>
                <input type="email" id="emailinst" name="emailinst" required>
                <label for="emailrecover">Correo Personal (Recuperación Contraseña):</label>
                <input type="email" id="emailrecover" name="emailrecover" required>
                <button type="submit" class="btn-submit">Enviar código</button>
            </form>
        </div>
    </div>

    <!-- Modal de verificación y cambio de contraseña -->
    <div id="codigoModal">
        <div class="modal-content">
            <h3>Verifica tu código</h3>
            <p>Ingresa el código de 4 dígitos enviado a tu correo personal.</p>
            <input type="text" id="codigoIngresado" maxlength="4" placeholder="Código">
            <input type="password" id="nuevaPassword" placeholder="Nueva contraseña">
            <input type="password" id="confirmarPassword" placeholder="Confirmar contraseña">
            <button onclick="verificarCodigo()" class="btn-submit">Verificar y cambiar</button>
            <p id="codigoError"></p>
        </div>
    </div>

    <script>
        function enviarCorreo() {
        
                const emailinst = document.getElementById('emailinst').value;
                const emailrecover = document.getElementById('emailrecover').value;

                fetch('enviar_codigo.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `emailinst=${encodeURIComponent(emailinst)}&emailrecover=${encodeURIComponent(emailrecover)}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('codigoModal').style.display = 'flex';
                        } else {
                            alert(data.message || 'Error al enviar correo.');
                        }
                    });

                return false;
           
        }

        function verificarCodigo() {
            const codigo = document.getElementById('codigoIngresado').value;
            const nuevaPassword = document.getElementById('nuevaPassword').value;
            const confirmarPassword = document.getElementById('confirmarPassword').value;
            const errorElem = document.getElementById('codigoError');
            errorElem.innerText = '';

            // Validaciones
            if (nuevaPassword !== confirmarPassword) {
                errorElem.innerText = 'Las contraseñas no coinciden.';
                return;
            }

            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}$/;
            if (!regex.test(nuevaPassword)) {
                errorElem.innerText = 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un carácter especial.';
                return;
            }

            // Verificar código
            fetch('verificar_codigo.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `codigo=${encodeURIComponent(codigo)}&password=${encodeURIComponent(nuevaPassword)}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'login.php';
                    } else {
                        errorElem.innerText = 'Código incorrecto.';
                    }
                });
        }
    </script>
</body>

</html>