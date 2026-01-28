<?php

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configuración segura de sesiones
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 cuando uses HTTPS
    
    session_start();
    
    // Regenerar ID de sesión periódicamente (previene session fixation)
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutos
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Función para verificar autenticación
 */
function verificar_autenticacion() {
    if (!isset($_SESSION['usuario'])) {
        header('Location: ../login.html');
        exit();
    }
    
    // Timeout de sesión (30 minutos de inactividad)
    if (isset($_SESSION['ultimo_acceso'])) {
        $inactividad = time() - $_SESSION['ultimo_acceso'];
        if ($inactividad > 1800) { // 30 minutos
            session_destroy();
            header('Location: ../login.html?timeout=1');
            exit();
        }
    }
    $_SESSION['ultimo_acceso'] = time();
}

/**
 * Función para verificar rol
 */
function verificar_rol($roles_permitidos) {
    verificar_autenticacion();
    
    if (!in_array($_SESSION['usuario']['rol'], $roles_permitidos)) {
        http_response_code(403);
        die("❌ Acceso denegado. No tienes permisos para acceder a esta página.");
    }
}

/**
 * Generar token CSRF
 */
function generar_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verificar_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die("❌ Token CSRF inválido. Posible ataque detectado.");
    }
    return true;
}

/**
 * Limpiar input (sanitización)
 */
function limpiar_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar entrada
 */
function validar_entrada($data, $tipo = 'texto') {
    switch ($tipo) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'numero':
            return is_numeric($data);
        case 'texto':
            return !empty(trim($data));
        case 'alfanumerico':
            return preg_match('/^[a-zA-Z0-9_]+$/', $data);
        default:
            return !empty(trim($data));
    }
}

/**
 * Registrar evento de seguridad
 */
function registrar_log_seguridad($evento, $detalles = '') {
    $log_file = __DIR__ . '/logs/seguridad.log';
    
    // Crear carpeta logs si no existe
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    $usuario = $_SESSION['usuario']['usuario'] ?? 'Anónimo';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
    $fecha = date('Y-m-d H:i:s');
    
    $mensaje = "[{$fecha}] Usuario: {$usuario} | IP: {$ip} | Evento: {$evento} | Detalles: {$detalles}\n";
    
    file_put_contents($log_file, $mensaje, FILE_APPEND);
}

/**
 * Protección contra fuerza bruta en login
 */
function verificar_intentos_login($usuario) {
    if (!isset($_SESSION['login_intentos'])) {
        $_SESSION['login_intentos'] = [];
    }
    
    $intentos = &$_SESSION['login_intentos'];
    
    // Limpiar intentos antiguos (más de 15 minutos)
    $intentos = array_filter($intentos, function($tiempo) {
        return (time() - $tiempo) < 900; // 15 minutos
    });
    
    // Verificar número de intentos
    if (count($intentos) >= 5) {
        $tiempo_espera = 900 - (time() - min($intentos));
        $minutos = ceil($tiempo_espera / 60);
        
        registrar_log_seguridad('Bloqueo por intentos excesivos', "Usuario: {$usuario}");
        
        return [
            'bloqueado' => true,
            'mensaje' => "Demasiados intentos fallidos. Espera {$minutos} minuto(s).",
            'tiempo_restante' => $tiempo_espera
        ];
    }
    
    return ['bloqueado' => false];
}

/**
 * Registrar intento de login fallido
 */
function registrar_intento_fallido($usuario) {
    if (!isset($_SESSION['login_intentos'])) {
        $_SESSION['login_intentos'] = [];
    }
    
    $_SESSION['login_intentos'][] = time();
    registrar_log_seguridad('Login fallido', "Usuario: {$usuario}");
}

/**
 * Limpiar intentos de login tras éxito
 */
function limpiar_intentos_login() {
    unset($_SESSION['login_intentos']);
}

/**
 * Headers de seguridad
 */
function establecer_headers_seguridad() {
    // Prevenir clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevenir MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Habilitar XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy básica
    header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");
}

// Establecer headers de seguridad automáticamente
establecer_headers_seguridad();
?>