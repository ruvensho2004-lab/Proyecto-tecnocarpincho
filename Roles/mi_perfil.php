<?php
session_start();

// Verificar que haya sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/conexion.php';

$usuario_id = $_SESSION['usuario']['id'];
$rol = $_SESSION['usuario']['rol'];
$mensaje = '';
$tipo_mensaje = '';

// Obtener información completa del usuario
$sql = "SELECT u.*, 
        CASE 
            WHEN u.rol = 1 THEN 'Administrador'
            WHEN u.rol = 3 THEN 'Profesor'
            WHEN u.rol = 4 THEN 'Alumno'
            ELSE 'Desconocido'
        END as rol_nombre
        FROM usuarios u 
        WHERE u.usuario_id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Si es profesor, obtener materias que imparte
$materias_profesor = [];
if ($rol == 3) {
    $sql_materias = "SELECT DISTINCT m.nombre_materia, g.nombre_grado, s.nombre_seccion
                     FROM profesor_materia_seccion pms
                     INNER JOIN materias m ON pms.materia_id = m.materia_id
                     INNER JOIN grados g ON pms.grado_id = g.grado_id
                     INNER JOIN secciones s ON pms.seccion_id = s.seccion_id
                     WHERE pms.profesor_id = :profesor_id AND pms.estado = 1
                     ORDER BY g.nombre_grado, s.nombre_seccion, m.nombre_materia";
    $stmt_materias = $pdo->prepare($sql_materias);
    $stmt_materias->execute(['profesor_id' => $usuario_id]);
    $materias_profesor = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
}

// Si es alumno, obtener información académica
$info_alumno = null;
if ($rol == 4) {
    $sql_alumno = "SELECT a.*, g.nombre_grado, s.nombre_seccion
                   FROM alumnos a
                   LEFT JOIN grados g ON a.grado_id = g.grado_id
                   LEFT JOIN secciones s ON a.seccion_id = s.seccion_id
                   WHERE a.usuario_id = :usuario_id";
    $stmt_alumno = $pdo->prepare($sql_alumno);
    $stmt_alumno->execute(['usuario_id' => $usuario_id]);
    $info_alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);
}

