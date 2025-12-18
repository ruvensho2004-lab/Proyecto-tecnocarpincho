<?php
session_start();
include_once("../includes/conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profesor_id = $_SESSION['usuario']['usuario_id'];
    $materia_id = $_POST['materia_id'];
    $periodo = $_POST['periodo'];
    $alumnos = $_POST['alumnos_id'];
    $notas = $_POST['notas'];

    $stmt = $pdo->prepare("INSERT INTO notas (alumno_id, materia_id, profesor_id, valor_nota, periodo, fecha)
                           VALUES (:alumno_id, :materia_id, :profesor_id, :valor_nota, :periodo, NOW())");

    foreach ($alumnos as $i => $alumno_id) {
        $nota = $notas[$i];
        $stmt->execute([
            'alumno_id' => $alumno_id,
            'materia_id' => $materia_id,
            'profesor_id' => $profesor_id,
            'valor_nota' => $nota,
            'periodo' => $periodo
        ]);
    }

    echo "✅ Notas guardadas correctamente.";
    echo "<br><a href='cargar_notas.php'>Volver</a>";
}
?>