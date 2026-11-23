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
require_once __DIR__ . '/detalle_alumno.php';

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

    $retos = est_retos_alumno($conn, $userId);

    // Colección del avatar: catálogo del juego + estado guardado en PlayerData.Progress.avatar.objetos
    $catalogoObjetos = require __DIR__ . '/catalogo_objetos_avatar.php';
    $stmt = $conn->prepare("SELECT Progress FROM PlayerData WHERE UserId = ?");
    $stmt->execute([$userId]);
    $progreso = json_decode((string)$stmt->fetchColumn(), true);
    $estadoObjetos = [];
    foreach (($progreso['avatar']['objetos'] ?? []) as $o) {
        if (is_array($o) && is_string($o['id'] ?? null) && !empty($o['obtenido'])) {
            $estadoObjetos[$o['id']] = is_string($o['fecha'] ?? null) ? $o['fecha'] : '';
        }
    }
    // Primero los obtenidos y después los que faltan, cada grupo en el orden del catálogo
    $obtenidos = [];
    $faltantes = [];
    foreach ($catalogoObjetos as $id => $definicion) {
        if (isset($estadoObjetos[$id])) {
            // La fecha del juego es ISO 8601 en UTC; basta con el día
            $obtenidos[] = $definicion + ['obtenido' => true, 'fecha' => substr($estadoObjetos[$id], 0, 10)];
        } else {
            $faltantes[] = $definicion + ['obtenido' => false, 'fecha' => ''];
        }
    }
    $objetos = array_merge($obtenidos, $faltantes);
    $objetosObtenidos = count($obtenidos);

    $stmt = $conn->prepare("
        SELECT i.IdInsignia, i.Nombre, i.Descripcion, i.IdReto, t.Titulo AS TituloReto, ij.ObtenidaEn
        FROM EstInsignias i
        LEFT JOIN EstRetos t ON t.IdReto = i.IdReto
        LEFT JOIN EstInsigniasJugador ij ON ij.IdInsignia = i.IdInsignia AND ij.UserId = ?
        WHERE i.Activa = 1 OR ij.UserId IS NOT NULL
        ORDER BY i.IdReto = 'level' DESC, COALESCE(t.Titulo, i.IdReto), i.IdInsignia LIKE 'CASO\\_Especialista\\_%', i.Nombre
    ");
    $stmt->execute([$userId]);
    // Catálogo completo; las que aún no gana llevan ObtenidaEn nulo
    $insignias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $insigniasGanadas = 0;
    $nombresGrupos = [];
    $gruposInsignias = [];
    foreach ($insignias as $insignia) {
        if ($insignia['ObtenidaEn'] !== null) {
            $insigniasGanadas++;
        }
        $idGrupo = (string)$insignia['IdReto'];
        $gruposInsignias[$idGrupo][] = $insignia;
        // Nombre del grupo: el título del reto en el catálogo; las del caso clínico van juntas
        if (!isset($nombresGrupos[$idGrupo])) {
            $nombresGrupos[$idGrupo] = $idGrupo === 'level' ? 'Casos clínicos'
                : ($insignia['TituloReto'] ?: ($idGrupo !== '' ? $idGrupo : 'Otras'));
        }
    }

    // Lo que escribió en cada caso clínico, con la conversación con el paciente
    $casos = est_casos_alumno($conn, $userId);
} catch (Throwable $e) {
    error_log('[mi_avance] ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar tu avance. Inténtalo más tarde.');
}

$tarjetas = [
    ['Lugar general', $posicion !== null ? '#' . number_format($posicion) : '—', 'bi-trophy', 'hv-icono-oro'],
    ['Puntos', number_format((int)$jugador['TotalPuntos']), 'bi-lightning-charge', ''],
    ['Estrellas', number_format((int)$jugador['TotalEstrellas']), 'bi-star', 'hv-icono-oro'],
    ['Retos completados', number_format((int)$jugador['RetosCompletados']), 'bi-flag', 'hv-icono-turquesa'],
    ['Insignias', number_format((int)$jugador['TotalInsignias']), 'bi-award', 'hv-icono-coral'],
];
$medallas = ['Bronce', 'Plata', 'Oro', 'Platino', 'Diamante'];

