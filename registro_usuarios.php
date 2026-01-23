<?php
session_start();
if ($_SESSION['admin'] != 1) {
    exit("❌ No autorizado");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registrar Usuario</title>
</head>
<body>
<h2>Registrar Usuario</h2>

<form action="guardar_usuario.php" method="POST">
    <label>Usuario: </label>
    <input type="text" name="usuario" required><br><br>

    <label>Email: </label>
    <input type="email" name="email" required><br><br>

    <label>Contraseña: </label>
    <input type="password" name="clave" required><br><br>

    <label>Rol: </label>
    <select name="rol" required>
        <option value="1">Administrador</option>
        <option value="3">Profesor</option>
        <option value="4">Alumno</option>
    </select><br><br>

    <button type="submit">Registrar</button>
</form>

</body>
</html>
