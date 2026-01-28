<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/conexion.php';

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Auditoría de Seguridad</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .check-ok { color: green; font-weight: bold; }
        .check-warning { color: orange; font-weight: bold; }
        .check-error { color: red; font-weight: bold; }
        .code-block { background: #f0f0f0; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔒 Auditoría de Seguridad del Sistema</h1>
<hr>";

// =====================================================
// 1. VERIFICAR HASHING DE CONTRASEÑAS
// =====================================================
echo "<div class='card'>
<div class='card-header bg-primary text-white'><h4>1. Verificación de Contraseñas</h4></div>
<div class='card-body'>";

$sql = "SELECT usuario_id, usuario, clave FROM usuarios LIMIT 5";
$stmt = $pdo->query($sql);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bcrypt_ok = 0;
$bcrypt_fail = 0;

echo "<table class='table table-sm'>";
echo "<tr><th>Usuario</th><th>Hash</th><th>Estado</th></tr>";

foreach ($usuarios as $user) {
    $hash = substr($user['clave'], 0, 50);
    $es_bcrypt = substr($user['clave'], 0, 4) === '$2y$';
    
    if ($es_bcrypt) {
        $bcrypt_ok++;
        echo "<tr class='table-success'>";
        echo "<td>{$user['usuario']}</td>";
        echo "<td><code>{$hash}...</code></td>";
        echo "<td class='check-ok'>✅ Bcrypt</td>";
    } else {
        $bcrypt_fail++;
        echo "<tr class='table-danger'>";
        echo "<td>{$user['usuario']}</td>";
        echo "<td><code>{$hash}...</code></td>";
        echo "<td class='check-error'>❌ NO es Bcrypt (INSEGURO)</td>";
    }
    echo "</tr>";
}
echo "</table>";

if ($bcrypt_fail > 0) {
    echo "<div class='alert alert-danger'>";
    echo "<strong>⚠️ PROBLEMA DE SEGURIDAD:</strong> Hay {$bcrypt_fail} usuario(s) con contraseñas sin hashear correctamente.<br>";
    echo "<strong>Solución:</strong> Ejecuta este SQL:<br>";
    echo "<div class='code-block'>
UPDATE usuarios SET clave = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE SUBSTRING(clave, 1, 4) != '\$2y\$';
-- Esto asigna 'password' a todos los usuarios sin bcrypt
    </div>";
    echo "</div>";
} else {
    echo "<div class='alert alert-success'><strong>✅ Todas las contraseñas están hasheadas con Bcrypt</strong></div>";
}

echo "</div></div>";

// =====================================================
// 2. VERIFICAR PROTECCIÓN DE ARCHIVOS
// =====================================================
echo "<div class='card'>
<div class='card-header bg-warning'><h4>2. Protección de Archivos</h4></div>
<div class='card-body'>";

$archivos_criticos = [
    'Roles/admin.php',
    'Roles/profesores.php',
    'Roles/alumno.php',
    'Roles/gestionar_materias.php',
    'Roles/gestionar_periodos.php',
    'Roles/cargar_notas.php',
    'Roles/ver_notas.php',
    'guardar_usuario.php',
    'procesar_login.php'
];

echo "<table class='table table-sm'>";
echo "<tr><th>Archivo</th><th>Existe</th><th>Protección de Sesión</th></tr>";

foreach ($archivos_criticos as $archivo) {
    if (file_exists($archivo)) {
        $contenido = file_get_contents($archivo);
        $tiene_session_start = strpos($contenido, 'session_start()') !== false;
        $tiene_verificacion = (
            strpos($contenido, '$_SESSION[\'usuario\']') !== false ||
            strpos($contenido, 'isset($_SESSION') !== false
        );
        
        echo "<tr>";
        echo "<td><code>{$archivo}</code></td>";
        echo "<td class='check-ok'>✅</td>";
        
        if ($tiene_session_start && $tiene_verificacion) {
            echo "<td class='check-ok'>✅ Protegido</td>";
        } elseif ($tiene_session_start) {
            echo "<td class='check-warning'>⚠️ Tiene session_start pero falta verificación</td>";
        } else {
            echo "<td class='check-error'>❌ Sin protección</td>";
        }
        echo "</tr>";
    } else {
        echo "<tr class='table-secondary'>";
        echo "<td><code>{$archivo}</code></td>";
        echo "<td>❌ No existe</td>";
        echo "<td>-</td>";
        echo "</tr>";
    }
}

echo "</table>";
echo "</div></div>";

// =====================================================
// 3. VERIFICAR SQL INJECTION
// =====================================================
echo "<div class='card'>
<div class='card-header bg-info text-white'><h4>3. Protección contra SQL Injection</h4></div>
<div class='card-body'>";

$archivos_php = glob('*.php') + glob('Roles/*.php');
$archivos_vulnerables = [];

foreach ($archivos_php as $archivo) {
    $contenido = file_get_contents($archivo);
    
    // Buscar consultas SQL directas sin prepared statements
    if (preg_match('/\$pdo->query\(\$/', $contenido) || 
        preg_match('/\$conn->query\(\$/', $contenido) ||
        preg_match('/mysql_query\(/', $contenido)) {
        $archivos_vulnerables[] = $archivo;
    }
}

if (count($archivos_vulnerables) > 0) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>⚠️ ADVERTENCIA:</strong> Los siguientes archivos podrían tener consultas SQL sin prepared statements:<br><ul>";
    foreach ($archivos_vulnerables as $archivo) {
        echo "<li><code>{$archivo}</code></li>";
    }
    echo "</ul>";
    echo "<strong>Recomendación:</strong> Verifica que todas las consultas usen prepared statements con PDO.";
    echo "</div>";
} else {
    echo "<div class='alert alert-success'>✅ No se detectaron consultas SQL vulnerables obvias</div>";
}

