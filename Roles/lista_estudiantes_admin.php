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

// Obtener todos los grados y secciones
$grados = $pdo->query("SELECT * FROM grados ORDER BY grado_id")->fetchAll(PDO::FETCH_ASSOC);
$secciones = $pdo->query("SELECT * FROM secciones ORDER BY nombre_seccion")->fetchAll(PDO::FETCH_ASSOC);

// Filtros seleccionados
$filtro_grado = isset($_GET['grado']) ? (int)$_GET['grado'] : 0;
$filtro_seccion = isset($_GET['seccion']) ? (int)$_GET['seccion'] : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir consulta de estudiantes
$sql_estudiantes = "SELECT 
                        a.alumno_id,
                        a.nombre,
                        a.apellido,
                        a.cedula,
                        a.fecha_nacimiento,
                        a.direccion,
                        a.telefono,
                        g.nombre_grado,
                        s.nombre_seccion,
                        u.usuario,
                        u.estado,
                        u.fecha_creacion
                    FROM alumnos a
                    INNER JOIN grados g ON a.grado_id = g.grado_id
                    INNER JOIN secciones s ON a.seccion_id = s.seccion_id
                    INNER JOIN usuarios u ON a.usuario_id = u.usuario_id
                    WHERE 1=1";

$params = [];

if ($filtro_grado > 0) {
    $sql_estudiantes .= " AND a.grado_id = :grado_id";
    $params['grado_id'] = $filtro_grado;
}

if ($filtro_seccion > 0) {
    $sql_estudiantes .= " AND a.seccion_id = :seccion_id";
    $params['seccion_id'] = $filtro_seccion;
}

if ($filtro_estado === 'activo') {
    $sql_estudiantes .= " AND u.estado = 1";
} elseif ($filtro_estado === 'inactivo') {
    $sql_estudiantes .= " AND u.estado = 0";
}

if (!empty($busqueda)) {
    $sql_estudiantes .= " AND (a.nombre LIKE :busqueda OR a.apellido LIKE :busqueda OR a.cedula LIKE :busqueda OR u.usuario LIKE :busqueda)";
    $params['busqueda'] = "%$busqueda%";
}

$sql_estudiantes .= " ORDER BY g.nombre_grado, s.nombre_seccion, a.apellido, a.nombre";

$stmt_estudiantes = $pdo->prepare($sql_estudiantes);
$stmt_estudiantes->execute($params);
$estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$total_estudiantes = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
$estudiantes_activos = $pdo->query("SELECT COUNT(*) FROM alumnos a INNER JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE u.estado = 1")->fetchColumn();
$estudiantes_inactivos = $total_estudiantes - $estudiantes_activos;

