<?php
require_once '../includes/security.php';
require_once '../includes/conexion.php';

verificar_rol([1]);

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $accion = $_POST['accion'] ?? '';
        
        switch ($accion) {
            case 'crear':
                $grado_id = (int)$_POST['grado_id'];
                $nombre_seccion = strtoupper(trim($_POST['nombre_seccion']));
                
                $sql = "INSERT INTO secciones (nombre_seccion, grado_id, estado) VALUES (:nombre, :grado_id, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['nombre' => $nombre_seccion, 'grado_id' => $grado_id]);
                
                $mensaje = "Sección creada exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'editar':
                $seccion_id = (int)$_POST['seccion_id'];
                $nombre_seccion = strtoupper(trim($_POST['nombre_seccion']));
                
                $sql = "UPDATE secciones SET nombre_seccion = :nombre WHERE seccion_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['nombre' => $nombre_seccion, 'id' => $seccion_id]);
                
                $mensaje = "Sección actualizada";
                $tipo_mensaje = "success";
                break;
                
            case 'cambiar_estado':
                $seccion_id = (int)$_POST['seccion_id'];
                $estado = (int)$_POST['nuevo_estado'];
                
                $sql = "UPDATE secciones SET estado = :estado WHERE seccion_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['estado' => $estado, 'id' => $seccion_id]);
                
                $mensaje = "Estado actualizado";
                $tipo_mensaje = "success";
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

$grados = $pdo->query("SELECT * FROM grados WHERE estado = 1 ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);
$secciones = $pdo->query("SELECT s.*, g.nombre_grado FROM secciones s INNER JOIN grados g ON s.grado_id = g.grado_id ORDER BY g.grado_id, s.nombre_seccion")->fetchAll(PDO::FETCH_ASSOC);

// Organizar secciones por grado
$secciones_por_grado = [];
foreach ($secciones as $seccion) {
    $secciones_por_grado[$seccion['nombre_grado']][] = $seccion;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Secciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f4f4f4; 
        }
        .header-custom { 
            background: linear-gradient(135deg, #001f3f 0%, #003366 100%);
            color: white; 
            padding: 1.5rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .card { 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
        }
        .seccion-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #0d6efd;
        }
        .seccion-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .grado-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .grado-title {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-layer-group"></i> Gestión de Secciones</h3>
        <small>Administrador: <?php echo htmlspecialchars($nombre_admin); ?></small>
    </div>
</div>

<div class="container mt-4">
    <a href="admin.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver
    </a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Formulario para crear sección -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Agregar Nueva Sección</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Grado/Año:</label>
                        <select name="grado_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($grados as $grado): ?>
                            <option value="<?php echo $grado['grado_id']; ?>">
                                <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nombre de Sección:</label>
                        <input type="text" name="nombre_seccion" class="form-control" 
                               placeholder="Ej: A, B, C" required maxlength="10">
                        <small class="text-muted">Se convertirá a mayúsculas automáticamente</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Crear
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Secciones por grado -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-list"></i> Secciones por Grado</h5>
        </div>
        <div class="card-body">
            <?php if (empty($secciones_por_grado)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay secciones registradas. Crea una nueva arriba.
                </div>
            <?php else: ?>
                <?php foreach ($secciones_por_grado as $nombre_grado => $secciones_grado): ?>
                <div class="grado-section">
                    <h5 class="grado-title">
                        <i class="fas fa-graduation-cap text-primary"></i> 
                        <?php echo htmlspecialchars($nombre_grado); ?>
                        <span class="badge bg-secondary float-end">
                            <?php echo count($secciones_grado); ?> 
                            <?php echo count($secciones_grado) == 1 ? 'sección' : 'secciones'; ?>
                        </span>
                    </h5>
                    <div class="row">
                        <?php foreach ($secciones_grado as $seccion): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card seccion-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0">
                                            <i class="fas fa-bookmark text-primary"></i> 
                                            Sección <?php echo htmlspecialchars($seccion['nombre_seccion']); ?>
                                        </h6>
                                        <span class="badge bg-<?php echo $seccion['estado'] == 1 ? 'success' : 'danger'; ?>">
                                            <?php echo $seccion['estado'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-sm btn-warning flex-fill" 
                                                onclick="editarSeccion(<?php echo $seccion['seccion_id']; ?>, '<?php echo $seccion['nombre_seccion']; ?>')">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="POST" class="d-inline flex-fill">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="seccion_id" value="<?php echo $seccion['seccion_id']; ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?php echo $seccion['estado'] == 1 ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $seccion['estado'] == 1 ? 'danger' : 'success'; ?> w-100"
                                                    onclick="return confirm('¿Seguro que deseas cambiar el estado?')">
                                                <i class="fas fa-<?php echo $seccion['estado'] == 1 ? 'times-circle' : 'check-circle'; ?>"></i>
                                                <?php echo $seccion['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Editar Sección
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="seccion_id" id="edit_seccion_id">
                <div class="modal-body">
                    <label class="form-label">Nombre de Sección:</label>
                    <input type="text" name="nombre_seccion" id="edit_nombre_seccion" 
                           class="form-control" required maxlength="10" 
                           placeholder="Ej: A, B, C">
                    <small class="text-muted">Se convertirá a mayúsculas automáticamente</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
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
function editarSeccion(id, nombre) {
    document.getElementById('edit_seccion_id').value = id;
    document.getElementById('edit_nombre_seccion').value = nombre;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>

</body>
</html>