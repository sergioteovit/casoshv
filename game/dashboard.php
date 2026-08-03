<?php
session_start();

if (!isset($_SESSION['UserId'])) {
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['Role'] ?? 'Player';
$progress = json_decode($_SESSION['Progress'], true);
$statistics = json_decode($_SESSION['Statistics'], true);

// 1. Conexión a la base de datos para obtener el Leaderboard
$host = "localhost";
$db_name = "myhvirtual";
$user = "myhvirtual";
$pass = "uPLtaPntlDJnThpf";

$topPlayers = [];
$allUsers = [];

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Consulta para la Tabla de Posiciones de TODOS los usuarios con rol 'Player'
    $playersRankingQuery = "
        SELECT 
            u.Username, 
            CAST(JSON_EXTRACT(p.Statistics, '$.patientsCured') AS UNSIGNED) AS CuredCount,
            CAST(JSON_EXTRACT(p.Progress, '$.currentLevel') AS UNSIGNED) AS CurrentLevel
        FROM Users u
        JOIN Roles r ON u.RoleId = r.RoleId
        LEFT JOIN PlayerData p ON u.UserId = p.UserId
        WHERE r.RoleName = 'Player'
        ORDER BY CuredCount DESC
    ";
    $stmtRanking = $conn->prepare($playersRankingQuery);
    $stmtRanking->execute();
    $playersRanking = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    // 2. Consulta exclusiva para Administrador
    if ($userRole === 'Administrator') {
        $stmtUsers = $conn->prepare("
            SELECT 
                u.UserId, u.Username, u.Email, u.RoleId, r.RoleName, u.CreatedAt, u.LastLogin,
                p.Preferences, p.Statistics, p.Progress
            FROM Users u 
            JOIN Roles r ON u.RoleId = r.RoleId
            LEFT JOIN PlayerData p ON u.UserId = p.UserId
            ORDER BY u.CreatedAt DESC
        ");
        $stmtUsers->execute();
        $allUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hospital Virtual - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">🏥 Hospital Virtual</a>
        <span class="navbar-text text-white">
            Dr. <?php echo htmlspecialchars($_SESSION['Username']); ?> 
            <span class="badge bg-light text-dark ms-1"><?php echo htmlspecialchars($userRole); ?></span> | 
            <a href="logout.php" class="text-white text-decoration-none ms-2">Cerrar Sesión</a>
        </span>
    </div>
</nav>

<div class="container mt-4 mb-5">
    <h2>Resumen de tu cuenta</h2>
    <hr>
    
    <!-- TARJETAS DE ESTADÍSTICAS DEL JUGADOR LOGUEADO -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">📈 Progreso Actual</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Nivel:</strong> <?php echo $progress['currentLevel'] ?? 1; ?></li>
                        <li class="list-group-item"><strong>Presupuesto:</strong> $<?php echo number_format($progress['inGameCurrency'] ?? 0, 2); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">📊 Estadísticas Médicas</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Pacientes Curados:</strong> <?php echo $statistics['patientsCured'] ?? 0; ?></li>
                        <li class="list-group-item"><strong>Ingresos Históricos:</strong> $<?php echo number_format($statistics['totalMoneyEarned'] ?? 0, 2); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE POSICIONES GLOBAL (EXCLUSIVO ROL PLAYER) -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center fw-bold">
                    <span>🏆 Tabla de Posiciones - Jugadores (<?php echo count($playersRanking); ?>)</span>
                    <span class="badge bg-dark text-warning">Ranking General</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-striped table-hover mb-0 text-center align-middle">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th scope="col" style="width: 100px;">Posición</th>
                                    <th scope="col">Nombre del Doctor</th>
                                    <th scope="col">Nivel</th>
                                    <th scope="col">Pacientes Curados</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($playersRanking) > 0): ?>
                                    <?php foreach ($playersRanking as $index => $player): ?>
                                        <tr class="<?php echo ($player['Username'] === $_SESSION['Username']) ? 'table-success fw-bold' : ''; ?>">
                                            <td>
                                                <?php 
                                                    $rank = $index + 1;
                                                    if ($rank == 1) echo '<span class="badge bg-warning text-dark fs-6">🥇 1°</span>';
                                                    elseif ($rank == 2) echo '<span class="badge bg-secondary fs-6">🥈 2°</span>';
                                                    elseif ($rank == 3) echo '<span class="badge bg-danger fs-6">🥉 3°</span>';
                                                    else echo '<span class="fw-bold">#' . $rank . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($player['Username']); ?>
                                                <?php if ($player['Username'] === $_SESSION['Username']): ?>
                                                    <span class="badge bg-success ms-1">Tú</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">Nivel <?php echo $player['CurrentLevel'] ?? 1; ?></span>
                                            </td>
                                            <td>
                                                <?php echo number_format($player['CuredCount'] ?? 0); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-muted py-3">No hay jugadores registrados en la tabla de posiciones aún.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN SÓLO PARA ADMINISTRADORES: GESTIÓN DE USUARIOS -->
    <?php if ($userRole === 'Administrator'): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm border-danger">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">⚙️ Panel de Administración - Gestión de Usuarios</h5>
                        <span class="badge bg-light text-danger fw-bold"><?php echo count($allUsers); ?> Usuarios Totales</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Usuario / ID</th>
                                        <th>Correo Electrónico</th>
                                        <th>Rol</th>
                                        <th>Último Acceso</th>
                                        <th class="text-center">Descargar Datos (JSON)</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allUsers as $userItem): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($userItem['Username']); ?></strong><br>
                                                <small class="text-muted" style="font-size: 0.75rem;"><?php echo $userItem['UserId']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($userItem['Email']); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $userItem['RoleName'] === 'Administrator' ? 'bg-danger' : 
                                                        ($userItem['RoleName'] === 'Editor' ? 'bg-warning text-dark' : 'bg-primary'); 
                                                ?>">
                                                    <?php echo $userItem['RoleName']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $userItem['LastLogin'] ? date('d/m/Y H:i', strtotime($userItem['LastLogin'])) : 'Sin registro'; ?></td>
                                            
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-info" title="Descargar Preferencias"
                                                            data-json="<?php echo htmlspecialchars($userItem['Preferences'] ?? '{}', ENT_QUOTES); ?>"
                                                            onclick="downloadUserData('<?php echo htmlspecialchars($userItem['Username'], ENT_QUOTES); ?>_preferences.json', this)">
                                                        <i class="bi bi-gear-fill"></i> Prefs
                                                    </button>
                                                    <button class="btn btn-outline-success" title="Descargar Estadísticas"
                                                            data-json="<?php echo htmlspecialchars($userItem['Statistics'] ?? '{}', ENT_QUOTES); ?>"
                                                            onclick="downloadUserData('<?php echo htmlspecialchars($userItem['Username'], ENT_QUOTES); ?>_statistics.json', this)">
                                                        <i class="bi bi-bar-chart-fill"></i> Stats
                                                    </button>
                                                    <button class="btn btn-outline-warning text-dark" title="Descargar Progreso"
                                                            data-json="<?php echo htmlspecialchars($userItem['Progress'] ?? '{}', ENT_QUOTES); ?>"
                                                            onclick="downloadUserData('<?php echo htmlspecialchars($userItem['Username'], ENT_QUOTES); ?>_progress.json', this)">
                                                        <i class="bi bi-controller"></i> Progreso
                                                    </button>
                                                </div>
                                            </td>

                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="openEditModal(
                                                            '<?php echo $userItem['UserId']; ?>',
                                                            '<?php echo htmlspecialchars($userItem['Username'], ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars($userItem['Email'], ENT_QUOTES); ?>',
                                                            '<?php echo $userItem['RoleId']; ?>'
                                                        )">
                                                    <i class="bi bi-pencil-square"></i> Editar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE EDICIÓN DE USUARIO -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editUserForm" onsubmit="saveUserChanges(event)">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">✏️ Editar Usuario</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="modalAlert" class="alert d-none"></div>

                            <input type="hidden" id="editUserId" name="user_id">

                            <div class="mb-3">
                                <label class="form-label">Nombre de Usuario</label>
                                <input type="text" class="form-control" id="editUsername" name="username" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Rol de Usuario</label>
                                <select class="form-select" id="editRoleId" name="role_id" required>
                                    <option value="1">Player</option>
                                    <option value="2">Editor</option>
                                    <option value="3">Administrator</option>
                                </select>
                            </div>

                            <hr>
                            <h6 class="text-muted"><i class="bi bi-key-fill"></i> Reseteo de Contraseña</h6>
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="editNewPassword" name="new_password" placeholder="Dejar en blanco para no cambiar">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('editNewPassword', 'iconNewPass')">
                                        <i id="iconNewPass" class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
