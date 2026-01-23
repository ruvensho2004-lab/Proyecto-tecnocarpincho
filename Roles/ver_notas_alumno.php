<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 4) {
    header("Location: ../login.html");
    exit();
}

require_once '../includes/conexion.php';

$nombre_alumno = $_SESSION['usuario']['nombre'] ?? 'Estudiante';
$alumno_id = $_SESSION['usuario']['id'];

// Obtener el alumno_id de la tabla alumnos usando el usuario_id
$sqlAlumno = "SELECT alumno_id FROM alumnos WHERE usuario_id = :usuario_id";
$stmtAlumno = $pdo->prepare($sqlAlumno);
$stmtAlumno->execute(['usuario_id' => $alumno_id]);
$alumno = $stmtAlumno->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    die("<div class='alert alert-danger'>No se encontró información del alumno. Contacta al administrador.</div>");
}

$alumno_id = $alumno['alumno_id'];

// Obtener periodos para el filtro
$periodos = $pdo->query("SELECT * FROM periodos WHERE estado = 1 ORDER BY periodo_id")->fetchAll(PDO::FETCH_ASSOC);

// Filtro de periodo
$periodo_seleccionado = $_GET['periodo'] ?? '';

// Obtener notas del alumno
$sqlNotas = "SELECT n.*, m.nombre_materia, p.nombre_periodo, a.nombre_actividad
             FROM notas n
             INNER JOIN materias m ON n.materia_id = m.materia_id
             INNER JOIN periodos p ON n.periodo_id = p.periodo_id
             INNER JOIN actividad a ON n.actividad_id = a.actividad_id
             WHERE n.alumno_id = :alumno_id";

if ($periodo_seleccionado) {
    $sqlNotas .= " AND n.periodo_id = :periodo_id";
}

$sqlNotas .= " ORDER BY p.periodo_id, m.nombre_materia, a.nombre_actividad";

$stmtNotas = $pdo->prepare($sqlNotas);
$stmtNotas->bindParam(':alumno_id', $alumno_id);
if ($periodo_seleccionado) {
    $stmtNotas->bindParam(':periodo_id', $periodo_seleccionado);
}
$stmtNotas->execute();
$notas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);

// Calcular promedios por materia y periodo
$promedios = [];
foreach ($notas as $nota) {
    $key = $nota['nombre_periodo'] . '|' . $nota['nombre_materia'];
    if (!isset($promedios[$key])) {
        $promedios[$key] = [
            'periodo' => $nota['nombre_periodo'],
            'materia' => $nota['nombre_materia'],
            'suma' => 0,
            'cantidad' => 0,
            'notas' => []
        ];
    }
    $promedios[$key]['suma'] += $nota['valor_nota'];
    $promedios[$key]['cantidad']++;
    $promedios[$key]['notas'][] = [
        'actividad' => $nota['nombre_actividad'],
        'valor' => $nota['valor_nota']
    ];
}

// Calcular promedio general
$suma_total = 0;
$cantidad_total = 0;
foreach ($notas as $nota) {
    $suma_total += $nota['valor_nota'];
    $cantidad_total++;
}
$promedio_general = $cantidad_total > 0 ? round($suma_total / $cantidad_total, 2) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Calificaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .promedio-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .promedio-numero { font-size: 3rem; font-weight: bold; }
        .materia-card { border-left: 4px solid #001f3f; }
        .nota-item { 
            display: flex; 
            justify-content: space-between; 
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .nota-item:last-child { border-bottom: none; }
        .nota-excelente { color: #28a745; font-weight: bold; }
        .nota-buena { color: #ffc107; font-weight: bold; }
        .nota-regular { color: #dc3545; font-weight: bold; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="header-custom no-print">
    <div class="container">
        <h3><i class="fas fa-graduation-cap"></i> Mis Calificaciones</h3>
        <small>Estudiante: <?php echo htmlspecialchars($nombre_alumno); ?></small>
    </div>
</div>

<div class="container mt-4">
    <div class="no-print">
        <a href="alumno.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        <button onclick="window.print()" class="btn btn-success mb-3 float-end">
            <i class="fas fa-print"></i> Imprimir Boletín
        </button>
    </div>

    <!-- Promedio General -->
    <?php if ($cantidad_total > 0): ?>
    <div class="promedio-card mb-4">
        <h4>Promedio General</h4>
        <div class="promedio-numero"><?php echo $promedio_general; ?></div>
        <p>Basado en <?php echo $cantidad_total; ?> calificaciones</p>
    </div>
    <?php endif; ?>

    <!-- Filtro por periodo -->
    <div class="card no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Filtrar por periodo:</label>
                    <select name="periodo" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los periodos</option>
                        <?php foreach ($periodos as $periodo): ?>
                        <option value="<?php echo $periodo['periodo_id']; ?>" 
                                <?php echo $periodo_seleccionado == $periodo['periodo_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($periodo['nombre_periodo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <a href="ver_notas.php" class="btn btn-secondary d-block">
                        <i class="fas fa-times"></i> Limpiar filtro
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Notas por materia y periodo -->
    <?php if (count($promedios) > 0): ?>
        <?php 
        $periodo_actual = '';
        foreach ($promedios as $key => $data):
            $promedio_materia = round($data['suma'] / $data['cantidad'], 2);
            
            // Encabezado de periodo
            if ($periodo_actual != $data['periodo']):
                if ($periodo_actual != '') echo '</div>'; // Cerrar row anterior
                $periodo_actual = $data['periodo'];
        ?>
        <h4 class="mt-4 mb-3"><?php echo htmlspecialchars($periodo_actual); ?></h4>
        <div class="row">
        <?php endif; ?>
        
        <!-- Card de materia -->
        <div class="col-md-6 mb-3">
            <div class="card materia-card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <?php echo htmlspecialchars($data['materia']); ?>
                        <span class="float-end badge bg-primary">
                            Promedio: <?php echo $promedio_materia; ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($data['notas'] as $nota): 
                        $clase_nota = '';
                        if ($nota['valor'] >= 16) $clase_nota = 'nota-excelente';
                        elseif ($nota['valor'] >= 10) $clase_nota = 'nota-buena';
                        else $clase_nota = 'nota-regular';
                    ?>
                    <div class="nota-item">
                        <span><?php echo htmlspecialchars($nota['actividad']); ?></span>
                        <span class="<?php echo $clase_nota; ?>">
                            <?php echo $nota['valor']; ?>/20
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php endforeach; ?>
        </div> <!-- Cerrar último row -->
    <?php else: ?>
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle"></i> 
        No tienes calificaciones registradas<?php echo $periodo_seleccionado ? ' para el periodo seleccionado' : ''; ?>.
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>