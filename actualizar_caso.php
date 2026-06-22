<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()]);
    exit;
}

$idCaso = isset($_POST['id_caso']) ? intval($_POST['id_caso']) : null;
$datosJson = isset($_POST['datos_json']) ? $_POST['datos_json'] : null;

if (!$idCaso || !$datosJson) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros requeridos para actualizar.']);
    exit;
}

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
?>