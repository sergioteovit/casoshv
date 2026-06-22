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

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $correo = $_POST['correo'];
    $departamento = $_POST['departamento'];
    $telefono = $_POST['telefono'];
    
    $sql = "UPDATE usuarios SET nombre=?, apellidos=?, correo=?, departamento=?, telefono=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $apellidos, $correo, $departamento, $telefono, $_SESSION['usuario_id']]);
    
    $_SESSION['nombre_completo'] = $nombre . ' ' . $apellidos;
    $mensaje = "Perfil actualizado exitosamente.";
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

                        <button type="submit" class="btn btn-primary w-100">Actualizar Información</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>