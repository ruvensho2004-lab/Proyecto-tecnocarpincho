<?php
session_start();

// ✅ CORRECCIÓN: Verificar sesión correctamente
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    header("Location: index.php");
    exit("❌ No autorizado. Solo administradores.");
}

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 30px; }
        .btn-volver { margin-top: 20px; }
        #mensaje { margin-top: 15px; padding: 10px; border-radius: 5px; display: none; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center mb-4">📝 Registrar Nuevo Usuario</h2>
    <p class="text-muted text-center">Sesión: <?php echo htmlspecialchars($nombre_admin); ?></p>
    
    <form id="formRegistro" method="POST">
        <div class="mb-3">
            <label class="form-label">Nombre Completo:</label>
            <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Usuario:</label>
            <input type="text" name="usuario" class="form-control" placeholder="Ej: jperez" required>
            <small class="text-muted">Sin espacios, solo letras y números</small>
        </div>

        <div class="mb-3">
            <label class="form-label">Email:</label>
            <input type="email" name="email" class="form-control" placeholder="ejemplo@correo.com" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Contraseña:</label>
            <input type="password" name="clave" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
        </div>

        <div class="mb-3">
            <label class="form-label">Rol:</label>
            <select name="rol" class="form-select" required>
                <option value="">-- Seleccione un rol --</option>
                <option value="1">Administrador</option>
                <option value="2">Asistente</option>
                <option value="3">Profesor</option>
                <option value="4">Alumno</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary w-100">Registrar Usuario</button>
        
        <div id="mensaje"></div>
    </form>

    <a href="Roles/admin.php" class="btn btn-secondary w-100 btn-volver">← Volver al Panel</a>
</div>

<script>
document.getElementById('formRegistro').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const datos = new FormData(form);
    const mensaje = document.getElementById('mensaje');
    const btnSubmit = form.querySelector('button[type="submit"]');
    
    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Registrando...';
    
    try {
        const res = await fetch('guardar_usuarios.php', {
            method: 'POST',
            body: datos
        });
        
        const text = await res.text();
        
        mensaje.style.display = 'block';
        
        if (text.includes('✅')) {
            mensaje.className = 'success';
            mensaje.innerHTML = text;
            form.reset();
            
            setTimeout(() => {
                window.location.href = 'Roles/admin.php';
            }, 2000);
        } else {
            mensaje.className = 'error';
            mensaje.innerHTML = text;
        }
        
    } catch (err) {
        mensaje.style.display = 'block';
        mensaje.className = 'error';
        mensaje.textContent = '❌ Error de conexión con el servidor.';
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Registrar Usuario';
    }
});
</script>

</body>
</html>