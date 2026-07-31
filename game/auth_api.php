<?php
session_start();
header('Content-Type: application/json');

$host = "localhost";
$db_name = "myhvirtual";
$user = "myhvirtual";
$pass = "uPLtaPntlDJnThpf";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => "Error de base de datos."]);
    exit();
}

$action = $_POST['action'] ?? '';

// === LOGIN ===
if ($action === 'login') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT u.UserId, u.Username, u.PasswordHash, p.Progress, p.Statistics 
                            FROM Users u 
                            JOIN PlayerData p ON u.UserId = p.UserId 
                            WHERE u.Email = :email");
    $stmt->execute([':email' => $email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userRow && password_verify($password, $userRow['PasswordHash'])) {
        // Login exitoso: Guardar datos en la sesión PHP
        $_SESSION['UserId'] = $userRow['UserId'];
        $_SESSION['Username'] = $userRow['Username'];
        $_SESSION['Progress'] = $userRow['Progress']; // El JSON de progreso
        $_SESSION['Statistics'] = $userRow['Statistics']; // El JSON de estadísticas
        
        echo json_encode(["success" => true, "message" => "Conectando..."]);
    } else {
        echo json_encode(["success" => false, "error" => "Credenciales incorrectas."]);
    }
}

// === REGISTRO ===
elseif ($action === 'register') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $userId = bin2hex(random_bytes(16)); // UUID simplificado

    // Datos por defecto para el juego Hospital Virtual
    $defaultPrefs = json_encode(["audio" => ["masterVolume" => 1.0], "ui" => ["language" => "es-MX"]]);
    $defaultStats = json_encode(["totalPlayTimeSeconds" => 0, "patientsCured" => 0, "totalMoneyEarned" => 0]);
    $defaultProg = json_encode(["currentLevel" => 1, "inGameCurrency" => 5000.00, "hospitalLayout" => []]);

    try {
        $conn->beginTransaction();
        
        $stmtUser = $conn->prepare("INSERT INTO Users (UserId, Username, Email, PasswordHash) VALUES (?, ?, ?, ?)");
        $stmtUser->execute([$userId, $username, $email, $password]);

        $stmtData = $conn->prepare("INSERT INTO PlayerData (UserId, Preferences, Statistics, Progress) VALUES (?, ?, ?, ?)");
        $stmtData->execute([$userId, $defaultPrefs, $defaultStats, $defaultProg]);

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Cuenta creada. Por favor inicia sesión."]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["success" => false, "error" => "El correo o usuario ya existe."]);
    }
}

// === RECUPERAR CONTRASEÑA ===
elseif ($action === 'recover') {
    // Aquí iría la lógica para enviar un email con la función mail() de PHP o PHPMailer.
    echo json_encode(["success" => true, "message" => "Si el correo existe, recibirás un enlace."]);
}
?>