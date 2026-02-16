<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Verificar que sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 1) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/conexion.php';

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
$mensaje = '';
$tipo_mensaje = '';

// Obtener todos los profesores
$profesores = $pdo->query("SELECT usuario_id, nombre, usuario FROM usuarios WHERE rol = 3 AND estado = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las materias
$materias = $pdo->query("SELECT materia_id, nombre_materia, grado_id FROM materias WHERE estado = 1 ORDER BY nombre_materia")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los grados
$grados = $pdo->query("SELECT grado_id, nombre_grado FROM grados WHERE estado = 1 ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las secciones
$secciones = $pdo->query("SELECT s.seccion_id, s.nombre_seccion, s.grado_id FROM secciones s WHERE s.estado = 1 ORDER BY s.grado_id, s.nombre_seccion")->fetchAll(PDO::FETCH_ASSOC);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['accion'])) {
            switch ($_POST['accion']) {
                case 'asignar':
                    $profesor_id = (int)$_POST['profesor_id'];
                    $materia_id = (int)$_POST['materia_id'];
                    $grado_id = (int)$_POST['grado_id'];
                    $seccion_id = (int)$_POST['seccion_id'];
                    
                    // Verificar que no exista ya la asignación
                    $sql_check = "SELECT asignacion_id FROM profesor_materia_seccion 
                                  WHERE profesor_id = :profesor_id 
                                  AND materia_id = :materia_id 
                                  AND grado_id = :grado_id 
                                  AND seccion_id = :seccion_id";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute([
                        'profesor_id' => $profesor_id,
                        'materia_id' => $materia_id,
                        'grado_id' => $grado_id,
                        'seccion_id' => $seccion_id
                    ]);
                    
                    if ($stmt_check->rowCount() > 0) {
                        throw new Exception("Esta asignación ya existe");
                    }
                    
                    // Insertar asignación
                    $sql = "INSERT INTO profesor_materia_seccion (profesor_id, materia_id, grado_id, seccion_id, estado) 
                            VALUES (:profesor_id, :materia_id, :grado_id, :seccion_id, 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'profesor_id' => $profesor_id,
                        'materia_id' => $materia_id,
                        'grado_id' => $grado_id,
                        'seccion_id' => $seccion_id
                    ]);
                    
                    $mensaje = "Asignación creada exitosamente";
                    $tipo_mensaje = "success";
                    break;
                    
                case 'eliminar':
                    $asignacion_id = (int)$_POST['asignacion_id'];
                    
                    $sql = "DELETE FROM profesor_materia_seccion WHERE asignacion_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['id' => $asignacion_id]);
                    
                    $mensaje = "Asignación eliminada exitosamente";
                    $tipo_mensaje = "success";
                    break;
                    
                case 'cambiar_estado':
                    $asignacion_id = (int)$_POST['asignacion_id'];
                    $nuevo_estado = (int)$_POST['nuevo_estado'];
                    
                    $sql = "UPDATE profesor_materia_seccion SET estado = :estado WHERE asignacion_id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['estado' => $nuevo_estado, 'id' => $asignacion_id]);
                    
                    $mensaje = $nuevo_estado == 1 ? "Asignación activada" : "Asignación desactivada";
                    $tipo_mensaje = "success";
                    break;
            }
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener todas las asignaciones con información completa
$sql_asignaciones = "SELECT 
                        pms.asignacion_id,
                        pms.profesor_id,
                        pms.estado,
                        pms.fecha_asignacion,
                        u.nombre AS profesor_nombre,
                        m.nombre_materia,
                        g.nombre_grado,
                        s.nombre_seccion
                    FROM profesor_materia_seccion pms
                    INNER JOIN usuarios u ON pms.profesor_id = u.usuario_id
                    INNER JOIN materias m ON pms.materia_id = m.materia_id
                    INNER JOIN grados g ON pms.grado_id = g.grado_id
                    INNER JOIN secciones s ON pms.seccion_id = s.seccion_id
                    ORDER BY u.nombre, g.nombre_grado, s.nombre_seccion, m.nombre_materia";
$stmt_asignaciones = $pdo->query($sql_asignaciones);
$asignaciones = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);

