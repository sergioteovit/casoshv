<?php
// Progreso del jugador con sesión iniciada y su decisión de aparecer o no en las posiciones.
// Solo muestra el título de los casos que el jugador ya intentó.

require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['UserId'])) {
    header('Location: acceso.php');
    exit();
}

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/estadisticas_comun.php';

header('Cache-Control: no-store');

$userId = $_SESSION['UserId'];

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hv_csrf_valido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('Solicitud no válida. Recarga la página.');
        }

        $stmt = $conn->prepare("
            INSERT INTO EstPerfiles (UserId, MostrarEnPosiciones) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE MostrarEnPosiciones = VALUES(MostrarEnPosiciones)
        ");
        $stmt->execute([$userId, ($_POST['mostrar'] ?? '') === '1' ? 1 : 0]);

        header('Location: mi_avance.php?guardado=1');
        exit();
    }

    $stmt = $conn->prepare("
        SELECT u.Username, r.RoleName,
               COALESCE(s.TotalPuntos, 0) AS TotalPuntos,
               COALESCE(s.TotalEstrellas, 0) AS TotalEstrellas,
               COALESCE(s.Bronce, 0) AS Bronce,
               COALESCE(s.Plata, 0) AS Plata,
               COALESCE(s.Oro, 0) AS Oro,
               COALESCE(s.Platino, 0) AS Platino,
               COALESCE(s.Diamante, 0) AS Diamante,
               COALESCE(s.RetosCompletados, 0) AS RetosCompletados,
               COALESCE(s.TotalInsignias, 0) AS TotalInsignias,
               s.PuntajeAlcanzadoEn,
               COALESCE(pf.MostrarEnPosiciones, 1) AS Mostrar,
               COALESCE(pf.ExcluirDePosiciones, 0) AS Excluir
        FROM Users u
        JOIN Roles r ON r.RoleId = u.RoleId
        LEFT JOIN EstResumenJugador s ON s.UserId = u.UserId
        LEFT JOIN EstPerfiles pf ON pf.UserId = u.UserId
        WHERE u.UserId = ?
    ");
    $stmt->execute([$userId]);
    $jugador = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$jugador) {
        // La cuenta se eliminó mientras la sesión seguía abierta
        header('Location: logout.php');
        exit();
    }

    $participa = $jugador['RoleName'] === 'Player' && !(int)$jugador['Excluir'];
    $mostrar = (bool)(int)$jugador['Mostrar'];

    $posicion = null;
    if ($participa && $mostrar && (int)$jugador['TotalPuntos'] > 0) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) + 1
            FROM EstResumenJugador s
            JOIN Users u ON u.UserId = s.UserId
            JOIN Roles ro ON ro.RoleId = u.RoleId AND ro.RoleName = 'Player'
            LEFT JOIN EstPerfiles pf ON pf.UserId = u.UserId
            WHERE COALESCE(pf.MostrarEnPosiciones, 1) = 1
              AND COALESCE(pf.ExcluirDePosiciones, 0) = 0
              AND (s.TotalPuntos > :puntos1
                   OR (s.TotalPuntos = :puntos2 AND s.TotalEstrellas > :estrellas1)
                   OR (s.TotalPuntos = :puntos3 AND s.TotalEstrellas = :estrellas2 AND s.PuntajeAlcanzadoEn < :fecha))
        ");
        $stmt->execute([
            ':puntos1'    => $jugador['TotalPuntos'],
            ':puntos2'    => $jugador['TotalPuntos'],
            ':puntos3'    => $jugador['TotalPuntos'],
            ':estrellas1' => $jugador['TotalEstrellas'],
            ':estrellas2' => $jugador['TotalEstrellas'],
            ':fecha'      => $jugador['PuntajeAlcanzadoEn'],
        ]);
        $posicion = (int)$stmt->fetchColumn();
    }

    $stmt = $conn->prepare("
        SELECT t.Titulo, t.Tipo, t.Sistema, r.MejorPuntaje, r.MejorEstrellas, r.Intentos, r.Victorias, r.ActualizadoEn
        FROM EstResultadosReto r
        JOIN EstRetos t ON t.IdReto = r.IdReto
        WHERE r.UserId = ?
        ORDER BY r.ActualizadoEn DESC
    ");
    $stmt->execute([$userId]);
    $retos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT i.Nombre, i.Descripcion, ij.ObtenidaEn
        FROM EstInsigniasJugador ij
        JOIN EstInsignias i ON i.IdInsignia = ij.IdInsignia
        WHERE ij.UserId = ?
        ORDER BY ij.ObtenidaEn DESC
    ");
    $stmt->execute([$userId]);
    $insignias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[mi_avance] ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar tu avance. Inténtalo más tarde.');
}

