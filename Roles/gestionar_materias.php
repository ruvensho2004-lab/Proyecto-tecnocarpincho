<?php
session_start();

// Verificar que sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    header("Location: ../login.html");
    exit();
}

require_once '../includes/conexion.php';

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';

// Obtener grados
$grados = $pdo->query("SELECT * FROM grados WHERE estado = 1 ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);

// Procesar acciones (Crear, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['accion'])) {
            switch ($_POST['accion']) {
                case 'crear':
                    $nombre = trim($_POST['nombre_materia']);
                    $grado_id = !empty($_POST['grado_id']) ? (int)$_POST['grado_id'] : null;
                    
                    if (empty($nombre)) {
                        throw new Exception("El nombre de la materia es obligatorio");
                    }
                    
                    $sql = "INSERT INTO materias (nombre_materia, grado_id, estado) VALUES (:nombre, :grado_id, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['nombre' => $nombre, 'grado_id' => $grado_id]);
                    $mensaje = "Materia creada exitosamente";
                    $tipo_mensaje = "success";
                    break;
                    
                case 'editar':
                    $id = (int)$_POST['materia_id'];
                    $nombre = trim($_POST['nombre_materia']);
                    $grado_id = !empty($_POST['grado_id']) ? (int)$_POST['grado_id'] : null;
                    
                    if (empty($nombre)) {
                        throw new Exception("El nombre de la materia es obligatorio");
                    }
                    
                    $sql = "UPDATE materias SET nombre_materia = :nombre, grado_id = :grado_id WHERE materia_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['nombre' => $nombre, 'grado_id' => $grado_id, 'id' => $id]);
                    $mensaje = "Materia actualizada exitosamente";
                    $tipo_mensaje = "success";
                    break;
                    
                case 'cambiar_estado':
                    $id = (int)$_POST['materia_id'];
                    $estado = (int)$_POST['nuevo_estado'];
                    
                    $sql = "UPDATE materias SET estado = :estado WHERE materia_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['estado' => $estado, 'id' => $id]);
                    $mensaje = $estado == 1 ? "Materia activada" : "Materia desactivada";
                    $tipo_mensaje = "success";
                    break;
            }
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener todas las materias con información del grado
$sql = "SELECT m.*, g.nombre_grado 
        FROM materias m 
        LEFT JOIN grados g ON m.grado_id = g.grado_id 
        ORDER BY m.nombre_materia ASC";
$stmt = $pdo->query($sql);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materias</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .table-actions button { margin-right: 5px; }
        .badge-activo { background-color: #28a745; }
        .badge-inactivo { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-book"></i> Gestión de Materias</h3>
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

    <!-- Formulario para crear nueva materia -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-plus-circle"></i> Agregar Nueva Materia</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" name="nombre_materia" class="form-control" 
                               placeholder="Nombre de la materia (ej: Matemáticas, Español, etc.)" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Guardar Materia
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de materias -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-list"></i> Materias Registradas (<?php echo count($materias); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (count($materias) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre de la Materia</th>
                            <th>Grado Asignado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materias as $materia): ?>
                        <tr>
                            <td><?php echo $materia['materia_id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($materia['nombre_materia']); ?></strong>
                            </td>
                            <td>
                                <?php if ($materia['nombre_grado']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($materia['nombre_grado']); ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Todos los grados</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $materia['estado'] == 1 ? 'badge-activo' : 'badge-inactivo'; ?>">
                                    <?php echo $materia['estado'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <!-- Botón Editar -->
                                <button class="btn btn-sm btn-warning" 
                                        onclick="editarMateria(<?php echo $materia['materia_id']; ?>, '<?php echo htmlspecialchars($materia['nombre_materia']); ?>', <?php echo $materia['grado_id'] ?? 'null'; ?>)">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                
                                <!-- Botón Activar/Desactivar -->
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="materia_id" value="<?php echo $materia['materia_id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $materia['estado'] == 1 ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $materia['estado'] == 1 ? 'btn-danger' : 'btn-success'; ?>">
                                        <i class="fas fa-<?php echo $materia['estado'] == 1 ? 'times' : 'check'; ?>"></i>
                                        <?php echo $materia['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>
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
                <i class="fas fa-info-circle"></i> No hay materias registradas. Agrega la primera materia usando el formulario de arriba.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para editar materia -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Materia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="materia_id" id="edit_materia_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre de la Materia:</label>
                        <input type="text" name="nombre_materia" id="edit_nombre_materia" 
                               class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Grado Asignado:</label>
                        <select name="grado_id" id="edit_grado_id" class="form-select">
                            <option value="">Todos los grados</option>
                            <?php foreach ($grados as $grado): ?>
                            <option value="<?php echo $grado['grado_id']; ?>">
                                <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Dejar en blanco para que sea visible en todos los grados</small>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarMateria(id, nombre, gradoId) {
    document.getElementById('edit_materia_id').value = id;
    document.getElementById('edit_nombre_materia').value = nombre;
    document.getElementById('edit_grado_id').value = gradoId || '';
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>

</body>
</html>