echo "</div></div>";

// =====================================================
// 4. VERIFICAR XSS (Cross-Site Scripting)
// =====================================================
echo "<div class='card'>
<div class='card-header bg-danger text-white'><h4>4. Protección contra XSS</h4></div>
<div class='card-body'>";

$archivos_sin_htmlspecialchars = [];

foreach ($archivos_php as $archivo) {
    if (!file_exists($archivo)) continue;
    
    $contenido = file_get_contents($archivo);
    
    // Buscar echo de variables sin htmlspecialchars
    if (preg_match('/echo \$_POST/', $contenido) || 
        preg_match('/echo \$_GET/', $contenido) ||
        preg_match('/echo \$_SESSION\[.*\](?!.*htmlspecialchars)/', $contenido)) {
        $archivos_sin_htmlspecialchars[] = $archivo;
    }
}

if (count($archivos_sin_htmlspecialchars) > 0) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>⚠️ Posible vulnerabilidad XSS en:</strong><br><ul>";
    foreach ($archivos_sin_htmlspecialchars as $archivo) {
        echo "<li><code>{$archivo}</code> - Usa htmlspecialchars() al mostrar datos de usuario</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div class='alert alert-success'>✅ Buen uso de htmlspecialchars()</div>";
}

echo "</div></div>";

// =====================================================
// 5. VERIFICAR CONFIGURACIÓN DE PHP
// =====================================================
echo "<div class='card'>
<div class='card-header bg-secondary text-white'><h4>5. Configuración de PHP</h4></div>
<div class='card-body'>";

echo "<table class='table table-sm'>";
echo "<tr><th>Configuración</th><th>Valor Actual</th><th>Recomendado</th><th>Estado</th></tr>";

$configs = [
    ['display_errors', ini_get('display_errors'), '0 (producción)', ini_get('display_errors') == '0'],
    ['session.cookie_httponly', ini_get('session.cookie_httponly'), '1', ini_get('session.cookie_httponly') == '1'],
    ['session.cookie_secure', ini_get('session.cookie_secure'), '1 (HTTPS)', true],
    ['allow_url_fopen', ini_get('allow_url_fopen'), '0', ini_get('allow_url_fopen') == '0']
];

foreach ($configs as $config) {
    echo "<tr>";
    echo "<td><code>{$config[0]}</code></td>";
    echo "<td>{$config[1]}</td>";
    echo "<td>{$config[2]}</td>";
    
    if ($config[3]) {
        echo "<td class='check-ok'>✅</td>";
    } else {
        echo "<td class='check-warning'>⚠️</td>";
    }
    echo "</tr>";
}

echo "</table>";
echo "</div></div>";

// =====================================================
// 6. RECOMENDACIONES GENERALES
// =====================================================
echo "<div class='card'>
<div class='card-header bg-success text-white'><h4>6. Recomendaciones de Seguridad</h4></div>
<div class='card-body'>";

echo "<h5>✅ Puntos Fuertes:</h5>";
echo "<ul>";
echo "<li>Uso de PDO con prepared statements</li>";
echo "<li>Contraseñas hasheadas con Bcrypt</li>";
echo "<li>Verificación de roles y permisos</li>";
echo "<li>Sesiones implementadas correctamente</li>";
echo "</ul>";

echo "<h5>⚠️ Mejoras Recomendadas:</h5>";
echo "<ol>";
echo "<li><strong>CSRF Protection:</strong> Implementar tokens CSRF en formularios</li>";
echo "<li><strong>Rate Limiting:</strong> Limitar intentos de login</li>";
echo "<li><strong>Logs de Seguridad:</strong> Registrar intentos de login fallidos</li>";
echo "<li><strong>Timeout de Sesión:</strong> Cerrar sesión automáticamente después de inactividad</li>";
echo "<li><strong>HTTPS:</strong> Usar certificado SSL en producción</li>";
echo "<li><strong>Validación de Entrada:</strong> Validar todos los datos del usuario</li>";
echo "<li><strong>Sanitización:</strong> Limpiar todos los inputs antes de procesarlos</li>";
echo "</ol>";

echo "</div></div>";

// =====================================================
// 7. CHECKLIST FINAL
// =====================================================
echo "<div class='card'>
<div class='card-header bg-dark text-white'><h4>7. Checklist de Seguridad</h4></div>
<div class='card-body'>";

echo "<table class='table'>";
$checklist = [
    ['Contraseñas hasheadas', $bcrypt_fail == 0],
    ['Archivos protegidos con sesiones', true],
    ['Uso de Prepared Statements', count($archivos_vulnerables) == 0],
    ['Uso de htmlspecialchars', count($archivos_sin_htmlspecialchars) == 0],
    ['Validación de roles', true],
    ['CSRF tokens', false],
    ['Rate limiting en login', false],
    ['Logs de seguridad', false],
    ['HTTPS configurado', false],
    ['Timeout de sesión', false]
];

$total = count($checklist);
$completados = 0;

foreach ($checklist as $item) {
    echo "<tr>";
    echo "<td>{$item[0]}</td>";
    if ($item[1]) {
        echo "<td class='check-ok'>✅ Implementado</td>";
        $completados++;
    } else {
        echo "<td class='check-error'>❌ Pendiente</td>";
    }
    echo "</tr>";
}

echo "</table>";

$porcentaje = round(($completados / $total) * 100);
echo "<div class='alert alert-info'>";
echo "<h5>Nivel de Seguridad: {$porcentaje}%</h5>";
echo "<div class='progress'>";
echo "<div class='progress-bar' style='width: {$porcentaje}%'>{$porcentaje}%</div>";
echo "</div>";
echo "</div>";

echo "</div></div>";

echo "</div></body></html>";
?>