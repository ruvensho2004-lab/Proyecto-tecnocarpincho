<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Alumno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">

<div class="container bg-white p-4 rounded shadow">
    <h4>Registro de Nuevo Estudiante</h4>
    <form method="post" action="registrar_alumno_guardar.php">
        <div class="mb-3">
            <label>Nombre Completo</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Cédula</label>
            <input type="text" name="cedula" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Correo electrónico</label>
            <input type="email" name="correo" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Registrar</button>
    </form>
</div>

</body>
</html>
