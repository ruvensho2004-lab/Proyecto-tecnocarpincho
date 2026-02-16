<?php
require_once '../includes/security.php';
require_once '../includes/conexion.php';
verificar_rol([1]);

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';

// ── Mapeo de grados a grado_id ────────────────────────────────────────────────
function normalizar_grado($texto) {
    $texto = strtolower(trim($texto));
    $map = [
        '1ro' => 1, '1°' => 1, '1' => 1, 'primero' => 1,  '1er' => 1,
        '2do' => 2, '2°' => 2, '2' => 2, 'segundo' => 2,  '2ndo'=> 2,
        '3ro' => 3, '3°' => 3, '3' => 3, 'tercero' => 3,  '3ero'=> 3,
        '4to' => 4, '4°' => 4, '4' => 4, 'cuarto'  => 4,  '4to' => 4,
        '5to' => 5, '5°' => 5, '5' => 5, 'quinto'  => 5,  '5to' => 5,
    ];
    return $map[$texto] ?? null;
}

// ── Materias: columna → nombre en BD ─────────────────────────────────────────
// Columnas del CSV (índice 0): 0=N°,1=Cédula,2=Apellidos,3=Nombres,4=Grado,
// 5=Sección,6=AñoEscolar,7=FechaNac,8=Edad,9=Sexo,10=Dirección,11=Teléfono,
// 12=Correo, 13=Castellano,14=Inglés,...,24=OrientaciónConvivencia,
// 25=Usuario,26=Contraseña
$MATERIAS_COLUMNAS = [
    13 => 'Castellano',
    14 => 'Inglés y otras lenguas extranjeras',
    15 => 'Matemáticas',
    16 => 'Educación Física',
    17 => 'Arte y Patrimonio',
    18 => 'Ciencias Naturales',
    19 => 'Química',
    20 => 'Física',
    21 => 'Biología',
    22 => 'Geografía, Historia y Ciudadanía',
    23 => 'Formación para la Soberanía Nacional',
    24 => 'Orientación y Convivencia',
];

