<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 4) {
    header("Location: ../login.html");
    exit();
}

$nombre = $_SESSION['usuario']['nombre'] ?? 'Estudiante';
$usuario = $_SESSION['usuario']['usuario'] ?? '';
$id = $_SESSION['usuario']['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Estudiante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f4f4f4; 
            margin: 0;
            padding: 0;
        }
        header { 
            background-color: #001f3f; 
            color: white; 
            padding: 1rem 2rem; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }
        .logo { height: 50px; }
        .menu { 
            background-color: #001f3f; 
            padding: 1rem; 
        }
        .menu a { 
            color: white; 
            margin-right: 15px; 
            text-decoration: none; 
            font-weight: bold; 
        }
        .menu a:hover { 
            text-decoration: underline; 
        }
        .welcome-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #001f3f;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<header>
    <div>
        <img src="../images/liceo_logo.png" alt="Logo del Liceo" class="logo" onerror="this.style.display='none'">
    </div>
    <h3>Estudiante: <?php echo htmlspecialchars($nombre); ?></h3>
</header>

<div class="menu">
    <a href="ver_notas_alumno.php"><i class="fas fa-book-open"></i> Ver Calificaciones</a>
    <a href="#"><i class="fas fa-tasks"></i> Actividades</a>
    <a href="#"><i class="fas fa-user"></i> Mi Perfil</a>
    <a href="logout.php" class="float-end"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
</div>

<div class="container mt-4">
    <div class="welcome-card">
        <h2>¡Bienvenido/a, <?php echo htmlspecialchars($nombre); ?>! 🎓</h2>
        <p class="text-muted">Este es tu panel de estudiante. Desde aquí puedes consultar tus calificaciones, actividades y gestionar tu perfil.</p>

    </div>
    
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">📚 Calificaciones</h5>
                    <p class="card-text">Consulta tus notas por materia y periodo</p>
                    <a href="ver_notas_alumno.php" class="btn btn-primary">Ver notas</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">📝 Actividades</h5>
                    <p class="card-text">Revisa tus tareas y trabajos pendientes</p>
                    <a href="#" class="btn btn-primary">Ver actividades</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">👤 Mi Perfil</h5>
                    <p class="card-text">Actualiza tu información personal</p>
                    <a href="#" class="btn btn-primary">Ver perfil</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>