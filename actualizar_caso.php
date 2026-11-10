<?php

session_start();

// Validar seguridad del rol
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] === 'Invitado') {
    header("Location: lista_casos.php");
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/subidas.php';
require_once __DIR__ . '/seguridad.php';

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
        if (!hv_json_objeto_valido($datosJson)) {
            echo json_encode(['status' => 'error', 'message' => 'Los datos del caso no tienen un formato válido. No se guardó nada.']);
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

        // Procesar nuevos archivos adjuntos opcionales en la edición (solo imágenes, PDF y Word)
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

        try {
            if (!empty($archivosSubidos)) {
                // Los archivos nuevos se agregan a los que ya tenía el caso (antes se reemplazaban)
                $stmtRutas = $pdo->prepare("SELECT rutas_archivos FROM casos_clinicos WHERE id = :id");
                $stmtRutas->execute([':id' => $idCaso]);
                $rutasAnteriores = json_decode((string)$stmtRutas->fetchColumn(), true);
                if (!is_array($rutasAnteriores)) {
                    $rutasAnteriores = [];
                }

                $sql = "UPDATE casos_clinicos SET datos_completos = :datos, rutas_archivos = :archivos WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':datos' => $datosJson,
                    ':archivos' => json_encode(array_values(array_merge($rutasAnteriores, $archivosSubidos))),
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