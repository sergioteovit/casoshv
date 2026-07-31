<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Mi Perfil</h5>
                    <a href="lista_casos.php" class="btn btn-sm btn-light">Volver al inicio</a>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($mensaje)): ?>
                        <div class="alert alert-success"><?= $mensaje ?></div>
                    <?php endif; ?>
                    
                    <!-- ALERTA DE ÉXITO -->
                    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'perfil_pass_ok'): ?>
                        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i> Perfil y contraseña actualizados correctamente.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- ALERTA DE ERROR -->
                    <?php if (isset($error_perfil)): ?>
                        <div class="alert alert-danger shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_perfil) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Usuario (Solo lectura)</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($perfil['usuario']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Rol en el sistema (Solo lectura)</label>
                                <input type="text" class="form-control bg-light fw-bold text-primary" value="<?= htmlspecialchars($perfil['rol']) ?>" readonly>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($perfil['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellidos</label>
                                <input type="text" name="apellidos" class="form-control" value="<?= htmlspecialchars($perfil['apellidos']) ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($perfil['correo']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono Celular</label>
                                <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($perfil['telefono']) ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Departamento de Adscripción</label>
                            <input type="text" name="departamento" class="form-control" value="<?= htmlspecialchars($perfil['departamento']) ?>">
                        </div>
                        
                        <hr class="my-4 text-muted">
        
                        <h5 class="mb-3 text-secondary">
                            <i class="bi bi-shield-lock me-2"></i>Cambiar Contraseña
                        </h5>
                        <p class="text-muted small mb-3">Deja estos campos en blanco si no deseas cambiar tu contraseña actual.</p>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold">Contraseña Actual</label>
                                <div class="input-group">
                                    <input type="password" name="password_actual" id="password_actual" class="form-control" placeholder="Ingresa tu contraseña actual">
                                    <button class="btn btn-outline-secondary btn-toggle-pass" type="button" data-target="password_actual">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="nueva_password" id="nueva_password" class="form-control" placeholder="Escribe la nueva contraseña">
                                    <button class="btn btn-outline-secondary btn-toggle-pass" type="button" data-target="nueva_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold">Confirmar Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="confirmar_password" id="confirmar_password" class="form-control" placeholder="Repite la nueva contraseña">
                                    <button class="btn btn-outline-secondary btn-toggle-pass" type="button" data-target="confirmar_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!--button type="submit" class="btn btn-primary w-100">Actualizar Información</button-->
                        <button type="submit" name="accion" value="actualizar_perfil" class="btn btn-primary shadow-sm">
                            <i class="bi bi-save me-1"></i> Actualizar Información
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Buscamos todos los botones de mostrar/ocultar contraseña
    const botonesOjo = document.querySelectorAll('.btn-toggle-pass');
    
    botonesOjo.forEach(boton => {
        boton.addEventListener('click', function() {
            // Obtenemos el ID del input que este botón debe controlar
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icono = this.querySelector('i');
            
            // Alternamos entre tipo 'password' y 'text'
            if (input.type === 'password') {
                input.type = 'text';
                // Cambiamos el ícono a ojo tachado
                icono.classList.remove('bi-eye');
                icono.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                // Cambiamos el ícono a ojo normal
                icono.classList.remove('bi-eye-slash');
                icono.classList.add('bi-eye');
            }
        });
    });
});
</script>
    
</body>
</html>