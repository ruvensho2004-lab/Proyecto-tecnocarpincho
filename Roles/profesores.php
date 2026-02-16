<?php
require_once '../includes/security.php';
verificar_rol([3]); // Solo profesores

$nombre = $_SESSION['usuario']['nombre'] ?? 'Profesor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Docente</title>
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
    <h3>Profesor: <?php echo htmlspecialchars($nombre); ?></h3>
</header>

<div class="menu">
    <a href="cargar_notas_profesor.php"><i class="fas fa-clipboard-list"></i> Cargar Notas</a>
    <a href="gestionar_actividades.php"><i class="fas fa-tasks"></i> Actividades</a>
    <a href="lista_estudiantes_profesor.php"><i class="fas fa-users"></i> Lista de Alumnos</a>>
    <a href="#"><i class="fas fa-chart-bar"></i> Reportes</a>
    <a href="mi_perfil.php"><i class="fas fa-user-circle"></i> Mi Perfil</a>
    <a href="logout.php" class="float-end"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
</div>

<div class="container mt-4">
    <h4>Bienvenido al panel docente</h4>
    <p>Aquí puedes gestionar tus materias, ingresar calificaciones y ver los grupos asignados.</p>
    
    <!-- Debug info (puedes eliminar esto después) -->
    <div class="alert alert-info mt-3">
        <strong>Sesión activa:</strong><br>
        Usuario: <?php echo htmlspecialchars($_SESSION['usuario']['usuario']); ?><br>
        Rol: <?php echo $_SESSION['usuario']['rol']; ?><br>
        ID: <?php echo $_SESSION['usuario']['id']; ?>
    </div>
</div>

</body>
</html>