<?php
require_once 'includes/security.php';
require_once 'includes/conexion.php';

verificar_autenticacion();

$usuario_id = $_SESSION['usuario']['id'];
$nombre = $_SESSION['usuario']['nombre'];
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Verificar CSRF token
        if (!isset($_POST['csrf_token'])) {
            throw new Exception("Token de seguridad no encontrado.");
        }
        verificar_csrf_token($_POST['csrf_token']);
        
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';
        $password_confirmar = $_POST['password_confirmar'] ?? '';
        
        // Validaciones
        if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
            throw new Exception("Todos los campos son obligatorios.");
        }
        
        if ($password_nueva !== $password_confirmar) {
            throw new Exception("Las contraseñas nuevas no coinciden.");
        }
        
        if (strlen($password_nueva) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres.");
        }
        
        // Verificar contraseña actual
        $sql = "SELECT clave FROM usuarios WHERE usuario_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $usuario_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($password_actual, $user['clave'])) {
            registrar_log_seguridad('Intento fallido de cambio de contraseña', "Usuario ID: {$usuario_id}");
            throw new Exception("La contraseña actual es incorrecta.");
        }
        
        // Actualizar contraseña
        $nueva_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET clave = :clave WHERE usuario_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'clave' => $nueva_hash,
            'id' => $usuario_id
        ]);
        
        registrar_log_seguridad('Cambio de contraseña exitoso', "Usuario ID: {$usuario_id}");
        
        $mensaje = "✅ Contraseña actualizada exitosamente.";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

$csrf_token = generar_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg,rgb(0, 47, 255) 0%,rgb(33, 214, 238) 100%); min-height: 100vh; }
        .card-container { max-width: 500px; margin: 50px auto; }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        .strength-weak { background-color: #dc3545; width: 33%; }
        .strength-medium { background-color: #ffc107; width: 66%; }
        .strength-strong { background-color: #28a745; width: 100%; }
    </style>
</head>
<body>

<div class="container">
    <div class="card-container">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white text-center">
                <h4><i class="fas fa-key"></i> Cambiar Contraseña</h4>
                <small><?php echo htmlspecialchars($nombre); ?></small>
            </div>
            <div class="card-body">
                <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($mensaje); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" id="formCambiarPassword">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Contraseña Actual:
                        </label>
                        <input type="password" name="password_actual" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Nueva Contraseña:
                        </label>
                        <input type="password" name="password_nueva" id="password_nueva" 
                               class="form-control" required minlength="6">
                        <div id="password-strength" class="password-strength"></div>
                        <small class="text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-check"></i> Confirmar Nueva Contraseña:
                        </label>
                        <input type="password" name="password_confirmar" id="password_confirmar" 
                               class="form-control" required minlength="6">
                        <small id="match-message"></small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Actualizar Contraseña
                    </button>
                </form>

                <hr>
                
                <div class="text-center">
                    <a href="javascript:history.back()" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>

                <div class="mt-4 p-3 bg-light rounded">
                    <h6><i class="fas fa-shield-alt"></i> Recomendaciones de Seguridad:</h6>
                    <ul class="small mb-0">
                        <li>Usa al menos 8 caracteres</li>
                        <li>Combina mayúsculas, minúsculas y números</li>
                        <li>Incluye caracteres especiales (@, #, $, etc.)</li>
                        <li>No uses información personal</li>
                        <li>No reutilices contraseñas</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Verificar fortaleza de contraseña
document.getElementById('password_nueva').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('password-strength');
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    strengthBar.className = 'password-strength';
    if (strength <= 2) {
        strengthBar.classList.add('strength-weak');
    } else if (strength <= 4) {
        strengthBar.classList.add('strength-medium');
    } else {
        strengthBar.classList.add('strength-strong');
    }
});

// Verificar que las contraseñas coincidan
document.getElementById('password_confirmar').addEventListener('input', function() {
    const nueva = document.getElementById('password_nueva').value;
    const confirmar = this.value;
    const message = document.getElementById('match-message');
    
    if (confirmar === '') {
        message.textContent = '';
        message.className = '';
    } else if (nueva === confirmar) {
        message.textContent = '✅ Las contraseñas coinciden';
        message.className = 'text-success';
    } else {
        message.textContent = '❌ Las contraseñas no coinciden';
        message.className = 'text-danger';
    }
});
</script>

</body>
</html>