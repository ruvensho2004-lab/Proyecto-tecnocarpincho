<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../includes/security.php';
require_once '../includes/conexion.php';

verificar_rol([1, 3]); // Admin y Profesores

$nombre_usuario = $_SESSION['usuario']['nombre'] ?? 'Usuario';
$rol_usuario = $_SESSION['usuario']['rol'];
$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $accion = $_POST['accion'] ?? '';
        
        switch ($accion) {
            case 'crear':
                $nombre = trim($_POST['nombre_actividad']);
                $grado_id = (int)$_POST['grado_id'];
                $seccion_id = !empty($_POST['seccion_id']) ? (int)$_POST['seccion_id'] : null;
                $materia_id = (int)$_POST['materia_id'];
                $descripcion = trim($_POST['descripcion']);
                $fecha = $_POST['fecha_actividad'];
                $ponderacion = (float)$_POST['ponderacion'];
                $periodo_id = (int)$_POST['periodo_id'];
                
                $sql = "INSERT INTO actividad (nombre_actividad, grado_id, materia_id, seccion_id, descripcion, fecha_actividad, ponderacion, estado) 
                        VALUES (:nombre, :grado_id, :materia_id, :seccion_id, :descripcion, :fecha, :ponderacion, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre,
                    'grado_id' => $grado_id,
                    'materia_id' => $materia_id,
                    'seccion_id' => $seccion_id,
                    'descripcion' => $descripcion,
                    'fecha' => $fecha,
                    'ponderacion' => $ponderacion
                ]);
                
                registrar_log_seguridad('Actividad creada', "Actividad: {$nombre}, Grado: {$grado_id}");
                $mensaje = "Actividad creada exitosamente";
                $tipo_mensaje = "success";
                break;
                
            case 'editar':
                $actividad_id = (int)$_POST['actividad_id'];
                $nombre = trim($_POST['nombre_actividad']);
                $descripcion = trim($_POST['descripcion']);
                $fecha = $_POST['fecha_actividad'];
                $ponderacion = (float)$_POST['ponderacion'];
                
                $sql = "UPDATE actividad SET 
                        nombre_actividad = :nombre,
                        descripcion = :descripcion,
                        fecha_actividad = :fecha,
                        ponderacion = :ponderacion
                        WHERE actividad_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'fecha' => $fecha,
                    'ponderacion' => $ponderacion,
                    'id' => $actividad_id
                ]);
                
                $mensaje = "Actividad actualizada";
                $tipo_mensaje = "success";
                break;
                
            case 'eliminar':
                $actividad_id = (int)$_POST['actividad_id'];
                
                $sql = "UPDATE actividad SET estado = 0 WHERE actividad_id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id' => $actividad_id]);
                
                $mensaje = "Actividad eliminada";
                $tipo_mensaje = "success";
                break;
        }
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener datos para los filtros
try {
    $grados = $pdo->query("SELECT * FROM grados WHERE estado = 1 ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);
    
    // IMPORTANTE: Incluir grado_id en la consulta si existe en la tabla
    // Si tu tabla materias NO tiene grado_id, usa COALESCE o quita esta columna del SELECT
    $materias = $pdo->query("SELECT materia_id, nombre_materia, grado_id FROM materias WHERE estado = 1 ORDER BY nombre_materia")->fetchAll(PDO::FETCH_ASSOC);
    
    $periodos = $pdo->query("SELECT * FROM periodos WHERE estado = 1 ORDER BY periodo_id")->fetchAll(PDO::FETCH_ASSOC);
    $secciones = $pdo->query("SELECT s.*, g.nombre_grado FROM secciones s INNER JOIN grados g ON s.grado_id = g.grado_id WHERE s.estado = 1")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='padding:20px; background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; border-radius:5px; margin:20px;'>
        <h3>❌ Error al cargar datos</h3>
        <p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p><strong>Código:</strong> " . $e->getCode() . "</p>
        <hr>
        <small><strong>Posible solución:</strong> Verifica que tu tabla 'materias' tenga la columna 'grado_id'. Si no la tiene, ejecuta:<br>
        <code>ALTER TABLE materias ADD COLUMN grado_id INT NULL;</code></small>
        </div>");
}

// Filtros
$filtro_grado = $_GET['filtro_grado'] ?? '';
$filtro_materia = $_GET['filtro_materia'] ?? '';

// Obtener actividades
$sql = "SELECT a.*, g.nombre_grado, m.nombre_materia, s.nombre_seccion
        FROM actividad a
        INNER JOIN grados g ON a.grado_id = g.grado_id
        INNER JOIN materias m ON a.materia_id = m.materia_id
        LEFT JOIN secciones s ON a.seccion_id = s.seccion_id
        WHERE a.estado = 1";

if ($filtro_grado) $sql .= " AND a.grado_id = :grado_id";
if ($filtro_materia) $sql .= " AND a.materia_id = :materia_id";

$sql .= " ORDER BY a.fecha_actividad DESC, g.grado_id, m.nombre_materia";

$stmt = $pdo->prepare($sql);
if ($filtro_grado) $stmt->bindParam(':grado_id', $filtro_grado);
if ($filtro_materia) $stmt->bindParam(':materia_id', $filtro_materia);
$stmt->execute();
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Actividades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .activity-card { border-left: 4px solid #007bff; transition: transform 0.2s; }
        .activity-card:hover { transform: translateX(5px); }
        .badge-ponderacion { font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-tasks"></i> Gestión de Actividades Académicas</h3>
        <small><?php echo htmlspecialchars($nombre_usuario); ?> (<?php echo $rol_usuario == 1 ? 'Administrador' : 'Profesor'; ?>)</small>
    </div>
</div>

<div class="container mt-4">
    <a href="<?php echo $rol_usuario == 1 ? 'admin.php' : 'profesores.php'; ?>" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver
    </a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Botón crear actividad -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalCrear">
        <i class="fas fa-plus-circle"></i> Nueva Actividad
    </button>

    <!-- Filtros -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5><i class="fas fa-filter"></i> Filtrar Actividades</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Grado:</label>
                    <select name="filtro_grado" class="form-select">
                        <option value="">Todos los grados</option>
                        <?php foreach ($grados as $grado): ?>
                        <option value="<?php echo $grado['grado_id']; ?>" 
                                <?php echo $filtro_grado == $grado['grado_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Materia:</label>
                    <select name="filtro_materia" class="form-select">
                        <option value="">Todas las materias</option>
                        <?php foreach ($materias as $materia): ?>
                        <option value="<?php echo $materia['materia_id']; ?>" 
                                <?php echo $filtro_materia == $materia['materia_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($materia['nombre_materia']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="gestionar_actividades.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de actividades -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-list"></i> Actividades Programadas (<?php echo count($actividades); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (count($actividades) > 0): ?>
                <?php foreach ($actividades as $actividad): ?>
                <div class="card activity-card mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-1">
                                    <i class="fas fa-clipboard-check text-primary"></i>
                                    <?php echo htmlspecialchars($actividad['nombre_actividad']); ?>
                                </h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($actividad['descripcion'] ?? 'Sin descripción'); ?></p>
                                <div>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($actividad['nombre_grado']); ?></span>
                                    <?php if ($actividad['nombre_seccion']): ?>
                                    <span class="badge bg-info">Sección <?php echo htmlspecialchars($actividad['nombre_seccion']); ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Todas las secciones</span>
                                    <?php endif; ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($actividad['nombre_materia']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="mb-2">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('d/m/Y', strtotime($actividad['fecha_actividad'])); ?>
                                </div>
                                <span class="badge bg-warning badge-ponderacion">
                                    Ponderación: <?php echo $actividad['ponderacion']; ?>%
                                </span>
                            </div>
                            <div class="col-md-3 text-end">
                                <a href="cargar_notas_actividad.php?actividad_id=<?php echo $actividad['actividad_id']; ?>" 
                                   class="btn btn-sm btn-success mb-1">
                                    <i class="fas fa-edit"></i> Cargar Notas
                                </a>
                                <button class="btn btn-sm btn-warning mb-1" 
                                        onclick='editarActividad(<?php echo json_encode($actividad); ?>)'>
                                    <i class="fas fa-pencil-alt"></i> Editar
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta actividad?')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="actividad_id" value="<?php echo $actividad['actividad_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger mb-1">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay actividades programadas con los filtros seleccionados.
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
                <h5 class="modal-title">Crear Nueva Actividad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre de la Actividad *</label>
                            <input type="text" name="nombre_actividad" class="form-control" 
                                   placeholder="Ej: Examen Final, Exposición, Proyecto" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Realización *</label>
                            <input type="date" name="fecha_actividad" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Grado *</label>
                            <select name="grado_id" id="crear_grado" class="form-select" required 
                                    onchange="cargarSeccionesPorGrado(this.value, 'crear_seccion'); cargarMateriasPorGrado(this.value, 'crear_materia');">
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
                            <select name="seccion_id" id="crear_seccion" class="form-select">
                                <option value="">Todas las secciones</option>
                            </select>
                            <small class="text-muted">Dejar en blanco para todas</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Materia *</label>
                            <select name="materia_id" id="crear_materia" class="form-select" required>
                                <option value="">Seleccione grado primero</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Periodo *</label>
                            <select name="periodo_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                <?php foreach ($periodos as $periodo): ?>
                                <option value="<?php echo $periodo['periodo_id']; ?>">
                                    <?php echo htmlspecialchars($periodo['nombre_periodo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ponderación (%) *</label>
                            <input type="number" name="ponderacion" class="form-control" 
                                   min="1" max="100" step="0.01" value="100" required>
                            <small class="text-muted">Peso de la actividad</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3" 
                                  placeholder="Detalles de la actividad..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Actividad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Editar Actividad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="actividad_id" id="edit_actividad_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre_actividad" id="edit_nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha_actividad" id="edit_fecha" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ponderación (%)</label>
                        <input type="number" name="ponderacion" id="edit_ponderacion" class="form-control" 
                               min="1" max="100" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const seccionesPorGrado = <?php echo json_encode(array_reduce($secciones, function($c, $i) {
    $c[$i['grado_id']][] = ['id' => $i['seccion_id'], 'nombre' => $i['nombre_seccion']];
    return $c;
}, [])); ?>;

const materiasPorGrado = <?php echo json_encode(array_reduce($materias, function($c, $i) {
    // Verificar si existe grado_id y si tiene valor
    if (isset($i['grado_id']) && !empty($i['grado_id'])) {
        $c[$i['grado_id']][] = ['id' => $i['materia_id'], 'nombre' => $i['nombre_materia']];
    } else {
        // Materias para todos los grados
        $c['todas'][] = ['id' => $i['materia_id'], 'nombre' => $i['nombre_materia']];
    }
    return $c;
}, ['todas' => []])); ?>;

function cargarSeccionesPorGrado(gradoId, selectId) {
    const select = document.getElementById(selectId);
    select.innerHTML = '<option value="">Todas las secciones</option>';
    
    if (gradoId && seccionesPorGrado[gradoId]) {
        seccionesPorGrado[gradoId].forEach(s => {
            select.innerHTML += `<option value="${s.id}">Sección ${s.nombre}</option>`;
        });
    }
}

function cargarMateriasPorGrado(gradoId, selectId) {
    const select = document.getElementById(selectId);
    select.innerHTML = '<option value="">Seleccione...</option>';
    
    // Materias específicas del grado
    if (gradoId && materiasPorGrado[gradoId]) {
        materiasPorGrado[gradoId].forEach(m => {
            select.innerHTML += `<option value="${m.id}">${m.nombre}</option>`;
        });
    }
    
    // Materias para todos los grados
    if (materiasPorGrado['todas']) {
        materiasPorGrado['todas'].forEach(m => {
            select.innerHTML += `<option value="${m.id}">${m.nombre} (Todos)</option>`;
        });
    }
}

function editarActividad(act) {
    document.getElementById('edit_actividad_id').value = act.actividad_id;
    document.getElementById('edit_nombre').value = act.nombre_actividad;
    document.getElementById('edit_fecha').value = act.fecha_actividad;
    document.getElementById('edit_ponderacion').value = act.ponderacion;
    document.getElementById('edit_descripcion').value = act.descripcion || '';
    
    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>

</body>
</html>