<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    header("Location: ../login.html");
    exit();
}

require_once '../includes/conexion.php';

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $tabla = $_POST['tabla'] ?? '';
        $accion = $_POST['accion'] ?? '';
        
        if ($tabla == 'periodos') {
            switch ($accion) {
                case 'crear':
                    $nombre = trim($_POST['nombre_periodo']);
                    $sql = "INSERT INTO periodos (nombre_periodo, estado) VALUES (:nombre, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['nombre' => $nombre]);
                    $mensaje = "Periodo creado exitosamente";
                    break;
                    
                case 'editar':
                    $id = (int)$_POST['periodo_id'];
                    $nombre = trim($_POST['nombre_periodo']);
                    $sql = "UPDATE periodos SET nombre_periodo = :nombre WHERE periodo_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['nombre' => $nombre, 'id' => $id]);
                    $mensaje = "Periodo actualizado";
                    break;
                    
                case 'cambiar_estado':
                    $id = (int)$_POST['periodo_id'];
                    $estado = (int)$_POST['nuevo_estado'];
                    $sql = "UPDATE periodos SET estado = :estado WHERE periodo_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['estado' => $estado, 'id' => $id]);
                    $mensaje = "Estado actualizado";
                    break;
            }
        } elseif ($tabla == 'actividades') {
            switch ($accion) {
                case 'crear':
                    $nombre = trim($_POST['nombre_actividad']);
                    $sql = "INSERT INTO actividad (nombre_actividad, estado) VALUES (:nombre, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['nombre' => $nombre]);
                    $mensaje = "Tipo de actividad creada exitosamente";
                    break;
                    
                case 'editar':
                    $id = (int)$_POST['actividad_id'];
                    $nombre = trim($_POST['nombre_actividad']);
                    $sql = "UPDATE actividad SET nombre_actividad = :nombre WHERE actividad_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['nombre' => $nombre, 'id' => $id]);
                    $mensaje = "Actividad actualizada";
                    break;
                    
                case 'cambiar_estado':
                    $id = (int)$_POST['actividad_id'];
                    $estado = (int)$_POST['nuevo_estado'];
                    $sql = "UPDATE actividad SET estado = :estado WHERE actividad_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['estado' => $estado, 'id' => $id]);
                    $mensaje = "Estado actualizado";
                    break;
            }
        }
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener datos
$periodos = $pdo->query("SELECT * FROM periodos ORDER BY periodo_id ASC")->fetchAll(PDO::FETCH_ASSOC);
$actividades = $pdo->query("SELECT * FROM actividad ORDER BY actividad_id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Periodos y Actividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-calendar-alt"></i> Gestión de Periodos y Actividades</h3>
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

    <div class="row">
        <!-- PERIODOS -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-calendar"></i> Periodos Escolares</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="tabla" value="periodos">
                        <input type="hidden" name="accion" value="crear">
                        <div class="input-group">
                            <input type="text" name="nombre_periodo" class="form-control" 
                                   placeholder="Ej: 1er Periodo, 2do Periodo" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($periodos as $p): ?>
                                <tr>
                                    <td><?php echo $p['periodo_id']; ?></td>
                                    <td><?php echo htmlspecialchars($p['nombre_periodo']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $p['estado'] == 1 ? 'success' : 'danger'; ?>">
                                            <?php echo $p['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="editarPeriodo(<?php echo $p['periodo_id']; ?>, '<?php echo htmlspecialchars($p['nombre_periodo']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="tabla" value="periodos">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="periodo_id" value="<?php echo $p['periodo_id']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $p['estado'] == 1 ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $p['estado'] == 1 ? 'danger' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $p['estado'] == 1 ? 'times' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIVIDADES -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5><i class="fas fa-tasks"></i> Tipos de Actividades</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="tabla" value="actividades">
                        <input type="hidden" name="accion" value="crear">
                        <div class="input-group">
                            <input type="text" name="nombre_actividad" class="form-control" 
                                   placeholder="Ej: Examen, Tarea, Proyecto" required>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividades as $a): ?>
                                <tr>
                                    <td><?php echo $a['actividad_id']; ?></td>
                                    <td><?php echo htmlspecialchars($a['nombre_actividad']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $a['estado'] == 1 ? 'success' : 'danger'; ?>">
                                            <?php echo $a['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="editarActividad(<?php echo $a['actividad_id']; ?>, '<?php echo htmlspecialchars($a['nombre_actividad']); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="tabla" value="actividades">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="actividad_id" value="<?php echo $a['actividad_id']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $a['estado'] == 1 ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $a['estado'] == 1 ? 'danger' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $a['estado'] == 1 ? 'times' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales -->
<div class="modal fade" id="modalEditarPeriodo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Periodo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="tabla" value="periodos">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="periodo_id" id="edit_periodo_id">
                <div class="modal-body">
                    <input type="text" name="nombre_periodo" id="edit_nombre_periodo" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarActividad" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Actividad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="tabla" value="actividades">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="actividad_id" id="edit_actividad_id">
                <div class="modal-body">
                    <input type="text" name="nombre_actividad" id="edit_nombre_actividad" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editarPeriodo(id, nombre) {
    document.getElementById('edit_periodo_id').value = id;
    document.getElementById('edit_nombre_periodo').value = nombre;
    new bootstrap.Modal(document.getElementById('modalEditarPeriodo')).show();
}

function editarActividad(id, nombre) {
    document.getElementById('edit_actividad_id').value = id;
    document.getElementById('edit_nombre_actividad').value = nombre;
    new bootstrap.Modal(document.getElementById('modalEditarActividad')).show();
}
</script>

</body>
</html>