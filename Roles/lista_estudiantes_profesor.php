<?php
session_start();

// Verificar que sea profesor
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/conexion.php';

$profesor_id = $_SESSION['usuario']['id'];
$nombre_profesor = $_SESSION['usuario']['nombre'] ?? 'Profesor';

// Obtener las materias, grados y secciones que imparte este profesor
$sql_asignaciones = "SELECT DISTINCT 
                        m.materia_id, m.nombre_materia,
                        g.grado_id, g.nombre_grado,
                        s.seccion_id, s.nombre_seccion
                     FROM profesor_materia_seccion pms
                     INNER JOIN materias m ON pms.materia_id = m.materia_id
                     INNER JOIN grados g ON pms.grado_id = g.grado_id
                     INNER JOIN secciones s ON pms.seccion_id = s.seccion_id
                     WHERE pms.profesor_id = :profesor_id AND pms.estado = 1
                     ORDER BY g.nombre_grado, s.nombre_seccion, m.nombre_materia";
$stmt_asignaciones = $pdo->prepare($sql_asignaciones);
$stmt_asignaciones->execute(['profesor_id' => $profesor_id]);
$asignaciones = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);

// Filtros seleccionados
$filtro_grado = isset($_GET['grado']) ? (int)$_GET['grado'] : 0;
$filtro_seccion = isset($_GET['seccion']) ? (int)$_GET['seccion'] : 0;
$filtro_materia = isset($_GET['materia']) ? (int)$_GET['materia'] : 0;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir consulta de estudiantes
$sql_estudiantes = "SELECT DISTINCT
                        a.alumno_id,
                        a.nombre,
                        a.apellido,
                        a.cedula,
                        a.fecha_nacimiento,
                        g.nombre_grado,
                        s.nombre_seccion,
                        u.usuario,
                        u.estado
                    FROM alumnos a
                    INNER JOIN grados g ON a.grado_id = g.grado_id
                    INNER JOIN secciones s ON a.seccion_id = s.seccion_id
                    INNER JOIN usuarios u ON a.usuario_id = u.usuario_id
                    INNER JOIN profesor_materia_seccion pms ON 
                        pms.grado_id = a.grado_id AND 
                        pms.seccion_id = a.seccion_id
                    WHERE pms.profesor_id = :profesor_id 
                    AND pms.estado = 1";

$params = ['profesor_id' => $profesor_id];

if ($filtro_grado > 0) {
    $sql_estudiantes .= " AND a.grado_id = :grado_id";
    $params['grado_id'] = $filtro_grado;
}

if ($filtro_seccion > 0) {
    $sql_estudiantes .= " AND a.seccion_id = :seccion_id";
    $params['seccion_id'] = $filtro_seccion;
}

if (!empty($busqueda)) {
    $sql_estudiantes .= " AND (a.nombre LIKE :busqueda OR a.apellido LIKE :busqueda OR a.cedula LIKE :busqueda)";
    $params['busqueda'] = "%$busqueda%";
}

$sql_estudiantes .= " ORDER BY g.nombre_grado, s.nombre_seccion, a.apellido, a.nombre";

