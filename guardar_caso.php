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

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/subidas.php';
require_once __DIR__ . '/seguridad.php';

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
        if (!hv_json_objeto_valido($datosJson)) {
            echo json_encode(['status' => 'error', 'message' => 'Los datos del caso no tienen un formato válido. No se guardó nada.']);
            exit;
        }

        // 3. PROCESAR SUBIDA DE ARCHIVOS (solo imágenes, PDF y Word; si uno no es válido no se guarda nada)
        $validacion = hv_subidas_validar('archivos');
        if ($validacion['error'] !== null) {
            echo json_encode(['status' => 'error', 'message' => $validacion['error']]);
            exit;
        }
        $archivosSubidos = hv_subidas_guardar($validacion['archivos']);
        if ($archivosSubidos === null) {
            echo json_encode(['status' => 'error', 'message' => HV_SUBIDAS_ERROR_CARPETA]);
            exit;
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