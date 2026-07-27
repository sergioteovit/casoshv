<?php
// 1. Configurar las cabeceras para aceptar JSON y responder JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Leer el cuerpo de la petición (el JSON que envía Unity)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Validar que recibimos los datos mínimos necesarios
if (!$input || !isset($input['UserId'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["error" => "Datos inválidos o UserId faltante."]);
    exit();
}

$userId = $input['UserId'];

// Volvemos a convertir los arreglos PHP a strings JSON para guardarlos en la BD
$preferences = json_encode($input['Preferences']);
$statistics = json_encode($input['Statistics']);
$progress = json_encode($input['Progress']);

// 3. Credenciales de tu base de datos MySQL
$host = "localhost";
$db_name = "myhvirtual";
$username = "myhvirtual";
$password = "uPLtaPntlDJnThpf";

try {
    // 4. Crear la conexión PDO a MySQL
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    // Configurar PDO para que lance excepciones en caso de error
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 5. Preparar la consulta SQL (UPDATE)
    // Usamos parámetros (:) para evitar inyección SQL
    $query = "UPDATE PlayerData 
              SET Preferences = :preferences, 
                  Statistics = :statistics, 
                  Progress = :progress 
              WHERE UserId = :userId";

    $stmt = $conn->prepare($query);

    // 6. Asignar los valores a los parámetros y ejecutar
    $stmt->bindParam(":preferences", $preferences);
    $stmt->bindParam(":statistics", $statistics);
    $stmt->bindParam(":progress", $progress);
    $stmt->bindParam(":userId", $userId);

    $stmt->execute();

    // 7. Responder a Unity
    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode(["message" => "Partida guardada exitosamente"]);
    } else {
        // rowCount es 0 si el usuario no existe, o si los datos enviados son exactamente 
        // iguales a los que ya estaban en la base de datos y no hubo nada que actualizar.
        http_response_code(200); 
        echo json_encode(["message" => "Proceso terminado (sin cambios nuevos o usuario no encontrado)"]);
    }

} catch(PDOException $exception) {
    // Manejo de errores de base de datos
    http_response_code(500); // Internal Server Error
    echo json_encode(["error" => "Error en el servidor: " . $exception->getMessage()]);
}
?>