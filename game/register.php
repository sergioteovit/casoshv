<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['Username']) || !isset($input['Email']) || !isset($input['Password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Faltan datos de registro."]);
    exit();
}

// 1. Generar un UUID v4 para el nuevo usuario
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$userId = generateUUID();
$username = $input['Username'];
$email = $input['Email'];
// 2. NUNCA guardar la contraseña en texto plano. PHP tiene una función nativa muy segura:
$passwordHash = password_hash($input['Password'], PASSWORD_DEFAULT); 

// 3. Definir los JSON por defecto para un jugador nuevo
$defaultPreferences = json_encode([
    "audio" => ["masterVolume" => 1.0, "musicVolume" => 0.8, "sfxVolume" => 1.0],
    "ui" => ["language" => "es-MX", "colorBlindMode" => false, "showTutorials" => true]
]);

$defaultStatistics = json_encode([
    "totalPlayTimeSeconds" => 0,
    "patientsAdmitted" => 0,
    "patientsCured" => 0,
    "emergenciesResolved" => 0,
    "totalMoneyEarned" => 0,
    "favoriteDepartment" => "Ninguno",
    "doctorsHired" => 0
]);

$defaultProgress = json_encode([
    "currentLevel" => 1,
    "experiencePoints" => 0,
    "inGameCurrency" => 5000.00, // Dinero inicial
    "unlockedDepartments" => ["Reception"],
    "hospitalLayout" => [],
    "inventory" => []
]);

// 4. Conexión a la BD
$host = "localhost";
$db_name = "myhvirtual";
$user = "myhvirtual";
$pass = "uPLtaPntlDJnThpf";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 5. Iniciar la Transacción (Todo o nada)
    $conn->beginTransaction();

    // Insertar en la tabla Users
    $queryUsers = "INSERT INTO Users (UserId, Username, Email, PasswordHash) VALUES (:id, :user, :email, :pass)";
    $stmtUsers = $conn->prepare($queryUsers);
    $stmtUsers->execute([':id' => $userId, ':user' => $username, ':email' => $email, ':pass' => $passwordHash]);

    // Insertar en la tabla PlayerData
    $queryData = "INSERT INTO PlayerData (UserId, Preferences, Statistics, Progress) VALUES (:id, :pref, :stat, :prog)";
    $stmtData = $conn->prepare($queryData);
    $stmtData->execute([':id' => $userId, ':pref' => $defaultPreferences, ':stat' => $defaultStatistics, ':prog' => $defaultProgress]);

    // Confirmar la transacción
    $conn->commit();

    http_response_code(201); // 201 = Created
    echo json_encode([
        "message" => "Usuario registrado exitosamente.",
        "userId" => $userId // Devolvemos el ID a Unity para que pueda iniciar sesión automáticamente si queremos
    ]);

} catch(PDOException $e) {
    // Si algo falla, revertimos cualquier cambio hecho en la base de datos
    $conn->rollBack();
    
    // Capturar si el usuario o correo ya existen (Error 1062 en MySQL)
    if ($e->getCode() == 23000) {
        http_response_code(409); // Conflict
        echo json_encode(["error" => "El nombre de usuario o correo electrónico ya está en uso."]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error al registrar: " . $e->getMessage()]);
    }
}
?>