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
require_once __DIR__ . '/plantilla.php';
hv_encabezado('Administración', 'admin');
hv_cabecera('Administración', 'Estadísticas totales, jugadores, datos descartados y bitácora de la plataforma.', 'bi-shield-lock');

$iconosSeccion = ['resumen' => 'bi-speedometer2', 'jugadores' => 'bi-person-lines-fill', 'descartes' => 'bi-funnel', 'bitacora' => 'bi-clock-history'];
?>
<main class="container position-relative">
    <div class="hv-tarjeta p-2 mb-4">
        <ul class="nav hv-pestanas">
            <?php foreach ($secciones as $clave => $nombre): ?>
                <li class="nav-item">
                    <a class="nav-link<?php echo $clave === $seccion ? ' active' : ''; ?>" href="administracion.php?seccion=<?php echo $clave; ?>"<?php echo $clave === $seccion ? ' aria-current="page"' : ''; ?>>
                        <i class="bi <?php echo $iconosSeccion[$clave]; ?> me-1"></i><?php echo h($nombre); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($seccion === 'resumen'): ?>
        <?php
        $t = $datos['totales'];
        $tarjetas = [
            ['Activos en 7 días', $t['Activos7'], 'bi-person-check', 'hv-icono-turquesa'],
            ['Activos en 30 días', $t['Activos30'], 'bi-calendar-check', 'hv-icono-turquesa'],
            ['Jugadores con resultados', $t['ConResultados'], 'bi-controller', ''],
            ['Mejoras este mes', $t['MejorasMes'], 'bi-graph-up-arrow', ''],
            ['Insignias otorgadas', $t['Insignias'], 'bi-award', 'hv-icono-oro'],
            ['Grupos activos', $t['GruposActivos'], 'bi-people', 'hv-icono-oro'],
            ['Descartes por revisar', $t['DescartesPendientes'], 'bi-funnel', 'hv-icono-coral'],
            ['Cuentas excluidas', $t['Excluidas'], 'bi-person-x', 'hv-icono-coral'],
        ];
        ?>
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-3">
            <?php foreach ($tarjetas as $tarjeta): ?>
                <div class="col">
                    <div class="hv-tarjeta hv-dato h-100">
                        <span class="hv-icono <?php echo h($tarjeta[3]); ?>"><i class="bi <?php echo h($tarjeta[2]); ?>"></i></span>
                        <div>
                            <div class="hv-numero"><?php echo number_format((int)$tarjeta[1]); ?></div>
                            <div class="hv-rotulo"><?php echo h($tarjeta[0]); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-muted small mb-4">
            Cuentas por rol:
            <?php foreach ($datos['roles'] as $i => $fila): ?>
                <?php echo $i > 0 ? '·' : ''; ?> <?php echo h($fila['RoleName']); ?> <strong><?php echo (int)$fila['Cuentas']; ?></strong>
            <?php endforeach; ?>
            <br>Los resultados, mejoras, insignias y retos no incluyen las cuentas excluidas de las posiciones.
        </p>

        <section class="hv-tarjeta">
            <div class="hv-tarjeta-encabezado"><i class="bi bi-bar-chart me-2 text-primary"></i>Retos más jugados</div>
            <?php if (!$datos['retos']): ?>
                <div class="hv-vacio"><i class="bi bi-bar-chart"></i><div class="fw-bold">Todavía no hay resultados.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead>
                            <tr>
                                <th>Reto</th>
                                <th>Sistema</th>
                                <th class="col-numero">Jugadores</th>
                                <th class="col-numero">Intentos</th>
                                <th class="col-numero">Victorias</th>
                                <th class="col-numero">Puntaje medio</th>
                                <th class="col-numero">Estrellas medias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos['retos'] as $reto): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo h($reto['Titulo']); ?></div>
                                        <span class="hv-chip<?php echo $reto['Tipo'] === 'caso' ? '' : ' hv-chip-turquesa'; ?>"><?php echo $reto['Tipo'] === 'caso' ? 'Caso clínico' : 'Minijuego'; ?></span>
                                    </td>
                                    <td><?php echo h(est_nombre_sistema($reto['Sistema'])); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$reto['Jugadores']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$reto['Intentos']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$reto['Victorias']); ?></td>
                                    <td class="col-numero fw-bold"><?php echo number_format((int)$reto['PromedioPuntaje']); ?></td>
                                    <td class="col-numero"><?php echo h($reto['PromedioEstrellas']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($seccion === 'jugadores'): ?>
        <form method="GET" class="hv-tarjeta p-3 mb-3 d-flex flex-wrap gap-2">
            <input type="hidden" name="seccion" value="jugadores">
            <label for="q" class="visually-hidden">Buscar</label>
            <div class="input-group" style="max-width: 28rem">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" id="q" name="q" class="form-control" placeholder="Buscar por usuario o correo" value="<?php echo h($datos['busqueda']); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>

        <p class="text-muted small">"Puntos anteriores" es el total que guardaba el juego antes de las tablas nuevas; no cuenta en las posiciones. Se muestran hasta 200 cuentas.</p>

        <section class="hv-tarjeta">
            <?php if (!$datos['jugadores']): ?>
                <div class="hv-vacio"><i class="bi bi-search"></i><div class="fw-bold">No hay cuentas que coincidan.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th class="col-numero">Puntos</th>
                                <th class="col-numero">Estrellas</th>
                                <th class="col-numero">Retos</th>
                                <th class="col-numero">Insignias</th>
                                <th class="col-numero">Puntos anteriores</th>
                                <th>Último acceso</th>
                                <th>Posiciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datos['jugadores'] as $j): ?>
                                <?php $diferencia = (int)$j['PuntosAnteriores'] - (int)$j['TotalPuntos']; ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="hv-avatar"><?php echo h(hv_iniciales($j['Username'])); ?></span>
                                            <div>
                                                <div class="fw-semibold"><?php echo h($j['Username']); ?></div>
                                                <div class="small text-muted"><?php echo h($j['Email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="hv-chip"><?php echo h($j['RoleName']); ?></span></td>
                                    <td class="col-numero fw-bold"><?php echo number_format((int)$j['TotalPuntos']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$j['TotalEstrellas']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$j['RetosCompletados']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$j['TotalInsignias']); ?></td>
                                    <td class="col-numero<?php echo $diferencia > 0 ? ' text-danger fw-bold' : ''; ?>"><?php echo number_format((int)$j['PuntosAnteriores']); ?></td>
                                    <td class="text-muted small"><?php echo h(fecha_hora($j['LastLogin'])); ?></td>
                                    <td>
                                        <?php if ($j['RoleName'] !== 'Player'): ?>
                                            <span class="text-muted small">No participa</span>
                                        <?php else: ?>
                                            <div class="small mb-1">
                                                <?php
                                                if ((int)$j['Excluir']) {
                                                    echo '<span class="hv-chip">Excluida</span>';
                                                } elseif (!(int)$j['Mostrar']) {
                                                    echo '<span class="hv-chip">Oculta por el jugador</span>';
                                                } else {
                                                    echo '<span class="hv-chip hv-chip-turquesa">Aparece</span>';
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
            <?php endif; ?>
        </section>

    <?php elseif ($seccion === 'descartes'): ?>
        <p class="text-muted small">Datos que el juego envió y que no se copiaron a las estadísticas (reto fuera del catálogo, valores fuera de rango, etc.). Siguen guardados en la partida del jugador.</p>

        <section class="hv-tarjeta">
            <?php if (!$datos['descartes']): ?>
                <div class="hv-vacio"><i class="bi bi-check2-circle"></i><div class="fw-bold">No hay datos descartados.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead>
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
                            <?php foreach ($datos['descartes'] as $d): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo h(fecha_hora($d['Fecha'])); ?></td>
                                    <td><?php echo h($d['Username']); ?></td>
                                    <td><?php echo h($d['IdReto']); ?></td>
                                    <td><?php echo h($d['Motivo']); ?></td>
                                    <td class="small text-break"><?php echo h($d['Detalle']); ?></td>
                                    <td class="text-end">
                                        <?php if ((int)$d['Revisado']): ?>
                                            <span class="hv-chip hv-chip-turquesa">Revisado</span>
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
            <?php endif; ?>
        </section>

    <?php else: ?>
        <p class="text-muted small">Últimas 100 acciones de gestión.</p>

        <section class="hv-tarjeta">
            <?php if (!$datos['bitacora']): ?>
                <div class="hv-vacio"><i class="bi bi-clock-history"></i><div class="fw-bold">Sin acciones registradas.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead>
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
                            <?php foreach ($datos['bitacora'] as $b): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo h(fecha_hora($b['Fecha'])); ?></td>
                                    <td><?php echo h($b['Username'] ?? '(cuenta eliminada)'); ?></td>
                                    <td><span class="hv-chip"><?php echo h($b['Accion']); ?></span></td>
                                    <td class="small"><?php echo h($b['ObjetivoTipo'] . ' ' . $b['ObjetivoId']); ?></td>
                                    <td class="small text-break"><?php echo h($b['Detalle']); ?></td>
                                    <td class="small text-muted"><?php echo h($b['Ip']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<?php hv_pie(); ?>
</body>
</html>
