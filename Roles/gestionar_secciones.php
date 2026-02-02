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

$grados = $pdo->query("SELECT * FROM grados ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);
$secciones = $pdo->query("SELECT s.*, g.nombre_grado FROM secciones s INNER JOIN grados g ON s.grado_id = g.grado_id ORDER BY g.grado_id, s.nombre_seccion")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Secciones</title>
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
        <h3><i class="fas fa-layer-group"></i> Gestión de Secciones</h3>
        <small>Administrador: <?php echo htmlspecialchars($nombre_admin); ?></small>
    </div>
</div>

<div class="container mt-4">
    <a href="admin.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Volver</a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-plus-circle"></i> Agregar Nueva Sección</h5>
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
                        <input type="text" name="nombre_seccion" class="form-control" placeholder="Ej: A, B, C" required maxlength="10">
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

    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-list"></i> Secciones por Grado</h5>
        </div>
        <div class="card-body">
            <?php
            $grado_actual = '';
            foreach ($secciones as $seccion):
                if ($grado_actual != $seccion['nombre_grado']):
                    if ($grado_actual != '') echo '</div></div>';
                    $grado_actual = $seccion['nombre_grado'];
            ?>
            <h6 class="mt-3"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($grado_actual); ?></h6>
            <div class="row">
            <?php endif; ?>
                <div class="col-md-3 mb-2">
                    <div class="card">
                        <div class="card-body p-2">
                            <strong>Sección <?php echo htmlspecialchars($seccion['nombre_seccion']); ?></strong>
                            <span class="badge bg-<?php echo $seccion['estado'] == 1 ? 'success' : 'danger'; ?> float-end">
                                <?php echo $seccion['estado'] == 1 ? 'Activa' : 'Inactiva'; ?>
                            </span>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-warning" onclick="editarSeccion(<?php echo $seccion['seccion_id']; ?>, '<?php echo $seccion['nombre_seccion']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="seccion_id" value="<?php echo $seccion['seccion_id']; ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?php echo $seccion['estado'] == 1 ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $seccion['estado'] == 1 ? 'danger' : 'success'; ?>">
                                        <i class="fas fa-<?php echo $seccion['estado'] == 1 ? 'times' : 'check'; ?>"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($grado_actual != '') echo '</div>'; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Sección</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="seccion_id" id="edit_seccion_id">
                <div class="modal-body">
                    <label class="form-label">Nombre de Sección:</label>
                    <input type="text" name="nombre_seccion" id="edit_nombre_seccion" class="form-control" required>
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
function editarSeccion(id, nombre) {
    document.getElementById('edit_seccion_id').value = id;
    document.getElementById('edit_nombre_seccion').value = nombre;
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>

</body>
</html>