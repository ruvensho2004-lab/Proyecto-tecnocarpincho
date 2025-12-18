<?php
    $alert = "";
    session_start();

    if(!empty($_SESSION['active'])) {
        header('Location: sistema/');
    } else {
        if(!empty($_POST)) {
            if(empty($_POST['usuario']) || empty($_POST['pass'])) {
                $alert = 'Todos los campos son necesarios';
            } else {
                require_once 'sistema/includes/config.php';
                $usuario = $_POST['usuario'];
                $pass = $_POST['pass'];

                $sql = "SELECT u.user_id,u.nombre,u.usuario,u.password,r.rol_id,r.nombre_rol FROM usuarios as u INNER JOIN rol as r ON u.rol = r.rol_id WHERE u.usuario = ?";
                $query = $pdo->prepare($sql);
                $query->execute(array($usuario));
                $data = $query->fetch();

                if(password_verify($pass, $data['password'])) {
                    $_SESSION['active'] = true;
                    $_SESSION['idUser'] = $data['user_id'];
                    $_SESSION['nombre'] = $data['nombre'];
                    $_SESSION['user'] = $data['usuario'];
                    $_SESSION['rol'] = $data['rol_id'];
                    $_SESSION['rol_name'] = $data['nombre_rol'];
                    $_SESSION['tiempo'];

                    header("Location: sistema/");
                } else {
                    $alert = 'El usuario o la clave son incorrectos';
                    session_destroy();
                }
            }
        }
    }
    ?>
    <!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso al Sistema Académico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #001f3f;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background-color: #ffffff;
            color: #333;
            padding: 2rem;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
        }
        .login-box img {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        .form-select, .form-control {
            margin-bottom: 1rem;
        }
        .btn-primary {
            background-color: #001f3f;
            border: none;
        }
        .btn-primary:hover {
            background-color: #003366;
        }
        #mensaje {
            margin-top: 1rem;
            color: red;
        }
    </style>
</head>
<body>

<div class="login-box text-center">
    <img src="./images/liceo_logo.png" alt="Logo del Liceo">
    <h4 class="mb-4">Sistema Académico</h4>
    <form id="form-login">
        <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
        <input type="password" name="clave" class="form-control" placeholder="Contraseña" required>
        <select name="tipo" class="form-select" required>
            <option value="">Selecciona tu rol</option>
            <option value="administrador">Administrador</option>
            <option value="profesor">Profesor</option>
            <option value="alumno">Alumno</option>
        </select>
        <button type="submit" class="btn btn-primary w-100">Ingresar</button>
    </form>
    <div id="mensaje"></div>
</div>

<script>
document.getElementById('form-login').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = e.target;
    const datos = new FormData(form);
    const mensaje = document.getElementById('mensaje');
    mensaje.innerText = '';

    try {
        const res = await fetch('procesar_login.php', {
            method: 'POST',
            body: datos
        });

        const text = await res.text();

        if (text.startsWith('OK:')) {
            const destino = text.substring(3); // Elimina 'OK:' y redirige
            window.location.href = destino;
        } else {
            mensaje.innerText = text;
        }

    } catch (err) {
        mensaje.innerText = "Error de conexión con el servidor.";
    }
});
</script>

</body>
</html>
