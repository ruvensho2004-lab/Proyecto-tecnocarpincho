<?php
require_once '../includes/security.php';
verificar_rol([3]);
require_once '../includes/conexion.php';

$nombre  = $_SESSION['usuario']['nombre'] ?? 'Profesor';
$usuario = $_SESSION['usuario']['usuario'] ?? '';
$prof_id = $_SESSION['usuario']['id'] ?? 0;

// ── Estadísticas del profesor ────────────────────────────────────────────────
// Materias asignadas
$total_materias = $pdo->prepare("SELECT COUNT(DISTINCT materia_id) FROM profesor_materia_seccion WHERE profesor_id = :id AND estado = 1");
$total_materias->execute(['id' => $prof_id]);
$total_materias = $total_materias->fetchColumn();

// Secciones asignadas
$total_secciones = $pdo->prepare("SELECT COUNT(DISTINCT seccion_id) FROM profesor_materia_seccion WHERE profesor_id = :id AND estado = 1");
$total_secciones->execute(['id' => $prof_id]);
$total_secciones = $total_secciones->fetchColumn();

// Total alumnos en sus secciones
$total_alumnos = $pdo->prepare("
    SELECT COUNT(DISTINCT a.alumno_id)
    FROM alumnos a
    INNER JOIN profesor_materia_seccion pms ON a.seccion_id = pms.seccion_id
    WHERE pms.profesor_id = :id AND pms.estado = 1
");
$total_alumnos->execute(['id' => $prof_id]);
$total_alumnos = $total_alumnos->fetchColumn();

// Total notas registradas por este profesor (via sus alumnos y materias asignadas)
$total_notas = $pdo->prepare("
    SELECT COUNT(DISTINCT n.nota_id)
    FROM notas n
    INNER JOIN profesor_materia_seccion pms ON n.materia_id = pms.materia_id
    INNER JOIN alumnos a ON n.alumno_id = a.alumno_id AND a.seccion_id = pms.seccion_id
    WHERE pms.profesor_id = :id AND pms.estado = 1
");
$total_notas->execute(['id' => $prof_id]);
$total_notas = $total_notas->fetchColumn() ?: 0;

// Mis materias con secciones y grados
$mis_materias = $pdo->prepare("
    SELECT DISTINCT m.nombre_materia, g.grado_id, g.nombre_grado, s.nombre_seccion,
           COUNT(DISTINCT a.alumno_id) as total_alumnos
    FROM profesor_materia_seccion pms
    INNER JOIN materias m  ON pms.materia_id  = m.materia_id
    INNER JOIN grados   g  ON pms.grado_id    = g.grado_id
    INNER JOIN secciones s ON pms.seccion_id  = s.seccion_id
    LEFT  JOIN alumnos  a  ON a.seccion_id    = pms.seccion_id
    WHERE pms.profesor_id = :id AND pms.estado = 1
    GROUP BY m.materia_id, g.grado_id, s.seccion_id
    ORDER BY g.grado_id, m.nombre_materia
");
$mis_materias->execute(['id' => $prof_id]);
$mis_materias = $mis_materias->fetchAll(PDO::FETCH_ASSOC);

// Últimas notas de los alumnos de este profesor
$ultimas_notas = $pdo->prepare("
    SELECT n.valor_nota, n.fecha,
           a.nombre_alumno,
           m.nombre_materia,
           p.nombre_periodo
    FROM notas n
    INNER JOIN alumnos  a ON n.alumno_id  = a.alumno_id
    INNER JOIN materias m ON n.materia_id = m.materia_id
    INNER JOIN periodos p ON n.periodo_id = p.periodo_id
    INNER JOIN profesor_materia_seccion pms ON n.materia_id = pms.materia_id
        AND a.seccion_id = pms.seccion_id
    WHERE pms.profesor_id = :id AND pms.estado = 1
    ORDER BY n.fecha DESC, n.nota_id DESC
    LIMIT 6
");
$ultimas_notas->execute(['id' => $prof_id]);
$ultimas_notas = $ultimas_notas->fetchAll(PDO::FETCH_ASSOC);

$hora   = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 18 ? 'Buenas tardes' : 'Buenas noches');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Docente — Liceo Elba Hernández</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy:       #001f3f;
            --navy-light: #002d5a;
            --navy-dark:  #001428;
            --accent:     #00aaff;
            --accent2:    #00d4aa;
            --accent3:    #ff6b35;
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
        .nav-item.active { background: linear-gradient(135deg, #00aa88, #00d4aa); color: white; box-shadow: 0 4px 15px rgba(0,212,170,.3); }
        .nav-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: rgba(255,255,255,.07); flex-shrink: 0; }
        .nav-item.active .nav-icon { background: rgba(255,255,255,.2); }
        .sidebar-footer { padding: 14px 12px; border-top: 1px solid rgba(255,255,255,.08); }
        .user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,.05); border-radius: 10px; }
        .user-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #00aa88, var(--accent)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; flex-shrink: 0; }
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
        .topbar-badge i { color: var(--accent2); margin-right: 5px; }
        .content { padding: 28px 32px; flex: 1; }

        /* ── WELCOME BANNER ── */
        .welcome-banner { background: linear-gradient(135deg, #003d2a 0%, #005940 50%, #007a55 100%); border-radius: 16px; padding: 28px 32px; color: white; position: relative; overflow: hidden; margin-bottom: 28px; }
        .welcome-banner::before { content: ''; position: absolute; top: -40px; right: -40px; width: 200px; height: 200px; border-radius: 50%; background: rgba(0,212,170,.12); }
        .welcome-banner::after  { content: ''; position: absolute; bottom: -60px; right: 120px; width: 150px; height: 150px; border-radius: 50%; background: rgba(0,170,255,.08); }
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
        .stat-icon.purple { background: #f0eeff; color: #7c3aed; }
        .stat-icon.teal   { background: #e8fafa; color: #0d9488; }
        .stat-body { flex: 1; }
        .stat-value { font-size: 28px; font-weight: 800; line-height: 1; color: var(--text); }
        .stat-label { font-size: 12px; color: var(--muted); margin-top: 3px; font-weight: 500; }

        /* ── QUICK ACTIONS ── */
        .quick-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 28px; }
        .quick-btn { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 12px; text-align: center; text-decoration: none; color: var(--text); font-size: 12px; font-weight: 600; transition: all .2s; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .quick-btn:hover { border-color: var(--accent2); color: #00a878; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,212,170,.12); }
        .quick-btn i { font-size: 22px; }

        /* ── BOTTOM GRID ── */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .panel-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; }
        .panel-card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .panel-card-header h6 { font-size: 13px; font-weight: 700; color: var(--text); margin: 0; }
        .panel-card-header a { font-size: 11.5px; color: var(--accent2); text-decoration: none; font-weight: 600; }
        .panel-card-body { padding: 16px 20px; }

        /* Tabla notas */
        .mini-table { width: 100%; }
        .mini-table tr td { padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
        .mini-table tr:last-child td { border-bottom: none; }
        .nota-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .nota-alta   { background: #e8fdf5; color: #00a878; }
        .nota-media  { background: #fffbea; color: #d97706; }
        .nota-baja   { background: #fef2f2; color: #dc2626; }

        /* Materias chips */
        .materia-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); }
        .materia-item:last-child { border-bottom: none; }
        .materia-name { font-size: 13px; font-weight: 600; color: var(--text); }
        .materia-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #e8fdf5; color: #00a878; }

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
            .quick-actions { grid-template-columns: repeat(3, 1fr); }
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
            <span>Panel Docente</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="profesores.php" class="nav-item active">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div>
            Dashboard
        </a>

        <div class="nav-section-label">Académico</div>
        <a href="cargar_notas_profesor.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
            Cargar Notas
        </a>
        <a href="lista_estudiantes_profesor.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-users"></i></div>
            Mis Alumnos
        </a>
        <a href="gestionar_actividades.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-tasks"></i></div>
            Actividades
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
                <span>Docente</span>
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
            <h1>Panel Docente</h1>
            <p>Complejo Educativo Elba Hernández de Yánez</p>
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
            <h2><?php echo $saludo; ?>, Prof. <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?> 👨‍🏫</h2>
            <p>Tienes <?php echo $total_materias; ?> materia(s) asignada(s) en <?php echo $total_secciones; ?> sección(es) este período.</p>
            <div class="date-chip">
                <i class="fas fa-calendar-check"></i>
                <?php
                    $dias   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                    $meses  = ['enero','febrero','marzo','abril','mayo','junio',
                               'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    echo $dias[date('w')] . ', ' . date('d') . ' de ' . $meses[(int)date('m')-1] . ' de ' . date('Y');
                ?>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card fade-up delay-1">
                <div class="stat-icon blue"><i class="fas fa-book-open"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_materias; ?></div>
                    <div class="stat-label">Materias Asignadas</div>
                </div>
            </div>
            <div class="stat-card fade-up delay-2">
                <div class="stat-icon teal"><i class="fas fa-layer-group"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_secciones; ?></div>
                    <div class="stat-label">Secciones</div>
                </div>
            </div>
            <div class="stat-card fade-up delay-3">
                <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_alumnos; ?></div>
                    <div class="stat-label">Alumnos a Cargo</div>
                </div>
            </div>
            <div class="stat-card fade-up delay-4">
                <div class="stat-icon purple"><i class="fas fa-pen-nib"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_notas; ?></div>
                    <div class="stat-label">Notas Registradas</div>
                </div>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="quick-actions fade-up">
            <a href="cargar_notas_profesor.php" class="quick-btn">
                <i class="fas fa-clipboard-list" style="color:#0080cc;"></i>
                Cargar Notas
            </a>
            <a href="lista_estudiantes_profesor.php" class="quick-btn">
                <i class="fas fa-users" style="color:#00a878;"></i>
                Ver Mis Alumnos
            </a>
            <a href="gestionar_actividades.php" class="quick-btn">
                <i class="fas fa-tasks" style="color:#7c3aed;"></i>
                Gestionar Actividades
            </a>
        </div>

        <!-- Bottom Grid -->
        <div class="bottom-grid fade-up">

            <!-- Mis materias -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h6><i class="fas fa-book-open" style="color:var(--accent2); margin-right:6px;"></i> Mis Materias</h6>
                    <a href="lista_estudiantes_profesor.php">Ver alumnos →</a>
                </div>
                <div class="panel-card-body">
                    <?php if (empty($mis_materias)): ?>
                        <p style="color:var(--muted); font-size:13px; text-align:center; padding:20px 0;">
                            Sin materias asignadas aún.
                        </p>
                    <?php else: foreach ($mis_materias as $m): ?>
                    <div class="materia-item">
                        <div>
                            <div class="materia-name"><?php echo htmlspecialchars($m['nombre_materia']); ?></div>
                            <div class="materia-sub"><?php echo htmlspecialchars($m['nombre_grado']); ?> — Sección <?php echo htmlspecialchars($m['nombre_seccion']); ?></div>
                        </div>
                        <span class="chip">
                            <i class="fas fa-user-graduate"></i>
                            <?php echo $m['total_alumnos']; ?> alumnos
                        </span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Últimas notas registradas -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h6><i class="fas fa-clock" style="color:var(--accent); margin-right:6px;"></i> Últimas notas registradas</h6>
                    <a href="cargar_notas_profesor.php">Cargar notas →</a>
                </div>
                <div class="panel-card-body">
                    <?php if (empty($ultimas_notas)): ?>
                        <p style="color:var(--muted); font-size:13px; text-align:center; padding:20px 0;">
                            No has registrado notas aún.
                        </p>
                    <?php else: ?>
                    <table class="mini-table">
                        <?php foreach ($ultimas_notas as $n):
                            $cls = $n['valor_nota'] >= 15 ? 'nota-alta' : ($n['valor_nota'] >= 10 ? 'nota-media' : 'nota-baja');
                        ?>
                        <tr>
                            <td>
                                <strong style="font-size:13px;"><?php echo htmlspecialchars($n['nombre_alumno']); ?></strong><br>
                                <span style="font-size:11px; color:var(--muted);"><?php echo htmlspecialchars($n['nombre_materia']); ?></span>
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