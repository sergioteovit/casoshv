<?php
session_start();

// 1. SEGURIDAD: Verificar que el usuario sea Administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'Administrador') {
    // Si no está logueado o no es admin, lo expulsamos de esta página
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
    die("Error de conexión a la Base de Datos: " . $e->getMessage());
}

// =================================================================
// NUEVO: PROCESAR CAMBIO DE ROL ASÍNCRONO (AJAX/FETCH)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_rol') {
    // Configuramos cabecera JSON para la respuesta asíncrona
    header('Content-Type: application/json; charset=utf-8');
    
    $idUsuario = isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0;
    $nuevoRol = isset($_POST['nuevo_rol']) ? trim($_POST['nuevo_rol']) : '';
    
    // Validar los roles permitidos
    $rolesPermitidos = ['Invitado', 'Editor', 'Administrador'];
    
    if ($idUsuario === 0 || !in_array($nuevoRol, $rolesPermitidos)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos inválidos.']);
        exit;
    }
    
    // Evitar que el administrador se quite el rol a sí mismo por accidente
    if ($idUsuario === intval($_SESSION['usuario_id']) && $nuevoRol !== 'Administrador') {
        echo json_encode(['status' => 'error', 'message' => 'No puedes cambiar tu propio rol de Administrador para evitar perder el acceso al sistema.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET rol = :rol WHERE id = :id");
        $stmt->execute([':rol' => $nuevoRol, ':id' => $idUsuario]);
        
        echo json_encode(['status' => 'success', 'message' => 'Rol actualizado correctamente.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
    exit; // Detener la ejecución para que no renderice el HTML en la petición AJAX
}

// --- PROCESAR CREACIÓN DE NUEVO USUARIO (Por el Administrador) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    $nuevoUsuario = trim($_POST['usuario']);
    $nuevoPass = $_POST['password'];
    $nuevoRol = $_POST['rol'];
    $nuevoNombre = $_POST['nombre'];
    $nuevosApellidos = $_POST['apellidos'];
    $nuevoCorreo = trim($_POST['correo']);

    // Encriptar la contraseña de forma segura
    $hashPassword = password_hash($nuevoPass, PASSWORD_BCRYPT);

    try {
        $sql = "INSERT INTO usuarios (usuario, password, rol, nombre, apellidos, correo) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevoUsuario, $hashPassword, $nuevoRol, $nuevoNombre, $nuevosApellidos, $nuevoCorreo]);
        $mensajeExito = "Usuario creado exitosamente.";
    } catch(PDOException $e) {
        // Error 23000 es violación de restricción única (usuario o correo repetido)
        if ($e->getCode() == 23000) {
            $mensajeError = "Error: El nombre de usuario o correo electrónico ya están registrados.";
        } else {
            $mensajeError = "Error al crear usuario: " . $e->getMessage();
        }
    }
}

// --- PROCESAR ELIMINACIÓN DE USUARIO ---
if (isset($_GET['eliminar'])) {
    $idEliminar = intval($_GET['eliminar']);
    // Evitar que el administrador se elimine a sí mismo por error
    if ($idEliminar !== $_SESSION['usuario_id']) {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$idEliminar]);
        header("Location: gestion_usuarios.php?msg=deleted");
        exit;
    } else {
        $mensajeError = "No puedes eliminar tu propia cuenta de administrador mientras estás en sesión.";
    }
}

// ==========================================
// PROCESAR RESTABLECIMIENTO DE CONTRASEÑA
// ==========================================
if (isset($_GET['reset_id']) && is_numeric($_GET['reset_id'])) {

    // 🔒 SEGURIDAD: Solo el Administrador puede hacer esto
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
        die("Acceso denegado: Solo los Administradores pueden restablecer contraseñas.");
    }

    $id_reset = intval($_GET['reset_id']);

    // Definir la contraseña temporal por defecto
    $password_temporal = 'Usuario123!';
    // Encriptar la contraseña de forma segura usando el algoritmo nativo de PHP
    $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);

    try {
        // Actualizar la contraseña en la base de datos
        // NOTA: Verifica que tu tabla se llame 'usuarios' y la columna 'password'
        $stmtReset = $pdo->prepare("UPDATE usuarios SET password = :hash WHERE id = :id");
        $stmtReset->execute([
            ':hash' => $password_hash,
            ':id' => $id_reset
        ]);

        // Redirigir para limpiar la URL y mostrar mensaje de éxito
        header("Location: gestion_usuarios.php?msg=pass_reset");
        exit;
    } catch (PDOException $e) {
        $error_sistema = "Error al restablecer la contraseña: " . $e->getMessage();
    }
}

