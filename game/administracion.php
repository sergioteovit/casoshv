<?php
// Administración de las estadísticas: resumen, jugadores, datos descartados y bitácora.
// Solo para administradores. La gestión de cuentas (rol, correo, contraseña) sigue en dashboard.php.

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
$secciones = [
    'resumen'    => 'Resumen',
    'jugadores'  => 'Jugadores',
    'descartes'  => 'Datos descartados',
    'bitacora'   => 'Bitácora',
];
$seccion = isset($secciones[$_GET['seccion'] ?? '']) ? $_GET['seccion'] : 'resumen';

// Las estadísticas de uso no cuentan las cuentas excluidas de las posiciones (cuentas de prueba)
$sinExcluidas = "NOT EXISTS (SELECT 1 FROM EstPerfiles pf WHERE pf.UserId = %s AND pf.ExcluirDePosiciones = 1)";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("
        SELECT r.RoleName
        FROM Users u
        JOIN Roles r ON r.RoleId = u.RoleId
        WHERE u.UserId = ?
    ");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() !== 'Administrator') {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hv_csrf_valido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('Solicitud no válida. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';

        if ($accion === 'excluir') {
            $objetivo = (string)($_POST['user_id'] ?? '');
            $excluir = ($_POST['valor'] ?? '') === '1' ? 1 : 0;

            $stmt = $conn->prepare("SELECT Username FROM Users WHERE UserId = ?");
            $stmt->execute([$objetivo]);
            $nombre = $stmt->fetchColumn();

            if ($nombre !== false) {
                $stmt = $conn->prepare("
                    INSERT INTO EstPerfiles (UserId, ExcluirDePosiciones) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE ExcluirDePosiciones = VALUES(ExcluirDePosiciones)
                ");
                $stmt->execute([$objetivo, $excluir]);
                est_bitacora($conn, $userId, $excluir ? 'excluir_de_posiciones' : 'incluir_en_posiciones', 'usuario', $objetivo, $nombre);
            }

            $q = (string)($_POST['q'] ?? '');
            header('Location: administracion.php?seccion=jugadores' . ($q !== '' ? '&q=' . urlencode($q) : ''));
            exit();
        }

        if ($accion === 'revisar_descarte') {
            $descarteId = (int)($_POST['descarte'] ?? 0);
            $stmt = $conn->prepare("UPDATE EstDatosDescartados SET Revisado = 1 WHERE DescarteId = ? AND Revisado = 0");
            $stmt->execute([$descarteId]);
            if ($stmt->rowCount() > 0) {
                est_bitacora($conn, $userId, 'revisar_descarte', 'descarte', $descarteId, '');
            }
            header('Location: administracion.php?seccion=descartes');
            exit();
        }

        http_response_code(403);
        exit('Acción no permitida.');
    }

    $datos = [];

    if ($seccion === 'resumen') {
        $datos['roles'] = $conn->query("
            SELECT r.RoleName, COUNT(u.UserId) AS Cuentas
            FROM Roles r
            LEFT JOIN Users u ON u.RoleId = r.RoleId
            GROUP BY r.RoleId, r.RoleName
            ORDER BY r.RoleId
        ")->fetchAll(PDO::FETCH_ASSOC);

        $datos['totales'] = $conn->query("
            SELECT
                (SELECT COUNT(*) FROM Users WHERE LastLogin >= NOW() - INTERVAL 7 DAY) AS Activos7,
                (SELECT COUNT(*) FROM Users WHERE LastLogin >= NOW() - INTERVAL 30 DAY) AS Activos30,
                (SELECT COUNT(DISTINCT r.UserId) FROM EstResultadosReto r WHERE " . sprintf($sinExcluidas, 'r.UserId') . ") AS ConResultados,
                (SELECT COUNT(*) FROM EstMejoras m WHERE m.Fecha >= DATE_FORMAT(NOW(), '%Y-%m-01') AND " . sprintf($sinExcluidas, 'm.UserId') . ") AS MejorasMes,
                (SELECT COUNT(*) FROM EstInsigniasJugador ij WHERE " . sprintf($sinExcluidas, 'ij.UserId') . ") AS Insignias,
                (SELECT COUNT(*) FROM EstGrupos WHERE Activo = 1) AS GruposActivos,
                (SELECT COUNT(*) FROM EstDatosDescartados WHERE Revisado = 0) AS DescartesPendientes,
                (SELECT COUNT(*) FROM EstPerfiles WHERE ExcluirDePosiciones = 1) AS Excluidas
        ")->fetch(PDO::FETCH_ASSOC);

        $datos['retos'] = $conn->query("
            SELECT t.Titulo, t.Tipo, t.Sistema,
                   COUNT(*) AS Jugadores,
                   SUM(r.Intentos) AS Intentos,
                   SUM(r.Victorias) AS Victorias,
                   ROUND(AVG(r.MejorPuntaje)) AS PromedioPuntaje,
                   ROUND(AVG(r.MejorEstrellas), 1) AS PromedioEstrellas
            FROM EstResultadosReto r
            JOIN EstRetos t ON t.IdReto = r.IdReto
            WHERE " . sprintf($sinExcluidas, 'r.UserId') . "
            GROUP BY t.IdReto, t.Titulo, t.Tipo, t.Sistema
            ORDER BY Jugadores DESC, Intentos DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($seccion === 'jugadores') {
        $busqueda = trim((string)($_GET['q'] ?? ''));
        $patron = '%' . addcslashes($busqueda, '%_\\') . '%';

        // "Puntos anteriores" es el total que guardaba el juego antes de las tablas nuevas
        $stmt = $conn->prepare("
            SELECT u.UserId, u.Username, u.Email, r.RoleName, u.LastLogin,
                   COALESCE(s.TotalPuntos, 0) AS TotalPuntos,
                   COALESCE(s.TotalEstrellas, 0) AS TotalEstrellas,
                   COALESCE(s.RetosCompletados, 0) AS RetosCompletados,
                   COALESCE(s.TotalInsignias, 0) AS TotalInsignias,
                   COALESCE(pf.ExcluirDePosiciones, 0) AS Excluir,
                   COALESCE(pf.MostrarEnPosiciones, 1) AS Mostrar,
                   CAST(JSON_UNQUOTE(JSON_EXTRACT(p.Statistics, '$.totalPoints')) AS SIGNED) AS PuntosAnteriores
            FROM Users u
            JOIN Roles r ON r.RoleId = u.RoleId
            LEFT JOIN EstResumenJugador s ON s.UserId = u.UserId
            LEFT JOIN EstPerfiles pf ON pf.UserId = u.UserId
            LEFT JOIN PlayerData p ON p.UserId = u.UserId
            WHERE ? = '' OR u.Username LIKE ? OR u.Email LIKE ?
            ORDER BY TotalPuntos DESC, u.Username
            LIMIT 200
        ");
        $stmt->execute([$busqueda, $patron, $patron]);
        $datos['jugadores'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $datos['busqueda'] = $busqueda;
    }

    if ($seccion === 'descartes') {
        $datos['descartes'] = $conn->query("
            SELECT d.DescarteId, d.IdReto, d.Motivo, d.Detalle, d.Revisado, d.Fecha, u.Username
            FROM EstDatosDescartados d
            LEFT JOIN Users u ON u.UserId = d.UserId
            ORDER BY d.Revisado, d.Fecha DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($seccion === 'bitacora') {
        $datos['bitacora'] = $conn->query("
            SELECT b.Fecha, b.Accion, b.ObjetivoTipo, b.ObjetivoId, b.Detalle, b.Ip, u.Username
            FROM EstBitacora b
            LEFT JOIN Users u ON u.UserId = b.ActorId
            ORDER BY b.Fecha DESC, b.BitacoraId DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('[administracion] ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar la administración. Inténtalo más tarde.');
}

$csrf = h(hv_token_csrf());

function fecha_hora($fecha)
{
    return $fecha ? date('d/m/Y H:i', strtotime($fecha)) : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Virtual - Administración</title>
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
    <h1 class="h3">Administración</h1>

    <ul class="nav nav-tabs my-3">
        <?php foreach ($secciones as $clave => $nombre): ?>
            <li class="nav-item">
                <a class="nav-link<?php echo $clave === $seccion ? ' active' : ''; ?>" href="administracion.php?seccion=<?php echo $clave; ?>"><?php echo h($nombre); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($seccion === 'resumen'): ?>
        <?php
        $t = $datos['totales'];
        $tarjetas = [
            'Activos en 7 días'          => $t['Activos7'],
            'Activos en 30 días'         => $t['Activos30'],
            'Jugadores con resultados'   => $t['ConResultados'],
            'Mejoras este mes'           => $t['MejorasMes'],
            'Insignias otorgadas'        => $t['Insignias'],
            'Grupos activos'             => $t['GruposActivos'],
            'Descartes por revisar'      => $t['DescartesPendientes'],
            'Cuentas excluidas'          => $t['Excluidas'],
        ];
        ?>
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
            <?php foreach ($tarjetas as $nombre => $valor): ?>
                <div class="col">
                    <div class="card shadow-sm h-100 text-center">
                        <div class="card-body">
                            <div class="h4 mb-0"><?php echo number_format((int)$valor); ?></div>
                            <div class="text-muted small"><?php echo h($nombre); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-muted small">
            Cuentas por rol:
            <?php foreach ($datos['roles'] as $i => $fila): ?>
                <?php echo $i > 0 ? '·' : ''; ?> <?php echo h($fila['RoleName']); ?> <strong><?php echo (int)$fila['Cuentas']; ?></strong>
            <?php endforeach; ?>
            <br>Los resultados, mejoras, insignias y retos no incluyen las cuentas excluidas de las posiciones.
        </p>

        <div class="card shadow-sm">
            <div class="card-header fw-bold">Retos más jugados</div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Reto</th>
                            <th>Sistema</th>
                            <th class="text-end">Jugadores</th>
                            <th class="text-end">Intentos</th>
                            <th class="text-end">Victorias</th>
                            <th class="text-end">Puntaje medio</th>
                            <th class="text-end">Estrellas medias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$datos['retos']): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay resultados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($datos['retos'] as $reto): ?>
                            <tr>
                                <td>
                                    <?php echo h($reto['Titulo']); ?>
                                    <span class="badge text-bg-secondary"><?php echo $reto['Tipo'] === 'caso' ? 'Caso' : 'Minijuego'; ?></span>
                                </td>
                                <td><?php echo h(est_nombre_sistema($reto['Sistema'])); ?></td>
                                <td class="text-end"><?php echo number_format((int)$reto['Jugadores']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$reto['Intentos']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$reto['Victorias']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$reto['PromedioPuntaje']); ?></td>
                                <td class="text-end"><?php echo h($reto['PromedioEstrellas']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($seccion === 'jugadores'): ?>
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="seccion" value="jugadores">
            <div class="col-sm-8 col-md-5">
                <label for="q" class="visually-hidden">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" placeholder="Buscar por usuario o correo" value="<?php echo h($datos['busqueda']); ?>">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">Buscar</button></div>
        </form>

        <p class="text-muted small">"Puntos anteriores" es el total que guardaba el juego antes de las tablas nuevas; no cuenta en las posiciones. Se muestran hasta 200 cuentas.</p>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th class="text-end">Puntos</th>
                            <th class="text-end">Estrellas</th>
                            <th class="text-end">Retos</th>
                            <th class="text-end">Insignias</th>
                            <th class="text-end">Puntos anteriores</th>
                            <th>Último acceso</th>
                            <th>Posiciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$datos['jugadores']): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">No hay cuentas que coincidan.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($datos['jugadores'] as $j): ?>
                            <?php $diferencia = (int)$j['PuntosAnteriores'] - (int)$j['TotalPuntos']; ?>
                            <tr>
                                <td>
                                    <?php echo h($j['Username']); ?>
                                    <div class="small text-muted"><?php echo h($j['Email']); ?></div>
                                </td>
                                <td><?php echo h($j['RoleName']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$j['TotalPuntos']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$j['TotalEstrellas']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$j['RetosCompletados']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$j['TotalInsignias']); ?></td>
                                <td class="text-end<?php echo $diferencia > 0 ? ' text-danger fw-bold' : ''; ?>"><?php echo number_format((int)$j['PuntosAnteriores']); ?></td>
                                <td><?php echo h(fecha_hora($j['LastLogin'])); ?></td>
                                <td>
                                    <?php if ($j['RoleName'] !== 'Player'): ?>
                                        <span class="text-muted small">No participa</span>
                                    <?php else: ?>
                                        <div class="small mb-1">
                                            <?php
                                            if ((int)$j['Excluir']) {
                                                echo 'Excluida';
                                            } elseif (!(int)$j['Mostrar']) {
                                                echo 'Oculta por el jugador';
                                            } else {
                                                echo 'Aparece';
                                            }
                                            ?>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="accion" value="excluir">
                                            <input type="hidden" name="user_id" value="<?php echo h($j['UserId']); ?>">
                                            <input type="hidden" name="valor" value="<?php echo (int)$j['Excluir'] ? '0' : '1'; ?>">
                                            <input type="hidden" name="q" value="<?php echo h($datos['busqueda']); ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo (int)$j['Excluir'] ? 'Incluir' : 'Excluir'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($seccion === 'descartes'): ?>
        <p class="text-muted small">Datos que el juego envió y que no se copiaron a las estadísticas (reto fuera del catálogo, valores fuera de rango, etc.). Siguen guardados en la partida del jugador.</p>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Reto</th>
                            <th>Motivo</th>
                            <th>Detalle</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$datos['descartes']): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay datos descartados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($datos['descartes'] as $d): ?>
                            <tr>
                                <td><?php echo h(fecha_hora($d['Fecha'])); ?></td>
                                <td><?php echo h($d['Username']); ?></td>
                                <td><?php echo h($d['IdReto']); ?></td>
                                <td><?php echo h($d['Motivo']); ?></td>
                                <td class="small text-break"><?php echo h($d['Detalle']); ?></td>
                                <td class="text-end">
                                    <?php if ((int)$d['Revisado']): ?>
                                        <span class="text-muted small">Revisado</span>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="accion" value="revisar_descarte">
                                            <input type="hidden" name="descarte" value="<?php echo (int)$d['DescarteId']; ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Marcar revisado</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <p class="text-muted small">Últimas 100 acciones de gestión.</p>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha</th>
                            <th>Quién</th>
                            <th>Acción</th>
                            <th>Sobre</th>
                            <th>Detalle</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$datos['bitacora']): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Sin acciones registradas.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($datos['bitacora'] as $b): ?>
                            <tr>
                                <td><?php echo h(fecha_hora($b['Fecha'])); ?></td>
                                <td><?php echo h($b['Username'] ?? '(cuenta eliminada)'); ?></td>
                                <td><?php echo h($b['Accion']); ?></td>
                                <td><?php echo h($b['ObjetivoTipo'] . ' ' . $b['ObjetivoId']); ?></td>
                                <td class="small text-break"><?php echo h($b['Detalle']); ?></td>
                                <td class="small"><?php echo h($b['Ip']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