// Agrupar asignaciones por profesor
$asignaciones_por_profesor = [];
foreach ($asignaciones as $asig) {
    $asignaciones_por_profesor[$asig['profesor_id']][] = $asig;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Materias a Profesores</title>
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
        .card { 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
        }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem 1.5rem;
        }
        .profesor-card {
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }
        .profesor-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background 0.3s;
        }
        .profesor-header:hover {
            background: #e9ecef;
        }
        .asignacion-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .asignacion-item:last-child {
            border-bottom: none;
        }
        .badge-activo { background-color: #28a745; }
        .badge-inactivo { background-color: #dc3545; }
        .stats-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-chalkboard-teacher"></i> Asignar Materias a Profesores</h3>
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

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <i class="fas fa-chalkboard-teacher fa-3x text-primary mb-2"></i>
                <div class="stats-number"><?php echo count($profesores); ?></div>
                <div class="text-muted">Profesores Activos</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <i class="fas fa-clipboard-list fa-3x text-success mb-2"></i>
                <div class="stats-number"><?php echo count($asignaciones); ?></div>
                <div class="text-muted">Total Asignaciones</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <i class="fas fa-book fa-3x text-info mb-2"></i>
                <div class="stats-number"><?php echo count($materias); ?></div>
                <div class="text-muted">Materias Disponibles</div>
            </div>
        </div>
    </div>

    <!-- Formulario para Nueva Asignación -->
    <div class="card">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Nueva Asignación</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="accion" value="asignar">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Profesor *</label>
                        <select name="profesor_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($profesores as $prof): ?>
                                <option value="<?php echo $prof['usuario_id']; ?>">
                                    <?php echo htmlspecialchars($prof['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Materia *</label>
                        <select name="materia_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($materias as $mat): ?>
                                <option value="<?php echo $mat['materia_id']; ?>">
                                    <?php echo htmlspecialchars($mat['nombre_materia']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Grado *</label>
                        <select name="grado_id" id="select_grado" class="form-select" required onchange="filtrarSecciones(this.value)">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($grados as $grado): ?>
                                <option value="<?php echo $grado['grado_id']; ?>">
                                    <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Sección *</label>
                        <select name="seccion_id" id="select_seccion" class="form-select" required disabled>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($secciones as $seccion): ?>
                                <option value="<?php echo $seccion['seccion_id']; ?>"
                                        data-grado="<?php echo $seccion['grado_id']; ?>">
                                    <?php echo htmlspecialchars($seccion['nombre_seccion']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Asignar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de Asignaciones por Profesor -->
    <div class="card">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="fas fa-list"></i> Asignaciones por Profesor</h5>
        </div>
        <div class="card-body">
            <?php if (count($profesores) > 0): ?>
                <?php foreach ($profesores as $profesor): ?>
                    <div class="card profesor-card">
                        <div class="profesor-header" data-bs-toggle="collapse" data-bs-target="#profesor<?php echo $profesor['usuario_id']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <i class="fas fa-user-tie text-primary"></i>
                                        <?php echo htmlspecialchars($profesor['nombre']); ?>
                                    </h6>
                                    <small class="text-muted">Usuario: <?php echo htmlspecialchars($profesor['usuario']); ?></small>
                                </div>
                                <div>
                                    <span class="badge bg-info">
                                        <?php 
                                        $count = isset($asignaciones_por_profesor[$profesor['usuario_id']]) 
                                                ? count($asignaciones_por_profesor[$profesor['usuario_id']]) 
                                                : 0;
                                        echo $count;
                                        ?> asignación(es)
                                    </span>
                                    <i class="fas fa-chevron-down ms-2"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div id="profesor<?php echo $profesor['usuario_id']; ?>" class="collapse">
                            <div class="card-body p-0">
                                <?php if (isset($asignaciones_por_profesor[$profesor['usuario_id']])): ?>
                                    <?php foreach ($asignaciones_por_profesor[$profesor['usuario_id']] as $asig): ?>
                                        <div class="asignacion-item">
                                            <div>
                                                <strong><?php echo htmlspecialchars($asig['nombre_materia']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($asig['nombre_grado']); ?> - 
                                                    <?php echo htmlspecialchars($asig['nombre_seccion']); ?>
                                                </small>
                                                <br>
                                                <span class="badge <?php echo $asig['estado'] == 1 ? 'badge-activo' : 'badge-inactivo'; ?>">
                                                    <?php echo $asig['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <!-- Activar/Desactivar -->
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="asignacion_id" value="<?php echo $asig['asignacion_id']; ?>">
                                                    <input type="hidden" name="nuevo_estado" value="<?php echo $asig['estado'] == 1 ? 0 : 1; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $asig['estado'] == 1 ? 'btn-warning' : 'btn-success'; ?>" 
                                                            title="<?php echo $asig['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="fas fa-<?php echo $asig['estado'] == 1 ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- Eliminar -->
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('¿Estás seguro de eliminar esta asignación?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="asignacion_id" value="<?php echo $asig['asignacion_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-3 text-center text-muted">
                                        <i class="fas fa-inbox"></i> Sin asignaciones
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No hay profesores registrados.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filtrarSecciones(gradoId) {
    const selectSeccion = document.getElementById('select_seccion');
    const opciones = selectSeccion.querySelectorAll('option[data-grado]');

    // Resetear sección
    selectSeccion.value = '';

    if (!gradoId) {
        // Sin grado: ocultar todas y deshabilitar
        opciones.forEach(op => op.style.display = 'none');
        selectSeccion.disabled = true;
        return;
    }

    let hayOpciones = false;
    opciones.forEach(op => {
        if (op.dataset.grado === gradoId) {
            op.style.display = '';
            hayOpciones = true;
        } else {
            op.style.display = 'none';
        }
    });

    selectSeccion.disabled = !hayOpciones;
}

// Inicializar: ocultar todas las secciones al cargar
document.addEventListener('DOMContentLoaded', () => {
    const opciones = document.querySelectorAll('#select_seccion option[data-grado]');
    opciones.forEach(op => op.style.display = 'none');
});
</script>


</body>
</html>