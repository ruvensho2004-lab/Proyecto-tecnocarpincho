<?php
require_once '../includes/security.php';
verificar_rol([1]);

require_once '../includes/conexion.php';

$nombre = $_SESSION['usuario']['nombre'] ?? 'Administrador';
$usuario = $_SESSION['usuario']['usuario'] ?? '';

// ── Estadísticas ──────────────────────────────────────────────────────────
$total_alumnos     = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
$alumnos_activos   = $pdo->query("SELECT COUNT(*) FROM alumnos a INNER JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE u.estado = 1")->fetchColumn();
$total_profesores  = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 3 AND estado = 1")->fetchColumn();
$total_materias    = $pdo->query("SELECT COUNT(*) FROM materias WHERE estado = 1")->fetchColumn();
$total_secciones   = $pdo->query("SELECT COUNT(*) FROM secciones WHERE estado = 1")->fetchColumn();
$total_asignaciones= $pdo->query("SELECT COUNT(*) FROM profesor_materia_seccion WHERE estado = 1")->fetchColumn();

// Alumnos por grado
$alumnos_por_grado = $pdo->query("
    SELECT g.nombre_grado, COUNT(a.alumno_id) as total
    FROM grados g
    LEFT JOIN alumnos a ON g.grado_id = a.grado_id
    GROUP BY g.grado_id, g.nombre_grado
    ORDER BY g.grado_id
")->fetchAll(PDO::FETCH_ASSOC);

// Últimos alumnos registrados
$ultimos_alumnos = $pdo->query("
    SELECT a.nombre_alumno, g.nombre_grado, s.nombre_seccion, u.estado
    FROM alumnos a
    INNER JOIN grados g ON a.grado_id = g.grado_id
    INNER JOIN secciones s ON a.seccion_id = s.seccion_id
    INNER JOIN usuarios u ON a.usuario_id = u.usuario_id
    ORDER BY a.alumno_id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$hora = (int)date('H');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 18 ? 'Buenas tardes' : 'Buenas noches');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador — Liceo Elba Hernández</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* ── SIDEBAR ─────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--navy-dark);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            transition: transform .3s ease;
        }

        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 42px; height: 42px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: white; font-weight: 800; flex-shrink: 0;
        }

        .brand-text { line-height: 1.2; }
        .brand-text strong { color: white; font-size: 13px; font-weight: 700; display: block; }
        .brand-text span { color: rgba(255,255,255,.45); font-size: 11px; }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }

        .nav-section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.3);
            padding: 16px 10px 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: rgba(255,255,255,.65);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 2px;
            transition: all .2s;
            position: relative;
        }

        .nav-item:hover {
            background: rgba(255,255,255,.07);
            color: white;
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--accent), #0080cc);
            color: white;
            box-shadow: 0 4px 15px rgba(0,170,255,.3);
        }

        .nav-item .nav-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            background: rgba(255,255,255,.07);
            flex-shrink: 0;
        }

        .nav-item.active .nav-icon { background: rgba(255,255,255,.2); }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .user-card {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            background: rgba(255,255,255,.05);
            border-radius: 10px;
        }

        .user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent2), var(--accent));
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 13px; flex-shrink: 0;
        }

        .user-info strong { color: white; font-size: 12.5px; display: block; }
        .user-info span { color: rgba(255,255,255,.4); font-size: 11px; }

        .logout-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            color: rgba(255,100,100,.8);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 500;
            margin-top: 6px;
            transition: all .2s;
        }
        .logout-btn:hover { background: rgba(255,100,100,.1); color: #ff6b6b; }

        /* ── MAIN ─────────────────────────────────────────────── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── TOPBAR ───────────────────────────────────────────── */
        .topbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }

        .topbar-left h1 { font-size: 17px; font-weight: 700; color: var(--text); }
        .topbar-left p  { font-size: 12px; color: var(--muted); margin-top: 1px; }

        .topbar-right { display: flex; align-items: center; gap: 12px; }

        .topbar-badge {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
        }

        .topbar-badge i { color: var(--accent); margin-right: 5px; }

        /* ── CONTENT ──────────────────────────────────────────── */
        .content { padding: 28px 32px; flex: 1; }

        /* ── WELCOME BANNER ───────────────────────────────────── */
        .welcome-banner {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 50%, #003d80 100%);
            border-radius: 16px;
            padding: 28px 32px;
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(0,170,255,.12);
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 120px;
            width: 150px; height: 150px;
            border-radius: 50%;
            background: rgba(0,212,170,.08);
        }

        .welcome-banner h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .welcome-banner p  { color: rgba(255,255,255,.65); font-size: 13.5px; }

        .welcome-banner .date-chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 12px;
            color: rgba(255,255,255,.85);
            margin-top: 16px;
            backdrop-filter: blur(4px);
        }

        /* ── STAT CARDS ───────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 14px;
            padding: 20px 22px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform .2s, box-shadow .2s;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,.08);
            color: inherit;
        }

        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-icon.blue   { background: #e8f4ff; color: #0080cc; }
        .stat-icon.green  { background: #e8fdf5; color: #00a878; }
        .stat-icon.orange { background: #fff3ee; color: #e85d04; }
        .stat-icon.purple { background: #f0eeff; color: #7c3aed; }
        .stat-icon.teal   { background: #e8fafa; color: #0d9488; }
        .stat-icon.gold   { background: #fffbea; color: #d97706; }

        .stat-body { flex: 1; min-width: 0; }
        .stat-value { font-size: 28px; font-weight: 800; line-height: 1; color: var(--text); }
        .stat-label { font-size: 12px; color: var(--muted); margin-top: 3px; font-weight: 500; }
        .stat-sub   { font-size: 11px; color: var(--accent2); font-weight: 600; margin-top: 4px; }

        /* ── BOTTOM GRID ──────────────────────────────────────── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .panel-card {
            background: var(--card);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .panel-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }

        .panel-card-header h6 {
            font-size: 13px; font-weight: 700; color: var(--text); margin: 0;
        }

        .panel-card-header a {
            font-size: 11.5px; color: var(--accent); text-decoration: none; font-weight: 600;
        }

        .panel-card-body { padding: 16px 20px; }

        /* Tabla últimos alumnos */
        .mini-table { width: 100%; }
        .mini-table tr td { padding: 9px 0; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
        .mini-table tr:last-child td { border-bottom: none; }
        .mini-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), var(--accent));
            color: white; font-size: 11px; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center;
            margin-right: 8px; flex-shrink: 0;
        }
        .badge-grado {
            font-size: 10px; font-weight: 600;
            padding: 3px 8px; border-radius: 20px;
            background: #e8f4ff; color: #0080cc;
        }
        .badge-activo   { background: #e8fdf5; color: #00a878; }
        .badge-inactivo { background: #fef2f2; color: #dc2626; }

        /* Barras de grado */
        .grado-bar-item { margin-bottom: 14px; }
        .grado-bar-item:last-child { margin-bottom: 0; }
        .grado-bar-label {
            display: flex; justify-content: space-between;
            font-size: 12.5px; font-weight: 600; margin-bottom: 5px;
            color: var(--text);
        }
        .grado-bar-track {
            height: 8px; background: var(--bg);
            border-radius: 4px; overflow: hidden;
        }
        .grado-bar-fill {
            height: 100%; border-radius: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            transition: width 1s cubic-bezier(.4,0,.2,1);
        }

        /* ── QUICK ACTIONS ────────────────────────────────────── */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 28px;
        }

        .quick-btn {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 12px;
            text-align: center;
            text-decoration: none;
            color: var(--text);
            font-size: 12px;
            font-weight: 600;
            transition: all .2s;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
        }

        .quick-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,170,255,.12);
        }

        .quick-btn i { font-size: 20px; }

        /* ── RESPONSIVE ───────────────────────────────────────── */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .quick-actions { grid-template-columns: repeat(4, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .bottom-grid { grid-template-columns: 1fr; }
            .content { padding: 20px 16px; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── ANIMACIONES ──────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .fade-up { animation: fadeUp .4s ease both; }
        .delay-1 { animation-delay: .05s; }
        .delay-2 { animation-delay: .10s; }
        .delay-3 { animation-delay: .15s; }
        .delay-4 { animation-delay: .20s; }
        .delay-5 { animation-delay: .25s; }
        .delay-6 { animation-delay: .30s; }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ═══════════════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-text">
            <strong>Liceo Elba Hernández</strong>
            <span>Sistema Académico</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>
        <a href="admin.php" class="nav-item active">
            <div class="nav-icon"><i class="fas fa-th-large"></i></div>
            Dashboard
        </a>

        <div class="nav-section-label">Gestión Académica</div>
        <a href="gestionar_alumnos.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-user-graduate"></i></div>
            Alumnos
        </a>
        <a href="importar_alumnos.php" class="nav-item">
        <div class="nav-icon"><i class="fas fa-file-import"></i></div>
        Importación Masiva
        </a>
        <a href="gestionar_profesores.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            Profesores
        </a>
        <a href="gestionar_materias.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-book-open"></i></div>
            Materias
        </a>
        <a href="gestionar_secciones.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-layer-group"></i></div>
            Secciones
        </a>
        <a href="asignar_profesores_materias.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-link"></i></div>
            Asignar Materias
        </a>

        <div class="nav-section-label">Reportes</div>
        <a href="lista_estudiantes_admin.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-list-ul"></i></div>
            Lista de Estudiantes
        </a>
        <a href="gestionar_periodos_actividades.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-calendar-alt"></i></div>
            Periodos y Actividades
        </a>

        <div class="nav-section-label">Configuración</div>
        <a href="registro_usuarios.php" class="nav-item">
            <div class="nav-icon"><i class="fas fa-user-plus"></i></div>
            Registrar Usuario
        </a>
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
                <span>Administrador</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>
</aside>

<!-- ══ MAIN ═══════════════════════════════════════════════════════════════ -->
<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Panel de Administración</h1>
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
            <h2><?php echo $saludo ?>, <?php echo htmlspecialchars(explode(' ', $nombre)[0]); ?> 👋</h2>
            <p>Aquí tienes un resumen de lo que está pasando en el sistema académico hoy.</p>
            <div class="date-chip">
                <i class="fas fa-calendar-check"></i>
                <?php
                    $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                    $meses = ['enero','febrero','marzo','abril','mayo','junio',
                              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
                    echo $dias[date('w')] . ', ' . date('d') . ' de ' . $meses[(int)date('m')-1] . ' de ' . date('Y');
                ?>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <a href="gestionar_alumnos.php" class="stat-card fade-up delay-1">
                <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_alumnos; ?></div>
                    <div class="stat-label">Total Alumnos</div>
                    <div class="stat-sub"><?php echo $alumnos_activos; ?> activos</div>
                </div>
            </a>
            <a href="gestionar_profesores.php" class="stat-card fade-up delay-2">
                <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_profesores; ?></div>
                    <div class="stat-label">Profesores Activos</div>
                </div>
            </a>
            <a href="gestionar_materias.php" class="stat-card fade-up delay-3">
                <div class="stat-icon purple"><i class="fas fa-book-open"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_materias; ?></div>
                    <div class="stat-label">Materias</div>
                </div>
            </a>
            <a href="gestionar_secciones.php" class="stat-card fade-up delay-4">
                <div class="stat-icon teal"><i class="fas fa-layer-group"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_secciones; ?></div>
                    <div class="stat-label">Secciones</div>
                </div>
            </a>
            <a href="asignar_profesores_materias.php" class="stat-card fade-up delay-5">
                <div class="stat-icon orange"><i class="fas fa-link"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_asignaciones; ?></div>
                    <div class="stat-label">Asignaciones Activas</div>
                </div>
            </a>
            <a href="lista_estudiantes_admin.php" class="stat-card fade-up delay-6">
                <div class="stat-icon gold"><i class="fas fa-chart-pie"></i></div>
                <div class="stat-body">
                    <div class="stat-value"><?php echo $total_alumnos > 0 ? round(($alumnos_activos/$total_alumnos)*100) : 0; ?>%</div>
                    <div class="stat-label">Tasa de Actividad</div>
                </div>
            </a>
        </div>

        <!-- Acciones rápidas -->
        <div class="quick-actions fade-up">
            <a href="gestionar_alumnos.php" class="quick-btn">
                <i class="fas fa-user-plus" style="color:#0080cc;"></i>
                Nuevo Alumno
            </a>
            <a href="gestionar_profesores.php" class="quick-btn">
                <i class="fas fa-chalkboard-teacher" style="color:#00a878;"></i>
                Nuevo Profesor
            </a>
            <a href="asignar_profesores_materias.php" class="quick-btn">
                <i class="fas fa-tasks" style="color:#7c3aed;"></i>
                Asignar Materia
            </a>
            <a href="lista_estudiantes_admin.php" class="quick-btn">
                <i class="fas fa-file-alt" style="color:#d97706;"></i>
                Ver Reportes
            </a>
        </div>

        <!-- Bottom Grid -->
        <div class="bottom-grid fade-up">

            <!-- Últimos alumnos -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h6><i class="fas fa-clock" style="color:var(--accent); margin-right:6px;"></i> Últimos alumnos registrados</h6>
                    <a href="gestionar_alumnos.php">Ver todos →</a>
                </div>
                <div class="panel-card-body">
                    <?php if (empty($ultimos_alumnos)): ?>
                        <p style="color:var(--muted); font-size:13px; text-align:center; padding:20px 0;">
                            No hay alumnos registrados aún.
                        </p>
                    <?php else: ?>
                    <table class="mini-table">
                        <?php foreach ($ultimos_alumnos as $al): ?>
                        <tr>
                            <td>
                                <span class="mini-avatar">
                                    <?php echo strtoupper(substr($al['nombre_alumno'], 0, 1)); ?>
                                </span>
                                <strong style="font-size:13px;"><?php echo htmlspecialchars($al['nombre_alumno']); ?></strong>
                            </td>
                            <td style="text-align:right;">
                                <span class="badge-grado">
                                    <?php echo htmlspecialchars($al['nombre_grado']); ?>
                                    <?php echo htmlspecialchars($al['nombre_seccion']); ?>
                                </span>
                            </td>
                            <td style="text-align:right; padding-left:8px;">
                                <span class="badge-grado <?php echo $al['estado'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                                    <?php echo $al['estado'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alumnos por grado -->
            <div class="panel-card">
                <div class="panel-card-header">
                    <h6><i class="fas fa-chart-bar" style="color:var(--accent2); margin-right:6px;"></i> Distribución por grado</h6>
                    <a href="lista_estudiantes_admin.php">Ver lista →</a>
                </div>
                <div class="panel-card-body">
                    <?php if (empty($alumnos_por_grado)): ?>
                        <p style="color:var(--muted); font-size:13px; text-align:center; padding:20px 0;">
                            Sin datos disponibles.
                        </p>
                    <?php else:
                        $max = max(array_column($alumnos_por_grado, 'total')) ?: 1;
                        foreach ($alumnos_por_grado as $g):
                            $pct = round(($g['total'] / $max) * 100);
                    ?>
                    <div class="grado-bar-item">
                        <div class="grado-bar-label">
                            <span><?php echo htmlspecialchars($g['nombre_grado']); ?></span>
                            <span style="color:var(--muted);"><?php echo $g['total']; ?> alumnos</span>
                        </div>
                        <div class="grado-bar-track">
                            <div class="grado-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div><!-- /bottom-grid -->

    </div><!-- /content -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle en móvil
document.addEventListener('DOMContentLoaded', () => {
    // Animar barras al cargar
    document.querySelectorAll('.grado-bar-fill').forEach(bar => {
        const w = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => { bar.style.width = w; }, 300);
    });

    // Contador animado en stats
    document.querySelectorAll('.stat-value').forEach(el => {
        const target = parseFloat(el.textContent);
        if (isNaN(target) || el.textContent.includes('%')) return;
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