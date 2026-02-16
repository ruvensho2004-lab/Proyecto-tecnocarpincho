<?php
ob_start();
session_start();

require_once '../includes/security.php';
require_once '../includes/conexion.php';
require_once '../includes/fpdf/fpdf.php';

verificar_rol([1]);

// Función para convertir UTF-8 a ISO-8859-1
function u($text) {
    return mb_convert_encoding((string)$text, 'ISO-8859-1', 'UTF-8');
}

$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;

if (!$alumno_id) {
    die("ID de alumno no válido.");
}

// Obtener datos completos del alumno
$sql = "SELECT a.*, g.nombre_grado, s.nombre_seccion, u.usuario
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

// Número de comprobante
$num_comprobante = 'COMP-' . date('Y') . '-' . str_pad($alumno_id, 5, '0', STR_PAD_LEFT);

ob_end_clean();

// ── Generar PDF ──────────────────────────────────────────────────────────────
$pdf = new FPDF('P', 'mm', 'Letter');
$pdf->AddPage();
$pdf->SetMargins(20, 15, 20);

$pageW = 216;
$col   = $pageW - 40;

// Borde exterior decorativo
$pdf->SetLineWidth(0.8);
$pdf->SetDrawColor(0, 31, 63);
$pdf->Rect(15, 12, $pageW - 30, 255);

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
$pdf->SetDrawColor(0, 31, 63);
$pdf->Line(20, $pdf->GetY(), $pageW - 20, $pdf->GetY());
$pdf->Ln(4);

// ── Título del comprobante ───────────────────────────────────────────────────
$pdf->SetFillColor(0, 31, 63);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Times', 'B', 14);
$pdf->Cell($col, 10, u('COMPROBANTE DE REGISTRO DE ESTUDIANTE'), 0, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

// Número de comprobante y fecha
$pdf->SetFont('Times', '', 10);
$pdf->Cell($col / 2, 6, u('N° Comprobante: ' . $num_comprobante), 0, 0, 'L');
$pdf->Cell($col / 2, 6, u('Fecha de emisión: ' . date('d/m/Y H:i')), 0, 1, 'R');
$pdf->Ln(3);

// ── Datos personales ─────────────────────────────────────────────────────────
$pdf->SetFillColor(220, 230, 241);
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell($col, 7, u('  DATOS PERSONALES DEL ESTUDIANTE'), 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Times', '', 10);
$rowH = 7;
$wLabel = $col * 0.35;
$wValue = $col * 0.65;

$bd = ['style' => 'B', 'width' => 0.2, 'color' => [200, 200, 200]];

// Fila par/impar
$fill = false;
$datos = [
    [u('Apellidos y Nombres:'),    u(strtoupper($alumno['nombre_alumno']))],
    [u('Cédula de Identidad:'),    u($alumno['cedula'])],
    [u('Fecha de Nacimiento:'),    u(date('d/m/Y', strtotime($alumno['fecha_nac'])))],
    [u('Edad:'),                   u($alumno['edad'] . ' años')],
    [u('Dirección:'),              u($alumno['direccion'])],
    [u('Teléfono:'),               u($alumno['telefono'])],
    [u('Correo Electrónico:'),     u($alumno['correo'])],
];

foreach ($datos as $fila) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell($wLabel, $rowH, $fila[0], 'B', 0, 'L', $fill);
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell($wValue, $rowH, $fila[1], 'B', 1, 'L', $fill);
    $fill = !$fill;
}

$pdf->Ln(4);

// ── Datos académicos ─────────────────────────────────────────────────────────
$pdf->SetFillColor(220, 230, 241);
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell($col, 7, u('  DATOS ACADÉMICOS'), 0, 1, 'L', true);
$pdf->Ln(2);

$fill = false;
$datosAcad = [
    [u('Año Escolar:'),   u($alumno['ano_escolar'] ?? 'N/A')],
    [u('Grado / Año:'),   u($alumno['nombre_grado'] ?? 'Sin asignar')],
    [u('Sección:'),       u($alumno['nombre_seccion'] ?? 'Sin asignar')],
    [u('Fecha de Registro:'), u(date('d/m/Y', strtotime($alumno['fecha_registro'])))],
];

foreach ($datosAcad as $fila) {
    $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell($wLabel, $rowH, $fila[0], 'B', 0, 'L', $fill);
    $pdf->SetFont('Times', '', 10);
    $pdf->Cell($wValue, $rowH, $fila[1], 'B', 1, 'L', $fill);
    $fill = !$fill;
}

$pdf->Ln(4);

// ── Credenciales de acceso ───────────────────────────────────────────────────
$pdf->SetFillColor(220, 230, 241);
$pdf->SetFont('Times', 'B', 11);
$pdf->Cell($col, 7, u('  CREDENCIALES DE ACCESO AL SISTEMA'), 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFillColor(255, 255, 255);
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell($wLabel, $rowH, u('Usuario del sistema:'), 'B', 0, 'L');
$pdf->SetFont('Times', '', 10);
$pdf->Cell($wValue, $rowH, u($alumno['usuario'] ?? 'N/A'), 'B', 1, 'L');

$pdf->SetFont('Times', 'B', 10);
$pdf->Cell($wLabel, $rowH, u('Contraseña:'), 'B', 0, 'L');
$pdf->SetFont('Times', 'I', 10);
$pdf->Cell($wValue, $rowH, u('(La proporcionada al momento del registro)'), 'B', 1, 'L');

$pdf->Ln(8);

// ── Nota informativa ─────────────────────────────────────────────────────────
$pdf->SetFillColor(255, 243, 205);
$pdf->SetFont('Times', 'I', 9);
$pdf->MultiCell($col, 5, u('NOTA: Este comprobante certifica el registro del estudiante en el Sistema Académico del plantel. Conserve este documento como respaldo de inscripción. Para cualquier consulta comuníquese con la dirección del plantel.'), 1, 'L', true);

$pdf->Ln(10);

// ── Firmas ───────────────────────────────────────────────────────────────────
$wFirma = $col / 2;
$pdf->SetFont('Times', '', 11);
$pdf->Cell($wFirma, 0, '______________________________', 0, 0, 'C');
$pdf->Cell($wFirma, 0, '______________________________', 0, 1, 'C');
$pdf->SetFont('Times', 'B', 10);
$pdf->Cell($wFirma, 6, 'SELLO DEL PLANTEL',   0, 0, 'C');
$pdf->Cell($wFirma, 6, 'FIRMA DEL DIRECTOR',  0, 1, 'C');

// ── Salida ───────────────────────────────────────────────────────────────────
$nombreArchivo = 'Comprobante_' . preg_replace('/[^A-Za-z0-9_]/', '_', $alumno['nombre_alumno']) . '.pdf';
$pdf->Output('D', $nombreArchivo);
exit();
