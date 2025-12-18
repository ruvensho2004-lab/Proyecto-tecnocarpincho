<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 3) {
    header("Location: ../index.php");
    exit();
}
include_once('../includes/conexion.php');

$profesor_id = $_SESSION['usuario']['usuario_id'] ?? null;

// Obtener materias
$stmt_m = $conn->prepare("SELECT materia_id, nombre_materia FROM materias WHERE estado = 1");
$stmt_m->execute();
$materias = $stmt_m->fetchAll();

// Obtener alumnos
$stmt_a = $conn->prepare("SELECT alumno_id, nombre_alumno FROM alumnos WHERE estado = 1");
$stmt_a->execute();
$alumnos = $stmt_a->fetchAll();

// Obtener actividades
$stmt_act = $conn->prepare("SELECT actividad_id, nombre_actividad FROM actividad WHERE estado = 1");
$stmt_act->execute();
$actividades = $stmt_act->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
<style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7faff;
            padding: 20px;
        }
        h2 {
            color: #003366;
        }
        .form-nota {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-nota label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        .form-nota input,
        .form-nota select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .form-nota input[type="submit"] {
            background-color: #003366;
            color: white;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        .form-nota input[type="submit"]:hover {
            background-color: #00509e;
        }
    </style>
</head>
<body class="bg-light p-4">
<h2>Cargar Nota</h2>

<form action="guardar_nota.php" method="POST" class="form-nota">
    <label for="materia">Materia:</label>
    <select name="materia_id" required>
        <option value="">Seleccione materia</option>
        <?php foreach ($materias as $m): ?>
            <option value="<?= $m['materia_id'] ?>"><?= $m['nombre_materia'] ?></option>
        <?php endforeach; ?>
    </select>

    <label for="alumno">Alumno:</label>
    <select name="alumno_id" required>
        <option value="">Seleccione alumno</option>
        <?php foreach ($alumnos as $a): ?>
            <option value="<?= $a['alumno_id'] ?>"><?= $a['nombre_alumno'] . ' '?></option>
        <?php endforeach; ?>
    </select>

    <label for="actividad">Actividad:</label>
    <select name="actividad_id" required>
        <option value="">Seleccione actividad</option>
        <?php foreach ($actividades as $act): ?>
            <option value="<?= $act['actividad_id'] ?>"><?= $act['nombre_actividad'] ?></option>
        <?php endforeach; ?>
    </select>

    <label for="valor_nota">Nota:</label>
    <input type="number" name="valor_nota" step="0.01" min="0" max="20" required>

    <label for="periodo_id">Período:</label>
    <input type="number" name="periodo_id" min="1" required>

    <input type="submit" value="Guardar Nota">
</form>

        <div class="mb-3">
            <label>Notas (una por alumno)</label>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Nota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Puedes filtrar alumnos según materia/aula real aquí
                    $alumnos = $conn->query("SELECT alumno_id, nombre_alumno FROM alumnos WHERE estado = 1")->fetchAll();
                    foreach ($alumnos as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['nombre_alumno']) ?></td>
                            <td>
                                <input type="hidden" name="alumnos_id[]" value="<?= $a['alumno_id'] ?>">
                                <input type="number" name="notas[]" class="form-control" step="0.01" min="0" max="20" required>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Notas</button>
    </form>
</div>

</body>
</html>
