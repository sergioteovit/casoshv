<?php
// Plataforma de estadísticas del juego. Define $host, $db_name, $user y $pass.
// Los valores viven en game/config.local.php, que no se versiona (plantilla: config.local.example.php).

$rutaConfigLocal = __DIR__ . '/config.local.php';

if (!is_file($rutaConfigLocal)) {
    error_log('[estadisticas] Falta game/config.local.php');
    http_response_code(500);
    exit('Configuración del servidor incompleta.');
}

$configLocal = require $rutaConfigLocal;

$host    = $configLocal['host'];
$db_name = $configLocal['base'];
$user    = $configLocal['usuario'];
$pass    = $configLocal['contrasena'];

unset($configLocal, $rutaConfigLocal);