$stmt_estudiantes = $pdo->prepare($sql_estudiantes);
$stmt_estudiantes->execute($params);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista única de grados y secciones del profesor
$grados_profesor = [];
$secciones_profesor = [];
foreach ($asignaciones as $asig) {
    if (!isset($grados_profesor[$asig['grado_id']])) {
        $grados_profesor[$asig['grado_id']] = $asig['nombre_grado'];
    }
    if (!isset($secciones_profesor[$asig['seccion_id']])) {
        $secciones_profesor[$asig['seccion_id']] = $asig['nombre_seccion'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Estudiantes - Profesor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { 
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
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
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem 1.5rem;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #17a2b8;
        }
        .stats-label {
            color: #666;
            font-size: 0.9rem;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .student-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .badge-activo { background-color: #28a745; }
        .badge-inactivo { background-color: #dc3545; }
        .asignacion-badge {
            display: inline-block;
            padding: 5px 10px;
            margin: 3px;
            border-radius: 15px;
            font-size: 0.85rem;
            background: #e7f3ff;
            color: #0066cc;
            border: 1px solid #b3d9ff;
        }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-users"></i> Lista de Estudiantes</h3>
        <small>Profesor: <?php echo htmlspecialchars($nombre_profesor); ?></small>
    </div>
</div>

<div class="container mt-4">
    <a href="profesores.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver al Panel
    </a>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon text-primary">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stats-number"><?php echo count($estudiantes); ?></div>
                <div class="stats-label">Total de Estudiantes</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon text-success">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div class="stats-number"><?php echo count($grados_profesor); ?></div>
                <div class="stats-label">Grados Asignados</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card">
                <div class="stats-icon text-info">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stats-number"><?php echo count($secciones_profesor); ?></div>
                <div class="stats-label">Secciones Asignadas</div>
            </div>
        </div>
    </div>

    <!-- Mis Asignaciones -->
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3"><i class="fas fa-clipboard-list"></i> Mis Asignaciones:</h6>
            <?php if (count($asignaciones) > 0): ?>
                <?php foreach ($asignaciones as $asig): ?>
                    <span class="asignacion-badge">
                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($asig['nombre_materia']); ?> - 
                        <?php echo htmlspecialchars($asig['nombre_grado']); ?> 
                        <?php echo htmlspecialchars($asig['nombre_seccion']); ?>
                    </span>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> No tienes asignaciones activas.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtrar Estudiantes</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Grado</label>
                    <select name="grado" class="form-select" onchange="this.form.submit()">
                        <option value="0">Todos los grados</option>
                        <?php foreach ($grados_profesor as $grado_id => $nombre_grado): ?>
                            <option value="<?php echo $grado_id; ?>" <?php echo $filtro_grado == $grado_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nombre_grado); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Sección</label>
                    <select name="seccion" class="form-select" onchange="this.form.submit()">
                        <option value="0">Todas las secciones</option>
                        <?php foreach ($secciones_profesor as $seccion_id => $nombre_seccion): ?>
                            <option value="<?php echo $seccion_id; ?>" <?php echo $filtro_seccion == $seccion_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nombre_seccion); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busqueda" class="form-control" 
                           placeholder="Nombre, apellido o cédula..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                
                <?php if ($filtro_grado > 0 || $filtro_seccion > 0 || !empty($busqueda)): ?>
                <div class="col-12">
                    <a href="lista_estudiantes_profesor.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Listado de Estudiantes -->
    <div class="card mt-4">
        <div class="card-header-custom">
            <h5 class="mb-0">
                <i class="fas fa-list"></i> 
                Estudiantes Encontrados: <?php echo count($estudiantes); ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($estudiantes) > 0): ?>
            <div class="table-responsive">
                <table id="tablaEstudiantes" class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Estudiante</th>
                            <th>Cédula</th>
                            <th>Grado</th>
                            <th>Sección</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <td><?php echo $estudiante['alumno_id']; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="student-photo me-2">
                                        <?php echo strtoupper(substr($estudiante['nombre'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($estudiante['nombre'] . ' ' . $estudiante['apellido']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($estudiante['cedula'] ?? 'N/A'); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($estudiante['nombre_grado']); ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($estudiante['nombre_seccion']); ?></span></td>
                            <td><?php echo htmlspecialchars($estudiante['usuario']); ?></td>
                            <td>
                                <span class="badge <?php echo $estudiante['estado'] == 1 ? 'badge-activo' : 'badge-inactivo'; ?>">
                                    <?php echo $estudiante['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="verDetalles(<?php echo $estudiante['alumno_id']; ?>)">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                No se encontraron estudiantes con los filtros seleccionados.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botones de Acción -->
    <div class="mt-4 mb-4">
        <button onclick="imprimirLista()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimir Lista
        </button>
        <button onclick="exportarExcel()" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
    </div>
</div>

<!-- Modal para Ver Detalles -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Detalles del Estudiante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallesEstudiante">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#tablaEstudiantes').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        pageLength: 25,
        order: [[1, 'asc']]
    });
});

function verDetalles(alumnoId) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
    modal.show();
    
    // Aquí puedes cargar los detalles con AJAX si quieres
    document.getElementById('detallesEstudiante').innerHTML = `
        <p>Funcionalidad de detalles en desarrollo...</p>
        <p>ID del alumno: ${alumnoId}</p>
    `;
}

function imprimirLista() {
    window.print();
}

function exportarExcel() {
    alert('Funcionalidad de exportación a Excel en desarrollo');
}
</script>

</body>
</html>