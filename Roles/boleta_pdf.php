<?php
ob_start();
session_start();

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['usuario']['rol'], [4, 1])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/conexion.php';
require_once '../includes/fpdf/fpdf.php';

// ── UTF-8 → ISO-8859-1 ────────────────────────────────────────────────────────
function u($t) {
    return mb_convert_encoding((string)$t, 'ISO-8859-1', 'UTF-8');
}

// ── Obtener alumno_id ─────────────────────────────────────────────────────────
$usuario_id = $_SESSION['usuario']['id'];
$rol        = $_SESSION['usuario']['rol'];

if ($rol == 1 && isset($_GET['alumno_id'])) {
    $alumno_id = (int)$_GET['alumno_id'];
} else {
    $r = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
    $r->execute([$usuario_id]);
    $alumno_id = (int)$r->fetchColumn();
}

if (!$alumno_id) { ob_end_clean(); die("Alumno no encontrado."); }

// ── Datos del alumno ──────────────────────────────────────────────────────────
$stmtA = $pdo->prepare("
    SELECT a.nombre_alumno, a.cedula, a.ano_escolar, a.fecha_nac,
           g.nombre_grado, s.nombre_seccion, a.edad
    FROM alumnos a
    INNER JOIN grados    g ON a.grado_id   = g.grado_id
    INNER JOIN secciones s ON a.seccion_id = s.seccion_id
    WHERE a.alumno_id = ?
");
$stmtA->execute([$alumno_id]);
$alumno = $stmtA->fetch(PDO::FETCH_ASSOC);
if (!$alumno) { ob_end_clean(); die("Datos de alumno no encontrados."); }

// ── Periodos (Lapso 1, 2, 3 → tomamos los 3 primeros activos) ────────────────
$periodos = $pdo->query("SELECT periodo_id, nombre_periodo FROM periodos WHERE estado=1 ORDER BY periodo_id LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

// ── Materias ordenadas ────────────────────────────────────────────────────────
$materias_bd = $pdo->query("SELECT materia_id, nombre_materia FROM materias WHERE estado=1 ORDER BY materia_id")->fetchAll(PDO::FETCH_ASSOC);

// ── Notas: [periodo_id][materia_id] = promedio ────────────────────────────────
$stmtN = $pdo->prepare("
    SELECT n.periodo_id, n.materia_id, ROUND(AVG(n.valor_nota),0) AS prom
    FROM notas n
    WHERE n.alumno_id = ?
    GROUP BY n.periodo_id, n.materia_id
");
$stmtN->execute([$alumno_id]);
$notas_map = [];
foreach ($stmtN->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $notas_map[$row['periodo_id']][$row['materia_id']] = (int)$row['prom'];
}

// Nota final = promedio de todos los lapsos por materia
$notas_final = [];
foreach ($materias_bd as $mat) {
    $mid  = $mat['materia_id'];
    $vals = [];
    foreach ($periodos as $p) {
        if (isset($notas_map[$p['periodo_id']][$mid])) {
            $vals[] = $notas_map[$p['periodo_id']][$mid];
        }
    }
    $notas_final[$mid] = count($vals) ? (int)round(array_sum($vals) / count($vals)) : null;
}

// Promedio general final
$vals_gen = array_filter($notas_final, fn($v) => $v !== null);
$prom_general = count($vals_gen) ? round(array_sum($vals_gen) / count($vals_gen), 1) : 0;

// ── FPDF extendido ────────────────────────────────────────────────────────────
class Boleta extends FPDF {
    public $pageW = 216;
    function Header() {}
    function Footer() {}

    // Celda con texto centrado vertical
    function CellVC($w, $h, $txt, $border=1, $ln=0, $align='C', $fill=false) {
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill);
    }
}

ob_end_clean();

$pdf = new Boleta('P','mm','Letter');
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$pW  = 216;
$usW = $pW - 16; // 200mm usable

// ════════════════════════════════════════════════════════════
// ENCABEZADO
// ════════════════════════════════════════════════════════════
$pdf->SetFont('Arial','B',9);
$pdf->Cell($usW/2, 4, u('REPÚBLICA BOLIVARIANA DE VENEZUELA'), 0, 0, 'C');
$pdf->Cell($usW/2, 4, '', 0, 1, 'C');

$pdf->SetFont('Arial','',8);
$pdf->Cell($usW/2, 4, u('Ministerio del Poder Popular para la Educación'), 0, 0, 'C');

// Logo espacio derecha + FOTO izquierda
$pdf->Cell($usW/2, 4, '', 0, 1, 'C');

$pdf->SetFont('Arial','B',9);
$pdf->Cell($usW * 0.7, 4, u('U.E.N. ELBA HERNÁNDEZ DE YÁNEZ'), 0, 0, 'C');
$pdf->Cell($usW * 0.3, 4, '', 0, 1, 'C');

$pdf->SetFont('Arial','',7);
$pdf->Cell($usW * 0.7, 4, u('La Vega, Carretera Negra de Los Mangos — Distrito Capital'), 0, 0, 'C');
$pdf->Cell($usW * 0.3, 4, '', 0, 1, 'C');

// Título BOLETÍN
$pdf->Ln(1);
$pdf->SetFont('Arial','B',10);
$pdf->SetFillColor(0,31,63);
$pdf->SetTextColor(255,255,255);
$pdf->Cell($usW * 0.55, 6, u('BOLETÍN DE EVALUACIÓN'), 1, 0, 'C', true);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(0,0,0);
// Caja FOTO arriba derecha
$fotoX = 8 + $usW * 0.73;
$fotoY = 8;
$pdf->SetFont('Arial','',6);
$pdf->Cell($usW * 0.18, 6, '', 0, 0);
$pdf->Cell($usW * 0.27, 6, 'FOTO', 1, 1, 'C');

// ════════════════════════════════════════════════════════════
// DATOS DEL ESTUDIANTE
// ════════════════════════════════════════════════════════════
$pdf->Ln(1);
$pdf->SetFillColor(220,220,220);
$pdf->SetFont('Arial','B',7);
$pdf->Cell($usW, 4.5, u('DATOS DEL ESTUDIANTE'), 1, 1, 'C', true);

$pdf->SetFont('Arial','',7);
$w1 = $usW * 0.45;
$w2 = $usW * 0.20;
$w3 = $usW * 0.17;
$w4 = $usW * 0.18;

$fecha_nac_fmt = '';
if (!empty($alumno['fecha_nac'])) {
    $d = DateTime::createFromFormat('Y-m-d', $alumno['fecha_nac']);
    $fecha_nac_fmt = $d ? $d->format('d/m/Y') : $alumno['fecha_nac'];
}

// Fila 1: Nombre
$pdf->SetFont('Arial','B',7);
$pdf->Cell(28, 5, u('Apellidos y Nombres:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell($usW - 28, 5, u(strtoupper($alumno['nombre_alumno'])), 'B', 1, 'L');

// Fila 2: CI / Fecha nac / Edad / Sexo
$pdf->SetFont('Arial','B',7);
$pdf->Cell(20, 5, u('Cédula:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell(30, 5, u('V-' . $alumno['cedula']), 'B', 0, 'L');
$pdf->SetFont('Arial','B',7);
$pdf->Cell(26, 5, u('Fecha Nac.:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell(30, 5, $fecha_nac_fmt, 'B', 0, 'L');
$pdf->SetFont('Arial','B',7);
$pdf->Cell(14, 5, u('Edad:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell(20, 5, $alumno['edad'] ?? '', 'B', 0, 'C');
$pdf->SetFont('Arial','B',7);
$pdf->Cell(14, 5, u('LOC:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell($usW - 154, 5, 'Libertador', 'B', 1, 'L');

// Fila 3: Institución / Grado / Sección / Año
$pdf->SetFont('Arial','B',7);
$pdf->Cell(22, 5, u('Institución:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell(60, 5, u('U.E.N. ELBA HERNÁNDEZ DE YÁNEZ'), 'B', 0, 'L');
$pdf->SetFont('Arial','B',7);
$pdf->Cell(12, 5, u('Grado:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell(22, 5, u($alumno['nombre_grado']), 'B', 0, 'C');
$pdf->SetFont('Arial','B',7);
$pdf->Cell(16, 5, u('Sección:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell(16, 5, u($alumno['nombre_seccion']), 'B', 0, 'C');
$pdf->SetFont('Arial','B',7);
$pdf->Cell(16, 5, u('Año Esc.:'), 0, 0, 'L');
$pdf->SetFont('Arial','',7);
$pdf->Cell($usW - 164, 5, u($alumno['ano_escolar'] ?? '2024-2025'), 'B', 1, 'C');

$pdf->Ln(2);

// ════════════════════════════════════════════════════════════
// TABLA DE NOTAS
// ════════════════════════════════════════════════════════════

// Calcular anchos de columnas
$wMateria = 46;
$nLapsos  = count($periodos); // 1, 2 o 3
$wLapso   = 13;
$wFinal   = 13;
$wSit     = 10; // Situación (A/R)
$wObs     = $usW - $wMateria - ($nLapsos * $wLapso) - $wFinal - $wSit;
if ($wObs < 10) $wObs = 10;

$rowH  = 5.5;
$headH = 5;

// ── Encabezado de tabla ───────────────────────────────────────────────────────
$pdf->SetFillColor(0,31,63);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',6.5);
$pdf->Cell($wMateria, $headH, u('ÁREAS DE FORMACIÓN'), 1, 0, 'C', true);

foreach ($periodos as $i => $p) {
    $label = 'LAPSO ' . ($i+1);
    $pdf->Cell($wLapso, $headH, $label, 1, 0, 'C', true);
}

$pdf->Cell($wFinal, $headH, 'FINAL', 1, 0, 'C', true);
$pdf->Cell($wSit,   $headH, 'SIT',   1, 0, 'C', true);
$pdf->Cell($wObs,   $headH, 'OBS',   1, 1, 'C', true);

$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',6.5);

// ── Filas de materias ─────────────────────────────────────────────────────────
$fill = false;
$fillLight  = [245, 247, 250];
$fillWhite  = [255, 255, 255];

foreach ($materias_bd as $mat) {
    $mid = $mat['materia_id'];

    if ($fill) {
        $pdf->SetFillColor($fillLight[0], $fillLight[1], $fillLight[2]);
    } else {
        $pdf->SetFillColor($fillWhite[0], $fillWhite[1], $fillWhite[2]);
    }

    $pdf->Cell($wMateria, $rowH, u(strtoupper($mat['nombre_materia'])), 1, 0, 'L', $fill);

    foreach ($periodos as $p) {
        $nota = $notas_map[$p['periodo_id']][$mid] ?? '';
        $nota_txt = $nota !== '' ? (string)$nota : '';

        // Color semáforo en la celda
        if ($nota_txt !== '') {
            $n = (int)$nota_txt;
            if ($n >= 15)       $pdf->SetFillColor(212, 237, 218); // verde
            elseif ($n >= 10)   $pdf->SetFillColor(255, 243, 205); // amarillo
            else                $pdf->SetFillColor(248, 215, 218); // rojo
            $pdf->Cell($wLapso, $rowH, $nota_txt, 1, 0, 'C', true);
            // Restaurar fill
            if ($fill) $pdf->SetFillColor($fillLight[0],$fillLight[1],$fillLight[2]);
            else        $pdf->SetFillColor($fillWhite[0],$fillWhite[1],$fillWhite[2]);
        } else {
            $pdf->Cell($wLapso, $rowH, '', 1, 0, 'C', $fill);
        }
    }

    // Nota final
    $nf = $notas_final[$mid] ?? null;
    if ($nf !== null) {
        if ($nf >= 15)     $pdf->SetFillColor(180, 230, 190);
        elseif ($nf >= 10) $pdf->SetFillColor(255, 235, 170);
        else               $pdf->SetFillColor(240, 180, 180);
        $pdf->SetFont('Arial','B',6.5);
        $pdf->Cell($wFinal, $rowH, (string)$nf, 1, 0, 'C', true);
        $pdf->SetFont('Arial','',6.5);
        if ($fill) $pdf->SetFillColor($fillLight[0],$fillLight[1],$fillLight[2]);
        else        $pdf->SetFillColor($fillWhite[0],$fillWhite[1],$fillWhite[2]);
    } else {
        $pdf->Cell($wFinal, $rowH, '', 1, 0, 'C', $fill);
    }

    // Situación
    $sit = '';
    if ($nf !== null) $sit = $nf >= 10 ? 'A' : 'R';
    $pdf->SetFont('Arial','B',6.5);
    if ($sit === 'R') $pdf->SetTextColor(180,0,0);
    $pdf->Cell($wSit, $rowH, $sit, 1, 0, 'C', $fill);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial','',6.5);

    // Observaciones
    $pdf->Cell($wObs, $rowH, '', 1, 1, 'C', $fill);

    $fill = !$fill;
}

// ── Fila MEDIAS GLOBALES ──────────────────────────────────────────────────────
$pdf->SetFillColor(0,31,63);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',6.5);
$pdf->Cell($wMateria, $rowH, u('MEDIAS GLOBALES'), 1, 0, 'L', true);

foreach ($periodos as $p) {
    $vals_lap = [];
    foreach ($materias_bd as $mat) {
        $v = $notas_map[$p['periodo_id']][$mat['materia_id']] ?? null;
        if ($v !== null) $vals_lap[] = $v;
    }
    $media_lap = count($vals_lap) ? round(array_sum($vals_lap)/count($vals_lap),1) : '';
    $pdf->Cell($wLapso, $rowH, $media_lap !== '' ? (string)$media_lap : '', 1, 0, 'C', true);
}

$pdf->Cell($wFinal, $rowH, $prom_general ? (string)$prom_general : '', 1, 0, 'C', true);
$pdf->Cell($wSit + $wObs, $rowH, '', 1, 1, 'C', true);
$pdf->SetTextColor(0,0,0);

$pdf->Ln(2);

// ════════════════════════════════════════════════════════════
// SITUACIÓN GENERAL
// ════════════════════════════════════════════════════════════
$reprobadas = count(array_filter($notas_final, fn($v) => $v !== null && $v < 10));
$aprobado   = $reprobadas === 0 ? 'APROBADO/A' : ($reprobadas <= 2 ? 'REPARACIÓN' : 'REPROBADO/A');

$pdf->SetFont('Arial','B',7);
$pdf->SetFillColor(220,220,220);
$pdf->Cell($usW, 4.5, u('SITUACIÓN GENERAL DEL ESTUDIANTE'), 1, 1, 'C', true);
$pdf->SetFont('Arial','',7);

$wSG = $usW / 3;
$pdf->Cell($wSG, 5, u('Promedio General: ') . $prom_general, 1, 0, 'C');
$pdf->Cell($wSG, 5, u('Materias Reprobadas: ') . $reprobadas, 1, 0, 'C');

$pdf->SetFont('Arial','B',7);
if ($reprobadas === 0)    $pdf->SetTextColor(0,128,0);
elseif ($reprobadas <= 2) $pdf->SetTextColor(180,120,0);
else                      $pdf->SetTextColor(180,0,0);
$pdf->Cell($wSG, 5, u($aprobado), 1, 1, 'C');
$pdf->SetTextColor(0,0,0);

$pdf->Ln(2);

// ════════════════════════════════════════════════════════════
// OBSERVACIONES
// ════════════════════════════════════════════════════════════
$pdf->SetFont('Arial','B',7);
$pdf->SetFillColor(220,220,220);
$pdf->Cell($usW, 4.5, 'OBSERVACIONES', 1, 1, 'C', true);
$pdf->SetFont('Arial','',7);
$pdf->Cell($usW, 12, '', 1, 1, 'L');

$pdf->Ln(3);

// ════════════════════════════════════════════════════════════
// SECCIÓN DE FIRMAS
// ════════════════════════════════════════════════════════════
$wF3 = $usW / 3;

$pdf->SetFont('Arial','B',7);
$pdf->Cell($wF3, 4, u('DIRECTOR/A DEL PLANTEL'), 0, 0, 'C');
$pdf->Cell($wF3, 4, u('PROFESOR/A GUÍA'), 0, 0, 'C');
$pdf->Cell($wF3, 4, u('FIRMA DEL REPRESENTANTE'), 0, 1, 'C');

$pdf->SetFont('Arial','',7);
$pdf->Ln(8);

// Líneas de firma
$pdf->Cell($wF3, 0, '', 0, 0, 'C');
$xStart1 = 8 + $wF3 * 0.1;
$xEnd1   = 8 + $wF3 * 0.9;
$y = $pdf->GetY();
$pdf->Line($xStart1, $y, $xEnd1, $y);
$pdf->Line($xStart1 + $wF3, $y, $xEnd1 + $wF3, $y);
$pdf->Line($xStart1 + $wF3*2, $y, $xEnd1 + $wF3*2, $y);
$pdf->Ln(1);

$pdf->Cell($wF3, 4, '___________________________', 0, 0, 'C');
$pdf->Cell($wF3, 4, '___________________________', 0, 0, 'C');
$pdf->Cell($wF3, 4, '___________________________', 0, 1, 'C');

$pdf->SetFont('Arial','',6.5);
$pdf->Cell($wF3, 4, 'Nombre y Apellido / C.I.', 0, 0, 'C');
$pdf->Cell($wF3, 4, 'Nombre y Apellido / C.I.', 0, 0, 'C');
$pdf->Cell($wF3, 4, 'Nombre y Apellido / C.I.', 0, 1, 'C');

$pdf->Ln(2);

// ════════════════════════════════════════════════════════════
// PIE DE PÁGINA
// ════════════════════════════════════════════════════════════
$pdf->SetFont('Arial','I',6);
$pdf->SetTextColor(120,120,120);
$pdf->Cell($usW, 4, u('Generado por el Sistema de Gestión Académica — U.E.N. Elba Hernández de Yánez — ') . date('d/m/Y'), 0, 1, 'C');
$pdf->SetTextColor(0,0,0);

// ── Salida ────────────────────────────────────────────────────────────────────
$nombre_archivo = 'Boleta_' . preg_replace('/[^A-Za-z0-9_]/', '_', $alumno['nombre_alumno']) . '_' . date('Y') . '.pdf';
$pdf->Output('D', $nombre_archivo);
exit();