<?php
require_once '../includes/security.php';
verificar_rol([4]);
require_once '../includes/conexion.php';

$nombre  = $_SESSION['usuario']['nombre'] ?? 'Estudiante';
$usuario = $_SESSION['usuario']['usuario'] ?? '';
$user_id = $_SESSION['usuario']['id'] ?? 0;

// Obtener alumno_id
$stmtA = $pdo->prepare("SELECT alumno_id, grado_id, seccion_id, ano_escolar FROM alumnos WHERE usuario_id = :uid");
$stmtA->execute(['uid' => $user_id]);
$alumno = $stmtA->fetch(PDO::FETCH_ASSOC);
$alumno_id = $alumno['alumno_id'] ?? 0;

// Grado y sección
$grado_seccion = '';
if ($alumno) {
    $stmtGS = $pdo->prepare("SELECT g.nombre_grado, s.nombre_seccion FROM grados g INNER JOIN secciones s ON s.grado_id = g.grado_id WHERE g.grado_id = :gid AND s.seccion_id = :sid");
    $stmtGS->execute(['gid' => $alumno['grado_id'], 'sid' => $alumno['seccion_id']]);
    $gs = $stmtGS->fetch(PDO::FETCH_ASSOC);
    if ($gs) $grado_seccion = $gs['nombre_grado'] . ' — Sección ' . $gs['nombre_seccion'];
}

// Promedio general
$promedio_general = 0;
if ($alumno_id) {
    $stmtProm = $pdo->prepare("SELECT ROUND(AVG(valor_nota), 1) FROM notas WHERE alumno_id = :id");
    $stmtProm->execute(['id' => $alumno_id]);
    $promedio_general = $stmtProm->fetchColumn() ?: 0;
}

// Total notas
$total_notas = 0;
if ($alumno_id) {
    $stmtTN = $pdo->prepare("SELECT COUNT(*) FROM notas WHERE alumno_id = :id");
    $stmtTN->execute(['id' => $alumno_id]);
    $total_notas = $stmtTN->fetchColumn();
}

