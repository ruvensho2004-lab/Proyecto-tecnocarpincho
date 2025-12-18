<?php
session_start();
include_once("../../includes/conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $cedula = trim($_POST['cedula']);
    $correo = trim($_POST['correo']);

    // Generar usuario a partir de la cédula y clave cifrada
    $usuario = $cedula;
    $clave = password_hash($cedula, PASSWORD_DEFAULT); // clave por defecto = cédula

    try {
        // Verifica duplicado
        $check = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $check->execute([$usuario]);
        if ($check->rowCount() > 0) {
            throw new Exception("Ya existe un usuario con esa cédula.");
        }

        // Insertar en alumnos
        $stmtAlumno = $pdo->prepare("INSERT INTO alumnos (nombre_alumno, cedula, correo, estado) VALUES (?, ?, ?, 1)");
        $stmtAlumno->execute([$nombre, $cedula, $correo]);

        // Insertar en usuarios
        $stmtUsuario = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, clave, rol, estado) VALUES (?, ?, ?, 4, 1)");
        $stmtUsuario->execute([$nombre, $usuario, $clave]);

        echo "✅ Estudiante registrado correctamente con usuario: <strong>$usuario</strong> y clave: <strong>$cedula</strong>";
        echo "<br><a href='registrar_alumno.php'>Registrar otro</a>";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
        echo "<br><a href='registrar_alumno.php'>Volver</a>";
    }
}
?>