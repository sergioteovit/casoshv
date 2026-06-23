<?php

session_start();

// Validar seguridad del rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] === 'Invitado') {
    header("Location: lista_casos.php");
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

// Asegurar que recibimos el ID del caso a actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_caso'])) {
    $idCaso = isset($_POST['id_caso']) ? intval($_POST['id_caso']) : null;
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $datosJson = isset($_POST['datos_json']) ? $_POST['datos_json'] : null;

        if (!$idCaso || !$datosJson) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros requeridos para actualizar.']);
            exit;
        }
        
        /*if ($_SESSION['rol'] === 'Administrador') {
            // Si es Administrador, procesamos el valor que editó en el formulario
            $identificador = isset($_POST['identificador']) ? trim($_POST['identificador']) : null;

            if (empty($identificador)) {
                throw new Exception("El identificador del caso no puede quedar vacío.");
            }

            // Guardamos el nuevo identificador modificado en el array
            $_POST['identificador'] = $identificador;

        } else {
            // Si NO es Administrador, el campo no llegó por POST (porque estaba disabled).
            // Para no perder el identificador original, tenemos que recuperarlo de la base de datos
            // antes de sobreescribir el JSON.

            $stmt_check = $pdo->prepare("SELECT datos_completos FROM casos_clinicos WHERE id = ?");
            $stmt_check->execute([$id_caso]);
            $caso_antiguo = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($caso_antiguo) {
                $json_antiguo = json_decode($caso_antiguo['datos_completos'], true);
                // Rescatamos el identificador original que ya tenía el caso y lo reinyectamos al POST
                $_POST['identificador'] = $json_antiguo['identificador'] ?? 'SIN-ID';
            }
        }*/

        // Procesar nuevos archivos adjuntos opcionales en la edición
        $archivosSubidos = [];
        $uploadDir = __DIR__ . '/uploads/';

        if(isset($_FILES['archivos'])) {
            foreach($_FILES['archivos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['archivos']['name'][$key], PATHINFO_EXTENSION);
                    $fileNameLimpio = time() . '_edit_' . uniqid() . '.' . $extension; 
                    if(move_uploaded_file($tmp_name, $uploadDir . $fileNameLimpio)) {
                        $archivosSubidos[] = 'uploads/' . $fileNameLimpio;
                    }
                }
            }
        } 
        
        try {
            if (!empty($archivosSubidos)) {
                // Si subió archivos nuevos, los guardamos y actualizamos las rutas
                $sql = "UPDATE casos_clinicos SET datos_completos = :datos, rutas_archivos = :archivos WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':datos' => $datosJson,
                    ':archivos' => json_encode($archivosSubidos),
                    ':id' => $idCaso
                ]);
            } else {
                // Si no subió nuevos archivos, solo actualizamos los datos textuales del JSON
                $sql = "UPDATE casos_clinicos SET datos_completos = :datos WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':datos' => $datosJson,
                    ':id' => $idCaso
                ]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Registro actualizado correctamente.']);

        } catch(Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar el registro: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        // ERROR: Redirige de vuelta al editor manteniendo el ID y anexando el error ocurrido
        $mensajeError = urlencode($e->getMessage());
        exit;
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()]);
        exit;
    }
} 
?>