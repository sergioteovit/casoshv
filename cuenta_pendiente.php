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

require_once __DIR__ . '/plantilla.php';

hv_acceso_inicio(
    'Cuenta pendiente',
    'Tu cuenta ya está creada',
    'Mientras un administrador la revisa, no se muestran los casos clínicos. En cuanto te asigne un rol podrás entrar.',
    'assets/img/portada-heroe.png',
    'Dos docentes de medicina diseñan un caso clínico'
);
?>
<div class="text-center">
    <img class="hv-figura-espera img-fluid mb-3" src="assets/img/mascota-espera.png" alt="Robot mascota con un reloj de arena">
    <h2 class="h4 mb-2">Cuenta pendiente de aprobación</h2>
    <p class="text-muted">
        Hola, <strong><?= h($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') ?></strong>.
        Un administrador debe asignarte un rol antes de que puedas ver los casos clínicos.
    </p>
    <p class="text-muted small mb-4">Cuando te avisen que tu cuenta está activa, pulsa "Comprobar de nuevo".</p>
    <div class="d-grid gap-2">
        <a href="cuenta_pendiente.php" class="btn btn-primary btn-lg"><i class="bi bi-arrow-clockwise me-1"></i>Comprobar de nuevo</a>
        <div class="d-flex gap-2">
            <a href="perfil.php" class="btn btn-outline-primary flex-fill"><i class="bi bi-person-gear me-1"></i>Mi perfil</a>
            <a href="logout.php?csrf=<?= h(hv_token_csrf()) ?>" class="btn btn-outline-danger flex-fill"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
        </div>
    </div>
</div>
<?php hv_acceso_fin(); ?>
</body>
</html>
