<?php
require_once '../includes/security.php';
require_once '../includes/conexion.php';

verificar_rol([1]); // Solo administradores

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $accion = $_POST['accion'] ?? '';
        
        switch ($accion) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $direccion = trim($_POST['direccion']);
                $cedula = trim($_POST['cedula']);
                $telefono = trim($_POST['telefono']);
                $correo = trim($_POST['correo']);
                $nivel_est = trim($_POST['nivel_est']);
                $usuario = trim($_POST['usuario']);
                $clave = $_POST['clave'];
                
                // Validaciones
                if (empty($nombre) || empty($cedula) || empty($usuario) || empty($clave)) {
                    throw new Exception("Campos obligatorios faltantes");
                }
                
                if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Email inválido");
                }
                
                // Verificar que el usuario no exista
                $sqlCheck = "SELECT usuario_id FROM usuarios WHERE usuario = :usuario";
                $stmtCheck = $pdo->prepare($sqlCheck);
                $stmtCheck->execute(['usuario' => $usuario]);
                if ($stmtCheck->rowCount() > 0) {
                    throw new Exception("El usuario ya existe");
                }
                
                // Crear usuario primero
                $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                $sqlUsuario = "INSERT INTO usuarios (nombre, usuario, clave, rol, estado) 
                              VALUES (:nombre, :usuario, :clave, 3, 1)";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute([
                    'nombre' => $nombre,
                    'usuario' => $usuario,
                    'clave' => $clave_hash
                ]);
                
                $usuario_id = $pdo->lastInsertId();
                
                // Crear profesor
                $sqlProfesor = "INSERT INTO profesor (nombre, direccion, cedula, clave, telefono, correo, nivel_est, estado, usuario_id)
                               VALUES (:nombre, :direccion, :cedula, :clave_display, :telefono, :correo, :nivel_est, 1, :usuario_id)";
                $stmtProfesor = $pdo->prepare($sqlProfesor);
                $stmtProfesor->execute([
                    'nombre' => $nombre,
                    'direccion' => $direccion,
                    'cedula' => $cedula,
                    'clave_display' => '******', // No guardar contraseña en texto plano
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'nivel_est' => $nivel_est,
                    'usuario_id' => $usuario_id
                ]);
                
                registrar_log_seguridad('Profesor creado', "Profesor: {$nombre}, Usuario: {$usuario}");
                $mensaje = "Profesor registrado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'editar':
                $profesor_id = (int)$_POST['profesor_id'];
                $nombre = trim($_POST['nombre']);
                $direccion = trim($_POST['direccion']);
                $cedula = trim($_POST['cedula']);
                $telefono = trim($_POST['telefono']);
                $correo = trim($_POST['correo']);
                $nivel_est = trim($_POST['nivel_est']);
                
                if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Email inválido");
                }
                
                $sql = "UPDATE profesor SET 
                        nombre = :nombre,
                        direccion = :direccion,
                        cedula = :cedula,
                        telefono = :telefono,
                        correo = :correo,
                        nivel_est = :nivel_est
                        WHERE profesor_id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre,
                    'direccion' => $direccion,
                    'cedula' => $cedula,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'nivel_est' => $nivel_est,
                    'id' => $profesor_id
                ]);
                
                // Actualizar también en usuarios
                $sqlUsuario = "UPDATE usuarios u 
                              INNER JOIN profesor p ON u.usuario_id = p.usuario_id 
                              SET u.nombre = :nombre 
                              WHERE p.profesor_id = :profesor_id";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute(['nombre' => $nombre, 'profesor_id' => $profesor_id]);
                
                registrar_log_seguridad('Profesor editado', "ID: {$profesor_id}");
                $mensaje = "Profesor actualizado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'cambiar_estado':
                $profesor_id = (int)$_POST['profesor_id'];
                $nuevo_estado = (int)$_POST['nuevo_estado'];
                
                $sql = "UPDATE profesor SET estado = :estado WHERE profesor_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['estado' => $nuevo_estado, 'id' => $profesor_id]);
                
                // También cambiar estado del usuario
                $sqlUsuario = "UPDATE usuarios u 
                              INNER JOIN profesor p ON u.usuario_id = p.usuario_id 
                              SET u.estado = :estado 
                              WHERE p.profesor_id = :profesor_id";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute(['estado' => $nuevo_estado, 'profesor_id' => $profesor_id]);
                
                registrar_log_seguridad('Estado de profesor cambiado', "ID: {$profesor_id}, Nuevo estado: {$nuevo_estado}");
                $mensaje = $nuevo_estado == 1 ? "Profesor activado" : "Profesor desactivado";
                $tipo_mensaje = "success";
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener todos los profesores
$sql = "SELECT p.*, u.usuario 
        FROM profesor p 
        LEFT JOIN usuarios u ON p.usuario_id = u.usuario_id 
        ORDER BY p.nombre ASC";
