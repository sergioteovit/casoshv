<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

// Configuración de la BD

require_once __DIR__ . '/config_bd.php';

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
    $captcha_esperado = $_SESSION['captcha_resultado'] ?? null;
    unset($_SESSION['captcha_resultado']); // Cada operación sirve para un solo intento
    if ($captcha_ingresado !== $captcha_esperado) {
        header("Location: login.php?error=captcha");
        exit;
    }

    // Límite de intentos fallidos por cuenta + IP y por cuenta
    $claveCuenta = 'gestor|' . strtolower(trim((string)$usuario));
    $claveCuentaIp = $claveCuenta . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (hv_limite_excedido($claveCuentaIp, 8, 900) || hv_limite_excedido($claveCuenta, 30, 900)) {
        header("Location: login.php?error=intentos");
        exit;
    }

    // Verificar usuario en BD
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = :usuario");
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar contraseña (usando password_verify para mayor seguridad)
    if ($user && password_verify($password, $user['password'])) {
        hv_limite_reiniciar($claveCuentaIp);
        hv_limite_reiniciar($claveCuenta);
        session_regenerate_id(true);

        // Guardar datos en sesión
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellidos'];
        
        header("Location: lista_casos.php");
        exit;
    } else {
        hv_limite_registrar_fallo($claveCuentaIp, 900);
        hv_limite_registrar_fallo($claveCuenta, 900);
        header("Location: login.php?error=credenciales");
        exit;
    }
}
?>