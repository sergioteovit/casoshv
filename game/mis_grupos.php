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
require_once __DIR__ . '/plantilla.php';
hv_encabezado('Grupos', 'grupos');

if ($alumnoActual) {
    hv_cabecera($alumnoActual['Username'], 'Alumno del grupo ' . $grupoActual['Nombre'], 'bi-person-badge');
} elseif ($grupoActual) {
    hv_cabecera($grupoActual['Nombre'], (string)$grupoActual['Descripcion'], 'bi-people');
} else {
    hv_cabecera('Grupos', $puedeRevisar
        ? 'Crea grupos para tu clase y sigue el avance de tus alumnos.'
        : 'Únete al grupo de tu clase con el código que te dé tu docente.', 'bi-people');
}
?>
<main class="container position-relative">
    <?php if ($aviso): ?>
        <div class="alert alert-<?php echo $aviso[0] === 'success' ? 'success' : 'danger'; ?> shadow-sm" role="status">
            <i class="bi <?php echo $aviso[0] === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-1"></i><?php echo h($aviso[1]); ?>
        </div>
    <?php endif; ?>

    <?php if ($alumnoActual): ?>
        <a href="mis_grupos.php?grupo=<?php echo (int)$grupoActual['GrupoId']; ?>" class="btn btn-light btn-sm mb-3 shadow-sm"><i class="bi bi-arrow-left me-1"></i>Volver a <?php echo h($grupoActual['Nombre']); ?></a>

        <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
            <?php
            $datosAlumno = [
                ['Puntos', $alumnoActual['TotalPuntos'], 'bi-lightning-charge', ''],
                ['Estrellas', $alumnoActual['TotalEstrellas'], 'bi-star', 'hv-icono-oro'],
                ['Retos completados', $alumnoActual['RetosCompletados'], 'bi-flag', 'hv-icono-turquesa'],
                ['Insignias', $alumnoActual['TotalInsignias'], 'bi-award', 'hv-icono-coral'],
            ];
            ?>
            <?php foreach ($datosAlumno as $d): ?>
                <div class="col">
                    <div class="hv-tarjeta hv-dato h-100">
                        <span class="hv-icono <?php echo h($d[3]); ?>"><i class="bi <?php echo h($d[2]); ?>"></i></span>
                        <div>
                            <div class="hv-numero"><?php echo number_format((int)$d[1]); ?></div>
                            <div class="hv-rotulo"><?php echo h($d[0]); ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="hv-tarjeta mb-4">
            <div class="hv-tarjeta-encabezado"><i class="bi bi-flag me-2 text-primary"></i>Retos</div>
            <?php if (!$retosAlumno): ?>
                <div class="hv-vacio"><i class="bi bi-controller"></i><div class="fw-bold">Sin retos registrados.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead>
                            <tr>
                                <th>Reto</th>
                                <th>Sistema</th>
                                <th class="col-numero">Mejor puntaje</th>
                                <th>Nivel</th>
                                <th class="col-numero">Intentos</th>
                                <th class="col-numero">Victorias</th>
                                <th>Última vez</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($retosAlumno as $reto): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo h($reto['Titulo']); ?></div>
                                        <span class="hv-chip<?php echo $reto['Tipo'] === 'caso' ? '' : ' hv-chip-turquesa'; ?>"><?php echo $reto['Tipo'] === 'caso' ? 'Caso clínico' : 'Minijuego'; ?></span>
                                    </td>
                                    <td><?php echo h(est_nombre_sistema($reto['Sistema'])); ?></td>
                                    <td class="col-numero fw-bold"><?php echo number_format((int)$reto['MejorPuntaje']); ?></td>
                                    <td><?php echo hv_nivel($reto['MejorEstrellas']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$reto['Intentos']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$reto['Victorias']); ?></td>
                                    <td class="text-muted small"><?php echo h(fecha_corta($reto['ActualizadoEn'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="hv-tarjeta">
            <div class="hv-tarjeta-encabezado"><i class="bi bi-journal-medical me-2 text-primary"></i>Respuestas escritas en casos clínicos</div>
            <?php if (!$respuestasAlumno): ?>
                <div class="hv-vacio"><i class="bi bi-journal"></i><div class="fw-bold">Sin respuestas registradas.</div></div>
            <?php else: ?>
                <div class="accordion accordion-flush" id="respuestas">
                    <?php foreach ($respuestasAlumno as $i => $respuesta): ?>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#respuesta<?php echo $i; ?>" aria-expanded="false" aria-controls="respuesta<?php echo $i; ?>">
                                    <span class="me-2 fw-semibold"><?php echo h($respuesta['Titulo'] ?? ('Expediente ' . $respuesta['NumeroExpediente'])); ?></span>
                                    <?php if ((int)$respuesta['Resuelto']): ?><span class="hv-chip hv-chip-turquesa me-2">Resuelto</span><?php endif; ?>
                                    <span class="text-muted small"><?php echo h(fecha_corta($respuesta['ActualizadoEn'])); ?></span>
                                </button>
                            </h3>
                            <div id="respuesta<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#respuestas">
                                <div class="accordion-body">
                                    <?php foreach ($camposRespuesta as $columna => $etiqueta): ?>
                                        <?php if (trim((string)$respuesta[$columna]) !== ''): ?>
                                            <h4 class="h6 mt-3 text-primary"><?php echo h($etiqueta); ?></h4>
                                            <p class="mb-0"><?php echo nl2br(h($respuesta[$columna])); ?></p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($grupoActual): ?>
        <a href="mis_grupos.php" class="btn btn-light btn-sm mb-3 shadow-sm"><i class="bi bi-arrow-left me-1"></i>Volver a los grupos</a>

        <div class="hv-tarjeta p-3 p-md-4 mb-4 d-flex flex-wrap align-items-center gap-3">
            <span class="hv-icono hv-icono-oro"><i class="bi bi-key"></i></span>
            <div class="me-auto">
                <div class="text-muted small">Código para unirse</div>
                <div class="hv-titulo fs-3" style="letter-spacing: .12em"><?php echo h($grupoActual['Codigo']); ?></div>
            </div>
            <?php if (!(int)$grupoActual['Activo']): ?>
                <span class="hv-chip">Inactivo: no acepta alumnos nuevos</span>
            <?php endif; ?>
            <span class="text-muted"><i class="bi bi-people me-1"></i><?php echo count($alumnosGrupo); ?> <?php echo count($alumnosGrupo) === 1 ? 'alumno' : 'alumnos'; ?></span>
        </div>

        <section class="hv-tarjeta">
            <?php if (!$alumnosGrupo): ?>
                <div class="hv-vacio">
                    <i class="bi bi-person-plus"></i>
                    <div class="fw-bold">Todavía no hay alumnos en este grupo.</div>
                    <div class="small">Comparte el código para que tus alumnos se unan desde la página Grupos.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead>
                            <tr>
                                <th class="col-posicion">Lugar</th>
                                <th>Alumno</th>
                                <th class="col-numero">Puntos</th>
                                <th class="col-numero">Estrellas</th>
                                <th class="col-numero">Retos</th>
                                <th class="col-numero">Insignias</th>
                                <th>Última actividad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnosGrupo as $i => $alumno): ?>
                                <tr>
                                    <td class="col-posicion"><span class="hv-lugar"><?php echo $i + 1; ?></span></td>
                                    <td>
                                        <a class="d-flex align-items-center gap-2 text-decoration-none" href="mis_grupos.php?grupo=<?php echo (int)$grupoActual['GrupoId']; ?>&amp;alumno=<?php echo h(urlencode($alumno['UserId'])); ?>">
                                            <span class="hv-avatar"><?php echo h(hv_iniciales($alumno['Username'])); ?></span>
                                            <span class="fw-semibold"><?php echo h($alumno['Username']); ?></span>
                                        </a>
                                    </td>
                                    <td class="col-numero fw-bold"><?php echo number_format((int)$alumno['TotalPuntos']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$alumno['TotalEstrellas']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$alumno['RetosCompletados']); ?></td>
                                    <td class="col-numero"><?php echo number_format((int)$alumno['TotalInsignias']); ?></td>
                                    <td class="text-muted small"><?php echo h(fecha_corta($alumno['ActualizadoEn'])); ?></td>
                                    <td class="text-end">
                                        <form method="POST" onsubmit="return confirm('¿Quitar a este alumno del grupo? Sus resultados no se borran.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="accion" value="quitar_alumno">
                                            <input type="hidden" name="grupo" value="<?php echo (int)$grupoActual['GrupoId']; ?>">
                                            <input type="hidden" name="alumno" value="<?php echo h($alumno['UserId']); ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-person-dash me-1"></i>Quitar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php else: ?>
        <section class="hv-tarjeta hv-banner mb-4">
            <div class="row g-0 align-items-center">
                <div class="col-md-5">
                    <img class="hv-banner-img" src="assets/img/ilustracion-grupos.jpg" alt="Estudiantes de medicina juntando las manos con su profesora">
                </div>
                <div class="col-md-7 p-4 p-lg-5">
                    <span class="hv-chip hv-chip-turquesa mb-2">Aprender en equipo</span>
                    <?php if ($puedeRevisar): ?>
                        <h2 class="h4">Acompaña a tu clase</h2>
                        <p class="text-muted mb-0">Crea un grupo, comparte su código con tus alumnos y consulta sus retos y sus respuestas escritas en los casos clínicos.</p>
                    <?php else: ?>
                        <h2 class="h4">Juega con tu clase</h2>
                        <p class="text-muted mb-0">Únete al grupo de tu docente para que pueda seguir tu avance y darte retroalimentación. Es opcional y puedes salir cuando quieras.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <?php if ($esJugador): ?>
                <div class="col-lg-5">
                    <section class="hv-tarjeta p-4 h-100">
                        <span class="hv-icono hv-icono-turquesa mb-3"><i class="bi bi-door-open"></i></span>
                        <h2 class="h5">Unirme a un grupo</h2>
                        <p class="text-muted small">Pertenecer a un grupo es opcional. El docente del grupo podrá ver tus resultados y tus respuestas escritas.</p>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="accion" value="unirse">
                            <label for="codigo" class="visually-hidden">Código del grupo</label>
                            <input type="text" id="codigo" name="codigo" class="form-control text-uppercase" placeholder="Código del grupo" maxlength="20" autocomplete="off" required>
                            <button type="submit" class="btn btn-turquesa">Unirme</button>
                        </form>
                    </section>
                </div>

                <div class="col-lg-7">
                    <section class="hv-tarjeta h-100">
                        <div class="hv-tarjeta-encabezado"><i class="bi bi-people me-2 text-primary"></i>Mis grupos</div>
                        <?php if (!$misGrupos): ?>
                            <div class="hv-vacio">
                                <i class="bi bi-people"></i>
                                <div class="fw-bold">No perteneces a ningún grupo.</div>
                                <div class="small">Cuando tu docente te comparta un código, escríbelo a la izquierda.</div>
                            </div>
                        <?php else: ?>
                            <ul class="hv-logros">
                                <?php foreach ($misGrupos as $grupo): ?>
                                    <li class="align-items-center">
                                        <span class="hv-icono"><i class="bi bi-people-fill"></i></span>
                                        <div class="me-auto">
                                            <div class="fw-bold"><?php echo h($grupo['Nombre']); ?>
                                                <?php if (!(int)$grupo['Activo']): ?><span class="hv-chip ms-1">Inactivo</span><?php endif; ?>
                                            </div>
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
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($puedeRevisar): ?>
                <div class="col-12">
                    <section class="hv-tarjeta p-4">
                        <h2 class="h5"><i class="bi bi-plus-circle me-2 text-primary"></i>Crear un grupo</h2>
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
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Crear</button></div>
                        </form>
                    </section>
                </div>

                <div class="col-12">
                    <section class="hv-tarjeta">
                        <div class="hv-tarjeta-encabezado"><i class="bi bi-collection me-2 text-primary"></i><?php echo $esAdmin ? 'Todos los grupos' : 'Grupos a mi cargo'; ?></div>
                        <?php if (!$gruposRevision): ?>
                            <div class="hv-vacio"><i class="bi bi-collection"></i><div class="fw-bold">Todavía no hay grupos.</div></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle hv-tabla">
                                    <thead>
                                        <tr>
                                            <th>Grupo</th>
                                            <th>Código</th>
                                            <th class="col-numero">Alumnos</th>
                                            <th>Estado</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gruposRevision as $grupo): ?>
                                            <tr>
                                                <td>
                                                    <a class="fw-bold text-decoration-none" href="mis_grupos.php?grupo=<?php echo (int)$grupo['GrupoId']; ?>"><?php echo h($grupo['Nombre']); ?></a>
                                                    <div class="small text-muted"><?php echo h($grupo['Descripcion']); ?></div>
                                                </td>
                                                <td><code class="fs-6"><?php echo h($grupo['Codigo']); ?></code></td>
                                                <td class="col-numero"><?php echo number_format((int)$grupo['Alumnos']); ?></td>
                                                <td><span class="hv-chip<?php echo (int)$grupo['Activo'] ? ' hv-chip-turquesa' : ''; ?>"><?php echo (int)$grupo['Activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
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
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
<?php hv_pie(); ?>
</body>
</html>
