<?php
ob_start();
session_start();

require_once '../includes/security.php';
require_once '../includes/conexion.php';
require_once '../includes/fpdf/fpdf.php';

verificar_rol([1]);

function u($text) {
    return mb_convert_encoding((string)$text, 'ISO-8859-1', 'UTF-8');
}

$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;

if (!$alumno_id) {
    die("ID de alumno no válido.");
}

// Datos del alumno
$sql = "SELECT a.*, g.nombre_grado, s.nombre_seccion, u.usuario, u.estado as estado_usuario
        FROM alumnos a
        LEFT JOIN grados g ON a.grado_id = g.grado_id
        LEFT JOIN secciones s ON a.seccion_id = s.seccion_id
        LEFT JOIN usuarios u ON a.usuario_id = u.usuario_id
        WHERE a.alumno_id = :alumno_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['alumno_id' => $alumno_id]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    die("Alumno no encontrado.");
}

// Materias y promedio general del alumno
$sqlNotas = "SELECT m.nombre_materia,
                    COUNT(n.nota_id) as total_notas,
                    ROUND(AVG(n.valor_nota), 1) as promedio,
                    p.nombre_periodo
             FROM notas n
             INNER JOIN materias m ON n.materia_id = m.materia_id
             INNER JOIN periodos p ON n.periodo_id = p.periodo_id
             WHERE n.alumno_id = :alumno_id
             GROUP BY m.materia_id, p.periodo_id
             ORDER BY p.periodo_id, m.nombre_materia";
$stmtN = $pdo->prepare($sqlNotas);
$stmtN->execute(['alumno_id' => $alumno_id]);
$notas = $stmtN->fetchAll(PDO::FETCH_ASSOC);

// Promedio general
$sqlProm = "SELECT ROUND(AVG(valor_nota), 1) as promedio_general FROM notas WHERE alumno_id = :alumno_id";
$stmtP = $pdo->prepare($sqlProm);
$stmtP->execute(['alumno_id' => $alumno_id]);
$promedio_general = $stmtP->fetchColumn() ?: 'Sin notas';

ob_end_clean();

// ── Generar PDF ──────────────────────────────────────────────────────────────
$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->AddPage();
$pdf->SetMargins(20, 15, 20);

$pageW = 216;
$col   = $pageW - 40;

// Borde decorativo
$pdf->SetLineWidth(0.8);
$pdf->SetDrawColor(0, 31, 63);
$pdf->Rect(15, 12, $pageW - 30, 270);