$tarjetas = [
    'Lugar general'     => $posicion !== null ? '#' . number_format($posicion) : '—',
    'Puntos'            => number_format((int)$jugador['TotalPuntos']),
    'Estrellas'         => number_format((int)$jugador['TotalEstrellas']),
    'Retos completados' => number_format((int)$jugador['RetosCompletados']),
    'Insignias'         => number_format((int)$jugador['TotalInsignias']),
];
$medallas = ['Bronce', 'Plata', 'Oro', 'Platino', 'Diamante'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Virtual - Mi avance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">🏥 Hospital Virtual</span>
        <span class="navbar-text text-white">
            <a href="posiciones.php" class="text-white text-decoration-none">Posiciones</a> |
            <a href="mis_grupos.php" class="text-white text-decoration-none">Grupos</a> |
            <a href="dashboard.php" class="text-white text-decoration-none">Panel</a> |
            <a href="logout.php" class="text-white text-decoration-none">Cerrar sesión</a>
        </span>
    </div>
</nav>

<main class="container mt-4 mb-5">
    <h1 class="h3">Mi avance</h1>
    <p class="text-muted"><?php echo h($jugador['Username']); ?></p>

    <?php if (isset($_GET['guardado'])): ?>
        <div class="alert alert-success" role="status">Tu preferencia quedó guardada.</div>
    <?php endif; ?>

    <div class="row row-cols-2 row-cols-md-5 g-3 mb-3">
        <?php foreach ($tarjetas as $nombre => $valor): ?>
            <div class="col">
                <div class="card shadow-sm h-100 text-center">
                    <div class="card-body">
                        <div class="h4 mb-0"><?php echo h($valor); ?></div>
                        <div class="text-muted small"><?php echo h($nombre); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-muted small">
        <?php foreach ($medallas as $i => $medalla): ?>
            <?php echo $i > 0 ? '·' : ''; ?> <?php echo h($medalla); ?>: <strong><?php echo (int)$jugador[$medalla]; ?></strong>
        <?php endforeach; ?>
    </p>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Tablas de posiciones</h2>
            <?php if (!$participa): ?>
                <p class="mb-0 text-muted">Esta cuenta no participa en las tablas de posiciones.</p>
            <?php else: ?>
                <p>
                    <?php echo $mostrar
                        ? 'Tu nombre de usuario aparece en las tablas públicas de posiciones.'
                        : 'Tu nombre de usuario no aparece en las tablas públicas de posiciones.'; ?>
                    Tus resultados se guardan igual.
                </p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h(hv_token_csrf()); ?>">
                    <input type="hidden" name="mostrar" value="<?php echo $mostrar ? '0' : '1'; ?>">
                    <button type="submit" class="btn btn-outline-primary">
                        <?php echo $mostrar ? 'No aparecer en las posiciones' : 'Aparecer en las posiciones'; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header fw-bold">Mis retos</div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Reto</th>
                        <th>Sistema</th>
                        <th class="text-end">Mejor puntaje</th>
                        <th>Nivel</th>
                        <th class="text-end">Intentos</th>
                        <th class="text-end">Victorias</th>
                        <th>Última actividad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$retos): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Todavía no tienes retos registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($retos as $reto): ?>
                        <tr>
                            <td>
                                <?php echo h($reto['Titulo']); ?>
                                <span class="badge text-bg-secondary"><?php echo $reto['Tipo'] === 'caso' ? 'Caso' : 'Minijuego'; ?></span>
                            </td>
                            <td><?php echo h(est_nombre_sistema($reto['Sistema'])); ?></td>
                            <td class="text-end"><?php echo number_format((int)$reto['MejorPuntaje']); ?></td>
                            <td><?php echo h(est_nombre_nivel($reto['MejorEstrellas']) ?? 'Sin estrellas'); ?></td>
                            <td class="text-end"><?php echo number_format((int)$reto['Intentos']); ?></td>
                            <td class="text-end"><?php echo number_format((int)$reto['Victorias']); ?></td>
                            <td><?php echo h(fecha_corta($reto['ActualizadoEn'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-bold">Mis insignias</div>
        <ul class="list-group list-group-flush">
            <?php if (!$insignias): ?>
                <li class="list-group-item text-muted">Todavía no tienes insignias.</li>
            <?php endif; ?>
            <?php foreach ($insignias as $insignia): ?>
                <li class="list-group-item">
                    <strong><?php echo h($insignia['Nombre']); ?></strong>
                    <span class="text-muted small">· <?php echo h(fecha_corta($insignia['ObtenidaEn'])); ?></span>
                    <div class="small"><?php echo h($insignia['Descripcion']); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</main>
</body>
</html>
