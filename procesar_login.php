<?php
session_start();
require "includes/conexion.php";

$usuario = $_POST['usuario'] ?? '';
$clave = trim($_POST['clave'] ?? '');
$tipo = $_POST['tipo'] ?? '';

try {
    if (empty($usuario) || empty($clave) || empty($tipo)) {
        throw new Exception("Todos los campos son obligatorios.");
    }

    switch ($tipo) {
        case 'administrador':
            $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1 AND rol = 1";
            break;
        case 'profesor':
            $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1 AND rol = 3";
            break;
        case 'alumno':
            $sql = "SELECT * FROM usuarios WHERE usuario = :usuario AND estado = 1 AND rol = 4";
            break;    
        default:
            throw new Exception("Rol inválido.");
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['usuario' => $usuario]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Usuario no encontrado o inactivo.");
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($clave, $user['clave'])) {
        throw new Exception("Contraseña incorrecta.");
    }

    // 🔹 AHORA SÍ SE PUEDE GUARDAR EN SESIÓN
    $_SESSION['usuario'] = [
        "id" => $user['id'],
        "usuario" => $user['usuario'],
        "rol" => $user['rol']
    ];

    // 🔹 REDIRECCIÓN SEGÚN ROL
    switch ($user['rol']) {
        case 1: header("Location: Roles/admin.php"); break;
        case 3: header("Location: Roles/profesores.php"); break;
        case 4: header("Location: Roles/alumno.php"); break;
        default: throw new Exception("Rol desconocido.");
    }

    exit;

} catch (Exception $e) {
    echo "❌ " . $e->getMessage();
}