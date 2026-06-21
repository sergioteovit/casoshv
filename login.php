<?php
session_start();

// Si ya está logueado, redirigir al panel
if (isset($_SESSION['usuario_id'])) {
    header("Location: lista_casos.php");
    exit;
}

// Generar números aleatorios para el Captcha
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha_resultado'] = $num1 + $num2;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema de Casos Clínicos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; display: flex; align-items: center; height: 100vh; }
        .login-card { max-width: 400px; width: 100%; margin: auto; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .btn-toggle-password { cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-heart-pulse-fill text-primary" style="font-size: 3rem;"></i>
                <h4 class="mt-2 fw-bold">Casos Clínicos</h4>
                <p class="text-muted small">Inicie sesión para continuar</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger small py-2 text-center">
                    <?php 
                        if($_GET['error'] == 'captcha') echo "El resultado de la verificación matemática es incorrecto.";
                        else echo "Usuario o contraseña incorrectos.";
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'registrado'): ?>
                <div class="alert alert-success small py-2 text-center">
                    ¡Cuenta creada con éxito! Ahora puedes iniciar sesión.
                </div>
            <?php endif; ?>

            <form action="procesar_login.php" method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="usuario" id="inputUsuario" class="form-control" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="inputPassword" class="form-control" required>
                        <button class="btn btn-outline-secondary btn-toggle-password" type="button" id="btnTogglePassword" title="Mostrar contraseña">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label small fw-bold">Verificación: ¿Cuánto es <?= $num1 ?> + <?= $num2 ?>?</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                        <input type="number" name="captcha" class="form-control" placeholder="Escriba el resultado" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold">Ingresar</button>
                <div class="text-center mt-4">
                    <span class="small text-muted">¿No tienes cuenta?</span>
                    <a href="registro.php" class="small fw-bold text-decoration-none text-primary">Regístrate aquí</a>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    // --- Lógica para mostrar/ocultar la contraseña ---
    const btnTogglePassword = document.getElementById('btnTogglePassword');
    const inputPassword = document.getElementById('inputPassword');
    const eyeIcon = document.getElementById('eyeIcon');

    btnTogglePassword.addEventListener('click', function() {
        if (inputPassword.type === 'password') {
            inputPassword.type = 'text';
            eyeIcon.classList.remove('bi-eye');
            eyeIcon.classList.add('bi-eye-slash');
            this.title = "Ocultar contraseña";
        } else {
            inputPassword.type = 'password';
            eyeIcon.classList.remove('bi-eye-slash');
            eyeIcon.classList.add('bi-eye');
            this.title = "Mostrar contraseña";
        }
    });
</script>

</body>
</html>