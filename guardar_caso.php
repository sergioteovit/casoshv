<?php
// Configurar cabeceras para aceptar peticiones de otros dominios (CORS) si es necesario
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// 1. CONFIGURACIÓN DE TU BASE DE DATOS REMOTA
$host = 'localhost'; // Ej: remotemysql.com o la IP de tu servidor
$dbname = 'casoshv';
$user = 'root';
$pass = '';

try {
    // Conexión usando PDO (seguro contra inyecciones SQL)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a BD: ' . $e->getMessage()]);
    exit;
}

// 2. RECIBIR Y VALIDAR LOS DATOS JSON
$datosJson = isset($_POST['datos_json']) ? $_POST['datos_json'] : null;

if (!$datosJson) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos del formulario.']);
    exit;
}

// 3. PROCESAR SUBIDA DE ARCHIVOS
$archivosSubidos = [];
$uploadDir = __DIR__ . '/uploads/'; // Carpeta donde se guardarán los PDFs/Imágenes

// Crear la carpeta si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if(isset($_FILES['archivos'])) {
    foreach($_FILES['archivos']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK) {
            $fileNameOriginal = basename($_FILES['archivos']['name'][$key]);
            
            // Limpiar el nombre del archivo para evitar problemas (espacios, caracteres extraños)
            $extension = pathinfo($fileNameOriginal, PATHINFO_EXTENSION);
            $fileNameLimpio = time() . '_' . uniqid() . '.' . $extension; 
            
            $targetFilePath = $uploadDir . $fileNameLimpio;
            
            // Mover el archivo de la memoria temporal a la carpeta final
            if(move_uploaded_file($tmp_name, $targetFilePath)) {
                // Guardamos solo la ruta relativa para la base de datos
                $archivosSubidos[] = 'uploads/' . $fileNameLimpio;
            }
        }
    }
}

// 4. INSERTAR EN LA BASE DE DATOS
try {
    $sql = "INSERT INTO casos_clinicos (datos_completos, rutas_archivos) VALUES (:datos, :archivos)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':datos' => $datosJson, // Guardamos el JSON íntegro
        ':archivos' => json_encode($archivosSubidos) // Guardamos el array de rutas como JSON
    ]);
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Caso clínico guardado exitosamente.',
        'id_insertado' => $pdo->lastInsertId()
    ]);

} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar el registro: ' . $e->getMessage()]);
}
?>