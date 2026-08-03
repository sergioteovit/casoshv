<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Virtual - Acceso Web</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f8ff; height: 100vh; display: flex; align-items: center; }
        .card-auth { max-width: 400px; width: 100%; margin: auto; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .hidden { display: none !important; }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-auth p-4">
        <h3 class="text-center text-primary mb-4">🏥 Hospital Virtual</h3>
        
        <!-- Mensajes de Alerta -->
        <div id="alertBox" class="alert hidden" role="alert"></div>

        <!-- PANEL DE LOGIN -->
        <form id="loginPanel" onsubmit="handleRequest(event, 'login')">
            <h5 class="mb-3">Iniciar Sesión</h5>
            <div class="mb-3">
                <input type="email" class="form-control" name="email" placeholder="Correo electrónico" required>
            </div>

            <!-- Campo de contraseña con botón -->
            <div class="input-group mb-3">
                <input type="password" class="form-control" name="password" id="loginPassword" placeholder="Contraseña" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('loginPassword', 'iconLogin')">
                    <i id="iconLogin" class="bi bi-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-primary w-100">Entrar</button>
            <div class="text-center mt-3">
                <a href="#" onclick="togglePanels('registerPanel')">¿No tienes cuenta? Regístrate</a><br>
                <a href="#" onclick="togglePanels('recoverPanel')" class="text-muted small">Olvidé mi contraseña</a>
            </div>
        </form>

        <!-- PANEL DE REGISTRO -->
        <form id="registerPanel" class="hidden" onsubmit="handleRequest(event, 'register')">
            <h5 class="mb-3">Crear Nueva Cuenta</h5>
            <div class="mb-3">
                <input type="text" class="form-control" name="username" placeholder="Nombre de usuario" required>
            </div>
            <div class="mb-3">
                <input type="email" class="form-control" name="email" placeholder="Correo electrónico" required>
            </div>

            <!-- Contraseña -->
            <div class="input-group mb-3">
                <input type="password" class="form-control" name="password" id="regPassword" placeholder="Contraseña" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('regPassword', 'iconRegPass')">
                    <i id="iconRegPass" class="bi bi-eye"></i>
                </button>
            </div>

            <!-- Confirmar Contraseña -->
            <div class="input-group mb-3">
                <input type="password" class="form-control" name="password_confirm" id="regPasswordConfirm" placeholder="Confirmar Contraseña" required>
                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('regPasswordConfirm', 'iconRegPassConfirm')">
                    <i id="iconRegPassConfirm" class="bi bi-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-success w-100">Crear Cuenta y Perfil</button>
            <div class="text-center mt-3">
                <a href="#" onclick="togglePanels('loginPanel')">Volver al Login</a>
            </div>
        </form>

        <!-- PANEL DE RECUPERACIÓN -->
        <form id="recoverPanel" class="hidden" onsubmit="handleRequest(event, 'recover')">
            <h5 class="mb-3">Recuperar Contraseña</h5>
            <p class="small text-muted">Ingresa tu correo para recibir un enlace de recuperación.</p>
            <div class="mb-3">
                <input type="email" class="form-control" name="email" placeholder="Correo electrónico" required>
            </div>
            <button type="submit" class="btn btn-warning w-100">Enviar Enlace</button>
            <div class="text-center mt-3">
                <a href="#" onclick="togglePanels('loginPanel')">Volver al Login</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Alternar entre paneles
    function togglePanels(showId) {
        document.getElementById('loginPanel').classList.add('hidden');
        document.getElementById('registerPanel').classList.add('hidden');
        document.getElementById('recoverPanel').classList.add('hidden');
        document.getElementById('alertBox').classList.add('hidden');
        document.getElementById(showId).classList.remove('hidden');
    }

    // Manejar envíos de formularios hacia el backend PHP
    async function handleRequest(event, action) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', action); // Indicamos qué acción quiere hacer el usuario

        const alertBox = document.getElementById('alertBox');
        
        // Validar contraseñas en el frontend antes de enviar al servidor
        if (action === 'register') {
            const pass = formData.get('password');
            const passConfirm = formData.get('password_confirm');

            if (pass !== passConfirm) {
                alertBox.classList.remove('hidden', 'alert-success');
                alertBox.classList.add('alert-danger');
                alertBox.innerText = "Error: Las contraseñas no coinciden.";
                return; // Detenemos la ejecución aquí, no se envía nada al servidor
            }
        }
        
        try {
            const response = await fetch('auth_api.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            alertBox.classList.remove('hidden', 'alert-danger', 'alert-success');
            
            if (data.success) {
                alertBox.classList.add('alert-success');
                alertBox.innerText = data.message;
                
                if (action === 'login') {
                    // Redirigir al dashboard tras un login exitoso
                    setTimeout(() => window.location.href = 'dashboard.php', 1000);
                } else if (action === 'register') {
                    setTimeout(() => togglePanels('loginPanel'), 2000);
                }
            } else {
                alertBox.classList.add('alert-danger');
                alertBox.innerText = data.error;
            }
        } catch (error) {
            alertBox.classList.remove('hidden');
            alertBox.classList.add('alert-danger');
            alertBox.innerText = "Error de conexión con el servidor.";
        }
    }
    
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>
</body>
</html>