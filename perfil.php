<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/seguridad.php';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);

// Un envío sin el token CSRF de la sesión no cambia nada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hv_csrf_valido($_POST['csrf_token'] ?? null)) {
    $error_perfil = "La solicitud no es válida. Recarga la página e inténtalo de nuevo.";
    unset($_POST['accion']);
}

// ==========================================
// PROCESAR ACTUALIZACIÓN DE PERFIL Y CONTRASEÑA
// ==========================================
// Asumiendo que tu formulario envía una acción y tienes el ID en $_SESSION['id']
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {

    $id_usuario = $_SESSION['usuario_id']; 

    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $correo = $_POST['correo'];
    $departamento = $_POST['departamento'];
    $telefono = $_POST['telefono'];
    
    $sql = "UPDATE usuarios SET nombre=?, apellidos=?, correo=?, departamento=?, telefono=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $apellidos, $correo, $departamento, $telefono, $_SESSION['usuario_id']]);
    
    $_SESSION['nombre_completo'] = $nombre . ' ' . $apellidos;
    $mensaje = "Datos de perfil actualizados exitosamente.";

    // LÓGICA DE CAMBIO DE CONTRASEÑA
    $pass_actual = $_POST['password_actual'] ?? '';
    $pass_nueva = $_POST['nueva_password'] ?? '';
    $pass_confirmar = $_POST['confirmar_password'] ?? '';

    // Solo intentamos cambiarla si el usuario escribió algo en "Nueva Contraseña"
    if (!empty($pass_nueva)) {

        if ($pass_nueva !== $pass_confirmar) {
            $error_perfil = "Las contraseñas nuevas no coinciden.";
        } elseif (empty($pass_actual)) {
            $error_perfil = "Debes ingresar tu contraseña actual para poder cambiarla.";
        } else {
            // Consultamos el hash actual de la base de datos
            $stmtPass = $pdo->prepare("SELECT password FROM usuarios WHERE id = :id");
            $stmtPass->execute([':id' => $id_usuario]);
            $usuario = $stmtPass->fetch(PDO::FETCH_ASSOC);

            // Verificamos que la contraseña actual ingresada sea la correcta
            if ($usuario && password_verify($pass_actual, $usuario['password'])) {

                // Encriptamos la nueva contraseña
                $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);

                // Actualizamos en la base de datos
                $stmtUpdatePass = $pdo->prepare("UPDATE usuarios SET password = :hash WHERE id = :id");
                $stmtUpdatePass->execute([
                    ':hash' => $nuevo_hash,
                    ':id' => $id_usuario
                ]);

                header("Location: perfil.php?msg=perfil_pass_ok");
                exit;
            } else {
                $error_perfil = "La contraseña actual es incorrecta.";
            }
        }
    } else {
        // Si no quiso cambiar la contraseña, solo actualizamos sus datos normales
        // header("Location: perfil.php?msg=perfil_ok");
        // exit;
    }
}

// Obtener datos actuales
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$perfil = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/plantilla.php';

$nombreCompleto = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellidos'] ?? ''));

hv_encabezado('Mi perfil');
hv_cabecera('Mi perfil', 'Tus datos de contacto y tu contraseña.', 'bi-person-gear');
?>
<main class="container pb-5 position-relative">
    <?php if (isset($mensaje)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= h($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'perfil_pass_ok'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>Perfil y contraseña actualizados correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_perfil)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($error_perfil) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="hv-tarjeta p-4 text-center hv-aparecer">
                <span class="hv-avatar hv-avatar-grande mb-3" aria-hidden="true"><?= h(hv_iniciales($nombreCompleto !== '' ? $nombreCompleto : ($perfil['usuario'] ?? ''))) ?></span>
                <h2 class="h5 mb-1"><?= h($nombreCompleto) ?></h2>
                <div class="text-muted mb-2">@<?= h($perfil['usuario'] ?? '') ?></div>
                <div class="mb-3"><?= hv_rol($perfil['rol'] ?? '') ?></div>
                <?php if (!empty($perfil['departamento'])): ?>
                    <div class="small text-muted"><i class="bi bi-building me-1"></i><?= h($perfil['departamento']) ?></div>
                <?php endif; ?>
                <?php if (($perfil['rol'] ?? '') === 'Invitado'): ?>
                    <div class="alert alert-warning small mt-3 mb-0 text-start">
                        <i class="bi bi-hourglass-split me-1"></i>Tu cuenta espera la activación de un administrador.
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-lg-8">
            <form method="POST" class="hv-aparecer hv-aparecer-2">
                <input type="hidden" name="csrf_token" value="<?= h(hv_token_csrf()) ?>">

                <section class="hv-tarjeta mb-4">
                    <div class="hv-tarjeta-encabezado"><i class="bi bi-person-vcard me-2 text-primary"></i>Datos personales</div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small" for="campoNombre">Nombre</label>
                                <input type="text" name="nombre" id="campoNombre" class="form-control" value="<?= h($perfil['nombre'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small" for="campoApellidos">Apellidos</label>
                                <input type="text" name="apellidos" id="campoApellidos" class="form-control" value="<?= h($perfil['apellidos'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small" for="campoCorreo">Correo electrónico</label>
                                <input type="email" name="correo" id="campoCorreo" class="form-control" value="<?= h($perfil['correo'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small" for="campoTelefono">Teléfono celular</label>
                                <input type="text" name="telefono" id="campoTelefono" class="form-control" value="<?= h($perfil['telefono'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold small" for="campoDepartamento">Departamento de adscripción</label>
                                <input type="text" name="departamento" id="campoDepartamento" class="form-control" value="<?= h($perfil['departamento'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="hv-tarjeta mb-4">
                    <div class="hv-tarjeta-encabezado"><i class="bi bi-shield-lock me-2 text-primary"></i>Cambiar contraseña</div>
                    <div class="p-4">
                        <p class="text-muted small">Deja estos campos en blanco si no quieres cambiar tu contraseña.</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold small" for="password_actual">Contraseña actual</label>
                                <div class="input-group">
                                    <input type="password" name="password_actual" id="password_actual" class="form-control" autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" data-mostrar="password_actual" title="Mostrar contraseña" aria-label="Mostrar u ocultar la contraseña"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small" for="nueva_password">Nueva contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="nueva_password" id="nueva_password" class="form-control" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" data-mostrar="nueva_password" title="Mostrar contraseña" aria-label="Mostrar u ocultar la contraseña"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small" for="confirmar_password">Confirmar la nueva contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="confirmar_password" id="confirmar_password" class="form-control" autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" data-mostrar="confirmar_password" title="Mostrar contraseña" aria-label="Mostrar u ocultar la contraseña"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="<?= ($perfil['rol'] ?? '') === 'Invitado' ? 'cuenta_pendiente.php' : 'lista_casos.php' ?>" class="btn btn-outline-secondary">Volver</a>
                    <button type="submit" name="accion" value="actualizar_perfil" class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</main>
<?php hv_pie(); ?>
<script>
    // Mostrar u ocultar contraseñas: botones con data-mostrar="id-del-campo"
    document.querySelectorAll('[data-mostrar]').forEach(boton => {
        boton.addEventListener('click', () => {
            const campo = document.getElementById(boton.dataset.mostrar);
            const icono = boton.querySelector('i');
            const ver = campo.type === 'password';
            campo.type = ver ? 'text' : 'password';
            icono.classList.toggle('bi-eye', !ver);
            icono.classList.toggle('bi-eye-slash', ver);
        });
    });
</script>
</body>
</html>