// ── Procesar importación ──────────────────────────────────────────────────────
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $file = $_FILES['archivo_csv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $resultado = ['error_global' => 'Error al subir el archivo. Intente de nuevo.'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            $resultado = ['error_global' => 'Solo se aceptan archivos .csv. Exporte su Excel como CSV desde Archivo → Guardar como → CSV UTF-8.'];
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            // Detectar BOM UTF-8
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle);

            // Detectar delimitador
            $first_line = fgets($handle);
            rewind($handle);
            if ($bom !== "\xEF\xBB\xBF") rewind($handle); else fread($handle, 3);
            $delim = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';

            // Obtener período activo o el primero
            $periodo = $pdo->query("SELECT periodo_id, nombre_periodo FROM periodos WHERE estado = 1 ORDER BY periodo_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$periodo) $periodo = $pdo->query("SELECT periodo_id, nombre_periodo FROM periodos ORDER BY periodo_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $periodo_id = $periodo['periodo_id'] ?? 1;

            // Obtener actividad "Final" o la primera disponible
            $actividad = $pdo->query("SELECT actividad_id FROM actividad WHERE LOWER(nombre_actividad) LIKE '%final%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$actividad) $actividad = $pdo->query("SELECT actividad_id FROM actividad ORDER BY actividad_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $actividad_id = $actividad['actividad_id'] ?? 1;

            // Cargar grados y secciones de BD
            $grados_bd   = $pdo->query("SELECT grado_id, nombre_grado FROM grados WHERE estado=1")->fetchAll(PDO::FETCH_KEY_PAIR);
            $secciones_bd = $pdo->query("SELECT seccion_id, CONCAT(grado_id,'-',UPPER(nombre_seccion)) as key_sec FROM secciones WHERE estado=1")->fetchAll(PDO::FETCH_KEY_PAIR);
            // flip para buscar por key → id
            $sec_map = array_flip($secciones_bd); // 'grado_id-SECCION' => seccion_id

            // Cargar materias de BD
            $materias_bd = $pdo->query("SELECT materia_id, LOWER(nombre_materia) FROM materias WHERE estado=1")->fetchAll(PDO::FETCH_KEY_PAIR);
            $materias_bd = array_flip($materias_bd); // lower_nombre => materia_id

            $registrados = 0;
            $omitidos    = 0;
            $errores     = [];
            $fila_num    = 0;

            // Saltar encabezados (filas 1-4 de la plantilla → primeras 4 líneas del CSV)
            $cabeceras_saltadas = 0;
            while ($cabeceras_saltadas < 3) {
                fgetcsv($handle, 0, $delim);
                $cabeceras_saltadas++;
            }

            $pdo->beginTransaction();
            try {
                while (($row = fgetcsv($handle, 0, $delim)) !== false) {
                    $fila_num++;

                    // Saltar filas vacías o con N° inválido
                    if (empty($row[0]) || !is_numeric(trim($row[0]))) continue;
                    if (count($row) < 14) {
                        $errores[] = "Fila {$fila_num}: incompleta, omitida.";
                        $omitidos++;
                        continue;
                    }

                    $cedula      = trim($row[1]);
                    $apellidos   = trim($row[2]);
                    $nombres     = trim($row[3]);
                    $grado_txt   = trim($row[4]);
                    $seccion_txt = strtoupper(trim($row[5]));
                    $ano_escolar = trim($row[6]) ?: '2024-2025';
                    $fecha_nac   = trim($row[7]);
                    $edad        = (int)trim($row[8]);
                    $sexo        = strtoupper(trim($row[9]));
                    $direccion   = trim($row[10]);
                    $telefono    = preg_replace('/[^0-9]/', '', trim($row[11]));
                    $correo      = trim($row[12]);
                    $usuario     = trim($row[25] ?? '');
                    $clave_plain = trim($row[26] ?? '');

                    // Validaciones básicas
                    if (empty($cedula) || empty($apellidos) || empty($nombres)) {
                        $errores[] = "Fila {$fila_num}: falta cédula, apellidos o nombres.";
                        $omitidos++; continue;
                    }

                    // Normalizar cédula
                    $cedula = strtoupper($cedula);
                    if (!preg_match('/^V-?\d+$/i', $cedula)) {
                        $errores[] = "Fila {$fila_num} ({$apellidos}): cédula inválida '{$cedula}'.";
                        $omitidos++; continue;
                    }
                    $cedula_num = preg_replace('/[^0-9]/', '', $cedula);

                    // Verificar cédula duplicada
                    $chkCed = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE cedula = ?");
                    $chkCed->execute([$cedula_num]);
                    if ($chkCed->rowCount() > 0) {
                        $errores[] = "Fila {$fila_num} ({$apellidos} {$nombres}): cédula {$cedula_num} ya existe.";
                        $omitidos++; continue;
                    }

                    // Buscar grado_id
                    $grado_num = normalizar_grado($grado_txt);
                    $grado_id  = null;
                    foreach ($grados_bd as $gid => $gnombre) {
                        if ($grado_num && strpos($gnombre, (string)$grado_num) !== false) {
                            $grado_id = $gid; break;
                        }
                        if (strtolower($grado_txt) === strtolower($gnombre)) {
                            $grado_id = $gid; break;
                        }
                    }
                    if (!$grado_id) {
                        $errores[] = "Fila {$fila_num} ({$apellidos}): grado '{$grado_txt}' no encontrado en el sistema.";
                        $omitidos++; continue;
                    }

                    // Buscar seccion_id
                    $sec_key   = $grado_id . '-' . $seccion_txt;
                    $seccion_id = $sec_map[$sec_key] ?? null;
                    if (!$seccion_id) {
                        $errores[] = "Fila {$fila_num} ({$apellidos}): sección '{$seccion_txt}' del grado '{$grado_txt}' no existe en el sistema.";
                        $omitidos++; continue;
                    }

                    // Generar usuario si está vacío
                    if (empty($usuario)) {
                        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $apellidos));
                        $usuario = substr($base, 0, 6) . $cedula_num;
                    }
                    $usuario = strtolower($usuario);

                    // Verificar usuario duplicado
                    $chkUsr = $pdo->prepare("SELECT usuario_id FROM usuarios WHERE usuario = ?");
                    $chkUsr->execute([$usuario]);
                    if ($chkUsr->rowCount() > 0) {
                        $usuario = $usuario . '_' . substr($cedula_num, -3);
                    }

                    // Generar contraseña si está vacía
                    if (empty($clave_plain) || strlen($clave_plain) < 6) {
                        $clave_plain = 'clave' . substr($cedula_num, -4);
                    }
                    $clave_hash = password_hash($clave_plain, PASSWORD_DEFAULT);

                    // Normalizar fecha nacimiento DD/MM/YYYY → YYYY-MM-DD
                    $fecha_bd = null;
                    if (!empty($fecha_nac)) {
                        foreach (['/','.','-'] as $sep) {
                            $parts = explode($sep, $fecha_nac);
                            if (count($parts) === 3) {
                                if (strlen($parts[2]) === 4) { // DD/MM/YYYY
                                    $fecha_bd = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                                } else {                       // YYYY-MM-DD
                                    $fecha_bd = $fecha_nac;
                                }
                                break;
                            }
                        }
                    }
                    $fecha_bd = $fecha_bd ?: '2000-01-01';

                    // Nombre completo
                    $nombre_completo = $apellidos . ' ' . $nombres;

                    // INSERT usuarios
                    $sqlU = "INSERT INTO usuarios (nombre, usuario, clave, rol, estado) VALUES (?,?,?,4,1)";
                    $pdo->prepare($sqlU)->execute([$nombre_completo, $usuario, $clave_hash]);
                    $usuario_id = $pdo->lastInsertId();

                    // INSERT alumnos
                    $sqlA = "INSERT INTO alumnos (nombre_alumno, edad, direccion, cedula, telefono, correo,
                             fecha_nac, fecha_registro, ano_escolar, grado_id, seccion_id, estado, usuario_id)
                             VALUES (?,?,?,?,?,?,?,NOW(),?,?,?,1,?)";
                    $pdo->prepare($sqlA)->execute([
                        $nombre_completo, $edad, $direccion, $cedula_num,
                        $telefono, $correo, $fecha_bd, $ano_escolar,
                        $grado_id, $seccion_id, $usuario_id
                    ]);
                    $alumno_id = $pdo->lastInsertId();

                    // INSERT notas (si las hay)
                    foreach ($MATERIAS_COLUMNAS as $col_idx => $nombre_materia) {
                        $nota_val = trim($row[$col_idx] ?? '');
                        if ($nota_val === '' || $nota_val === '**' || $nota_val === '--') continue;
                        $nota_num = (int)$nota_val;
                        if ($nota_num < 0 || $nota_num > 20) continue;

                        // Buscar materia_id
                        $materia_id = $materias_bd[strtolower($nombre_materia)] ?? null;
                        if (!$materia_id) {
                            // Búsqueda parcial
                            foreach ($materias_bd as $mn => $mid) {
                                if (strpos(strtolower($nombre_materia), substr($mn, 0, 8)) !== false) {
                                    $materia_id = $mid; break;
                                }
                            }
                        }
                        if (!$materia_id) continue;

                        $sqlN = "INSERT INTO notas (alumno_id, materia_id, actividad_id, periodo_id, valor_nota, fecha)
                                 VALUES (?,?,?,?,?,NOW())
                                 ON DUPLICATE KEY UPDATE valor_nota = VALUES(valor_nota)";
                        $pdo->prepare($sqlN)->execute([$alumno_id, $materia_id, $actividad_id, $periodo_id, $nota_num]);
                    }

                    registrar_log_seguridad('Alumno importado masivamente', "Cédula: {$cedula_num}, Usuario: {$usuario}");
                    $registrados++;
                }

                $pdo->commit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $resultado = ['error_global' => 'Error en la base de datos: ' . $e->getMessage()];
            }

            fclose($handle);

            if (!isset($resultado['error_global'])) {
                $resultado = [
                    'registrados' => $registrados,
                    'omitidos'    => $omitidos,
                    'errores'     => $errores,
                    'periodo'     => $periodo['nombre_periodo'] ?? 'N/A',
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importación Masiva — Liceo Elba Hernández</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#001f3f; --navy-dark:#001428; --accent:#00aaff; --bg:#f0f4f8; --border:#e2e8f0; --sidebar-w:260px; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); display:flex; min-height:100vh; }

        .sidebar { width:var(--sidebar-w); background:var(--navy-dark); display:flex; flex-direction:column; position:fixed; top:0;left:0; min-height:100vh; z-index:100; }
        .sidebar-brand { padding:20px; border-bottom:1px solid rgba(255,255,255,.08); display:flex; align-items:center; gap:12px; }
        .brand-logo { width:44px;height:44px;border-radius:10px;overflow:hidden;background:white;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .brand-logo img { width:100%;height:100%;object-fit:contain; }
        .brand-text strong { color:white;font-size:12.5px;font-weight:700;display:block; }
        .brand-text span { color:rgba(255,255,255,.4);font-size:10.5px; }
        .sidebar-nav { flex:1;padding:14px 12px; }
        .nav-section-label { font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.3);padding:14px 10px 6px; }
        .nav-item { display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:rgba(255,255,255,.65);text-decoration:none;font-size:13.5px;font-weight:500;margin-bottom:2px;transition:all .2s; }
        .nav-item:hover { background:rgba(255,255,255,.07);color:white; }
        .nav-item.active { background:linear-gradient(135deg,var(--accent),#0080cc);color:white; }
        .nav-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;background:rgba(255,255,255,.07);flex-shrink:0; }
        .nav-item.active .nav-icon { background:rgba(255,255,255,.2); }
        .sidebar-footer { padding:14px 12px;border-top:1px solid rgba(255,255,255,.08); }
        .user-card { display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.05);border-radius:10px; }
        .user-avatar { width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#0080cc);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;flex-shrink:0; }
        .user-info strong { color:white;font-size:12.5px;display:block; }
        .user-info span { color:rgba(255,255,255,.4);font-size:11px; }
        .logout-btn { display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,100,100,.8);text-decoration:none;font-size:12.5px;font-weight:500;margin-top:6px;transition:all .2s; }
        .logout-btn:hover { background:rgba(255,100,100,.1);color:#ff6b6b; }

        .main { margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column; }
        .topbar { background:white;border-bottom:1px solid var(--border);padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50; }
        .topbar-left h1 { font-size:17px;font-weight:700; }
        .topbar-left p { font-size:12px;color:#6b7a90;margin-top:1px; }
        .content { padding:28px 32px;flex:1; }

        .upload-card { background:white;border-radius:16px;border:2px dashed var(--border);padding:40px;text-align:center;transition:all .2s;cursor:pointer; }
        .upload-card:hover, .upload-card.drag-over { border-color:var(--accent);background:#f0f9ff; }
        .upload-icon { font-size:48px;color:var(--accent);margin-bottom:16px; }

        .step-card { background:white;border-radius:14px;border:1px solid var(--border);padding:20px;margin-bottom:16px; }
        .step-num { width:32px;height:32px;border-radius:50%;background:var(--navy);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0; }

        .result-success { background:#e8fdf5;border:1px solid #00a878;border-radius:12px;padding:20px; }
        .result-error   { background:#fef2f2;border:1px solid #dc2626;border-radius:12px;padding:20px; }
        .result-warning { background:#fffbea;border:1px solid #d97706;border-radius:12px;padding:20px; }

        .error-list { max-height:300px;overflow-y:auto;font-size:12px;background:#f8f8f8;border-radius:8px;padding:12px;margin-top:12px; }
        .error-list li { padding:4px 0;border-bottom:1px solid #eee; }
        .error-list li:last-child { border-bottom:none; }

        .big-num { font-size:48px;font-weight:800;line-height:1; }
        .progress-bar-import { height:8px;border-radius:4px;background:linear-gradient(90deg,var(--accent),#00d4aa); }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo"><img src="../images/liceo_logo.png" alt="U.E.N."></div>
        <div class="brand-text">
            <strong>Liceo Elba Hernández</strong>
            <span>Sistema Académico</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="admin.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div> Dashboard
        </a>
        <div class="nav-section-label">Gestión</div>
        <a href="gestionar_alumnos.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-user-graduate"></i></div> Alumnos
        </a>
        <a href="importar_alumnos.php" class="nav-item active">
            <div class="nav-icon"><i class="fas fa-file-import"></i></div> Importación Masiva
        </a>
        <a href="gestionar_profesores.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-chalkboard-teacher"></i></div> Profesores
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($nombre_admin,0,1)); ?></div>
            <div class="user-info">
                <strong><?php echo htmlspecialchars($nombre_admin); ?></strong>
                <span>Administrador</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Importación Masiva de Estudiantes</h1>
            <p>Carga hasta 1500+ alumnos desde un archivo Excel/CSV en segundos</p>
        </div>
        <div>
            <a href="admin.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="content">

        <?php if ($resultado && isset($resultado['error_global'])): ?>
        <div class="result-error mb-4">
            <h5><i class="fas fa-times-circle text-danger"></i> Error</h5>
            <p class="mb-0"><?php echo htmlspecialchars($resultado['error_global']); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($resultado && isset($resultado['registrados'])): ?>
        <!-- RESULTADO -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="result-success text-center">
                    <div class="big-num text-success"><?php echo $resultado['registrados']; ?></div>
                    <div class="fw-600 mt-1">Estudiantes registrados</div>
                    <?php if ($resultado['registrados'] > 0): ?>
                    <div class="progress mt-3" style="height:8px;">
                        <div class="progress-bar progress-bar-import" style="width:100%"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="result-warning text-center">
                    <div class="big-num" style="color:#d97706;"><?php echo $resultado['omitidos']; ?></div>
                    <div class="fw-600 mt-1">Filas omitidas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:#e8f4ff;border:1px solid #0080cc;border-radius:12px;padding:20px;text-align:center;">
                    <div class="big-num" style="color:#0080cc;"><?php echo $resultado['registrados'] + $resultado['omitidos']; ?></div>
                    <div class="fw-600 mt-1">Total filas procesadas</div>
                    <small class="text-muted">Período: <?php echo htmlspecialchars($resultado['periodo']); ?></small>
                </div>
            </div>
        </div>

        <?php if (!empty($resultado['errores'])): ?>
        <div class="result-warning mb-4">
            <h6><i class="fas fa-exclamation-triangle text-warning"></i> <?php echo count($resultado['errores']); ?> filas con problemas:</h6>
            <ul class="error-list">
                <?php foreach ($resultado['errores'] as $err): ?>
                <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($resultado['registrados'] > 0): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>¡Importación completada!</strong>
            <?php echo $resultado['registrados']; ?> estudiantes registrados con usuario y contraseña automáticos.
            <a href="gestionar_alumnos.php" class="alert-link ms-2">Ver todos los alumnos →</a>
        </div>
        <?php endif; ?>

        <hr class="my-4">
        <h5 class="mb-3">¿Desea importar otro archivo?</h5>
        <?php endif; ?>

        <!-- PASOS -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="step-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-num">1</div>
                        <strong>Descarga la plantilla</strong>
                    </div>
                    <p class="text-muted" style="font-size:13px;">Usa la plantilla Excel oficial con el formato correcto. Ya tiene 50 filas de ejemplo que puedes reemplazar.</p>
                    <a href="../downloads/Plantilla_Importacion_Alumnos.xlsx" class="btn btn-outline-primary btn-sm mt-2" download>
                        <i class="fas fa-download"></i> Descargar Plantilla .xlsx
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-num">2</div>
                        <strong>Llena los datos</strong>
                    </div>
                    <p class="text-muted" style="font-size:13px;">Completa los datos de tus estudiantes. Las notas son opcionales. Si no pones usuario/clave, se generan automáticamente.</p>
                    <div style="background:#f0f9ff;border-radius:8px;padding:10px;font-size:12px;">
                        <i class="fas fa-info-circle text-primary"></i>
                        Después guarda como <strong>CSV UTF-8</strong> desde Archivo → Guardar como
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="step-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="step-num">3</div>
                        <strong>Sube el CSV</strong>
                    </div>
                    <p class="text-muted" style="font-size:13px;">Sube el archivo CSV aquí abajo. El sistema registra cada estudiante, crea su usuario y carga sus notas automáticamente.</p>
                    <div style="background:#e8fdf5;border-radius:8px;padding:10px;font-size:12px;">
                        <i class="fas fa-shield-alt text-success"></i>
                        Cédulas y usuarios duplicados se omiten sin borrar datos existentes
                    </div>
                </div>
            </div>
        </div>

        <!-- FORMULARIO UPLOAD -->
        <div class="upload-card" id="dropzone" onclick="document.getElementById('csv_input').click()">
            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <h4 class="fw-bold">Arrastra el CSV aquí o haz clic para seleccionar</h4>
            <p class="text-muted mt-2" id="file_name">Formatos aceptados: .csv (exportado desde Excel)</p>
            <form method="POST" enctype="multipart/form-data" id="upload_form">
                <input type="file" id="csv_input" name="archivo_csv" accept=".csv,.txt" style="display:none">
                <button type="submit" class="btn btn-primary btn-lg mt-3 px-5" id="btn_upload" style="display:none;">
                    <i class="fas fa-upload"></i> Iniciar Importación
                </button>
            </form>
        </div>

        <!-- LOADING -->
        <div id="loading" style="display:none;text-align:center;padding:40px;">
            <div class="spinner-border text-primary" style="width:3rem;height:3rem;"></div>
            <p class="mt-3 fw-600">Procesando estudiantes... esto puede tardar unos segundos.</p>
        </div>

        <!-- INSTRUCCIONES CSV -->
        <div class="mt-4 p-4" style="background:white;border-radius:14px;border:1px solid var(--border);">
            <h6 class="fw-bold mb-3"><i class="fas fa-question-circle text-primary"></i> ¿Cómo exportar a CSV desde Excel?</h6>
            <div class="row g-3" style="font-size:13px;">
                <div class="col-md-6">
                    <strong>Microsoft Excel:</strong><br>
                    Archivo → Guardar como → <em>CSV UTF-8 (delimitado por comas)</em>
                </div>
                <div class="col-md-6">
                    <strong>LibreOffice / Google Sheets:</strong><br>
                    Archivo → Descargar → <em>Valores separados por comas (.csv)</em>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
const input   = document.getElementById('csv_input');
const btnUp   = document.getElementById('btn_upload');
const fname   = document.getElementById('file_name');
const form    = document.getElementById('upload_form');
const loading = document.getElementById('loading');
const dropzone= document.getElementById('dropzone');

input.addEventListener('change', () => {
    if (input.files.length > 0) {
        fname.textContent = '📄 ' + input.files[0].name + '  (' + (input.files[0].size/1024).toFixed(1) + ' KB)';
        fname.style.color = '#0080cc';
        btnUp.style.display = 'inline-block';
    }
});

form.addEventListener('submit', e => {
    if (!input.files.length) { e.preventDefault(); return; }
    dropzone.style.display = 'none';
    loading.style.display  = 'block';
});

// Drag & drop
dropzone.addEventListener('dragover',  e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    }
});
</script>
</body>
</html>
