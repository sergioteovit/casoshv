<?php
// ==========================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ==========================================
require_once __DIR__ . '/config_bd.php';
$usuario  = $user;
$password = $pass;

try {
    // Especificar el charset utf8mb4 es vital para no corromper los acentos en el JSON
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Opciones de seguridad y optimización para PDO
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en caso de error SQL
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve arreglos asociativos por defecto
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Previene inyecciones SQL de forma más estricta
    ];
    
    // Crear la instancia de conexión
    $pdo = new PDO($dsn, $usuario, $password, $opciones);
    
} catch (PDOException $e) {
    // Si la conexión falla, se detiene la ejecución y oculta credenciales
    // En producción, es mejor registrar este error en un log en lugar de mostrarlo en pantalla
    die("Error de conexión a la base de datos: Verifica tus credenciales.");
}
?>