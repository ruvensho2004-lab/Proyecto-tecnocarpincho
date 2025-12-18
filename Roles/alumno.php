<?php
// Mostrar errores (opcional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar sesión solo si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener el nombre del alumno
$nombre = $_SESSION['usuario']['nombre'] ?? 'Estudiante'
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Estudiante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        header { background-color: #001f3f; color: white; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        .logo { height: 50px; }
        .menu { background-color: #001f3f; padding: 1rem; }
        .menu a { color: white; margin-right: 15px; text-decoration: none; font-weight: bold; }
        .menu a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<header>
    <img src="../images/liceo_logo.png" alt="Logo del Liceo" class="logo">
    <h3>Estudiante: <?php echo htmlspecialchars($nombre); ?></h3>
</header>

<div class="menu">
    <a href="./notas/ver_notas.php">Ver Calificaciones</a>
    <a href="#">Actividades</a>
    <a href="#">Mi Perfil</a>
    <a href="logout.php" class="float-end">Cerrar sesión</a>
</div>

<div class="container mt-4">
    <h4>Bienvenido al panel del estudiante</h4>
    <p>Consulta tus notas, actividades y perfil desde esta sección.</p>
</div>

</body>
</html>
