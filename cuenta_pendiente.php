<?php
// Página para las cuentas con rol Invitado: no ven casos hasta que un administrador les asigna otro rol.
// Vuelve a leer el rol de la base de datos, así que basta con recargarla después de que cambie.
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config_bd.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    $rolActual = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[casoshv] cuenta_pendiente: ' . $e->getMessage());
    $rolActual = $_SESSION['rol'] ?? 'Invitado';
}

if ($rolActual === false) {
    // La cuenta ya no existe
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['rol'] = $rolActual;
if ($rolActual !== 'Invitado') {
    header('Location: lista_casos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta pendiente de aprobación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; min-height: 100vh; }
        .login-card { max-width: 460px; width: 100%; margin: auto; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card border-0">
        <div class="card-body p-5 text-center">
            <i class="bi bi-hourglass-split text-primary" style="font-size: 3rem;"></i>
            <h4 class="mt-2 fw-bold">Cuenta pendiente de aprobación</h4>
            <p class="text-muted">
                Hola, <?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') ?>.
                Tu cuenta ya está creada, pero un administrador debe asignarte un rol antes de que puedas ver los casos clínicos.
            </p>
            <p class="text-muted small">Cuando te avisen que tu cuenta está activa, pulsa "Comprobar de nuevo".</p>
            <div class="d-grid gap-2 mt-4">
                <a href="cuenta_pendiente.php" class="btn btn-primary fw-bold"><i class="bi bi-arrow-clockwise me-1"></i> Comprobar de nuevo</a>
                <a href="perfil.php" class="btn btn-outline-secondary">Mi perfil</a>
                <a href="logout.php?csrf=<?= hv_token_csrf() ?>" class="btn btn-outline-danger">Salir</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