$stmt = $pdo->query($sql);
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Profesores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table-actions button { margin-right: 5px; margin-bottom: 5px; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-chalkboard-teacher"></i> Gestión de Profesores</h3>
        <small>Administrador: <?php echo htmlspecialchars($nombre_admin); ?></small>
    </div>
</div>

<div class="container mt-4">
    <a href="admin.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver al Panel
    </a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="fas fa-plus-circle"></i> Agregar Nuevo Profesor
        </button>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-list"></i> Profesores Registrados (<?php echo count($profesores); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (count($profesores) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Cédula</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Nivel de Estudios</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profesores as $profesor): ?>
                        <tr>
                            <td><?php echo $profesor['profesor_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($profesor['nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($profesor['cedula']); ?></td>
                            <td><?php echo htmlspecialchars($profesor['telefono']); ?></td>
                            <td><?php echo htmlspecialchars($profesor['correo']); ?></td>
                            <td><?php echo htmlspecialchars($profesor['nivel_est']); ?></td>
                            <td><code><?php echo htmlspecialchars($profesor['usuario'] ?? 'N/A'); ?></code></td>
                            <td>
                                <span class="badge bg-<?php echo $profesor['estado'] == 1 ? 'success' : 'danger'; ?>">
                                    <?php echo $profesor['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <button class="btn btn-sm btn-info" onclick="verDetalle(<?php echo htmlspecialchars(json_encode($profesor)); ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editarProfesor(<?php echo htmlspecialchars(json_encode($profesor)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="profesor_id" value="<?php echo $profesor['profesor_id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $profesor['estado'] == 1 ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $profesor['estado'] == 1 ? 'danger' : 'success'; ?>"
                                            onclick="return confirm('¿Estás seguro?')">
                                        <i class="fas fa-<?php echo $profesor['estado'] == 1 ? 'ban' : 'check'; ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay profesores registrados.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Registrar Nuevo Profesor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="cedula" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Electrónico *</label>
                            <input type="email" name="correo" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nivel de Estudios</label>
                        <input type="text" name="nivel_est" class="form-control" 
                               placeholder="Ej: Licenciado en Educación, Magister, etc.">
                    </div>
                    
                    <hr>
                    <h6>Credenciales de Acceso</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="usuario" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" name="clave" class="form-control" required minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Profesor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Profesor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="profesor_id" id="edit_profesor_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cédula</label>
                            <input type="text" name="cedula" id="edit_cedula" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="edit_telefono" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" id="edit_correo" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="edit_direccion" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nivel de Estudios</label>
                        <input type="text" name="nivel_est" id="edit_nivel_est" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Detalles del Profesor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContenido"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarProfesor(profesor) {
    document.getElementById('edit_profesor_id').value = profesor.profesor_id;
    document.getElementById('edit_nombre').value = profesor.nombre;
    document.getElementById('edit_cedula').value = profesor.cedula;
    document.getElementById('edit_telefono').value = profesor.telefono;
    document.getElementById('edit_direccion').value = profesor.direccion;
    document.getElementById('edit_correo').value = profesor.correo;
    document.getElementById('edit_nivel_est').value = profesor.nivel_est;
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function verDetalle(profesor) {
    const html = `
        <table class="table table-bordered">
            <tr><th>ID:</th><td>${profesor.profesor_id}</td></tr>
            <tr><th>Nombre:</th><td>${profesor.nombre}</td></tr>
            <tr><th>Cédula:</th><td>${profesor.cedula}</td></tr>
            <tr><th>Dirección:</th><td>${profesor.direccion}</td></tr>
            <tr><th>Teléfono:</th><td>${profesor.telefono}</td></tr>
            <tr><th>Correo:</th><td>${profesor.correo}</td></tr>
            <tr><th>Nivel de Estudios:</th><td>${profesor.nivel_est}</td></tr>
            <tr><th>Usuario:</th><td>${profesor.usuario || 'N/A'}</td></tr>
            <tr><th>Estado:</th><td><span class="badge bg-${profesor.estado == 1 ? 'success' : 'danger'}">${profesor.estado == 1 ? 'Activo' : 'Inactivo'}</span></td></tr>
        </table>
    `;
    document.getElementById('detalleContenido').innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalDetalle')).show();
}
</script>

</body>
</html>