// Calcular edad promedio
$edad_promedio = 0;
$count_edad = 0;
foreach ($estudiantes as $est) {
    if (!empty($est['fecha_nacimiento'])) {
        $fecha_nac = new DateTime($est['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nac)->y;
        $edad_promedio += $edad;
        $count_edad++;
    }
}
$edad_promedio = $count_edad > 0 ? round($edad_promedio / $count_edad, 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Estudiantes - Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.2s;
            height: 100%;
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
        }
        .stats-label {
            color: #666;
            font-size: 0.9rem;
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
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="header-custom no-print">
    <div class="container">
        <h3><i class="fas fa-users"></i> Lista de Estudiantes</h3>
        <small>Administrador: <?php echo htmlspecialchars($nombre_admin); ?></small>
    </div>
</div>

<div class="container mt-4">
    <div class="no-print">
        <a href="admin.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        <a href="gestionar_alumnos.php" class="btn btn-primary mb-3">
            <i class="fas fa-user-plus"></i> Gestionar Alumnos
        </a>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-primary">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stats-number text-primary"><?php echo $total_estudiantes; ?></div>
                <div class="stats-label">Total de Estudiantes</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-number text-success"><?php echo $estudiantes_activos; ?></div>
                <div class="stats-label">Estudiantes Activos</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stats-number text-danger"><?php echo $estudiantes_inactivos; ?></div>
                <div class="stats-label">Estudiantes Inactivos</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-info">
                    <i class="fas fa-birthday-cake"></i>
                </div>
                <div class="stats-number text-info"><?php echo $edad_promedio; ?></div>
                <div class="stats-label">Edad Promedio (años)</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card no-print">
        <div class="card-header-custom">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtrar Estudiantes</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Grado</label>
                    <select name="grado" class="form-select" onchange="this.form.submit()">
                        <option value="0">Todos</option>
                        <?php foreach ($grados as $grado): ?>
                            <option value="<?php echo $grado['grado_id']; ?>" <?php echo $filtro_grado == $grado['grado_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grado['nombre_grado']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sección</label>
                    <select name="seccion" class="form-select" onchange="this.form.submit()">
                        <option value="0">Todas</option>
                        <?php foreach ($secciones as $seccion): ?>
                            <option value="<?php echo $seccion['seccion_id']; ?>" <?php echo $filtro_seccion == $seccion['seccion_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($seccion['nombre_seccion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busqueda" class="form-control" 
                           placeholder="Nombre, apellido, cédula o usuario..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                
                <?php if ($filtro_grado > 0 || $filtro_seccion > 0 || $filtro_estado !== 'todos' || !empty($busqueda)): ?>
                <div class="col-12">
                    <a href="lista_estudiantes_admin.php" class="btn btn-secondary btn-sm">
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
                Estudiantes Registrados: <?php echo count($estudiantes); ?>
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
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th class="no-print">Acciones</th>
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
                            <td><?php echo htmlspecialchars($estudiante['telefono'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $estudiante['estado'] == 1 ? 'badge-activo' : 'badge-inactivo'; ?>">
                                    <?php echo $estudiante['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <button class="btn btn-sm btn-info" onclick="verDetalles(<?php echo $estudiante['alumno_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="gestionar_alumnos.php?edit=<?php echo $estudiante['alumno_id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
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
    <div class="mt-4 mb-4 no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimir Lista
        </button>
        <button onclick="exportarExcel()" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Exportar a Excel
        </button>
        <button onclick="exportarPDF()" class="btn btn-danger">
            <i class="fas fa-file-pdf"></i> Exportar a PDF
        </button>
    </div>
</div>

<!-- Modal para Ver Detalles -->
<div class="modal fade" id="modalDetalles" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
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
        order: [[1, 'asc']],
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });
});

function verDetalles(alumnoId) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalles'));
    modal.show();
    
    // Cargar detalles del estudiante (puedes mejorar esto con AJAX)
    fetch(`get_alumno_details.php?id=${alumnoId}`)
        .then(response => response.json())
        .then(data => {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nombre:</strong> ${data.nombre} ${data.apellido}</p>
                        <p><strong>Cédula:</strong> ${data.cedula || 'N/A'}</p>
                        <p><strong>Fecha de Nacimiento:</strong> ${data.fecha_nacimiento || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Grado:</strong> ${data.nombre_grado}</p>
                        <p><strong>Sección:</strong> ${data.nombre_seccion}</p>
                        <p><strong>Teléfono:</strong> ${data.telefono || 'N/A'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <p><strong>Dirección:</strong> ${data.direccion || 'N/A'}</p>
                    </div>
                </div>
            `;
            document.getElementById('detallesEstudiante').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('detallesEstudiante').innerHTML = `
                <div class="alert alert-danger">Error al cargar los detalles</div>
            `;
        });
}

function exportarExcel() {
    // Funcionalidad básica de exportación
    const table = document.getElementById('tablaEstudiantes');
    const html = table.outerHTML;
    const url = 'data:application/vnd.ms-excel,' + escape(html);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'lista_estudiantes.xls';
    link.click();
}

function exportarPDF() {
    alert('Funcionalidad de exportación a PDF en desarrollo');
}
</script>

</body>
</html>