// Materias con promedio
$materias_prom = [];
if ($alumno_id) {
    $stmtMP = $pdo->prepare("
        SELECT m.nombre_materia, ROUND(AVG(n.valor_nota),1) as promedio, COUNT(n.nota_id) as total
        FROM notas n
        INNER JOIN materias m ON n.materia_id = m.materia_id
        WHERE n.alumno_id = :id
        GROUP BY m.materia_id
        ORDER BY promedio DESC
    ");
    $stmtMP->execute(['id' => $alumno_id]);
    $materias_prom = $stmtMP->fetchAll(PDO::FETCH_ASSOC);
}

// Últimas notas
$ultimas_notas = [];
if ($alumno_id) {
    $stmtUN = $pdo->prepare("
        SELECT n.valor_nota, n.fecha, m.nombre_materia, p.nombre_periodo, a.nombre_actividad
        FROM notas n
        INNER JOIN materias    m ON n.materia_id   = m.materia_id
        INNER JOIN periodos    p ON n.periodo_id   = p.periodo_id
        INNER JOIN actividad a ON n.actividad_id = a.actividad_id
        WHERE n.alumno_id = :id
        ORDER BY n.fecha DESC, n.nota_id DESC
        LIMIT 6
    ");
    $stmtUN->execute(['id' => $alumno_id]);
    $ultimas_notas = $stmtUN->fetchAll(PDO::FETCH_ASSOC);
}

// Notas aprobadas vs reprobadas
$aprobadas  = 0;
$reprobadas = 0;
foreach ($ultimas_notas as $n) {
    $n['valor_nota'] >= 10 ? $aprobadas++ : $reprobadas++;
}

$hora   = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 18 ? 'Buenas tardes' : 'Buenas noches');

// Color semáforo del promedio
$prom_color = $promedio_general >= 15 ? '#00a878' : ($promedio_general >= 10 ? '#d97706' : '#dc2626');
$prom_bg    = $promedio_general >= 15 ? '#e8fdf5' : ($promedio_general >= 10 ? '#fffbea' : '#fef2f2');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Estudiante — Liceo Elba Hernández</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy:       #001f3f;
            --navy-dark:  #001428;
            --accent:     #00aaff;
            --accent2:    #00d4aa;
            --gold:       #f5c518;
            --bg:         #f0f4f8;
            --card:       #ffffff;
            --text:       #1a2332;
            --muted:      #6b7a90;
            --border:     #e2e8f0;
            --sidebar-w:  260px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar { width: var(--sidebar-w); min-height: 100vh; background: var(--navy-dark); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100; }
        .sidebar-brand { padding: 20px 20px 16px; border-bottom: 1px solid rgba(255,255,255,.08); display: flex; align-items: center; gap: 12px; }
        .brand-logo { width: 46px; height: 46px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: white; display: flex; align-items: center; justify-content: center; }
        .brand-logo img { width: 100%; height: 100%; object-fit: contain; }
        .brand-text { line-height: 1.25; }
        .brand-text strong { color: white; font-size: 12.5px; font-weight: 700; display: block; }
        .brand-text span { color: rgba(255,255,255,.4); font-size: 10.5px; }
        .sidebar-nav { flex: 1; padding: 14px 12px; overflow-y: auto; }
        .nav-section-label { font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(255,255,255,.3); padding: 14px 10px 6px; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: rgba(255,255,255,.65); text-decoration: none; font-size: 13.5px; font-weight: 500; margin-bottom: 2px; transition: all .2s; }
        .nav-item:hover { background: rgba(255,255,255,.07); color: white; }
        .nav-item.active { background: linear-gradient(135deg, #b07d00, #d4a000); color: white; box-shadow: 0 4px 15px rgba(212,160,0,.3); }
        .nav-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: rgba(255,255,255,.07); flex-shrink: 0; }
        .nav-item.active .nav-icon { background: rgba(255,255,255,.2); }
        .sidebar-footer { padding: 14px 12px; border-top: 1px solid rgba(255,255,255,.08); }
        .user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,.05); border-radius: 10px; }
        .user-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #b07d00, #d4a000); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; flex-shrink: 0; }
        .user-info strong { color: white; font-size: 12.5px; display: block; }
        .user-info span { color: rgba(255,255,255,.4); font-size: 11px; }
        .logout-btn { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; color: rgba(255,100,100,.8); text-decoration: none; font-size: 12.5px; font-weight: 500; margin-top: 6px; transition: all .2s; }
        .logout-btn:hover { background: rgba(255,100,100,.1); color: #ff6b6b; }

        /* ── MAIN ── */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar { background: white; border-bottom: 1px solid var(--border); padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
        .topbar-left h1 { font-size: 17px; font-weight: 700; color: var(--text); }
        .topbar-left p  { font-size: 12px; color: var(--muted); margin-top: 1px; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .topbar-badge { background: var(--bg); border: 1px solid var(--border); border-radius: 20px; padding: 6px 14px; font-size: 12px; color: var(--muted); font-weight: 500; }
        .topbar-badge i { color: var(--gold); margin-right: 5px; }
        .content { padding: 28px 32px; flex: 1; }

        /* ── WELCOME BANNER ── */
        .welcome-banner { background: linear-gradient(135deg, #3d2b00 0%, #5c4000 50%, #7a5500 100%); border-radius: 16px; padding: 28px 32px; color: white; position: relative; overflow: hidden; margin-bottom: 28px; }
        .welcome-banner::before { content: ''; position: absolute; top: -40px; right: -40px; width: 200px; height: 200px; border-radius: 50%; background: rgba(245,197,24,.1); }
        .welcome-banner::after  { content: ''; position: absolute; bottom: -60px; right: 120px; width: 150px; height: 150px; border-radius: 50%; background: rgba(0,170,255,.06); }
        .welcome-banner h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .welcome-banner p  { color: rgba(255,255,255,.65); font-size: 13.5px; }
        .welcome-banner .date-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); border-radius: 20px; padding: 5px 14px; font-size: 12px; color: rgba(255,255,255,.85); margin-top: 16px; }

        /* ── STAT CARDS ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--card); border-radius: 14px; padding: 20px 22px; border: 1px solid var(--border); display: flex; align-items: center; gap: 16px; transition: transform .2s, box-shadow .2s; text-decoration: none; color: inherit; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.08); color: inherit; }
        .stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-icon.blue   { background: #e8f4ff; color: #0080cc; }
        .stat-icon.green  { background: #e8fdf5; color: #00a878; }
        .stat-icon.gold   { background: #fffbea; color: #d97706; }
        .stat-icon.purple { background: #f0eeff; color: #7c3aed; }
        .stat-body { flex: 1; }
        .stat-value { font-size: 28px; font-weight: 800; line-height: 1; color: var(--text); }
        .stat-label { font-size: 12px; color: var(--muted); margin-top: 3px; font-weight: 500; }

        /* ── QUICK ACTIONS ── */
        .quick-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 28px; }
        .quick-btn { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 12px; text-align: center; text-decoration: none; color: var(--text); font-size: 12px; font-weight: 600; transition: all .2s; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .quick-btn:hover { border-color: var(--gold); color: #d97706; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(212,160,0,.12); }
        .quick-btn i { font-size: 22px; }

        /* ── BOTTOM GRID ── */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; }
        .panel-card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .panel-card-header h6 { font-size: 13px; font-weight: 700; color: var(--text); margin: 0; }
        .panel-card-header a { font-size: 11.5px; color: #d97706; text-decoration: none; font-weight: 600; }
        .panel-card-body { padding: 16px 20px; }

        /* Tabla notas */
        .mini-table { width: 100%; }
        .mini-table tr td { padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
        .mini-table tr:last-child td { border-bottom: none; }
        .nota-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .nota-alta   { background: #e8fdf5; color: #00a878; }
        .nota-media  { background: #fffbea; color: #d97706; }
        .nota-baja   { background: #fef2f2; color: #dc2626; }

        /* Barras materias */
        .materia-bar { margin-bottom: 14px; }
        .materia-bar:last-child { margin-bottom: 0; }
        .materia-bar-label { display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; margin-bottom: 5px; }
        .materia-bar-track { height: 8px; background: var(--bg); border-radius: 4px; overflow: hidden; }
        .materia-bar-fill  { height: 100%; border-radius: 4px; transition: width 1s cubic-bezier(.4,0,.2,1); }

        /* ── ANIMS ── */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
        .fade-up { animation: fadeUp .4s ease both; }
        .delay-1 { animation-delay: .05s; } .delay-2 { animation-delay: .10s; }
        .delay-3 { animation-delay: .15s; } .delay-4 { animation-delay: .20s; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .bottom-grid { grid-template-columns: 1fr; }
            .content { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <img src="../images/liceo_logo.png" alt="U.E.N.">
        </div>
        <div class="brand-text">
            <strong>Liceo Elba Hernández</strong>
            <span>Portal Estudiante</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="alumno.php" class="nav-item active">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div>
            Mi Panel
        </a>

        <div class="nav-section-label">Académico</div>
        <a href="ver_notas_alumno.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-book-open"></i></div>
            Mis Calificaciones
        </a>
        <a href="boleta_pdf.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-file-pdf"></i></div>
            Descargar Boleta
        </a>

        <div class="nav-section-label">Cuenta</div>
        <a href="mi_perfil.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
            Mi Perfil
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($nombre, 0, 1)); ?></div>
            <div class="user-info">
                <strong><?php echo htmlspecialchars($nombre); ?></strong>
                <span>Estudiante</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<main class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Mi Panel Estudiantil</h1>
            <p><?php echo $grado_seccion ?: 'Complejo Educativo Elba Hernández de Yánez'; ?></p>
        </div>
        <div class="topbar-right">
            <span class="topbar-badge">
                <i class="fas fa-circle" style="font-size:7px;"></i>
                <?php echo date('d/m/Y'); ?>
            </span>
            <span class="topbar-badge">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($usuario); ?>
            </span>
        </div>
    </div>

    <div class="content">

        <!-- Welcome Banner -->
        <div class="welcome-banner fade-up">
            <h2><?php echo $saludo; ?>, <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?> 🎓</h2>
            <p>
                <?php if ($alumno): ?>
                    Año Escolar: <strong><?php echo htmlspecialchars($alumno['ano_escolar'] ?? 'N/A'); ?></strong>
                    &nbsp;·&nbsp; <?php echo $grado_seccion; ?>
                <?php else: ?>
                    Bienvenido a tu portal estudiantil.
                <?php endif; ?>
            </p>
            <div class="date-chip">
                <i class="fas fa-calendar-check"></i>
                <?php
                    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                    $meses = ['enero','febrero','marzo','abril','mayo','junio',
                              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    echo $dias[date('w')] . ', ' . date('d') . ' de ' . $meses[(int)date('m')-1] . ' de ' . date('Y');
                ?>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card fade-up delay-1">
                <div class="stat-icon gold"><i class="fas fa-star"></i></div>
                <div class="stat-body">
                    <div class="stat-value" style="color:<?php echo $prom_color; ?>;"><?php echo $promedio_general ?: '—'; ?></div>
                    <div class="stat-label">Promedio General</div>
                </div>
            </div>
            <div class="stat-card fade-up delay-2">
                <div class="stat-icon blue"><i class="fas fa-pen-nib"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_notas; ?></div>
                    <div class="stat-label">Notas Registradas</div>
                </div>
            </div>
            <div class="stat-card fade-up delay-3">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo count($materias_prom); ?></div>
                    <div class="stat-label">Materias Evaluadas</div>
                </div>
            </div>
            <a href="boleta_pdf.php" class="stat-card fade-up delay-4">
                <div class="stat-icon purple"><i class="fas fa-file-pdf"></i></div>
                <div class="stat-body">
                    <div class="stat-value" style="font-size:16px; padding-top:4px;">PDF</div>
                    <div class="stat-label">Descargar Boleta</div>
                </div>
            </a>
        </div>

        <!-- Acciones rápidas -->
        <div class="quick-actions fade-up">
            <a href="ver_notas_alumno.php" class="quick-btn">
                <i class="fas fa-book-open" style="color:#0080cc;"></i>
                Ver Todas Mis Calificaciones
            </a>
            <a href="boleta_pdf.php" class="quick-btn">
                <i class="fas fa-download" style="color:#7c3aed;"></i>
                Descargar Boleta PDF
            </a>
        </div>

        <!-- Bottom Grid -->
        <div class="bottom-grid fade-up">

            <!-- Promedio por materia -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h6><i class="fas fa-chart-bar" style="color:#d97706; margin-right:6px;"></i> Rendimiento por Materia</h6>
                    <a href="ver_notas_alumno.php">Ver detalle →</a>
                </div>
                <div class="panel-card-body">
                    <?php if (empty($materias_prom)): ?>
                        <p style="color:var(--muted); font-size:13px; text-align:center; padding:20px 0;">
                            Aún no tienes calificaciones registradas.
                        </p>
                    <?php else: foreach ($materias_prom as $mp):
                        $pct  = round(($mp['promedio'] / 20) * 100);
                        $col  = $mp['promedio'] >= 15 ? 'linear-gradient(90deg,#00a878,#00d4aa)' :
                               ($mp['promedio'] >= 10 ? 'linear-gradient(90deg,#f59e0b,#fbbf24)' :
                                                        'linear-gradient(90deg,#ef4444,#f87171)');
                    ?>
                    <div class="materia-bar">
                        <div class="materia-bar-label">
                            <span><?php echo htmlspecialchars($mp['nombre_materia']); ?></span>
                            <span style="color:var(--muted);"><?php echo $mp['promedio']; ?>/20</span>
                        </div>
                        <div class="materia-bar-track">
                            <div class="materia-bar-fill" style="width:<?php echo $pct; ?>%; background:<?php echo $col; ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Últimas calificaciones -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h6><i class="fas fa-clock" style="color:var(--accent); margin-right:6px;"></i> Últimas Calificaciones</h6>
                    <a href="ver_notas_alumno.php">Ver todas →</a>
                </div>
                <div class="panel-card-body">
                    <?php if (empty($ultimas_notas)): ?>
                        <p style="color:var(--muted); font-size:13px; text-align:center; padding:20px 0;">
                            No hay calificaciones disponibles aún.
                        </p>
                    <?php else: ?>
                    <table class="mini-table">
                        <?php foreach ($ultimas_notas as $n):
                            $cls = $n['valor_nota'] >= 15 ? 'nota-alta' : ($n['valor_nota'] >= 10 ? 'nota-media' : 'nota-baja');
                        ?>
                        <tr>
                            <td>
                                <strong style="font-size:13px;"><?php echo htmlspecialchars($n['nombre_materia']); ?></strong><br>
                                <span style="font-size:11px; color:var(--muted);"><?php echo htmlspecialchars($n['nombre_actividad']); ?></span>
                            </td>
                            <td style="text-align:right;">
                                <span class="nota-pill <?php echo $cls; ?>"><?php echo $n['valor_nota']; ?>/20</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /bottom-grid -->
    </div><!-- /content -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Animar barras
    document.querySelectorAll('.materia-bar-fill').forEach(bar => {
        const w = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => { bar.style.width = w; }, 300);
    });
    // Contador animado
    document.querySelectorAll('.stat-value').forEach(el => {
        const target = parseInt(el.textContent);
        if (isNaN(target)) return;
        el.textContent = '0';
        let start = 0;
        const step = Math.ceil(target / 30);
        const timer = setInterval(() => {
            start = Math.min(start + step, target);
            el.textContent = start;
            if (start >= target) clearInterval(timer);
        }, 30);
    });
});
</script>
</body>
</html>