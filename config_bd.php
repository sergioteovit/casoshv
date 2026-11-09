<?php
// Define las variables de conexión que usan las páginas: $host, $dbname, $db_name, $user y $pass.
// Los valores viven en config.local.php, que no se versiona (plantilla: config.local.example.php).

$rutaConfigLocal = __DIR__ . '/config.local.php';

if (!is_file($rutaConfigLocal)) {
    error_log('[casoshv] Falta config.local.php');
    http_response_code(500);
    exit('Configuración del servidor incompleta.');
}

$configLocal = require $rutaConfigLocal;

$host    = $configLocal['host'];
$dbname  = $configLocal['base'];
$db_name = $dbname;
$user    = $configLocal['usuario'];
$pass    = $configLocal['contrasena'];

unset($configLocal, $rutaConfigLocal);
