<?php
// 1. Iniciar sesión y conexión a la base de datos
session_start();
// Reemplaza 'conexion.php' con el nombre de tu archivo de conexión PDO
require_once 'initconn.php'; 

// ==========================================
// 2. SEGURIDAD: Restringir estrictamente por rol
// ==========================================
$roles_permitidos = ['Administrador', 'Editor'];

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $roles_permitidos)) {
    // Si no tiene sesión o su rol no está en la lista de permitidos, se bloquea.
    die("Acceso denegado: Solo los Administradores tienen permisos para descargar copias de seguridad.");
}

try {
    // 3. Consultar todos los casos de la base de datos
    // NOTA: Ajusta 'casos' y 'datos_completos' a los nombres reales de tu tabla si es necesario
    $stmt = $pdo->query("SELECT id, datos_completos FROM casos_clinicos");
    $casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($casos)) {
        die("No hay casos registrados en la base de datos para descargar.");
    }

    // 4. Configurar el archivo ZIP temporal
    $zip = new ZipArchive();
    $nombre_zip = "backup_casos_" . date('Y-m-d_His') . ".zip";
    
    // Creamos un archivo temporal en el servidor
    $ruta_temporal = tempnam(sys_get_temp_dir(), 'backup_zip');

    if ($zip->open($ruta_temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Error: No se pudo crear el archivo ZIP temporal en el servidor.");
    }

    // 5. Recorrer los casos y agregarlos al ZIP
    foreach ($casos as $caso) {
        $json_crudo = $caso['datos_completos'];
        
        if ($json_crudo) {
            $datos_arreglo = json_decode($json_crudo, true);
            
            // Intentar extraer el nombre del paciente para nombrar el archivo. 
            // Si no existe, usa el ID del caso.
            $nombre_caso = isset($datos_arreglo['identificador']) ? $datos_arreglo['identificador'] : 'desconocido_id_' . $caso['id'];
            
            // Limpiar caracteres especiales para que el sistema operativo no dé error
            // $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_paciente);
            $nombre_archivo = "caso_" . $nombre_caso . ".json";
            
            // Formatear el JSON para que sea legible (Pretty Print)
            $json_formateado = json_encode($datos_arreglo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Agregar el archivo de texto directamente a la memoria del ZIP
            $zip->addFromString($nombre_archivo, $json_formateado);
        }
    }

    // 6. Cerrar y guardar el ZIP
    $zip->close();

    // ==========================================
    // 7. FORZAR LA DESCARGA EN EL NAVEGADOR
    // ==========================================
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nombre_zip . '"');
    header('Content-Length: ' . filesize($ruta_temporal));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Leer el archivo y enviarlo al usuario
    readfile($ruta_temporal);

    // 8. Limpieza: Borrar el archivo temporal del servidor para no ocupar espacio
    unlink($ruta_temporal);
    exit;

} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}
?>