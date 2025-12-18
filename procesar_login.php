<?php
session_start();
include_once("includes/conexion.php");

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

$clave = trim($_POST['clave'] ?? '');

if (!isset($user['clave']) || !password_verify($clave, $user['clave'])) {
    throw new Exception("Contraseña incorrecta.");
}

// Si todo bien, guardar datos en sesión

    

    $_SESSION['usuario'] = $user;
    $_SESSION['rol'] = $user['rol'];

    // Devolver destino según el rol
    switch ($user['rol']) {
        case 1: echo 'OK:Roles/admin.php'; break;
        case 2: echo 'OK:Roles/asistente.php'; break;
        case 3: echo 'OK:Roles/profesores.php'; break;
        case 4: echo 'OK:Roles/alumno.php'; break;
        default: throw new Exception("Rol desconocido.");
    }

} catch (Exception $e) {
    echo "❌ " . $e->getMessage();
}
