<?php

session_start();

// Validar seguridad básica antes de procesar
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] === 'Invitado') {
    header("Location: lista_casos.php");
    exit;
}

// Configurar cabeceras para aceptar peticiones de otros dominios (CORS) si es necesario
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        /*$json_recibido = file_get_contents('php://input');
        $_DATA = json_decode($json_recibido, true); // Lo convertimos en un arreglo asociativo de PHP

        // Si por alguna razón falló el JSON de JS, caemos en el $_POST tradicional por seguridad
        if (!is_array($_DATA)) {
            $_DATA = $_POST;
        }

        // Ahora usamos $_DATA en lugar de $_POST
        $identificador = isset($_DATA['identificador']) ? trim($_DATA['identificador']) : null;

        if ($_SESSION['rol'] === 'Administrador') {
            // Si es administrador, el campo no puede ir vacío
            if (empty($identificador)) {
                echo json_encode(['status' => 'error', 'message' => 'El identificador del caso es obligatorio para administradores.']);
                exit;
            }
        } else {
            // Si es un Editor, generamos el código automático aquí en el servidor
            $identificador = "CASO-" . substr(md5(time()), 0, 8); 
        }
        
        // Aseguramos que el identificador quede guardado dentro de los datos que irán a la BD
        $_DATA['identificador'] = $identificador;*/
        
        $datosJson = isset($_POST['datos_json']) ? $_POST['datos_json'] : null;
        //$datosJson = json_encode($_POST, JSON_UNESCAPED_UNICODE);

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
            // $sql = "INSERT INTO casos_clinicos (datos_completos, rutas_archivos) VALUES (:datos, :archivos)";
            $sql = "INSERT INTO casos_clinicos (datos_completos, rutas_archivos, fecha_registro) VALUES (:datos, :archivos, NOW())";
            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':datos' => $datosJson, // Guardamos el JSON íntegro
                ':archivos' => json_encode($archivosSubidos) // Guardamos el array de rutas como JSON
            ]);

            echo json_encode([
                'status' => 'success', 
                'message' => 'Caso clínico guardado exitosamente.'
            ]);
            exit;

        } catch(Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar el registro: ' . $e->getMessage()]);
        }
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar el registro: ' . $e->getMessage()]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a BD: ' . $e->getMessage()]);
    exit;
}
?>