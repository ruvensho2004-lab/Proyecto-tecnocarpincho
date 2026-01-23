<?php
// Mostrar errores (opcional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if ($_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$nombre = $_SESSION['usuario']['nombre'] ?? 'Administrador';


if ($rol !== 1) {
    echo "PERSONAL AUTORIZADO.";
    exit();
}
?>

<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    header("Location: login.html");
    exit();
}
$nombre = $_SESSION['usuario']['nombre'];
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
    <a href="#">Usuarios</a>
    <a href="#">Grados</a>
    <a href="#">Aulas</a>
    <a href="#">Materias</a>
    <a href="#">Periodos</a>
    <a href="#">Reportes</a>
    <a href="../registro_usuario.php">Registrar usuario</a>
    <a href="logout.php" class="float-end">Cerrar sesión</a>
</div>

<div class="container mt-4">
    <h4>Panel de administración</h4>
    <p>Desde aquí puedes gestionar toda la información del sistema académico.</p>
</div>

</body>
</html>
