<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

// Si ya está logueado, lo mandamos al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: lista_casos.php");
    exit;
}

require_once __DIR__ . '/config_bd.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $correo = trim($_POST['correo']);
    $departamento = trim($_POST['departamento']);

    // Validar en el backend que las contraseñas coincidan
    if ($password !== $password_confirm) {
        $error = "Las contraseñas no coinciden. Por favor, verifica e inténtalo de nuevo.";
    } else {
        // Por seguridad, todo registro público es Invitado
        $rol = 'Invitado'; 
        $hashPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            $sql = "INSERT INTO usuarios (usuario, password, rol, nombre, apellidos, correo, departamento) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario, $hashPassword, $rol, $nombre, $apellidos, $correo, $departamento]);
            
            // Redirigir al login con mensaje de éxito
            header("Location: login.php?msg=registrado");
            exit;
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "El nombre de usuario o correo electrónico ya existen. Por favor elige otros.";
            } else {
                $error = "Ocurrió un error al registrar: " . hv_error_interno($e);
            }
        }
    }
}

require_once __DIR__ . '/plantilla.php';

// Valor anterior de un campo (si el registro falló), escapado
function valor_previo($campo)
{
    return isset($_POST[$campo]) ? h($_POST[$campo]) : '';
}

hv_acceso_inicio(
    'Crear cuenta',
    'Súmate a quienes escriben los casos',
    'Crea tu cuenta para participar en la elaboración de casos clínicos. Un administrador la revisará y te asignará un rol.',
    'assets/img/portada-heroe.png',
    'Dos docentes de medicina diseñan un caso clínico'
);
?>
<h2 class="h4 mb-1">Crear cuenta</h2>
<p class="text-muted mb-4">Tu cuenta quedará pendiente hasta que un administrador la active.</p>

<?php if (isset($error)): ?>
    <div class="alert alert-danger small py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= h($error) ?></div>
<?php endif; ?>

<form action="registro.php" method="POST" id="formRegistro">
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label fw-bold small" for="campoNombre">Nombre(s)</label>
            <input type="text" name="nombre" id="campoNombre" class="form-control" autocomplete="given-name" required value="<?= valor_previo('nombre') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold small" for="campoApellidos">Apellidos</label>
            <input type="text" name="apellidos" id="campoApellidos" class="form-control" autocomplete="family-name" required value="<?= valor_previo('apellidos') ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold small" for="campoCorreo">Correo electrónico</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" name="correo" id="campoCorreo" class="form-control" autocomplete="email" required value="<?= valor_previo('correo') ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold small" for="campoDepartamento">Departamento o especialidad</label>
        <input type="text" name="departamento" id="campoDepartamento" class="form-control" placeholder="Ej. Fisiología, Cirugía, Salud Pública…" value="<?= valor_previo('departamento') ?>">
    </div>

    <hr class="my-4">

    <div class="mb-3">
        <label class="form-label fw-bold small" for="campoUsuario">Nombre de usuario</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" name="usuario" id="campoUsuario" class="form-control" placeholder="Ej. jlopez89" autocomplete="username" required value="<?= valor_previo('usuario') ?>">
        </div>
        <div class="form-text">Es el que escribirás para iniciar sesión.</div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold small" for="inputPassword">Contraseña</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" name="password" id="inputPassword" class="form-control" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary" type="button" data-mostrar="inputPassword" title="Mostrar contraseña" aria-label="Mostrar u ocultar la contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold small" for="inputPasswordConfirm">Confirmar contraseña</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" name="password_confirm" id="inputPasswordConfirm" class="form-control" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary" type="button" data-mostrar="inputPasswordConfirm" title="Mostrar contraseña" aria-label="Mostrar u ocultar la contraseña">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <div id="errorPassword" class="text-danger small mt-1" style="display: none;">
            <i class="bi bi-exclamation-circle me-1"></i>Las contraseñas no coinciden.
        </div>
    </div>

    <button type="submit" class="btn btn-turquesa btn-lg w-100"><i class="bi bi-person-plus me-1"></i>Crear cuenta</button>

    <div class="text-center mt-3 small">
        <span class="text-muted">¿Ya tienes una cuenta?</span>
        <a href="login.php" class="fw-bold">Inicia sesión</a>
    </div>
</form>
<?php hv_acceso_fin(); ?>
<script>
    // Las contraseñas deben coincidir antes de enviar el formulario
    document.getElementById('formRegistro').addEventListener('submit', function (event) {
        const confirmar = document.getElementById('inputPasswordConfirm');
        const coinciden = document.getElementById('inputPassword').value === confirmar.value;
        document.getElementById('errorPassword').style.display = coinciden ? 'none' : 'block';
        confirmar.classList.toggle('is-invalid', !coinciden);
        if (!coinciden) {
            event.preventDefault();
        }
    });

    // Quitar el aviso cuando se vuelve a escribir
    document.getElementById('inputPasswordConfirm').addEventListener('input', function () {
        document.getElementById('errorPassword').style.display = 'none';
        this.classList.remove('is-invalid');
    });
</script>
</body>
</html>