let editModalInstance = null;

function openEditModal(userId, username, email, roleId) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRoleId').value = roleId;
    document.getElementById('editNewPassword').value = '';
    
    document.getElementById('modalAlert').classList.add('d-none');

    if (!editModalInstance) {
        editModalInstance = new bootstrap.Modal(document.getElementById('editUserModal'));
    }
    editModalInstance.show();
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

async function saveUserChanges(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'admin_update_user');

    const alertBox = document.getElementById('modalAlert');

    try {
        const response = await fetch('auth_api.php', { method: 'POST', body: formData });
        const data = await response.json();

        alertBox.classList.remove('d-none', 'alert-danger', 'alert-success');

        if (data.success) {
            alertBox.classList.add('alert-success');
            alertBox.innerText = data.message;
            setTimeout(() => window.location.reload(), 1200);
        } else {
            alertBox.classList.add('alert-danger');
            alertBox.innerText = data.error;
        }
    } catch (error) {
        alertBox.classList.remove('d-none');
        alertBox.classList.add('alert-danger');
        alertBox.innerText = "Error al procesar la solicitud.";
    }
}

function downloadUserData(filename, buttonElement) {
    const rawJson = buttonElement.getAttribute('data-json');
    let formattedJson = rawJson;

    try {
        const parsedObject = JSON.parse(rawJson);
        formattedJson = JSON.stringify(parsedObject, null, 2);
    } catch (e) {}

    const blob = new Blob([formattedJson], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

</body>
</html>