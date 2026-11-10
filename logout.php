<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

// Solo se cierra la sesión desde el botón Salir (lleva el token CSRF), no desde un enlace externo.
if (isset($_SESSION['usuario_id']) && !hv_csrf_valido($_GET['csrf'] ?? null)) {
    header("Location: lista_casos.php");
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
}
session_destroy();

header("Location: login.php");
exit;
