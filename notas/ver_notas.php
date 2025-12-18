<?php
// Mostrar errores (opcional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 4) {
    header("Location: ../index.php");
    exit();
}

include_once("../includes/conexion.php");
$alumno_id = $_SESSION['usuario']['usuario_id'];

// Filtrar por periodo (opcional)
$periodo = $_GET['periodo'] ?? '1';

// Obtener notas del alumno
$sql = "SELECT m.nombre_materia, n.valor_nota, n.periodo, n.fecha
        FROM notas n
        JOIN materias m ON n.materia_id = m.materia_id
        WHERE n.alumno_id = :alumno_id AND n.periodo = :periodo
        ORDER BY m.nombre_materia";

$stmt = $pdo->prepare($sql);
$stmt->execute(['alumno_id' => $alumno_id, 'periodo' => $periodo]);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Notas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 bg-white p-4 rounded shadow">
    <h4>Mis Notas – Periodo <?= htmlspecialchars($periodo) ?></h4>

    <form method="get" class="mb-3">
        <label>Seleccionar Periodo:</label>
        <select name="periodo" onchange="this.form.submit()" class="form-select w-auto d-inline-block">
            <option value="1" <?= $periodo == 1 ? 'selected' : '' ?>>1er Trimestre</option>
            <option value="2" <?= $periodo == 2 ? 'selected' : '' ?>>2do Trimestre</option>
            <option value="3" <?= $periodo == 3 ? 'selected' : '' ?>>3er Trimestre</option>
        </select>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Materia</th>
                <th>Nota</th>
                <th>Fecha de Registro</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($notas) > 0): ?>
                <?php foreach ($notas as $n): ?>
                    <tr>
                        <td><?= htmlspecialchars($n['nombre_materia']) ?></td>
                        <td><?= $n['valor_nota'] ?></td>
                        <td><?= date('d/m/Y', strtotime($n['fecha'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3">No hay notas registradas para este periodo.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="alumno.php" class="btn btn-secondary mt-3">Volver al inicio</a>
</div>
</body>
</html>
