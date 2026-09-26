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
$userRankData = null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Consulta para la Tabla de Posiciones Global (Top 10 usuarios con rol 'Player')
    $playersRankingQuery = "
        SELECT 
            u.Username, 
            CAST(JSON_EXTRACT(p.Statistics, '$.totalPoints') AS UNSIGNED) AS CuredCount,
            CAST(JSON_EXTRACT(p.Progress, '$.playerLevel') AS UNSIGNED) AS CurrentLevel
        FROM Users u
        JOIN Roles r ON u.RoleId = r.RoleId
        LEFT JOIN PlayerData p ON u.UserId = p.UserId
        WHERE r.RoleName = 'Player'
        ORDER BY CuredCount DESC
        LIMIT 10
    ";
    $stmtRanking = $conn->prepare($playersRankingQuery);
    $stmtRanking->execute();
    $playersRanking = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    // 2. Consulta para obtener el Ranking Individual del usuario logueado respecto a TODOS los jugadores
    $stmtUserRank = $conn->prepare("
        SELECT 
            u.Username, 
            COALESCE(CAST(JSON_EXTRACT(p.Statistics, '$.totalPoints') AS UNSIGNED), 0) AS CuredCount,
            COALESCE(CAST(JSON_EXTRACT(p.Progress, '$.playerLevel') AS UNSIGNED), 1) AS CurrentLevel,
            (
                SELECT COUNT(*) + 1 
                FROM Users u2
                JOIN Roles r2 ON u2.RoleId = r2.RoleId
                LEFT JOIN PlayerData p2 ON u2.UserId = p2.UserId
                WHERE r2.RoleName = 'Player' 
                  AND COALESCE(CAST(JSON_EXTRACT(p2.Statistics, '$.totalPoints') AS UNSIGNED), 0) > COALESCE(CAST(JSON_EXTRACT(p.Statistics, '$.totalPoints') AS UNSIGNED), 0)
            ) AS RankPosition
        FROM Users u
        LEFT JOIN PlayerData p ON u.UserId = p.UserId
        WHERE u.UserId = :userId
    ");
    $stmtUserRank->execute([':userId' => $_SESSION['UserId']]);
    $userRankData = $stmtUserRank->fetch(PDO::FETCH_ASSOC);

    // 3. Consulta exclusiva para Administrador (Paginada a 10 registros por página)
    if ($userRole === 'Administrator') {
        $recordsPerPage = 10;
        
        $currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        
        $totalStmt = $conn->query("SELECT COUNT(*) FROM Users");
        $totalUsers = $totalStmt->fetchColumn();
        
        $totalPages = ceil($totalUsers / $recordsPerPage);
        if ($totalPages < 1) $totalPages = 1;
        
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        
        $offset = ($currentPage - 1) * $recordsPerPage;
        
        $stmtUsers = $conn->prepare("
            SELECT 
                u.UserId, u.Username, u.Email, u.RoleId, r.RoleName, u.CreatedAt, u.LastLogin,
                p.Preferences, p.Statistics, p.Progress
            FROM Users u 
            JOIN Roles r ON u.RoleId = r.RoleId
            LEFT JOIN PlayerData p ON u.UserId = p.UserId
            ORDER BY u.CreatedAt DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmtUsers->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
        $stmtUsers->bindValue(':offset', $offset, PDO::PARAM_INT);
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
    <h2>Panel del Jugador</h2>
    <hr>
    
    <!-- NUEVA TABLA: RANKING ACTUAL DEL JUGADOR LOGUEADO RESPECTO A TODOS -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center fw-bold">
                    <span>🎯 Tu Posición Actual en el Ranking</span>
                    <span class="badge bg-dark text-warning">Mi Ranking Global</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 text-center align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col" style="width: 100px;">Posición</th>
                                    <th scope="col">Jugador</th>
                                    <th scope="col">Nivel</th>
                                    <th scope="col">Puntos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($userRankData): ?>
                                    <tr class="table-success fw-bold">
                                        <td>
                                            <?php 
                                                $myRank = $userRankData['RankPosition'];
                                                if ($myRank == 1) echo '<span class="badge bg-warning text-dark fs-6">🥇 1°</span>';
                                                elseif ($myRank == 2) echo '<span class="badge bg-secondary fs-6">🥈 2°</span>';
                                                elseif ($myRank == 3) echo '<span class="badge bg-danger fs-6">🥉 3°</span>';
                                                else echo '<span class="fw-bold">#' . $myRank . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($userRankData['Username']); ?>
                                            <span class="badge bg-success ms-1">Tú</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark">Nivel <?php echo $userRankData['CurrentLevel']; ?></span>
                                        </td>
                                        <td>
                                            <?php echo number_format($userRankData['CuredCount']); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-muted py-3">No hay información de posición disponible para tu usuario.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- TABLA DE POSICIONES GLOBAL (TOP 10 JUGADORES) -->
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
                                    <th scope="col">Jugador</th>
                                    <th scope="col">Nivel</th>
                                    <th scope="col">Puntos</th>
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
    
    <!-- TARJETAS DE ESTADÍSTICAS DEL JUGADOR LOGUEADO -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">📈 Jugador</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Nombre del jugador:</strong> <?php echo $progress['playerName'] ?? ""; ?></li>
                        <li class="list-group-item"><strong>Género:</strong> <?php echo $progress['gender'] ?? ""; ?></li>
                        <li class="list-group-item"><strong>Nivel:</strong> <?php echo $progress['playerLevel'] ?? 0; ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">📊 Estadísticas</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>&#10024; Puntos totales:</strong> <?php echo $statistics['totalPoints'] ?? 0; ?></li>
                        <li class="list-group-item"><strong><span style="color: #bf8970;">★</span> Estrellas de bronze:</strong> <?php echo $statistics['bronzeStars'] ?? 0; ?></li>
                        <li class="list-group-item"><strong><span style="color: #E3E4E5;">★</span> Estrellas de plata:</strong> <?php echo $statistics['silverStars'] ?? 0; ?></li>
                        <li class="list-group-item"><strong><span style="color: #efbf04;">★</span> Estrellas de oro:</strong> <?php echo $statistics['goldStars'] ?? 0; ?></li>
                        <li class="list-group-item"><strong><span style="color: #e5e4e2;">★</span> Estrellas de platino:</strong> <?php echo $statistics['platinumStars'] ?? 0; ?></li>
                        <li class="list-group-item"><strong><span style="color: #a4f4f9;">★</span> Estrellas de diamante:</strong> <?php echo $statistics['diamondStars'] ?? 0; ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">📈 Insignias</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Insignia 1</strong> </li>
                        <li class="list-group-item"><strong>Insignia 2</strong> </li>
                        <li class="list-group-item"><strong>Insignia 3</strong> </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">📊 Items</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">1</li>
                        <li class="list-group-item">2</li>
                        <li class="list-group-item">3</li>
                        <li class="list-group-item">4</li>
                        <li class="list-group-item">5</li>
                        <li class="list-group-item">6</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN SÓLO PARA ADMINISTRADORES: GESTIÓN DE USUARIOS CON PAGINACIÓN -->
    <?php if ($userRole === 'Administrator'): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm border-danger">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">⚙️ Panel de Administración - Gestión de Usuarios</h5>
                        <span class="badge bg-light text-danger fw-bold"><?php echo $totalUsers; ?> Usuarios Totales</span>
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
                                    <?php if (count($allUsers) > 0): ?>
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
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button class="btn btn-outline-primary" title="Editar Usuario"
                                                                onclick="openEditModal(
                                                                    '<?php echo $userItem['UserId']; ?>',
                                                                    '<?php echo htmlspecialchars($userItem['Username'], ENT_QUOTES); ?>',
                                                                    '<?php echo htmlspecialchars($userItem['Email'], ENT_QUOTES); ?>',
                                                                    '<?php echo $userItem['RoleId']; ?>'
                                                                )">
                                                            <i class="bi bi-pencil-square"></i> Editar
                                                        </button>
                                                        <button class="btn btn-outline-danger" title="Eliminar Usuario"
                                                                onclick="deleteUser('<?php echo $userItem['UserId']; ?>', '<?php echo htmlspecialchars($userItem['Username'], ENT_QUOTES); ?>')">
                                                            <i class="bi bi-trash-fill"></i> Eliminar
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted">No se encontraron usuarios en esta página.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- BARRA DE PAGINACIÓN BOOTSTRAP -->
                    <div class="card-footer bg-white py-3">
                        <div class="row align-items-center">
                            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0 text-muted small">
                                Mostrando página <strong class="text-dark"><?php echo $currentPage; ?></strong> de <strong class="text-dark"><?php echo $totalPages; ?></strong>
                            </div>

                            <div class="col-md-6">
                                <nav aria-label="Navegación de usuarios">
                                    <ul class="pagination pagination-sm justify-content-center justify-content-md-end mb-0">
                                        
                                        <li class="page-item <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>">
                                                <i class="bi bi-chevron-left"></i> Anterior
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>">
                                                Siguiente <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>

                                    </ul>
                                </nav>
                            </div>
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

async function deleteUser(userId, username) {
    if (!confirm(`¿Estás seguro de que deseas eliminar al usuario "${username}"? Esta acción no se puede deshacer.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'admin_delete_user');
    formData.append('user_id', userId);

    try {
        const response = await fetch('auth_api.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.success) {
            alert(data.message || 'Usuario eliminado correctamente.');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar el usuario.'));
        }
    } catch (error) {
        alert("Error de conexión al intentar eliminar el usuario.");
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