// Obtener la lista de usuarios
$stmt = $pdo->query("SELECT id, usuario, rol, nombre, apellidos, correo, fecha_creacion FROM usuarios ORDER BY fecha_creacion DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style> body { background-color: #f4f7f6; } </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="lista_casos.php"><i class="bi bi-heart-pulse-fill text-danger me-2"></i>Casos Clínicos</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="lista_casos.php">Lista de Casos</a></li>
        <li class="nav-item"><a class="nav-link active" href="gestion_usuarios.php">Gestión de Usuarios</a></li>
      </ul>
      <div class="d-flex align-items-center text-white">
          <span class="me-3 small"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['nombre_completo']) ?></span>
          <a href="perfil.php" class="btn btn-sm btn-outline-light me-2">Mi Perfil</a>
          <a href="logout.php" class="btn btn-sm btn-danger">Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people-fill text-primary me-2"></i> Gestión de Usuarios</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
            <i class="bi bi-person-plus-fill me-1"></i> Dar de Alta Usuario
        </button>
    </div>

    <?php if(isset($mensajeExito)): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= $mensajeExito ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if(isset($mensajeError)): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= $mensajeError ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show">Usuario eliminado correctamente.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <!-- ALERTA DE CONTRASEÑA RESTABLECIDA -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'pass_reset'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-warning" role="alert">
            <i class="bi bi-key-fill me-2 fs-5"></i> 
            <strong>Contraseña restablecida con éxito.</strong> 
            La nueva contraseña temporal de este usuario es: <code class="fs-5 bg-white px-2 py-1 rounded text-dark border">Usuario123!</code><br>
            <small>Pide al usuario que inicie sesión y la cambie lo antes posible.</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div id="contenedor-alerta"></div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Nombre Completo</th>
                        <th>Usuario (Login)</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $user): ?>
                    <tr>
                        <td class="ps-3 fw-bold"><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']) ?></td>
                        <td><code><?= htmlspecialchars($user['usuario']) ?></code></td>
                        <td><?= htmlspecialchars($user['correo']) ?></td>
                        <td>
                            <select class="form-select form-select-sm select-cambio-rol fw-medium" 
                                    data-usuario-id="<?= $user['id'] ?>"
                                    style="border-left: 4px solid <?= $user['rol'] === 'Administrador' ? '#dc3545' : ($user['rol'] === 'Editor' ? '#ffc107' : '#6c757d') ?>;">
                                <option value="Invitado" <?= $user['rol'] === 'Invitado' ? 'selected' : '' ?>>Invitado (Lectura)</option>
                                <option value="Editor" <?= $user['rol'] === 'Editor' ? 'selected' : '' ?>>Editor (Escritura)</option>
                                <option value="Administrador" <?= $user['rol'] === 'Administrador' ? 'selected' : '' ?>>Administrador (Total)</option>
                            </select>
                        </td>
                        <td class="text-center">
                            <!-- BOTÓN RESTABLECER CONTRASEÑA (Solo Admin) -->
                            <?php if ($_SESSION['rol'] === 'Administrador'): ?>
                                <a href="?reset_id=<?= $user['id'] ?>" 
                                   class="btn btn-sm btn-outline-warning shadow-sm" 
                                   onclick="return confirm('¿Estás seguro de que deseas restablecer la contraseña de <?= htmlspecialchars($user['usuario'] ?? 'este usuario') ?>? La nueva contraseña será \'Usuario123!\'');" 
                                   title="Restablecer Contraseña">
                                    <i class="bi bi-key"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($user['id'] !== $_SESSION['usuario_id']): ?>
                                <a href="eliminar_usuario.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar usuario?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Registrar Nuevo Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="gestion_usuarios.php" method="POST">
          <div class="modal-body">
              <input type="hidden" name="accion" value="crear">
              
              <div class="row mb-3">
                  <div class="col-md-6">
                      <label class="form-label small">Nombre</label>
                      <input type="text" name="nombre" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small">Apellidos</label>
                      <input type="text" name="apellidos" class="form-control" required>
                  </div>
              </div>

              <div class="mb-3">
                  <label class="form-label small">Correo Electrónico</label>
                  <input type="email" name="correo" class="form-control" required>
              </div>

              <div class="row mb-3">
                  <div class="col-md-6">
                      <label class="form-label small">Nombre de Usuario (Para acceder)</label>
                      <input type="text" name="usuario" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                      <label class="form-label small">Contraseña</label>
                      <input type="password" name="password" class="form-control" required>
                  </div>
              </div>

              <div class="mb-3">
                  <label class="form-label small">Asignar Rol Inicial</label>
                  <select name="rol" class="form-select" required>
                      <option value="Invitado" selected>Invitado (Solo lectura)</option>
                      <option value="Editor">Editor (Puede crear y editar casos)</option>
                      <option value="Administrador">Administrador (Control total)</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Crear Usuario</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectsRol = document.querySelectorAll('.select-cambio-rol');
    const contenedorAlerta = document.getElementById('contenedor-alerta');

    selectsRol.forEach(select => {
        // Guardamos el valor original por si ocurre un error y necesitamos restaurarlo
        let valorAnterior = select.value;

        select.addEventListener('change', function() {
            const idUsuario = this.getAttribute('data-usuario-id');
            const nuevoRol = this.value;
            const selectElement = this;

            // Bloquear el select temporalmente mientras se guarda
            selectElement.disabled = true;

            // Preparar los datos del formulario
            const formData = new FormData();
            formData.append('accion', 'cambiar_rol');
            formData.append('id_usuario', idUsuario);
            formData.append('nuevo_rol', nuevoRol);

            // Enviar petición al mismo archivo gestion_usuarios.php
            fetch('gestion_usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Cambiar el color del borde izquierdo de forma dinámica según el nuevo rol
                    if (nuevoRol === 'Administrador') selectElement.style.borderLeft = '4px solid #dc3545';
                    else if (nuevoRol === 'Editor') selectElement.style.borderLeft = '4px solid #ffc107';
                    else selectElement.style.borderLeft = '4px solid #6c757d';

                    // Mostrar notificación flotante de éxito
                    mostrarNotificacion('success', data.message);
                    valorAnterior = nuevoRol; // Actualizar el respaldo
                } else {
                    // Mostrar error y revertir el select al rol que tenía antes
                    mostrarNotificacion('danger', data.message);
                    selectElement.value = valorAnterior;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('danger', 'No se pudo conectar con el servidor.');
                selectElement.value = valorAnterior;
            })
            .finally(() => {
                // Desbloquear el select
                selectElement.disabled = false;
            });
        });
    });

    // Función auxiliar para pintar alertas de Bootstrap temporales
    function mostrarNotificacion(tipo, mensaje) {
        contenedorAlerta.innerHTML = `
            <div class="alert alert-${tipo} alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
                <i class="bi ${tipo === 'success' ? 'bi-check-circle-fill me-2' : 'bi-exclamation-triangle-fill me-2'}"></i>
                <div>${mensaje}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        // Auto-eliminar la alerta después de 4 segundos
        setTimeout(() => {
            const alerta = contenedorAlerta.querySelector('.alert');
            if (alerta) {
                const bsAlert = new bootstrap.Alert(alerta);
                bsAlert.close();
            }
        }, 4000);
    }
});
</script>
    
</body>
</html>