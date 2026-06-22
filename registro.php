<?php
session_start();

// Si ya está logueado, lo mandamos al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: lista_casos.php");
    exit;
}

$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

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
                $error = "Ocurrió un error al registrar: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; min-height: 100vh; padding: 40px 0; }
        .login-card { max-width: 500px; width: 100%; margin: auto; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .toggle-password { cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-person-vcard text-primary" style="font-size: 3rem;"></i>
                <h4 class="mt-2 fw-bold">Crear Cuenta</h4>
                <p class="text-muted small">Llena tus datos para registrarte en la plataforma.</p>
            </div>

            <?php if(isset($error)): ?>
                <div class="alert alert-danger small py-2 text-center"><?= $error ?></div>
            <?php endif; ?>

            <form action="registro.php" method="POST" id="formRegistro">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nombre(s)</label>
                        <input type="text" name="nombre" class="form-control" required value="<?= isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Apellidos</label>
                        <input type="text" name="apellidos" class="form-control" required value="<?= isset($_POST['apellidos']) ? htmlspecialchars($_POST['apellidos']) : '' ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Correo Electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="correo" class="form-control" required value="<?= isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : '' ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Departamento / Especialidad</label>
                    <input type="text" name="departamento" class="form-control" placeholder="Ej. Urgencias, Cardiología..." value="<?= isset($_POST['departamento']) ? htmlspecialchars($_POST['departamento']) : '' ?>">
                </div>

                <hr class="my-4">

                <div class="mb-3">
                    <label class="form-label small fw-bold">Nombre de Usuario (Login)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="usuario" class="form-control" placeholder="Ej. jlopez89" required value="<?= isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : '' ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="inputPassword" class="form-control" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="inputPassword" title="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Confirmar Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="password_confirm" id="inputPasswordConfirm" class="form-control" required>
                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="inputPasswordConfirm" title="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div id="errorPassword" class="text-danger small mt-1" style="display: none;">
                        <i class="bi bi-exclamation-circle me-1"></i> Las contraseñas no coinciden.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold mb-3">Registrarme</button>
                
                <div class="text-center mt-3">
                    <span class="small text-muted">¿Ya tienes una cuenta?</span>
                    <a href="login.php" class="small fw-bold text-decoration-none">Inicia Sesión aquí</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Funcionalidad Mostrar/Ocultar Contraseña
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
                this.title = "Ocultar contraseña";
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                this.title = "Mostrar contraseña";
            }
        });
    });

    // 2. Validación Frontend (Antes de enviar el formulario)
    document.getElementById('formRegistro').addEventListener('submit', function(event) {
        const pass = document.getElementById('inputPassword').value;
        const passConfirm = document.getElementById('inputPasswordConfirm').value;
        const msjError = document.getElementById('errorPassword');

        if (pass !== passConfirm) {
            // Previene que el formulario se envíe
            event.preventDefault(); 
            // Muestra el mensaje de error debajo del input
            msjError.style.display = 'block';
            document.getElementById('inputPasswordConfirm').classList.add('is-invalid');
        } else {
            msjError.style.display = 'none';
            document.getElementById('inputPasswordConfirm').classList.remove('is-invalid');
        }
    });

    // 3. Quitar el error visual cuando el usuario empieza a escribir de nuevo
    document.getElementById('inputPasswordConfirm').addEventListener('input', function() {
        document.getElementById('errorPassword').style.display = 'none';
        this.classList.remove('is-invalid');
    });
</script>

</body>
</html>