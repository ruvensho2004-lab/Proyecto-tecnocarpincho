<?php
session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 3) {
    header("Location: ../login.html");
    exit();
}

require_once '../includes/conexion.php';

$nombre_profesor = $_SESSION['usuario']['nombre'] ?? 'Profesor';
$profesor_id = $_SESSION['usuario']['id'];
$mensaje = '';
$tipo_mensaje = '';

// Guardar nota
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_nota'])) {
    try {
        $alumno_id = (int)$_POST['alumno_id'];
        $materia_id = (int)$_POST['materia_id'];
        $actividad_id = (int)$_POST['actividad_id'];
        $periodo_id = (int)$_POST['periodo_id'];
        $valor_nota = (int)$_POST['valor_nota'];
        
        // Validar rango de nota
        if ($valor_nota < 0 || $valor_nota > 20) {
            throw new Exception("La nota debe estar entre 0 y 20");
        }
        
        // Verificar si ya existe una nota para esta combinación
        $sqlCheck = "SELECT nota_id FROM notas 
                     WHERE alumno_id = :alumno_id 
                     AND materia_id = :materia_id 
                     AND actividad_id = :actividad_id 
                     AND periodo_id = :periodo_id";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([
            'alumno_id' => $alumno_id,
            'materia_id' => $materia_id,
            'actividad_id' => $actividad_id,
            'periodo_id' => $periodo_id
        ]);
        
        if ($stmtCheck->rowCount() > 0) {
            // Actualizar nota existente
            $sql = "UPDATE notas SET valor_nota = :valor_nota 
                    WHERE alumno_id = :alumno_id 
                    AND materia_id = :materia_id 
                    AND actividad_id = :actividad_id 
                    AND periodo_id = :periodo_id";
            $mensaje = "Nota actualizada exitosamente";
        } else {
            // Insertar nueva nota
            $sql = "INSERT INTO notas (alumno_id, materia_id, actividad_id, periodo_id, valor_nota, fecha) 
                    VALUES (:alumno_id, :materia_id, :actividad_id, :periodo_id, :valor_nota, NOW())";
            $mensaje = "Nota registrada exitosamente";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'alumno_id' => $alumno_id,
            'materia_id' => $materia_id,
            'actividad_id' => $actividad_id,
            'periodo_id' => $periodo_id,
            'valor_nota' => $valor_nota
        ]);
        
        $tipo_mensaje = "success";
    } catch (Exception $e) {
        $mensaje = "Error: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// Obtener datos para los selectores
$materias = $pdo->query("SELECT * FROM materias WHERE estado = 1 ORDER BY nombre_materia")->fetchAll(PDO::FETCH_ASSOC);
$periodos = $pdo->query("SELECT * FROM periodos WHERE estado = 1 ORDER BY periodo_id")->fetchAll(PDO::FETCH_ASSOC);
$actividades = $pdo->query("SELECT * FROM actividad WHERE estado = 1 ORDER BY nombre_actividad")->fetchAll(PDO::FETCH_ASSOC);
$alumnos = $pdo->query("SELECT * FROM alumnos WHERE estado = 1 ORDER BY nombre_alumno")->fetchAll(PDO::FETCH_ASSOC);

// Obtener notas ya cargadas (filtradas si se selecciona)
$notas_cargadas = [];
$filtro_materia = $_GET['filtro_materia'] ?? '';
$filtro_periodo = $_GET['filtro_periodo'] ?? '';

$sqlNotas = "SELECT n.*, a.nombre_alumno, m.nombre_materia, p.nombre_periodo, ac.nombre_actividad
             FROM notas n
             INNER JOIN alumnos a ON n.alumno_id = a.alumno_id
             INNER JOIN materias m ON n.materia_id = m.materia_id
             INNER JOIN periodos p ON n.periodo_id = p.periodo_id
             INNER JOIN actividad ac ON n.actividad_id = ac.actividad_id
             WHERE 1=1";

if ($filtro_materia) {
    $sqlNotas .= " AND n.materia_id = :materia_id";
}
if ($filtro_periodo) {
    $sqlNotas .= " AND n.periodo_id = :periodo_id";
}

$sqlNotas .= " ORDER BY a.nombre_alumno, m.nombre_materia, p.periodo_id, ac.nombre_actividad";

$stmtNotas = $pdo->prepare($sqlNotas);
if ($filtro_materia) {
    $stmtNotas->bindParam(':materia_id', $filtro_materia);
}
if ($filtro_periodo) {
    $stmtNotas->bindParam(':periodo_id', $filtro_periodo);
}
$stmtNotas->execute();
$notas_cargadas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Notas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f4f4; }
        .header-custom { background-color: #001f3f; color: white; padding: 1rem 2rem; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .nota-excelente { background-color: #d4edda; }
        .nota-buena { background-color: #fff3cd; }
        .nota-regular { background-color: #f8d7da; }
    </style>
</head>
<body>

<div class="header-custom">
    <div class="container">
        <h3><i class="fas fa-clipboard-list"></i> Cargar Calificaciones</h3>
        <small>Profesor: <?php echo htmlspecialchars($nombre_profesor); ?></small>
    </div>
</div>

<div class="container mt-4">
    <a href="profesores.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Volver al Panel
    </a>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($mensaje); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Formulario para cargar nota -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5><i class="fas fa-plus-circle"></i> Registrar Nueva Calificación</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Alumno:</label>
                        <select name="alumno_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($alumnos as $alumno): ?>
                            <option value="<?php echo $alumno['alumno_id']; ?>">
                                <?php echo htmlspecialchars($alumno['nombre_alumno']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Materia:</label>
                        <select name="materia_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($materias as $materia): ?>
                            <option value="<?php echo $materia['materia_id']; ?>">
                                <?php echo htmlspecialchars($materia['nombre_materia']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Periodo:</label>
                        <select name="periodo_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($periodos as $periodo): ?>
                            <option value="<?php echo $periodo['periodo_id']; ?>">
                                <?php echo htmlspecialchars($periodo['nombre_periodo']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Actividad:</label>
                        <select name="actividad_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($actividades as $actividad): ?>
                            <option value="<?php echo $actividad['actividad_id']; ?>">
                                <?php echo htmlspecialchars($actividad['nombre_actividad']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Nota (0-20):</label>
                        <input type="number" name="valor_nota" class="form-control" 
                               min="0" max="20" step="1" required>
                    </div>
                </div>
                
                <button type="submit" name="guardar_nota" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Calificación
                </button>
            </form>
        </div>
    </div>

    <!-- Filtros y notas cargadas -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5><i class="fas fa-list"></i> Calificaciones Registradas</h5>
        </div>
        <div class="card-body">
            <!-- Filtros -->
            <form method="GET" class="row g-3 mb-3">
                <div class="col-md-4">
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
                    <select name="filtro_periodo" class="form-select">
                        <option value="">Todos los periodos</option>
                        <?php foreach ($periodos as $periodo): ?>
                        <option value="<?php echo $periodo['periodo_id']; ?>" 
                                <?php echo $filtro_periodo == $periodo['periodo_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($periodo['nombre_periodo']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="cargar_notas.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>

            <!-- Tabla de notas -->
            <?php if (count($notas_cargadas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Alumno</th>
                            <th>Materia</th>
                            <th>Periodo</th>
                            <th>Actividad</th>
                            <th>Nota</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notas_cargadas as $nota): 
                            $clase_nota = '';
                            if ($nota['valor_nota'] >= 16) $clase_nota = 'nota-excelente';
                            elseif ($nota['valor_nota'] >= 10) $clase_nota = 'nota-buena';
                            else $clase_nota = 'nota-regular';
                        ?>
                        <tr class="<?php echo $clase_nota; ?>">
                            <td><?php echo htmlspecialchars($nota['nombre_alumno']); ?></td>
                            <td><?php echo htmlspecialchars($nota['nombre_materia']); ?></td>
                            <td><?php echo htmlspecialchars($nota['nombre_periodo']); ?></td>
                            <td><?php echo htmlspecialchars($nota['nombre_actividad']); ?></td>
                            <td><strong><?php echo $nota['valor_nota']; ?>/20</strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($nota['fecha'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay calificaciones registradas con los filtros seleccionados.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>