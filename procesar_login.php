<?php
session_start();

// Configuración de la BD
$host = 'localhost';
$dbname = 'casoshv';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];
    $captcha_ingresado = intval($_POST['captcha']);
    
    // Verificar Captcha
    if ($captcha_ingresado !== $_SESSION['captcha_resultado']) {
        header("Location: login.php?error=captcha");
        exit;
    }

    // Verificar usuario en BD
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar contraseña (usando password_verify para mayor seguridad)
    if ($user && password_verify($password, $user['password'])) {
        // Guardar datos en sesión
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellidos'];
        
        header("Location: lista_casos.php");
        exit;
    } else {
        header("Location: login.php?error=credenciales");
        exit;
    }
}
?>