<?php
session_start();

// Verificar que el usuario sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    http_response_code(403);
    exit("❌ No autorizado. Solo administradores pueden registrar usuarios.");
}

// Incluir la conexión
require_once 'includes/conexion.php';

// Validar que llegaron todos los datos
if (empty($_POST['usuario']) || empty($_POST['clave']) || empty($_POST['rol'])) {
    exit("❌ Todos los campos son obligatorios");
}

$nombre = trim($_POST['nombre'] ?? $_POST['usuario']);
$usuario = trim($_POST['usuario']);
$email = trim($_POST['email'] ?? '');
$clave = $_POST['clave'];
$rol = (int)$_POST['rol'];

// Validar rol
if (!in_array($rol, [1, 2, 3, 4])) {
    exit("❌ Rol inválido");
}

// Validar longitud de contraseña
if (strlen($clave) < 6) {
    exit("❌ La contraseña debe tener al menos 6 caracteres");
}

try {
    // Verificar si el usuario ya existe
    $sqlCheck = "SELECT usuario_id FROM usuarios WHERE usuario = :usuario";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute(['usuario' => $usuario]);
    
    if ($stmtCheck->rowCount() > 0) {
        exit("❌ El usuario '$usuario' ya existe. Elige otro nombre de usuario.");
    }
    
    // Hashear la contraseña
    $passwordHash = password_hash($clave, PASSWORD_DEFAULT);
    
    // Insertar nuevo usuario
    $sql = "INSERT INTO usuarios (nombre, usuario, clave, rol, estado) 
            VALUES (:nombre, :usuario, :clave, :rol, 1)";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([
        'nombre' => $nombre,
        'usuario' => $usuario,
        'clave' => $passwordHash,
        'rol' => $rol
    ]);
    
    if ($resultado) {
        $rol_nombre = ['1' => 'Administrador', '2' => 'Asistente', '3' => 'Profesor', '4' => 'Alumno'][$rol];
        echo "✅ Usuario registrado correctamente<br>";
        echo "<strong>Usuario:</strong> $usuario<br>";
        echo "<strong>Rol:</strong> $rol_nombre<br>";
        echo "<strong>Contraseña temporal:</strong> $clave<br>";
        echo "<small>Comparte estas credenciales con el usuario.</small>";
    } else {
        exit("❌ Error al registrar usuario");
    }
    
} catch (PDOException $e) {
    error_log("Error al registrar usuario: " . $e->getMessage());
    exit("❌ Error al registrar usuario: " . $e->getMessage());
}
?>