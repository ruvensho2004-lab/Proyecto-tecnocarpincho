<?php
require_once '../includes/security.php';
verificar_rol([1]); // Solo administradores

$nombre = $_SESSION['usuario']['nombre'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Administrador</title>
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
    <h3>Administrador: <?php echo htmlspecialchars($nombre); ?></h3>
</header>

<div class="menu">
    <a href="gestionar_materias.php"><i class="fas fa-book"></i> Materias</a>
    <a href="gestionar_periodos_actividades.php"><i class="fas fa-calendar"></i> Periodos y Actividades</a>
    <a href="gestionar_alumnos.php"><i class="fas fa-user-graduate"></i> Alumnos</a>
    <a href="gestionar_profesores.php"><i class="fas fa-chalkboard-teacher"></i> Profesores</a>
    <a href="registro_usuarios.php"><i class="fas fa-user-plus"></i> Registrar usuario</a>
    <a href="../cambiar_contraseña.php"><i class="fas fa-key"></i> Cambiar Contraseña</a>
    <a href="logout.php" class="float-end"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
</div>

<div class="container mt-4">
    <h4>Panel de administración</h4>
    <p>Desde aquí puedes gestionar toda la información del sistema académico.</p>
    
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