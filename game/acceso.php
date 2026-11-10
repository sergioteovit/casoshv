<?php
// Inicio de sesión, registro y recuperación de contraseña de la web de estadísticas.
require_once __DIR__ . '/plantilla.php';
hv_encabezado('Iniciar sesión', '', ['barra' => false, 'clase' => 'hv-acceso']);
?>
<style>
    .hv-acceso .hv-principal { display: flex; align-items: center; padding: 2rem 0; }
    .hv-acceso-tarjeta { overflow: hidden; border: 0; }
    .hv-acceso-marca {
        background:
            radial-gradient(circle at 20% 15%, rgba(var(--hv-turquesa-rgb), .45), transparent 45%),
            linear-gradient(160deg, var(--hv-morado-medio), var(--hv-morado-oscuro));
        color: #fff;
    }
    .hv-acceso-marca img.hv-logo { max-width: 260px; }
    .hv-acceso-marca img.hv-figura { max-height: 260px; filter: drop-shadow(0 1rem 1.5rem rgba(0, 0, 0, .3)); }
    .hidden { display: none !important; }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="hv-tarjeta hv-acceso-tarjeta hv-aparecer">
                <div class="row g-0">
                    <div class="col-lg-6 hv-acceso-marca p-4 p-md-5 d-flex flex-column">
                        <a href="index.php"><img class="hv-logo img-fluid" src="assets/img/logo-hv-blanco.png" alt="Hospital Virtual FacMed · Desafío Clínico"></a>
                        <h1 class="h3 mt-4">¡Qué bueno verte de nuevo!</h1>
                        <p class="mb-4" style="color: rgba(255,255,255,.85)">
                            Entra para consultar tu avance, tus estrellas e insignias, y unirte a un grupo de tu clase.
                        </p>
                        <div class="text-center mt-auto d-none d-lg-block">
                            <img class="hv-figura img-fluid" src="assets/img/ilustracion-acceso.png" alt="">
                        </div>
                    </div>

                    <div class="col-lg-6 p-4 p-md-5 bg-white">
                        <div id="alertBox" class="alert hidden" role="alert"></div>

                        <!-- Iniciar sesión -->
                        <form id="loginPanel" onsubmit="handleRequest(event, 'login')">
                            <h2 class="h4 mb-1">Iniciar sesión</h2>
                            <p class="text-muted mb-4">Usa la misma cuenta que en el juego.</p>
                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="loginEmail">Correo electrónico</label>
                                <input type="email" class="form-control" name="email" id="loginEmail" autocomplete="email" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small" for="loginPassword">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="loginPassword" autocomplete="current-password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('loginPassword', 'iconLogin')" aria-label="Mostrar u ocultar la contraseña">
                                        <i id="iconLogin" class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar</button>
                            <div class="d-flex flex-wrap justify-content-between gap-2 mt-3 small">
                                <a href="#" onclick="togglePanels('registerPanel'); return false;">¿No tienes cuenta? Regístrate</a>
                                <a href="#" onclick="togglePanels('recoverPanel'); return false;" class="text-muted">Olvidé mi contraseña</a>
                            </div>
                        </form>

                        <!-- Registro -->
                        <form id="registerPanel" class="hidden" onsubmit="handleRequest(event, 'register')">
                            <h2 class="h4 mb-1">Crear cuenta</h2>
                            <p class="text-muted mb-4">Con esta cuenta también entrarás al juego.</p>
                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="regUsername">Nombre de usuario</label>
                                <input type="text" class="form-control" name="username" id="regUsername" autocomplete="username" required>
                                <div class="form-text">Es el nombre que verán los demás en las posiciones.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="regEmail">Correo electrónico</label>
                                <input type="email" class="form-control" name="email" id="regEmail" autocomplete="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="regPassword">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="regPassword" autocomplete="new-password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('regPassword', 'iconRegPass')" aria-label="Mostrar u ocultar la contraseña">
                                        <i id="iconRegPass" class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small" for="regPasswordConfirm">Confirmar contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password_confirm" id="regPasswordConfirm" autocomplete="new-password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('regPasswordConfirm', 'iconRegPassConfirm')" aria-label="Mostrar u ocultar la contraseña">
                                        <i id="iconRegPassConfirm" class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-turquesa btn-lg w-100"><i class="bi bi-person-plus me-1"></i>Crear cuenta</button>
                            <div class="text-center mt-3 small">
                                <a href="#" onclick="togglePanels('loginPanel'); return false;">Ya tengo cuenta</a>
                            </div>
                        </form>

                        <!-- Recuperación -->
                        <form id="recoverPanel" class="hidden" onsubmit="handleRequest(event, 'recover')">
                            <h2 class="h4 mb-1">Recuperar contraseña</h2>
                            <p class="text-muted mb-4">Escribe tu correo para recibir un enlace de recuperación.</p>
                            <div class="mb-4">
                                <label class="form-label fw-bold small" for="recEmail">Correo electrónico</label>
                                <input type="email" class="form-control" name="email" id="recEmail" autocomplete="email" required>
                            </div>
                            <button type="submit" class="btn btn-oro btn-lg w-100"><i class="bi bi-envelope me-1"></i>Enviar enlace</button>
                            <div class="text-center mt-3 small">
                                <a href="#" onclick="togglePanels('loginPanel'); return false;">Volver a iniciar sesión</a>
                            </div>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <a href="index.php" class="fw-bold"><i class="bi bi-trophy me-1"></i>Ver tablas de posiciones</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-center align-items-center gap-3 mt-4">
                <img src="assets/img/escudo-unam-blanco.png" alt="Universidad Nacional Autónoma de México" style="height: 56px">
                <img src="assets/img/escudo-fm-blanco-web.png" alt="Facultad de Medicina" style="height: 56px">
            </div>
            <p class="text-center small mt-2 mb-0" style="color: rgba(255,255,255,.75)">
                Facultad de Medicina, Universidad Nacional Autónoma de México
            </p>
        </div>
    </div>
</div>

<?php hv_pie(['pie' => false]); ?>
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
                alertBox.innerText = "Las contraseñas no coinciden.";
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

                if (action === 'login') {
                    alertBox.innerText = "¡Bienvenido! Entrando a tu panel…";
                    // Redirigir al panel tras un login exitoso
                    setTimeout(() => window.location.href = 'dashboard.php', 1000);
                } else {
                    alertBox.innerText = data.message;
                    if (action === 'register') {
                        setTimeout(() => togglePanels('loginPanel'), 2000);
                    }
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