// ── Encabezado ───────────────────────────────────────────────────────────────
$pdf->SetFont('Times', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell($col, 6, u('República Bolivariana de Venezuela'), 0, 1, 'C');
$pdf->SetFont('Times', '', 10);
$pdf->Cell($col, 5, u('Ministerio del Poder Popular para la Educación'), 0, 1, 'C');
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell($col, 6, u('Complejo Educativo Elba Hernández de Yánez'), 0, 1, 'C');
$pdf->SetFont('Times', '', 10);
$pdf->Cell($col, 5, 'La Vega - Dtto. Capital', 0, 1, 'C');

$pdf->Ln(3);
$pdf->SetLineWidth(0.5);
$pdf->Line(20, $pdf->GetY(), $pageW - 20, $pdf->GetY());
$pdf->Ln(4);

// Título
$pdf->SetFillColor(0, 31, 63);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Times', 'B', 14);
$pdf->Cell($col, 10, u('FICHA DEL ESTUDIANTE'), 0, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// Fecha
$pdf->SetFont('Times', '', 9);
$pdf->Cell($col, 5, u('Emitida el: ' . date('d/m/Y H:i') . '  |  Año Escolar: ' . ($alumno['ano_escolar'] ?? 'N/A')), 0, 1, 'R');
$pdf->Ln(2);

// ── Sección: Datos personales ────────────────────────────────────────────────
$pdf->SetFillColor(220, 230, 241);
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell($col, 7, u('  DATOS PERSONALES'), 0, 1, 'L', true);
$pdf->Ln(1);

$rowH  = 6.5;
$wLbl  = $col * 0.38;
$wVal  = $col * 0.62;
$fill  = false;

$personales = [
    [u('Apellidos y Nombres:'),    u(strtoupper($alumno['nombre_alumno']))],
    [u('Cédula de Identidad:'),    u($alumno['cedula'])],
    [u('Fecha de Nacimiento:'),    u(date('d/m/Y', strtotime($alumno['fecha_nac'])))],
    [u('Edad:'),                   u($alumno['edad'] . ' años')],
    [u('Dirección:'),              u($alumno['direccion'])],
    [u('Teléfono de Contacto:'),   u($alumno['telefono'])],
    [u('Correo Electrónico:'),     u($alumno['correo'])],
];

foreach ($personales as $row) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell($wLbl, $rowH, $row[0], 'B', 0, 'L', $fill);
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell($wVal, $rowH, $row[1], 'B', 1, 'L', $fill);
    $fill = !$fill;
}

$pdf->Ln(4);

// ── Sección: Datos académicos ────────────────────────────────────────────────
$pdf->SetFillColor(220, 230, 241);
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell($col, 7, u('  INFORMACIÓN ACADÉMICA'), 0, 1, 'L', true);
$pdf->Ln(1);

$fill = false;
$estado_txt = $alumno['estado'] == 1 ? u('Activo') : u('Inactivo');

$academicos = [
    [u('Año Escolar:'),        u($alumno['ano_escolar'] ?? 'N/A')],
    [u('Grado / Año:'),        u($alumno['nombre_grado'] ?? 'Sin asignar')],
    [u('Sección:'),            u($alumno['nombre_seccion'] ?? 'Sin asignar')],
    [u('Fecha de Ingreso:'),   u(date('d/m/Y', strtotime($alumno['fecha_registro'])))],
    [u('Estado:'),             $estado_txt],
    [u('Promedio General:'),   u((string)$promedio_general)],
    [u('Usuario del Sistema:'), u($alumno['usuario'] ?? 'N/A')],
];

foreach ($academicos as $row) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell($wLbl, $rowH, $row[0], 'B', 0, 'L', $fill);
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell($wVal, $rowH, $row[1], 'B', 1, 'L', $fill);
    $fill = !$fill;
}

$pdf->Ln(4);

// ── Sección: Tabla de notas (si hay) ─────────────────────────────────────────
if (count($notas) > 0) {
    $pdf->SetFillColor(220, 230, 241);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell($col, 7, u('  REGISTRO DE CALIFICACIONES'), 0, 1, 'L', true);
    $pdf->Ln(1);

    // Cabecera tabla
    $wPeriodo  = $col * 0.35;
    $wMateria  = $col * 0.40;
    $wPromedio = $col * 0.25;

    $pdf->SetFillColor(50, 80, 120);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell($wPeriodo,  7, u('Período'),  1, 0, 'C', true);
    $pdf->Cell($wMateria,  7, u('Materia'),  1, 0, 'C', true);
    $pdf->Cell($wPromedio, 7, u('Promedio'), 1, 1, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Times', '', 10);
    $fill = false;
    foreach ($notas as $nota) {
        $pdf->SetFillColor($fill ? 235 : 250, $fill ? 240 : 255, $fill ? 250 : 255);
        $pdf->Cell($wPeriodo,  6.5, u($nota['nombre_periodo']),  1, 0, 'L', $fill);
        $pdf->Cell($wMateria,  6.5, u($nota['nombre_materia']),  1, 0, 'L', $fill);
        $pdf->Cell($wPromedio, 6.5, u((string)$nota['promedio']), 1, 1, 'C', $fill);
        $fill = !$fill;
    }
    $pdf->Ln(3);
}

// ── Firmas ───────────────────────────────────────────────────────────────────
$pdf->Ln(6);
$wFirma = $col / 2;
$pdf->SetFont('Times', '', 11);
$pdf->Cell($wFirma, 0, '______________________________', 0, 0, 'C');
$pdf->Cell($wFirma, 0, '______________________________', 0, 1, 'C');
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell($wFirma, 6, 'SELLO DEL PLANTEL',   0, 0, 'C');
$pdf->Cell($wFirma, 6, 'FIRMA DEL DIRECTOR',  0, 1, 'C');

// ── Salida ───────────────────────────────────────────────────────────────────
$nombreArchivo = 'Ficha_' . preg_replace('/[^A-Za-z0-9_]/', '_', $alumno['nombre_alumno']) . '.pdf';
$pdf->Output('D', $nombreArchivo);
exit();