require_once __DIR__ . '/plantilla.php';
hv_encabezado('Mi avance', 'avance');
hv_cabecera('Mi avance', 'Hola, ' . $jugador['Username'] . '. Así vas en el Desafío Clínico.', 'bi-graph-up-arrow', 'assets/img/ilustracion-avance.png');
?>
<main class="container position-relative">
    <?php if (isset($_GET['guardado'])): ?>
        <div class="alert alert-success shadow-sm" role="status"><i class="bi bi-check-circle me-1"></i>Tu preferencia quedó guardada.</div>
    <?php endif; ?>

    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">
        <?php foreach ($tarjetas as $i => $t): ?>
            <div class="col">
                <div class="hv-tarjeta hv-tarjeta-animada hv-dato h-100 hv-aparecer">
                    <span class="hv-icono <?php echo h($t[3]); ?>"><i class="bi <?php echo h($t[2]); ?>"></i></span>
                    <div>
                        <div class="hv-numero"><?php echo h($t[1]); ?></div>
                        <div class="hv-rotulo"><?php echo h($t[0]); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="hv-tarjeta mb-4">
                <div class="hv-tarjeta-encabezado"><i class="bi bi-flag me-2 text-primary"></i>Mis retos</div>
                <?php if (!$retos): ?>
                    <div class="hv-vacio">
                        <img src="assets/img/mascota-trofeo.png" alt="">
                        <div class="fw-bold">Todavía no tienes retos registrados.</div>
                        <div class="small">Juega un caso clínico o un minijuego y tu resultado aparecerá aquí.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle hv-tabla">
                            <thead>
                                <tr>
                                    <th style="min-width: 13rem">Reto</th>
                                    <th class="col-numero">Mejor puntaje</th>
                                    <th>Nivel</th>
                                    <th class="col-numero">Intentos</th>
                                    <th>Último intento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($retos as $reto): ?>
                                    <tr>
                                        <td><?php echo hv_celda_reto($reto); ?></td>
                                        <td class="col-numero fw-bold"><?php echo number_format((int)$reto['MejorPuntaje']); ?></td>
                                        <td><?php echo hv_nivel($reto['MejorEstrellas']); ?></td>
                                        <td class="col-numero"><?php echo hv_celda_intentos($reto); ?></td>
                                        <td><?php echo hv_celda_ultimo_intento($reto); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php hv_tarjeta_casos($casos, 'Mis casos clínicos', 'Todavía no hay casos clínicos con respuestas guardadas.', 'mis-casos'); ?>

            <section class="hv-tarjeta">
                <div class="hv-tarjeta-encabezado d-flex flex-wrap align-items-center gap-2">
                    <span class="me-auto"><i class="bi bi-award me-2 text-primary"></i>Mis insignias</span>
                    <span class="small text-muted fw-normal"><?php echo $insigniasGanadas; ?> de <?php echo count($insignias); ?> ganadas</span>
                </div>
                <?php if (!$insignias): ?>
                    <div class="hv-vacio">
                        <i class="bi bi-award"></i>
                        <div class="fw-bold">Todavía no hay insignias en el catálogo.</div>
                    </div>
                <?php else: ?>
                    <div class="p-3 pb-0">
                        <div class="progress mb-3" style="height: .5rem" role="progressbar" aria-label="Insignias ganadas" aria-valuenow="<?php echo $insigniasGanadas; ?>" aria-valuemin="0" aria-valuemax="<?php echo count($insignias); ?>">
                            <div class="progress-bar bg-success" style="width: <?php echo round(100 * $insigniasGanadas / count($insignias)); ?>%"></div>
                        </div>
                        <ul class="nav hv-pestanas" id="filtroInsignias">
                            <li class="nav-item"><button type="button" class="nav-link active" data-filtro="todas">Todas</button></li>
                            <li class="nav-item"><button type="button" class="nav-link" data-filtro="ganada">Ganadas (<?php echo $insigniasGanadas; ?>)</button></li>
                            <li class="nav-item"><button type="button" class="nav-link" data-filtro="pendiente">Por ganar (<?php echo count($insignias) - $insigniasGanadas; ?>)</button></li>
                        </ul>
                    </div>
                    <?php
                    foreach ($gruposInsignias as $idReto => $grupo):
                    ?>
                        <div class="hv-grupo-insignias px-3 pt-3">
                            <h3 class="h6 text-muted mb-2"><?php echo h($nombresGrupos[$idReto]); ?></h3>
                            <div class="row g-3">
                                <?php foreach ($grupo as $insignia): $ganada = $insignia['ObtenidaEn'] !== null; ?>
                                    <div class="col-sm-6 hv-insignia-columna" data-estado="<?php echo $ganada ? 'ganada' : 'pendiente'; ?>">
                                        <div class="hv-insignia<?php echo $ganada ? '' : ' hv-insignia-bloqueada'; ?>">
                                            <span class="hv-icono <?php echo $ganada ? 'hv-icono-oro' : ''; ?>"><i class="bi <?php echo $ganada ? 'bi-award-fill' : 'bi-lock-fill'; ?>"></i></span>
                                            <div>
                                                <div class="fw-bold"><?php echo h($insignia['Nombre']); ?></div>
                                                <div class="small text-muted"><?php echo h($insignia['Descripcion']); ?></div>
                                                <div class="hv-logro-tiempo mt-1">
                                                    <?php if ($ganada): ?>
                                                        <i class="bi bi-check-circle-fill text-success me-1"></i>Obtenida el <?php echo h(fecha_corta($insignia['ObtenidaEn'])); ?>
                                                    <?php else: ?>
                                                        Aún no la obtienes
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="hv-vacio d-none" id="insigniasSinResultados">
                        <i class="bi bi-award"></i>
                        <div class="fw-bold">Todavía no tienes insignias.</div>
                        <div class="small">Se ganan por precisión, buen razonamiento clínico y constancia.</div>
                    </div>
                    <div class="pb-3"></div>
                    <script>
                        // Filtro de insignias: todas, ganadas o por ganar
                        document.querySelectorAll('#filtroInsignias [data-filtro]').forEach(function (boton) {
                            boton.addEventListener('click', function () {
                                var filtro = boton.dataset.filtro;
                                document.querySelectorAll('#filtroInsignias .nav-link').forEach(function (b) { b.classList.toggle('active', b === boton); });
                                document.querySelectorAll('.hv-insignia-columna').forEach(function (c) {
                                    c.classList.toggle('d-none', filtro !== 'todas' && c.dataset.estado !== filtro);
                                });
                                var visibles = 0;
                                document.querySelectorAll('.hv-grupo-insignias').forEach(function (g) {
                                    var n = g.querySelectorAll('.hv-insignia-columna:not(.d-none)').length;
                                    g.classList.toggle('d-none', n === 0);
                                    visibles += n;
                                });
                                document.getElementById('insigniasSinResultados').classList.toggle('d-none', visibles > 0);
                            });
                        });
                    </script>
                <?php endif; ?>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="hv-tarjeta p-4 mb-4">
                <h2 class="h6 mb-2"><i class="bi bi-person-bounding-box me-2 text-primary"></i>Mi avatar</h2>
                <p class="small text-muted mb-3">Elige el modelo y personaliza tu apariencia, uniforme y aditamentos.</p>
                <a href="avatar/" class="btn btn-primary btn-sm"><i class="bi bi-palette me-1"></i>Abrir el vestidor</a>
            </section>

            <section class="hv-tarjeta p-4 mb-4">
                <h2 class="h6 mb-1 d-flex align-items-center">
                    <span class="me-auto"><i class="bi bi-briefcase me-2 text-primary"></i>Mi colección</span>
                    <span class="small text-muted fw-normal"><?php echo $objetosObtenidos; ?> de <?php echo count($objetos); ?></span>
                </h2>
                <p class="small text-muted mb-2">Objetos para tu avatar que ganas en el juego: en retos, en casos clínicos o recorriendo los pisos del hospital.</p>
                <div class="progress mb-3" style="height: .5rem" role="progressbar" aria-label="Objetos obtenidos" aria-valuenow="<?php echo $objetosObtenidos; ?>" aria-valuemin="0" aria-valuemax="<?php echo count($objetos); ?>">
                    <div class="progress-bar bg-success" style="width: <?php echo count($objetos) ? round(100 * $objetosObtenidos / count($objetos)) : 0; ?>%"></div>
                </div>
                <ul class="list-unstyled mb-0 hv-coleccion">
                    <?php foreach ($objetos as $objeto): ?>
                        <li class="d-flex gap-2 py-2<?php echo $objeto['obtenido'] ? '' : ' text-muted'; ?>" title="<?php echo h($objeto['descripcion']); ?>">
                            <i class="bi <?php echo $objeto['obtenido'] ? 'bi-check-circle-fill text-success' : 'bi-lock-fill'; ?> mt-1"></i>
                            <div>
                                <div class="fw-semibold small"><?php echo h($objeto['nombre']); ?></div>
                                <div class="small">
                                    <?php if ($objeto['obtenido']): ?>
                                        <?php echo h($objeto['descripcion']); ?>
                                        <?php if ($objeto['fecha'] !== ''): ?>
                                            <span class="hv-logro-tiempo d-block">Obtenido el <?php echo h(fecha_corta($objeto['fecha'])); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i class="bi bi-lightbulb me-1"></i><?php echo h($objeto['pista']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="hv-tarjeta p-4 mb-4">
                <h2 class="h6 mb-3"><i class="bi bi-stars me-2 text-warning"></i>Mi medallero</h2>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($medallas as $i => $medalla): ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <?php echo hv_nivel($i + 1); ?>
                            <span class="fw-bold"><?php echo (int)$jugador[$medalla]; ?> <span class="text-muted fw-normal small"><?php echo (int)$jugador[$medalla] === 1 ? 'reto' : 'retos'; ?></span></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hv-tarjeta p-4">
                <h2 class="h6 mb-2"><i class="bi bi-eye me-2 text-primary"></i>Tablas de posiciones</h2>
                <?php if (!$participa): ?>
                    <p class="mb-0 text-muted small">Esta cuenta no participa en las tablas de posiciones.</p>
                <?php else: ?>
                    <p class="small mb-3">
                        <?php echo $mostrar
                            ? 'Tu nombre de usuario aparece en las tablas públicas de posiciones.'
                            : 'Tu nombre de usuario no aparece en las tablas públicas de posiciones.'; ?>
                        Tus resultados se guardan igual.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h(hv_token_csrf()); ?>">
                        <input type="hidden" name="mostrar" value="<?php echo $mostrar ? '0' : '1'; ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi <?php echo $mostrar ? 'bi-eye-slash' : 'bi-eye'; ?> me-1"></i><?php echo $mostrar ? 'No aparecer en las posiciones' : 'Aparecer en las posiciones'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<?php hv_pie(); ?>
</body>
</html>