// Procesar actualizaciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['accion'])) {
            switch ($_POST['accion']) {
                case 'actualizar_datos':
                    $nombre = trim($_POST['nombre']);
                    $usuario_nombre = trim($_POST['usuario']);
                    $email = trim($_POST['email'] ?? '');
                    
                    if (empty($nombre)) {
                        throw new Exception("El nombre es obligatorio");
                    }
                    
                    if (empty($usuario_nombre)) {
                        throw new Exception("El usuario es obligatorio");
                    }
                    
                    // Verificar que el usuario no esté en uso por otro usuario
                    $sql_check = "SELECT usuario_id FROM usuarios WHERE usuario = :usuario AND usuario_id != :id";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute(['usuario' => $usuario_nombre, 'id' => $usuario_id]);
                    
                    if ($stmt_check->rowCount() > 0) {
                        throw new Exception("El nombre de usuario ya está en uso");
                    }
                    
                    // Actualizar datos
                    $sql_update = "UPDATE usuarios SET nombre = :nombre, usuario = :usuario";
                    $params = ['nombre' => $nombre, 'usuario' => $usuario_nombre, 'id' => $usuario_id];
                    
                    if (!empty($email)) {
                        $sql_update .= ", email = :email";
                        $params['email'] = $email;
                    }
                    
                    $sql_update .= " WHERE usuario_id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->execute($params);
                    
                    // Actualizar sesión
                    $_SESSION['usuario']['nombre'] = $nombre;
                    $_SESSION['usuario']['usuario'] = $usuario_nombre;
                    
                    $mensaje = "Datos actualizados exitosamente";
                    $tipo_mensaje = "success";
                    
                    // Recargar datos del usuario
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['id' => $usuario_id]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
                    
                case 'cambiar_password':
                    $password_actual = $_POST['password_actual'];
                    $password_nueva = $_POST['password_nueva'];
                    $password_confirmar = $_POST['password_confirmar'];
                    
                    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
                        throw new Exception("Todos los campos son obligatorios");
                    }
                    
                    // Verificar contraseña actual
                    if (!password_verify($password_actual, $usuario['clave'])) {
                        throw new Exception("La contraseña actual es incorrecta");
                    }
                    
                    if ($password_nueva !== $password_confirmar) {
                        throw new Exception("Las contraseñas nuevas no coinciden");
                    }
                    
                    if (strlen($password_nueva) < 6) {
                        throw new Exception("La contraseña debe tener al menos 6 caracteres");
                    }
                    
                    // Actualizar contraseña
                    $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                    $sql_update_pass = "UPDATE usuarios SET clave = :clave WHERE usuario_id = :id";
                    $stmt_update_pass = $pdo->prepare($sql_update_pass);
                    $stmt_update_pass->execute(['clave' => $password_hash, 'id' => $usuario_id]);
                    
                    $mensaje = "Contraseña actualizada exitosamente";
                    $tipo_mensaje = "success";
                    
                    // Recargar datos del usuario
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['id' => $usuario_id]);
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
            }
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Determinar página de retorno según rol
$pagina_inicio = match($rol) {
    1 => 'admin.php',
    3 => 'profesores.php',
    4 => 'alumno.php',
    default => '../index.php'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema Académico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { 
            background: linear-gradient(135deg, #001f3f 0%, #003d7a 100%);
            color: white; 
            padding: 1.5rem 2rem; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-card { 
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: #667eea;
            border: 5px solid rgba(255,255,255,0.3);
        }
        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 1rem;
        }
        .role-badge {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .role-admin { background-color: #dc3545; color: white; }
        .role-profesor { background-color: #17a2b8; color: white; }
        .role-alumno { background-color: #28a745; color: white; }
        .card-custom {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        .materia-item {
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-user-circle"></i> Mi Perfil</h3>
        <small><?php echo htmlspecialchars($usuario['rol_nombre']); ?></small>
    </div>
</div>

<div class="container mt-4">
    <a href="<?php echo $pagina_inicio; ?>" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver al Panel
    </a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Columna Izquierda: Información del Perfil -->
        <div class="col-md-4">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4 class="mb-2"><?php echo htmlspecialchars($usuario['nombre']); ?></h4>
                    <span class="role-badge role-<?php echo strtolower($usuario['rol_nombre']); ?>">
                        <?php echo $usuario['rol_nombre']; ?>
                    </span>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <div class="info-label"><i class="fas fa-user"></i> Usuario</div>
                        <div class="info-value"><?php echo htmlspecialchars($usuario['usuario']); ?></div>
                    </div>
                    
                    <?php if (!empty($usuario['email'])): ?>
                    <div class="mb-3">
                        <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($usuario['email']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <div class="info-label"><i class="fas fa-calendar"></i> Fecha de Registro</div>
                        <div class="info-value">
                            <?php echo isset($usuario['fecha_creacion']) ? date('d/m/Y', strtotime($usuario['fecha_creacion'])) : 'No disponible'; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="info-label"><i class="fas fa-toggle-on"></i> Estado</div>
                        <div class="info-value">
                            <span class="badge bg-<?php echo $usuario['estado'] == 1 ? 'success' : 'danger'; ?>">
                                <?php echo $usuario['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($rol == 4 && $info_alumno): ?>
            <!-- Información Académica del Alumno -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6 class="mb-0"><i class="fas fa-graduation-cap"></i> Información Académica</h6>
                </div>
                <div class="card-body">
                    <?php if ($info_alumno['nombre_grado']): ?>
                    <div class="mb-3">
                        <div class="info-label">Grado</div>
                        <div class="info-value"><?php echo htmlspecialchars($info_alumno['nombre_grado']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($info_alumno['nombre_seccion']): ?>
                    <div class="mb-3">
                        <div class="info-label">Sección</div>
                        <div class="info-value"><?php echo htmlspecialchars($info_alumno['nombre_seccion']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($info_alumno['cedula'])): ?>
                    <div class="mb-3">
                        <div class="info-label">Cédula</div>
                        <div class="info-value"><?php echo htmlspecialchars($info_alumno['cedula']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($rol == 3 && count($materias_profesor) > 0): ?>
            <!-- Materias que Imparte -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h6 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Materias que Imparto</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($materias_profesor as $materia): ?>
                    <div class="materia-item">
                        <strong><?php echo htmlspecialchars($materia['nombre_materia']); ?></strong><br>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($materia['nombre_grado']); ?> - 
                            <?php echo htmlspecialchars($materia['nombre_seccion']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna Derecha: Formularios de Edición -->
        <div class="col-md-8">
            <!-- Editar Datos Personales -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-edit"></i> Editar Datos Personales</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="actualizar_datos">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" 
                                   value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                            <small class="text-muted">Tu nombre completo como aparecerá en el sistema</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="usuario" class="form-control" 
                                   value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required>
                            <small class="text-muted">Nombre de usuario para iniciar sesión</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" 
                                   placeholder="correo@ejemplo.com">
                            <small class="text-muted">Opcional - Para recuperación de contraseña</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </form>
                </div>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <h5 class="mb-0"><i class="fas fa-lock"></i> Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="formPassword">
                        <input type="hidden" name="accion" value="cambiar_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual *</label>
                            <input type="password" name="password_actual" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña *</label>
                            <input type="password" name="password_nueva" id="password_nueva" 
                                   class="form-control" required minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nueva Contraseña *</label>
                            <input type="password" name="password_confirmar" id="password_confirmar" 
                                   class="form-control" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>

            <!-- Información de Seguridad -->
            <div class="alert alert-info">
                <h6><i class="fas fa-shield-alt"></i> Consejos de Seguridad</h6>
                <ul class="mb-0">
                    <li>Usa una contraseña única y segura</li>
                    <li>No compartas tu contraseña con nadie</li>
                    <li>Cambia tu contraseña periódicamente</li>
                    <li>Cierra sesión al terminar de usar el sistema</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validar que las contraseñas coincidan
document.getElementById('formPassword').addEventListener('submit', function(e) {
    const nueva = document.getElementById('password_nueva').value;
    const confirmar = document.getElementById('password_confirmar').value;
    
    if (nueva !== confirmar) {
        e.preventDefault();
        alert('Las contraseñas no coinciden. Por favor verifica.');
    }
});
</script>

</body>
</html>