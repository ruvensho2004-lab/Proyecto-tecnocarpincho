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
                $nombre = trim($_POST['nombre_alumno']);
                $edad = (int)$_POST['edad'];
                $direccion = trim($_POST['direccion']);
                $cedula = trim($_POST['cedula']);
                $telefono = trim($_POST['telefono']);
                $correo = trim($_POST['correo']);
                $fecha_nac = $_POST['fecha_nac'];
                $ano_escolar = trim($_POST['ano_escolar']);
                $grado_id = (int)$_POST['grado_id'];
                $seccion_id = (int)$_POST['seccion_id'];
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
                              VALUES (:nombre, :usuario, :clave, 4, 1)";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute([
                    'nombre' => $nombre,
                    'usuario' => $usuario,
                    'clave' => $clave_hash
                ]);
                
                $usuario_id = $pdo->lastInsertId();
                
                // Crear alumno
                $sqlAlumno = "INSERT INTO alumnos (nombre_alumno, edad, direccion, cedula, telefono, correo, fecha_nac, fecha_registro, ano_escolar, grado_id, seccion_id, estado, usuario_id)
                             VALUES (:nombre, :edad, :direccion, :cedula, :telefono, :correo, :fecha_nac, NOW(), :ano_escolar, :grado_id, :seccion_id, 1, :usuario_id)";
                $stmtAlumno = $pdo->prepare($sqlAlumno);
                $stmtAlumno->execute([
                    'nombre' => $nombre,
                    'edad' => $edad,
                    'direccion' => $direccion,
                    'cedula' => $cedula,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'fecha_nac' => $fecha_nac,
                    'ano_escolar' => $ano_escolar,
                    'grado_id' => $grado_id,
                    'seccion_id' => $seccion_id,
                    'usuario_id' => $usuario_id
                ]);
                
                registrar_log_seguridad('Alumno creado', "Alumno: {$nombre}, Usuario: {$usuario}");
                $mensaje = "Alumno registrado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'editar':
                $alumno_id = (int)$_POST['alumno_id'];
                $nombre = trim($_POST['nombre_alumno']);
                $edad = (int)$_POST['edad'];
                $direccion = trim($_POST['direccion']);
                $cedula = trim($_POST['cedula']);
                $telefono = trim($_POST['telefono']);
                $correo = trim($_POST['correo']);
                $fecha_nac = $_POST['fecha_nac'];
                $ano_escolar = trim($_POST['ano_escolar']);
                $grado_id = (int)$_POST['grado_id'];
                $seccion_id = (int)$_POST['seccion_id'];
                
                if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Email inválido");
                }
                
                $sql = "UPDATE alumnos SET 
                        nombre_alumno = :nombre,
                        edad = :edad,
                        direccion = :direccion,
                        cedula = :cedula,
                        telefono = :telefono,
                        correo = :correo,
                        fecha_nac = :fecha_nac,
                        ano_escolar = :ano_escolar,
                        grado_id = :grado_id,
                        seccion_id = :seccion_id
                        WHERE alumno_id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre,
                    'edad' => $edad,
                    'direccion' => $direccion,
                    'cedula' => $cedula,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'fecha_nac' => $fecha_nac,
                    'ano_escolar' => $ano_escolar,
                    'grado_id' => $grado_id,
                    'seccion_id' => $seccion_id,
                    'id' => $alumno_id
                ]);
                
                // Actualizar también en usuarios
                $sqlUsuario = "UPDATE usuarios u 
                              INNER JOIN alumnos a ON u.usuario_id = a.usuario_id 
                              SET u.nombre = :nombre 
                              WHERE a.alumno_id = :alumno_id";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute(['nombre' => $nombre, 'alumno_id' => $alumno_id]);
                
                registrar_log_seguridad('Alumno editado', "ID: {$alumno_id}");
                $mensaje = "Alumno actualizado exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'cambiar_estado':
                $alumno_id = (int)$_POST['alumno_id'];
                $nuevo_estado = (int)$_POST['nuevo_estado'];
                
                $sql = "UPDATE alumnos SET estado = :estado WHERE alumno_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['estado' => $nuevo_estado, 'id' => $alumno_id]);
                
                // También cambiar estado del usuario
                $sqlUsuario = "UPDATE usuarios u 
                              INNER JOIN alumnos a ON u.usuario_id = a.usuario_id 
                              SET u.estado = :estado 
                              WHERE a.alumno_id = :alumno_id";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute(['estado' => $nuevo_estado, 'alumno_id' => $alumno_id]);
                
                registrar_log_seguridad('Estado de alumno cambiado', "ID: {$alumno_id}, Nuevo estado: {$nuevo_estado}");
                $mensaje = $nuevo_estado == 1 ? "Alumno activado" : "Alumno desactivado";
                $tipo_mensaje = "success";
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener grados y secciones para los selectores
$grados = $pdo->query("SELECT * FROM grados WHERE estado = 1 ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);
$secciones = $pdo->query("SELECT s.*, g.nombre_grado FROM secciones s INNER JOIN grados g ON s.grado_id = g.grado_id WHERE s.estado = 1 ORDER BY s.grado_id, s.nombre_seccion")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los alumnos con su grado y sección
$sql = "SELECT a.*, u.usuario, g.nombre_grado, s.nombre_seccion 
        FROM alumnos a 
        LEFT JOIN usuarios u ON a.usuario_id = u.usuario_id 
        LEFT JOIN grados g ON a.grado_id = g.grado_id
        LEFT JOIN secciones s ON a.seccion_id = s.seccion_id
        ORDER BY a.nombre_alumno ASC";
$stmt = $pdo->query($sql);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Alumnos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table-actions button { margin-right: 5px; margin-bottom: 5px; }
        .badge-activo { background-color: #28a745; }
        .badge-inactivo { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-user-graduate"></i> Gestión de Alumnos</h3>
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

    <!-- Botón para agregar alumno -->
    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
            <i class="fas fa-plus-circle"></i> Agregar Nuevo Alumno
        </button>
    </div>

    <!-- Listado de alumnos -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-list"></i> Alumnos Registrados (<?php echo count($alumnos); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (count($alumnos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Año Escolar</th>
                            <th>Grado y Sección</th>
                            <th>Cédula</th>
                            <th>Edad</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos as $alumno): ?>
                        <tr>
                            <td><?php echo $alumno['alumno_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($alumno['nombre_alumno']); ?></strong></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($alumno['ano_escolar'] ?? 'N/A'); ?></span></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php 
                                    echo htmlspecialchars($alumno['nombre_grado'] ?? 'Sin asignar');
                                    if (!empty($alumno['nombre_seccion'])) {
                                        echo ' - Sección ' . htmlspecialchars($alumno['nombre_seccion']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($alumno['cedula']); ?></td>
                            <td><?php echo $alumno['edad']; ?> años</td>
                            <td><code><?php echo htmlspecialchars($alumno['usuario'] ?? 'N/A'); ?></code></td>
                            <td>
                                <span class="badge <?php echo $alumno['estado'] == 1 ? 'badge-activo' : 'badge-inactivo'; ?>">
                                    <?php echo $alumno['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <button class="btn btn-sm btn-info" onclick="verDetalle(<?php echo htmlspecialchars(json_encode($alumno)); ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editarAlumno(<?php echo htmlspecialchars(json_encode($alumno)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="alumno_id" value="<?php echo $alumno['alumno_id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $alumno['estado'] == 1 ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $alumno['estado'] == 1 ? 'btn-danger' : 'btn-success'; ?>"
                                            onclick="return confirm('¿Estás seguro?')">
                                        <i class="fas fa-<?php echo $alumno['estado'] == 1 ? 'ban' : 'check'; ?>"></i>
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
                <i class="fas fa-info-circle"></i> No hay alumnos registrados.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Crear Alumno -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Registrar Nuevo Alumno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre_alumno" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Edad *</label>
                            <input type="number" name="edad" class="form-control" min="5" max="100" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha Nacimiento *</label>
                            <input type="date" name="fecha_nac" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Año Escolar *</label>
                            <input type="text" name="ano_escolar" class="form-control" 
                                   placeholder="Ej: 2024-2025" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grado/Año *</label>
                            <select name="grado_id" id="grado_select" class="form-select" required onchange="cargarSecciones(this.value, 'seccion_select')">
                                <option value="">Seleccione...</option>
                                <?php foreach ($grados as $grado): ?>
                                <option value="<?php echo $grado['grado_id']; ?>">
                                    <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sección *</label>
                            <select name="seccion_id" id="seccion_select" class="form-select" required>
                                <option value="">Primero seleccione un grado</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cédula *</label>
                            <input type="text" name="cedula" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Correo Electrónico *</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    
                    <hr>
                    <h6>Credenciales de Acceso</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usuario *</label>
                            <input type="text" name="usuario" class="form-control" required>
                            <small class="text-muted">Solo letras, números y guión bajo</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña *</label>
                            <input type="password" name="clave" class="form-control" required minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Registrar Alumno
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Alumno -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Alumno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="alumno_id" id="edit_alumno_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" name="nombre_alumno" id="edit_nombre" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Edad</label>
                            <input type="number" name="edad" id="edit_edad" class="form-control" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha Nacimiento</label>
                            <input type="date" name="fecha_nac" id="edit_fecha_nac" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Año Escolar</label>
                            <input type="text" name="ano_escolar" id="edit_ano_escolar" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grado</label>
                            <select name="grado_id" id="edit_grado" class="form-select" required onchange="cargarSecciones(this.value, 'edit_seccion')">
                                <option value="">Seleccione...</option>
                                <?php foreach ($grados as $grado): ?>
                                <option value="<?php echo $grado['grado_id']; ?>">
                                    <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sección</label>
                            <select name="seccion_id" id="edit_seccion" class="form-select" required>
                                <option value="">Seleccione grado primero</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cédula</label>
                            <input type="text" name="cedula" id="edit_cedula" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" id="edit_telefono" class="form-control">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" id="edit_direccion" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" id="edit_correo" class="form-control" required>
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

<!-- Modal Ver Detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Detalles del Alumno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContenido">
                <!-- Se llenará con JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Datos de secciones por grado (PHP a JavaScript)
const seccionesPorGrado = <?php echo json_encode(array_reduce($secciones, function($carry, $item) {
    $carry[$item['grado_id']][] = ['id' => $item['seccion_id'], 'nombre' => $item['nombre_seccion']];
    return $carry;
}, [])); ?>;

function cargarSecciones(gradoId, selectId) {
    const seccionSelect = document.getElementById(selectId);
    seccionSelect.innerHTML = '<option value="">Seleccione...</option>';
    
    if (gradoId && seccionesPorGrado[gradoId]) {
        seccionesPorGrado[gradoId].forEach(seccion => {
            const option = document.createElement('option');
            option.value = seccion.id;
            option.textContent = `Sección ${seccion.nombre}`;
            seccionSelect.appendChild(option);
        });
    }
}

function editarAlumno(alumno) {
    document.getElementById('edit_alumno_id').value = alumno.alumno_id;
    document.getElementById('edit_nombre').value = alumno.nombre_alumno;
    document.getElementById('edit_edad').value = alumno.edad;
    document.getElementById('edit_cedula').value = alumno.cedula;
    document.getElementById('edit_telefono').value = alumno.telefono;
    document.getElementById('edit_direccion').value = alumno.direccion;
    document.getElementById('edit_correo').value = alumno.correo;
    document.getElementById('edit_fecha_nac').value = alumno.fecha_nac;
    document.getElementById('edit_ano_escolar').value = alumno.ano_escolar || '';
    document.getElementById('edit_grado').value = alumno.grado_id || '';
    
    // Cargar secciones y seleccionar la actual
    if (alumno.grado_id) {
        cargarSecciones(alumno.grado_id, 'edit_seccion');
        setTimeout(() => {
            document.getElementById('edit_seccion').value = alumno.seccion_id || '';
        }, 100);
    }
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}

function verDetalle(alumno) {
    const html = `
        <table class="table table-bordered">
            <tr><th>ID:</th><td>${alumno.alumno_id}</td></tr>
            <tr><th>Nombre:</th><td>${alumno.nombre_alumno}</td></tr>
            <tr><th>Año Escolar:</th><td>${alumno.ano_escolar || 'N/A'}</td></tr>
            <tr><th>Grado:</th><td>${alumno.nombre_grado || 'Sin asignar'}</td></tr>
            <tr><th>Sección:</th><td>${alumno.nombre_seccion || 'Sin asignar'}</td></tr>
            <tr><th>Cédula:</th><td>${alumno.cedula}</td></tr>
            <tr><th>Edad:</th><td>${alumno.edad} años</td></tr>
            <tr><th>Fecha Nacimiento:</th><td>${alumno.fecha_nac}</td></tr>
            <tr><th>Dirección:</th><td>${alumno.direccion}</td></tr>
            <tr><th>Teléfono:</th><td>${alumno.telefono}</td></tr>
            <tr><th>Correo:</th><td>${alumno.correo}</td></tr>
            <tr><th>Usuario:</th><td>${alumno.usuario || 'N/A'}</td></tr>
            <tr><th>Fecha Registro:</th><td>${alumno.fecha_registro}</td></tr>
            <tr><th>Estado:</th><td><span class="badge ${alumno.estado == 1 ? 'bg-success' : 'bg-danger'}">${alumno.estado == 1 ? 'Activo' : 'Inactivo'}</span></td></tr>
        </table>
    `;
    document.getElementById('detalleContenido').innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalDetalle')).show();
}
</script>

</body>
</html>