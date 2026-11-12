<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

// Si ya está logueado, redirigir al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: lista_casos.php");
    exit;
}

// Generar números aleatorios para el Captcha
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha_resultado'] = $num1 + $num2;

require_once __DIR__ . '/plantilla.php';

$mensajesError = [
    'captcha'  => 'El resultado de la verificación matemática es incorrecto.',
    'intentos' => 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.',
];
$error = isset($_GET['error']) ? ($mensajesError[$_GET['error']] ?? 'Usuario o contraseña incorrectos.') : null;

hv_acceso_inicio(
    'Iniciar sesión',
    '¡Qué bueno verte de nuevo!',
    'Entra para elaborar, revisar y enriquecer los casos clínicos que se juegan en Hospital Virtual.',
    'assets/img/ilustracion-acceso.png',
    'Médico docente con una carpeta clínica y un holograma de una lista de verificación'
);
?>
<h2 class="h4 mb-1">Iniciar sesión</h2>
<p class="text-muted mb-4">Usa tu cuenta del gestor de casos.</p>

<?php if ($error !== null): ?>
    <div class="alert alert-danger small py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= h($error) ?></div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'registrado'): ?>
    <div class="alert alert-success small py-2">
        <i class="bi bi-check-circle me-1"></i>¡Cuenta creada con éxito! Un administrador debe activarla antes de que puedas ver los casos.
    </div>
<?php endif; ?>

<form action="procesar_login.php" method="POST" id="loginForm">
    <div class="mb-3">
        <label class="form-label fw-bold small" for="inputUsuario">Usuario</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="usuario" id="inputUsuario" class="form-control" autocomplete="username" required autofocus>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold small" for="inputPassword">Contraseña</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="inputPassword" class="form-control" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" data-mostrar="inputPassword" title="Mostrar contraseña" aria-label="Mostrar u ocultar la contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold small" for="inputCaptcha">Verificación: ¿cuánto es <span class="hv-captcha"><?= $num1 ?> + <?= $num2 ?></span>?</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
            <input type="number" name="captcha" id="inputCaptcha" class="form-control" placeholder="Escribe el resultado" required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar</button>

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3 small">
        <a href="registro.php" class="fw-bold">¿No tienes cuenta? Regístrate</a>
        <span class="text-muted">¿Olvidaste tu contraseña? Pide a un administrador que la restablezca.</span>
    </div>
</form>
<?php hv_acceso_fin(); ?>
</body>
</html>
