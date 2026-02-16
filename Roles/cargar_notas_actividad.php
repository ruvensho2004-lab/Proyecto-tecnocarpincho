<?php
require_once '../includes/security.php';
require_once '../includes/conexion.php';

verificar_rol([3]); // Solo profesores

$actividad_id = $_GET['actividad_id'] ?? 0;

if (!$actividad_id) {
    header('Location: gestionar_actividades.php');
    exit();
}

// Obtener información de la actividad
$sqlAct = "SELECT a.*, g.nombre_grado, m.nombre_materia, s.nombre_seccion, p.nombre_periodo
           FROM actividad a
           INNER JOIN grados g ON a.grado_id = g.grado_id
           INNER JOIN materias m ON a.materia_id = m.materia_id
           LEFT JOIN secciones s ON a.seccion_id = s.seccion_id
           LEFT JOIN periodos p ON a.periodo_id = p.periodo_id
           WHERE a.actividad_id = :id";
$stmtAct = $pdo->prepare($sqlAct);
$stmtAct->execute(['id' => $actividad_id]);
$actividad = $stmtAct->fetch(PDO::FETCH_ASSOC);

if (!$actividad) {
    die("Actividad no encontrada");
}

$mensaje = '';
$tipo_mensaje = '';

// Guardar notas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_notas'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['notas'] as $alumno_id => $valor_nota) {
            if ($valor_nota === '') continue;
            
            $valor_nota = (int)$valor_nota;
            
            if ($valor_nota < 0 || $valor_nota > 20) {
                throw new Exception("La nota debe estar entre 0 y 20");
            }
            
            // Verificar si ya existe
            $sqlCheck = "SELECT nota_id FROM notas 
                        WHERE alumno_id = :alumno_id 
                        AND actividad_id = :actividad_id 
                        AND materia_id = :materia_id 
                        AND periodo_id = :periodo_id";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([
                'alumno_id' => $alumno_id,
                'actividad_id' => $actividad_id,
                'materia_id' => $actividad['materia_id'],
                'periodo_id' => $actividad['periodo_id']
            ]);
            
            if ($stmtCheck->rowCount() > 0) {
                // Actualizar
                $sql = "UPDATE notas SET valor_nota = :nota 
                        WHERE alumno_id = :alumno_id 
                        AND actividad_id = :actividad_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nota' => $valor_nota,
                    'alumno_id' => $alumno_id,
                    'actividad_id' => $actividad_id
                ]);
            } else {
                // Insertar
                $sql = "INSERT INTO notas (alumno_id, materia_id, actividad_id, periodo_id, valor_nota, fecha)
                        VALUES (:alumno_id, :materia_id, :actividad_id, :periodo_id, :nota, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'alumno_id' => $alumno_id,
                    'materia_id' => $actividad['materia_id'],
                    'actividad_id' => $actividad_id,
                    'periodo_id' => $actividad['periodo_id'],
                    'nota' => $valor_nota
                ]);
            }
        }
        
        $pdo->commit();
        $mensaje = "Notas guardadas exitosamente";
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener alumnos del grado y sección
$sqlAlumnos = "SELECT a.alumno_id, a.nombre_alumno, a.cedula, 
               (SELECT n.valor_nota FROM notas n 
                WHERE n.alumno_id = a.alumno_id 
                AND n.actividad_id = :actividad_id LIMIT 1) as nota_actual
               FROM alumnos a
               WHERE a.grado_id = :grado_id 
               AND a.estado = 1";

if ($actividad['seccion_id']) {
    $sqlAlumnos .= " AND a.seccion_id = :seccion_id";
}

$sqlAlumnos .= " ORDER BY a.nombre_alumno ASC";

