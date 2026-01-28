<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Gestión de Alumnos</h2>";

// Test 1: Verificar security.php
echo "<h3>1. Verificando security.php</h3>";
if (file_exists('../includes/security.php')) {
    echo "✅ security.php existe<br>";
    require_once '../includes/security.php';
    echo "✅ security.php cargado correctamente<br>";
} else {
    echo "❌ security.php NO existe en: " . realpath('../includes/security.php') . "<br>";
    die();
}

// Test 2: Verificar conexión
echo "<h3>2. Verificando conexion.php</h3>";
if (file_exists('../includes/conexion.php')) {
    echo "✅ conexion.php existe<br>";
    require_once '../includes/conexion.php';
    echo "✅ conexion.php cargado correctamente<br>";
} else {
    echo "❌ conexion.php NO existe<br>";
    die();
}

// Test 3: Verificar sesión
echo "<h3>3. Verificando sesión</h3>";
if (isset($_SESSION['usuario'])) {
    echo "✅ Sesión activa<br>";
    echo "Usuario: " . $_SESSION['usuario']['usuario'] . "<br>";
    echo "Rol: " . $_SESSION['usuario']['rol'] . "<br>";
} else {
    echo "❌ No hay sesión activa<br>";
    echo "<a href='../login.html'>Ir al login</a><br>";
    die();
}

// Test 4: Verificar permisos
echo "<h3>4. Verificando permisos</h3>";
if ($_SESSION['usuario']['rol'] == 1) {
    echo "✅ Usuario es administrador<br>";
} else {
    echo "❌ Usuario NO es administrador (rol: " . $_SESSION['usuario']['rol'] . ")<br>";
    die();
}

// Test 5: Verificar tabla alumnos
echo "<h3>5. Verificando tabla alumnos</h3>";
try {
    $sql = "SELECT COUNT(*) as total FROM alumnos";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla alumnos existe<br>";
    echo "Total de alumnos: " . $result['total'] . "<br>";
} catch (Exception $e) {
    echo "❌ Error con tabla alumnos: " . $e->getMessage() . "<br>";
}

// Test 6: Verificar tabla usuarios
echo "<h3>6. Verificando tabla usuarios</h3>";
try {
    $sql = "SELECT COUNT(*) as total FROM usuarios";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla usuarios existe<br>";
    echo "Total de usuarios: " . $result['total'] . "<br>";
} catch (Exception $e) {
    echo "❌ Error con tabla usuarios: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>✅ Todas las verificaciones pasaron!</h3>";
echo "<p><a href='gestionar_alumnos.php'>Ir a gestionar_alumnos.php</a></p>";
?>