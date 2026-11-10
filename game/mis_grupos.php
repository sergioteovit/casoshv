<?php
// Grupos por código. El alumno se une si quiere; revisores y administradores crean grupos
// y consultan el detalle de sus alumnos. El revisor solo ve los grupos que tiene asignados.

require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['UserId'])) {
    header('Location: acceso.php');
    exit();
}

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/estadisticas_comun.php';

header('Cache-Control: no-store');

function avisar($tipo, $texto)
{
    $_SESSION['grupos_aviso'] = [$tipo, $texto];
}

function volver($destino = 'mis_grupos.php')
{
    header('Location: ' . $destino);
    exit();
}

function generar_codigo()
{
    // Sin 0, O, 1, I ni L, que se confunden al dictarlos
    $letras = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $codigo = '';
    for ($i = 0; $i < 8; $i++) {
        $codigo .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $codigo;
}

function grupo_accesible($conn, $userId, $esAdmin, $grupoId)
{
    $stmt = $conn->prepare("
        SELECT g.GrupoId, g.Nombre, g.Descripcion, g.Codigo, g.Activo
        FROM EstGrupos g
        WHERE g.GrupoId = ?
          AND (? = 1 OR EXISTS (SELECT 1 FROM EstGrupoRevisores r WHERE r.GrupoId = g.GrupoId AND r.UserId = ?))
    ");
    $stmt->execute([$grupoId, $esAdmin ? 1 : 0, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function sin_acceso()
{
    http_response_code(403);
    exit('No tienes acceso a este grupo.');
}

$userId = $_SESSION['UserId'];
$conn = null;

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // El rol se consulta en la base para que un cambio aplique de inmediato
    $stmt = $conn->prepare("
        SELECT r.RoleName
        FROM Users u
        JOIN Roles r ON r.RoleId = u.RoleId
        WHERE u.UserId = ?
    ");
    $stmt->execute([$userId]);
    $rol = $stmt->fetchColumn();

    if ($rol === false) {
        volver('logout.php');
    }

    $esAdmin = $rol === 'Administrator';
    $esJugador = $rol === 'Player';
    $puedeRevisar = $esAdmin || $rol === 'Revisor';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hv_csrf_valido($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('Solicitud no válida. Recarga la página.');
        }

        $accion = $_POST['accion'] ?? '';
        $grupoId = (int)($_POST['grupo'] ?? 0);

        if ($accion === 'unirse' && $esJugador) {
            $claveLimite = 'grupo|' . $userId;
            if (hv_limite_excedido($claveLimite, 10, 900)) {
                avisar('danger', 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.');
                volver();
            }

            $codigo = strtoupper(preg_replace('/[\s-]+/', '', (string)($_POST['codigo'] ?? '')));
            $stmt = $conn->prepare("SELECT GrupoId, Nombre FROM EstGrupos WHERE Codigo = ? AND Activo = 1");
            $stmt->execute([$codigo]);
            $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$grupo) {
                hv_limite_registrar_fallo($claveLimite, 900);
                avisar('danger', 'Ese código no existe o el grupo ya no está activo.');
                volver();
            }

            $stmt = $conn->prepare("INSERT IGNORE INTO EstGrupoAlumnos (GrupoId, UserId) VALUES (?, ?)");
            $stmt->execute([$grupo['GrupoId'], $userId]);
            avisar('success', 'Te uniste al grupo ' . $grupo['Nombre'] . '.');
            volver();
        }

        if ($accion === 'salir') {
            $stmt = $conn->prepare("DELETE FROM EstGrupoAlumnos WHERE GrupoId = ? AND UserId = ?");
            $stmt->execute([$grupoId, $userId]);
            avisar('success', 'Saliste del grupo.');
            volver();
        }

        if ($accion === 'crear' && $puedeRevisar) {
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));

            if (!preg_match('/^.{1,120}$/su', $nombre) || !preg_match('/^.{0,255}$/su', $descripcion)) {
                avisar('danger', 'El nombre es obligatorio (hasta 120 caracteres) y la descripción admite hasta 255.');
                volver();
            }

            $conn->beginTransaction();

            $insertar = $conn->prepare("INSERT INTO EstGrupos (Nombre, Descripcion, Codigo, CreadoPor) VALUES (?, ?, ?, ?)");
            for ($intento = 1; ; $intento++) {
                $codigo = generar_codigo();
                try {
                    $insertar->execute([$nombre, $descripcion !== '' ? $descripcion : null, $codigo, $userId]);
                    break;
                } catch (PDOException $e) {
                    // Solo se reintenta si el código aleatorio ya existía
                    if ($e->getCode() !== '23000' || $intento >= 5) {
                        throw $e;
                    }
                }
            }
            $nuevoId = (int)$conn->lastInsertId();

            $stmt = $conn->prepare("INSERT INTO EstGrupoRevisores (GrupoId, UserId) VALUES (?, ?)");
            $stmt->execute([$nuevoId, $userId]);
            est_bitacora($conn, $userId, 'crear_grupo', 'grupo', $nuevoId, $nombre);

            $conn->commit();
            avisar('success', 'Grupo creado. Comparte con tus alumnos el código ' . $codigo . '.');
            volver();
        }

        if ($accion === 'cambiar_estado' && $puedeRevisar) {
            $grupo = grupo_accesible($conn, $userId, $esAdmin, $grupoId);
            if (!$grupo) {
                sin_acceso();
            }

            $stmt = $conn->prepare("UPDATE EstGrupos SET Activo = 1 - Activo WHERE GrupoId = ?");
            $stmt->execute([$grupoId]);
            est_bitacora($conn, $userId, $grupo['Activo'] ? 'desactivar_grupo' : 'activar_grupo', 'grupo', $grupoId, $grupo['Nombre']);
            avisar('success', $grupo['Activo'] ? 'Grupo desactivado: ya no acepta alumnos nuevos.' : 'Grupo activado.');
            volver();
        }

        if ($accion === 'quitar_alumno' && $puedeRevisar) {
            if (!grupo_accesible($conn, $userId, $esAdmin, $grupoId)) {
                sin_acceso();
            }

            $alumno = (string)($_POST['alumno'] ?? '');
            $stmt = $conn->prepare("DELETE FROM EstGrupoAlumnos WHERE GrupoId = ? AND UserId = ?");
            $stmt->execute([$grupoId, $alumno]);
            if ($stmt->rowCount() > 0) {
                est_bitacora($conn, $userId, 'quitar_alumno', 'grupo', $grupoId, $alumno);
            }
            avisar('success', 'El alumno ya no pertenece al grupo.');
            volver('mis_grupos.php?grupo=' . $grupoId);
        }

        http_response_code(403);
        exit('Acción no permitida.');
    }

    $aviso = $_SESSION['grupos_aviso'] ?? null;
    unset($_SESSION['grupos_aviso']);

    $grupoVer = (int)($_GET['grupo'] ?? 0);
    $alumnoVer = is_string($_GET['alumno'] ?? null) ? $_GET['alumno'] : '';

    $grupoActual = null;
    $alumnosGrupo = [];
    $alumnoActual = null;
    $retosAlumno = [];
    $respuestasAlumno = [];
    $misGrupos = [];
    $gruposRevision = [];

    if ($grupoVer > 0) {
        $grupoActual = $puedeRevisar ? grupo_accesible($conn, $userId, $esAdmin, $grupoVer) : null;
        if (!$grupoActual) {
            sin_acceso();
        }

        if ($alumnoVer !== '') {
            // Solo se puede consultar a quien pertenece a este grupo
            $stmt = $conn->prepare("
                SELECT u.UserId, u.Username, ga.UnidoEn,
                       COALESCE(s.TotalPuntos, 0) AS TotalPuntos,
                       COALESCE(s.TotalEstrellas, 0) AS TotalEstrellas,
                       COALESCE(s.RetosCompletados, 0) AS RetosCompletados,
                       COALESCE(s.TotalInsignias, 0) AS TotalInsignias
                FROM EstGrupoAlumnos ga
                JOIN Users u ON u.UserId = ga.UserId
                LEFT JOIN EstResumenJugador s ON s.UserId = ga.UserId
                WHERE ga.GrupoId = ? AND ga.UserId = ?
            ");
            $stmt->execute([$grupoVer, $alumnoVer]);
            $alumnoActual = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$alumnoActual) {
                sin_acceso();
            }

            $stmt = $conn->prepare("
                SELECT t.Titulo, t.Tipo, t.Sistema, r.MejorPuntaje, r.MejorEstrellas, r.Intentos, r.Victorias, r.ActualizadoEn
                FROM EstResultadosReto r
                JOIN EstRetos t ON t.IdReto = r.IdReto
                WHERE r.UserId = ?
                ORDER BY r.ActualizadoEn DESC
            ");
            $stmt->execute([$alumnoVer]);
            $retosAlumno = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $conn->prepare("
                SELECT c.*, t.Titulo
                FROM EstRespuestasCaso c
                LEFT JOIN EstRetos t ON t.IdReto = CONCAT('Caso_', c.NumeroExpediente)
                WHERE c.UserId = ?
                ORDER BY c.ActualizadoEn DESC
            ");
            $stmt->execute([$alumnoVer]);
            $respuestasAlumno = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $conn->prepare("
                SELECT u.UserId, u.Username, ga.UnidoEn,
                       COALESCE(s.TotalPuntos, 0) AS TotalPuntos,
                       COALESCE(s.TotalEstrellas, 0) AS TotalEstrellas,
                       COALESCE(s.RetosCompletados, 0) AS RetosCompletados,
                       COALESCE(s.TotalInsignias, 0) AS TotalInsignias,
                       s.ActualizadoEn
                FROM EstGrupoAlumnos ga
                JOIN Users u ON u.UserId = ga.UserId
                LEFT JOIN EstResumenJugador s ON s.UserId = ga.UserId
                WHERE ga.GrupoId = ?
                ORDER BY TotalPuntos DESC, u.Username
            ");
            $stmt->execute([$grupoVer]);
            $alumnosGrupo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        if ($esJugador) {
            $stmt = $conn->prepare("
                SELECT g.GrupoId, g.Nombre, g.Descripcion, g.Activo, ga.UnidoEn
                FROM EstGrupoAlumnos ga
                JOIN EstGrupos g ON g.GrupoId = ga.GrupoId
                WHERE ga.UserId = ?
                ORDER BY ga.UnidoEn DESC
            ");
            $stmt->execute([$userId]);
            $misGrupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($puedeRevisar) {
            $stmt = $conn->prepare("
                SELECT g.GrupoId, g.Nombre, g.Descripcion, g.Codigo, g.Activo,
                       (SELECT COUNT(*) FROM EstGrupoAlumnos ga WHERE ga.GrupoId = g.GrupoId) AS Alumnos
                FROM EstGrupos g
                WHERE ? = 1 OR EXISTS (SELECT 1 FROM EstGrupoRevisores r WHERE r.GrupoId = g.GrupoId AND r.UserId = ?)
                ORDER BY g.Activo DESC, g.CreadoEn DESC
            ");
            $stmt->execute([$esAdmin ? 1 : 0, $userId]);
            $gruposRevision = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('[mis_grupos] ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo completar la operación. Inténtalo más tarde.');
}

$csrf = h(hv_token_csrf());
$camposRespuesta = [
    'Interrogatorio'         => 'Interrogatorio',
    'DescripcionExploracion' => 'Exploración',
    'PresentacionCaso'       => 'Presentación del caso',
    'Paraclinicos'           => 'Paraclínicos',
    'DiagnosticoPrincipal'   => 'Diagnóstico principal',
    'Tratamiento'            => 'Tratamiento',
    'Metacognicion'          => 'Metacognición',
    'Retroalimentacion'      => 'Retroalimentación',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Virtual - Grupos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">🏥 Hospital Virtual</span>
        <span class="navbar-text text-white">
            <a href="posiciones.php" class="text-white text-decoration-none">Posiciones</a> |
            <a href="mi_avance.php" class="text-white text-decoration-none">Mi avance</a> |
            <a href="dashboard.php" class="text-white text-decoration-none">Panel</a> |
            <a href="logout.php" class="text-white text-decoration-none">Cerrar sesión</a>
        </span>
    </div>
</nav>

<main class="container mt-4 mb-5">
    <?php if ($aviso): ?>
        <div class="alert alert-<?php echo $aviso[0] === 'success' ? 'success' : 'danger'; ?>" role="status"><?php echo h($aviso[1]); ?></div>
    <?php endif; ?>

    <?php if ($alumnoActual): ?>
        <p><a href="mis_grupos.php?grupo=<?php echo (int)$grupoActual['GrupoId']; ?>">← Volver a <?php echo h($grupoActual['Nombre']); ?></a></p>
        <h1 class="h3"><?php echo h($alumnoActual['Username']); ?></h1>
        <p class="text-muted">
            <?php echo number_format((int)$alumnoActual['TotalPuntos']); ?> puntos ·
            <?php echo number_format((int)$alumnoActual['TotalEstrellas']); ?> estrellas ·
            <?php echo number_format((int)$alumnoActual['RetosCompletados']); ?> retos completados ·
            <?php echo number_format((int)$alumnoActual['TotalInsignias']); ?> insignias
        </p>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Retos</div>
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
                        <?php if (!$retosAlumno): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin retos registrados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($retosAlumno as $reto): ?>
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
            <div class="card-header fw-bold">Respuestas escritas en casos clínicos</div>
            <ul class="list-group list-group-flush">
                <?php if (!$respuestasAlumno): ?>
                    <li class="list-group-item text-muted">Sin respuestas registradas.</li>
                <?php endif; ?>
                <?php foreach ($respuestasAlumno as $respuesta): ?>
                    <li class="list-group-item">
                        <details>
                            <summary>
                                <?php echo h($respuesta['Titulo'] ?? ('Expediente ' . $respuesta['NumeroExpediente'])); ?>
                                <?php if ((int)$respuesta['Resuelto']): ?><span class="badge text-bg-success">Resuelto</span><?php endif; ?>
                                <span class="text-muted small">· <?php echo h(fecha_corta($respuesta['ActualizadoEn'])); ?></span>
                            </summary>
                            <?php foreach ($camposRespuesta as $columna => $etiqueta): ?>
                                <?php if (trim((string)$respuesta[$columna]) !== ''): ?>
                                    <h3 class="h6 mt-3"><?php echo h($etiqueta); ?></h3>
                                    <p class="mb-0"><?php echo nl2br(h($respuesta[$columna])); ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </details>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    <?php elseif ($grupoActual): ?>
        <p><a href="mis_grupos.php">← Volver a los grupos</a></p>
        <h1 class="h3"><?php echo h($grupoActual['Nombre']); ?></h1>
        <p class="text-muted">
            <?php echo h($grupoActual['Descripcion']); ?>
            Código: <code><?php echo h($grupoActual['Codigo']); ?></code>
            <?php if (!(int)$grupoActual['Activo']): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?>
        </p>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Alumno</th>
                            <th class="text-end">Puntos</th>
                            <th class="text-end">Estrellas</th>
                            <th class="text-end">Retos</th>
                            <th class="text-end">Insignias</th>
                            <th>Última actividad</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$alumnosGrupo): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay alumnos en este grupo.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($alumnosGrupo as $alumno): ?>
                            <tr>
                                <td><a href="mis_grupos.php?grupo=<?php echo (int)$grupoActual['GrupoId']; ?>&amp;alumno=<?php echo h(urlencode($alumno['UserId'])); ?>"><?php echo h($alumno['Username']); ?></a></td>
                                <td class="text-end"><?php echo number_format((int)$alumno['TotalPuntos']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$alumno['TotalEstrellas']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$alumno['RetosCompletados']); ?></td>
                                <td class="text-end"><?php echo number_format((int)$alumno['TotalInsignias']); ?></td>
                                <td><?php echo h(fecha_corta($alumno['ActualizadoEn'])); ?></td>
                                <td class="text-end">
                                    <form method="POST" onsubmit="return confirm('¿Quitar a este alumno del grupo? Sus resultados no se borran.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="accion" value="quitar_alumno">
                                        <input type="hidden" name="grupo" value="<?php echo (int)$grupoActual['GrupoId']; ?>">
                                        <input type="hidden" name="alumno" value="<?php echo h($alumno['UserId']); ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <h1 class="h3 mb-3">Grupos</h1>

        <?php if ($esJugador): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Unirme a un grupo</h2>
                    <p class="text-muted small">Pertenecer a un grupo es opcional. El docente del grupo podrá ver tus resultados y tus respuestas escritas.</p>
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="accion" value="unirse">
                        <div class="col-sm-6 col-md-4">
                            <label for="codigo" class="visually-hidden">Código del grupo</label>
                            <input type="text" id="codigo" name="codigo" class="form-control" placeholder="Código del grupo" maxlength="20" autocomplete="off" required>
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-primary">Unirme</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-bold">Mis grupos</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$misGrupos): ?>
                        <li class="list-group-item text-muted">No perteneces a ningún grupo.</li>
                    <?php endif; ?>
                    <?php foreach ($misGrupos as $grupo): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <strong><?php echo h($grupo['Nombre']); ?></strong>
                                <?php if (!(int)$grupo['Activo']): ?><span class="badge text-bg-secondary">Inactivo</span><?php endif; ?>
                                <div class="small text-muted"><?php echo h($grupo['Descripcion']); ?></div>
                            </div>
                            <form method="POST" onsubmit="return confirm('¿Salir de este grupo?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="accion" value="salir">
                                <input type="hidden" name="grupo" value="<?php echo (int)$grupo['GrupoId']; ?>">
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Salir</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($puedeRevisar): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Crear un grupo</h2>
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="accion" value="crear">
                        <div class="col-md-4">
                            <label for="nombre" class="visually-hidden">Nombre del grupo</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Nombre del grupo" maxlength="120" required>
                        </div>
                        <div class="col-md-6">
                            <label for="descripcion" class="visually-hidden">Descripción</label>
                            <input type="text" id="descripcion" name="descripcion" class="form-control" placeholder="Descripción (opcional)" maxlength="255">
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-success">Crear</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-bold"><?php echo $esAdmin ? 'Todos los grupos' : 'Grupos a mi cargo'; ?></div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Grupo</th>
                                <th>Código</th>
                                <th class="text-end">Alumnos</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$gruposRevision): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">Todavía no hay grupos.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($gruposRevision as $grupo): ?>
                                <tr>
                                    <td>
                                        <a href="mis_grupos.php?grupo=<?php echo (int)$grupo['GrupoId']; ?>"><?php echo h($grupo['Nombre']); ?></a>
                                        <div class="small text-muted"><?php echo h($grupo['Descripcion']); ?></div>
                                    </td>
                                    <td><code><?php echo h($grupo['Codigo']); ?></code></td>
                                    <td class="text-end"><?php echo number_format((int)$grupo['Alumnos']); ?></td>
                                    <td><?php echo (int)$grupo['Activo'] ? 'Activo' : 'Inactivo'; ?></td>
                                    <td class="text-end">
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="grupo" value="<?php echo (int)$grupo['GrupoId']; ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo (int)$grupo['Activo'] ? 'Desactivar' : 'Activar'; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
