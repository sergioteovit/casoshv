<?php
// Panel de inicio después de iniciar sesión: resumen del jugador, accesos directos y,
// para administradores, la gestión de cuentas (rol, correo, contraseña y datos del juego).
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['UserId'])) {
    header("Location: acceso.php");
    exit();
}

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/plantilla.php';

header('Cache-Control: no-store');

$userId = $_SESSION['UserId'];
$allUsers = [];
$totalUsers = 0;
$totalPages = 1;
$currentPage = 1;

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // El rol y el avance se leen de la base: los de la sesión son los del momento del login
    $stmt = $conn->prepare("
        SELECT u.Username, r.RoleName, p.Progress,
               COALESCE(s.TotalPuntos, 0) AS TotalPuntos,
               COALESCE(s.TotalEstrellas, 0) AS TotalEstrellas,
               COALESCE(s.RetosCompletados, 0) AS RetosCompletados,
               COALESCE(s.TotalInsignias, 0) AS TotalInsignias
        FROM Users u
        JOIN Roles r ON r.RoleId = u.RoleId
        LEFT JOIN PlayerData p ON p.UserId = u.UserId
        LEFT JOIN EstResumenJugador s ON s.UserId = u.UserId
        WHERE u.UserId = ?
    ");
    $stmt->execute([$userId]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        header('Location: logout.php');
        exit();
    }

    $userRole = $cuenta['RoleName'];
    $progress = json_decode((string)$cuenta['Progress'], true);
    if (!is_array($progress)) {
        $progress = [];
    }

    // Gestión de cuentas, paginada a 10 registros, solo para administradores
    if ($userRole === 'Administrator') {
        $recordsPerPage = 10;
        $currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

        $totalUsers = (int)$conn->query("SELECT COUNT(*) FROM Users")->fetchColumn();
        $totalPages = max(1, (int)ceil($totalUsers / $recordsPerPage));
        $currentPage = min($currentPage, $totalPages);
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
} catch (Throwable $e) {
    error_log('[dashboard] ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar el panel. Inténtalo más tarde.');
}

$nombreJugador = trim((string)($progress['playerName'] ?? ''));
$accesos = [
    ['mi_avance.php', 'Mi avance', 'Tus retos, estrellas, insignias y tu lugar en la tabla.', 'bi-graph-up-arrow', ''],
    ['index.php', 'Posiciones', 'Las tablas generales, por sistema y del mes.', 'bi-trophy', 'hv-icono-oro'],
    ['mis_grupos.php', 'Grupos', $userRole === 'Player' ? 'Únete al grupo de tu clase con un código.' : 'Crea grupos y sigue el avance de tus alumnos.', 'bi-people', 'hv-icono-turquesa'],
];
if ($userRole === 'Administrator') {
    $accesos[] = ['administracion.php', 'Administración', 'Estadísticas totales, jugadores, descartes y bitácora.', 'bi-shield-lock', 'hv-icono-coral'];
}

hv_encabezado('Mi panel', 'panel');
hv_cabecera('¡Hola, ' . $cuenta['Username'] . '!', 'Este es tu panel de Hospital Virtual FacMed · Desafío Clínico.', 'bi-grid');
?>
<main class="container position-relative">
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <section class="hv-tarjeta p-4 h-100 hv-aparecer">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="hv-avatar" style="width:3.5rem;height:3.5rem;font-size:1.2rem"><?php echo h(hv_iniciales($cuenta['Username'])); ?></span>
                    <div>
                        <div class="hv-titulo fs-5"><?php echo h($cuenta['Username']); ?></div>
                        <span class="hv-chip"><?php echo h($userRole); ?></span>
                    </div>
                </div>
                <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
                    <li><i class="bi bi-person me-2 text-primary"></i><strong>Personaje:</strong> <?php echo $nombreJugador !== '' ? h($nombreJugador) : '<span class="text-muted">sin nombre</span>'; ?></li>
                    <li><i class="bi bi-gender-ambiguous me-2 text-primary"></i><strong>Género:</strong> <?php echo h($progress['gender'] ?? '—'); ?></li>
                    <li><i class="bi bi-bar-chart-steps me-2 text-primary"></i><strong>Nivel en el juego:</strong> <?php echo (int)($progress['playerLevel'] ?? 0); ?></li>
                </ul>
            </section>
        </div>
        <div class="col-lg-8">
            <div class="row row-cols-2 g-3 h-100">
                <?php
                $resumen = [
                    ['Puntos', $cuenta['TotalPuntos'], 'bi-lightning-charge', ''],
                    ['Estrellas', $cuenta['TotalEstrellas'], 'bi-star', 'hv-icono-oro'],
                    ['Retos completados', $cuenta['RetosCompletados'], 'bi-flag', 'hv-icono-turquesa'],
                    ['Insignias', $cuenta['TotalInsignias'], 'bi-award', 'hv-icono-coral'],
                ];
                ?>
                <?php foreach ($resumen as $d): ?>
                    <div class="col">
                        <div class="hv-tarjeta hv-dato h-100 hv-aparecer hv-aparecer-2">
                            <span class="hv-icono <?php echo h($d[3]); ?>"><i class="bi <?php echo h($d[2]); ?>"></i></span>
                            <div>
                                <div class="hv-numero"><?php echo number_format((int)$d[1]); ?></div>
                                <div class="hv-rotulo"><?php echo h($d[0]); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">¿A dónde vamos?</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-<?php echo count($accesos); ?> g-3">
        <?php foreach ($accesos as $a): ?>
            <div class="col">
                <a href="<?php echo h($a[0]); ?>" class="hv-tarjeta hv-tarjeta-animada hv-paso d-block text-decoration-none text-reset">
                    <span class="hv-icono <?php echo h($a[4]); ?>"><i class="bi <?php echo h($a[3]); ?>"></i></span>
                    <h3 class="h5"><?php echo h($a[1]); ?> <i class="bi bi-arrow-right-short"></i></h3>
                    <p class="text-muted small mb-0"><?php echo h($a[2]); ?></p>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($userRole === 'Administrator'): ?>
        <section class="hv-tarjeta mt-5">
            <div class="hv-tarjeta-encabezado d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-person-gear me-2 text-primary"></i>Gestión de usuarios</span>
                <span class="hv-chip"><?php echo number_format($totalUsers); ?> usuarios en total</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle hv-tabla">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Correo electrónico</th>
                            <th>Rol</th>
                            <th>Último acceso</th>
                            <th class="text-center">Descargar datos (JSON)</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$allUsers): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No se encontraron usuarios en esta página.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($allUsers as $userItem): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="hv-avatar"><?php echo h(hv_iniciales($userItem['Username'])); ?></span>
                                        <div>
                                            <div class="fw-semibold"><?php echo h($userItem['Username']); ?></div>
                                            <div class="text-muted" style="font-size: .72rem"><?php echo h($userItem['UserId']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo h($userItem['Email']); ?></td>
                                <td>
                                    <span class="hv-chip<?php echo $userItem['RoleName'] === 'Revisor' ? ' hv-chip-turquesa' : ''; ?>"<?php echo $userItem['RoleName'] === 'Administrator' ? ' style="background:#ffece8;color:#c2412d"' : ''; ?>><?php echo h($userItem['RoleName']); ?></span>
                                </td>
                                <td class="text-muted small"><?php echo $userItem['LastLogin'] ? h(date('d/m/Y H:i', strtotime($userItem['LastLogin']))) : 'Sin registro'; ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-secondary" title="Descargar preferencias"
                                                data-json="<?php echo h($userItem['Preferences'] ?? '{}'); ?>"
                                                data-nombre="<?php echo h($userItem['Username']); ?>"
                                                onclick="downloadUserData(this.dataset.nombre + '_preferences.json', this)">
                                            <i class="bi bi-gear-fill"></i> Prefs
                                        </button>
                                        <button class="btn btn-outline-secondary" title="Descargar estadísticas"
                                                data-json="<?php echo h($userItem['Statistics'] ?? '{}'); ?>"
                                                data-nombre="<?php echo h($userItem['Username']); ?>"
                                                onclick="downloadUserData(this.dataset.nombre + '_statistics.json', this)">
                                            <i class="bi bi-bar-chart-fill"></i> Stats
                                        </button>
                                        <button class="btn btn-outline-secondary" title="Descargar progreso"
                                                data-json="<?php echo h($userItem['Progress'] ?? '{}'); ?>"
                                                data-nombre="<?php echo h($userItem['Username']); ?>"
                                                onclick="downloadUserData(this.dataset.nombre + '_progress.json', this)">
                                            <i class="bi bi-controller"></i> Progreso
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" title="Editar usuario"
                                                data-user-id="<?php echo h($userItem['UserId']); ?>"
                                                data-username="<?php echo h($userItem['Username']); ?>"
                                                data-email="<?php echo h($userItem['Email']); ?>"
                                                data-role-id="<?php echo (int)$userItem['RoleId']; ?>"
                                                onclick="openEditModal(this.dataset.userId, this.dataset.username, this.dataset.email, this.dataset.roleId)">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </button>
                                        <button class="btn btn-outline-danger" title="Eliminar usuario"
                                                data-user-id="<?php echo h($userItem['UserId']); ?>"
                                                data-username="<?php echo h($userItem['Username']); ?>"
                                                onclick="deleteUser(this.dataset.userId, this.dataset.username)">
                                            <i class="bi bi-trash-fill"></i> Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
                <div class="text-muted small">Página <strong><?php echo $currentPage; ?></strong> de <strong><?php echo $totalPages; ?></strong></div>
                <nav aria-label="Páginas de usuarios">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        </section>

        <!-- Ventana para editar una cuenta -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="tituloEditar" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0" style="border-radius: var(--hv-radio)">
                    <form id="editUserForm" onsubmit="saveUserChanges(event)">
                        <div class="modal-header text-white" style="background: linear-gradient(135deg, var(--hv-morado-oscuro), var(--hv-morado)); border-radius: var(--hv-radio) var(--hv-radio) 0 0">
                            <h5 class="modal-title" id="tituloEditar"><i class="bi bi-pencil-square me-2"></i>Editar usuario</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div id="modalAlert" class="alert d-none"></div>
                            <input type="hidden" id="editUserId" name="user_id">

                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="editUsername">Nombre de usuario</label>
                                <input type="text" class="form-control" id="editUsername" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="editEmail">Correo electrónico</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small" for="editRoleId">Rol</label>
                                <select class="form-select" id="editRoleId" name="role_id" required>
                                    <option value="1">Player</option>
                                    <option value="2">Revisor</option>
                                    <option value="3">Administrator</option>
                                </select>
                            </div>

                            <hr>
                            <h6 class="text-muted"><i class="bi bi-key-fill me-1"></i>Restablecer la contraseña</h6>
                            <div class="mb-1">
                                <label class="form-label fw-bold small" for="editNewPassword">Nueva contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="editNewPassword" name="new_password" placeholder="Dejar en blanco para no cambiar">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('editNewPassword', 'iconNewPass')" aria-label="Mostrar u ocultar la contraseña">
                                        <i id="iconNewPass" class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php hv_pie(); ?>
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
    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

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
    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
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