$stmtAlumnos = $pdo->prepare($sqlAlumnos);
$stmtAlumnos->bindParam(':actividad_id', $actividad_id);
$stmtAlumnos->bindParam(':grado_id', $actividad['grado_id']);
if ($actividad['seccion_id']) {
    $stmtAlumnos->bindParam(':seccion_id', $actividad['seccion_id']);
}
$stmtAlumnos->execute();
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cargar Notas - <?php echo htmlspecialchars($actividad['nombre_actividad']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .activity-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .nota-input { width: 80px; text-align: center; font-weight: bold; }
        .nota-guardada { background-color: #d4edda; }
        .row-alumno:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-clipboard-check"></i> Cargar Calificaciones por Actividad</h3>
    </div>
</div>

<div class="container mt-4">
    <a href="gestionar_actividades.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver a Actividades
    </a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Información de la actividad -->
    <div class="activity-info">
        <h4><i class="fas fa-tasks"></i> <?php echo htmlspecialchars($actividad['nombre_actividad']); ?></h4>
        <div class="row mt-3">
            <div class="col-md-3">
                <strong><i class="fas fa-graduation-cap"></i> Grado:</strong><br>
                <?php echo htmlspecialchars($actividad['nombre_grado']); ?>
                <?php if ($actividad['nombre_seccion']): ?>
                    - Sección <?php echo htmlspecialchars($actividad['nombre_seccion']); ?>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <strong><i class="fas fa-book"></i> Materia:</strong><br>
                <?php echo htmlspecialchars($actividad['nombre_materia']); ?>
            </div>
            <div class="col-md-3">
                <strong><i class="fas fa-calendar"></i> Fecha:</strong><br>
                <?php echo date('d/m/Y', strtotime($actividad['fecha_actividad'])); ?>
            </div>
            <div class="col-md-3">
                <strong><i class="fas fa-percentage"></i> Ponderación:</strong><br>
                <?php echo $actividad['ponderacion']; ?>%
            </div>
        </div>
        <?php if ($actividad['descripcion']): ?>
        <div class="mt-2">
            <strong><i class="fas fa-info-circle"></i> Descripción:</strong><br>
            <?php echo htmlspecialchars($actividad['descripcion']); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Formulario de notas -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-users"></i> Lista de Alumnos (<?php echo count($alumnos); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (count($alumnos) > 0): ?>
            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Cédula</th>
                                <th>Nombre del Alumno</th>
                                <th width="150">Calificación (0-20)</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
                            foreach ($alumnos as $alumno): 
                                $tiene_nota = !is_null($alumno['nota_actual']);
                            ?>
                            <tr class="row-alumno <?php echo $tiene_nota ? 'nota-guardada' : ''; ?>">
                                <td><?php echo $contador++; ?></td>
                                <td><?php echo htmlspecialchars($alumno['cedula']); ?></td>
                                <td><strong><?php echo htmlspecialchars($alumno['nombre_alumno']); ?></strong></td>
                                <td>
                                    <input type="number" 
                                           name="notas[<?php echo $alumno['alumno_id']; ?>]" 
                                           class="form-control nota-input" 
                                           min="0" max="20" step="1"
                                           value="<?php echo $tiene_nota ? $alumno['nota_actual'] : ''; ?>"
                                           placeholder="-">
                                </td>
                                <td>
                                    <?php if ($tiene_nota): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> Calificado
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-clock"></i> Pendiente
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" name="guardar_notas" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Guardar Todas las Calificaciones
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No hay alumnos inscritos en este grado
                <?php echo $actividad['nombre_seccion'] ? ' y sección' : ''; ?>.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Estadísticas -->
    <?php
    $total = count($alumnos);
    $calificados = count(array_filter($alumnos, fn($a) => !is_null($a['nota_actual'])));
    $pendientes = $total - $calificados;
    $porcentaje = $total > 0 ? round(($calificados / $total) * 100) : 0;
    ?>
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5><i class="fas fa-chart-pie"></i> Progreso de Calificación</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <h3><?php echo $total; ?></h3>
                    <p>Total Alumnos</p>
                </div>
                <div class="col-md-3">
                    <h3 class="text-success"><?php echo $calificados; ?></h3>
                    <p>Calificados</p>
                </div>
                <div class="col-md-3">
                    <h3 class="text-warning"><?php echo $pendientes; ?></h3>
                    <p>Pendientes</p>
                </div>
                <div class="col-md-3">
                    <h3 class="text-primary"><?php echo $porcentaje; ?>%</h3>
                    <p>Completado</p>
                </div>
            </div>
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" style="width: <?php echo $porcentaje; ?>%">
                    <?php echo $porcentaje; ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-focus en el primer input vacío
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.nota-input');
    for (let input of inputs) {
        if (!input.value) {
            input.focus();
            break;
        }
    }
});

// Navegar con Enter
document.querySelectorAll('.nota-input').forEach((input, index, inputs) => {
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        }
    });
});
</script>

</body>
</html>