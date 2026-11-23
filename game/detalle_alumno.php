<?php
// Detalle de un alumno: sus retos y lo que hizo en cada caso clínico, empezando por la conversación con
// el paciente virtual. Lo usan Mi avance (el propio alumno), Grupos (revisor y administrador, alumnos de
// sus grupos) y Administración (cualquier cuenta). Cada página comprueba antes quién puede ver a quién.
// El juego guarda en cada caso solo la última partida: el texto se reemplaza al volver a jugarlo.
require_once __DIR__ . '/estadisticas_comun.php';

function est_retos_alumno($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT t.Titulo, t.Tipo, t.Sistema, r.MejorPuntaje, r.MejorEstrellas, r.Intentos, r.Victorias, r.ActualizadoEn,
               r.Inicios, r.UltimoPuntaje, r.UltimasEstrellas, r.UltimaVictoria, r.UltimaFecha
        FROM EstResultadosReto r
        JOIN EstRetos t ON t.IdReto = r.IdReto
        WHERE r.UserId = ?
        ORDER BY COALESCE(r.UltimaFecha, r.ActualizadoEn) DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Celda «Reto» de las tablas de retos: título, tipo y sistema (sin columna aparte, para que la tabla quepa)
function hv_celda_reto($reto)
{
    $esCaso = $reto['Tipo'] === 'caso';
    $html = '<div class="fw-semibold">' . h($reto['Titulo']) . '</div>'
        . '<span class="hv-chip' . ($esCaso ? '' : ' hv-chip-turquesa') . '">' . ($esCaso ? 'Caso clínico' : 'Minijuego') . '</span>';
    if (!empty($reto['Sistema'])) {
        $html .= ' <span class="small text-muted">' . h(est_nombre_sistema($reto['Sistema'])) . '</span>';
    }
    return $html;
}

// Celda «Intentos» de las tablas de retos: terminados, ganados y, si los hay, los que se abrieron sin terminar
function hv_celda_intentos($reto)
{
    $intentos = (int)$reto['Intentos'];
    $victorias = (int)$reto['Victorias'];
    $inicios = (int)($reto['Inicios'] ?? 0);
    $html = '<div class="fw-semibold">' . number_format($intentos) . '</div>'
        . '<div class="small text-muted">' . number_format($victorias) . ($victorias === 1 ? ' ganado' : ' ganados') . '</div>';
    if ($inicios > $intentos) {
        $html .= '<div class="small text-muted" title="Veces que se abrió el reto">de '
            . number_format($inicios) . ' inicios</div>';
    }
    return $html;
}

// Celda «Último intento»: puntaje, nivel y resultado del último intento terminado (juego 26.11.23 o
// posterior); para registros anteriores, solo la fecha de la última actualización
function hv_celda_ultimo_intento($reto)
{
    if (!empty($reto['UltimaFecha']) && $reto['UltimoPuntaje'] !== null) {
        $victoria = (int)$reto['UltimaVictoria'] === 1;
        return '<div class="fw-semibold text-nowrap">' . number_format((int)$reto['UltimoPuntaje']) . ' pts · '
            . ($victoria ? '<span class="text-success">Victoria</span>' : '<span class="text-danger">Derrota</span>')
            . '</div><div>' . hv_nivel($reto['UltimasEstrellas']) . '</div>'
            . '<div class="small text-muted">' . h(fecha_corta($reto['UltimaFecha'])) . '</div>';
    }
    if ((int)$reto['Intentos'] === 0 && (int)($reto['Inicios'] ?? 0) > 0) {
        return '<div class="small">Iniciado, sin terminar</div>'
            . '<div class="small text-muted">' . h(fecha_corta($reto['ActualizadoEn'])) . '</div>';
    }
    return '<div class="small text-muted">' . h(fecha_corta($reto['ActualizadoEn'])) . '</div>';
}

// Casos clínicos con respuestas, con el mejor resultado del caso si lo hay
function est_casos_alumno($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT c.*, t.Titulo, t.Sistema, r.MejorPuntaje, r.MejorEstrellas, r.Intentos
        FROM EstRespuestasCaso c
        LEFT JOIN EstRetos t ON t.IdReto = CONCAT('Caso_', c.NumeroExpediente)
        LEFT JOIN EstResultadosReto r ON r.UserId = c.UserId AND r.IdReto = CONCAT('Caso_', c.NumeroExpediente)
        WHERE c.UserId = ?
        ORDER BY c.ActualizadoEn DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// El juego guarda el chat como texto: «Dr. Nombre: "pregunta"» y «Paciente: respuesta», un mensaje por
// renglón (los renglones siguientes sin «Quién:» continúan el mensaje anterior).
// Devuelve [['quien' => alumno|paciente|otro, 'autor' => ..., 'texto' => ...], ...]; vacío si no se reconoce.
function hv_conversacion_mensajes($texto)
{
    $mensajes = [];
    $reconocidos = 0;
    foreach (preg_split('/\r\n|\r|\n/', (string)$texto) as $renglon) {
        $renglon = trim($renglon);
        if ($renglon === '') {
            continue;
        }
        if (preg_match('/^([^:"]{1,60}):\s*(.*)$/u', $renglon, $m)) {
            $autor = trim($m[1]);
            $contenido = trim($m[2]);
            if (strcasecmp($autor, 'Paciente') === 0) {
                $quien = 'paciente';
            } elseif ($contenido !== '' && $contenido[0] === '"') {
                $quien = 'alumno';
                $contenido = trim($contenido, "\" \t");
            } else {
                $quien = 'otro';
            }
            if ($quien !== 'otro') {
                $reconocidos++;
            }
            $mensajes[] = ['quien' => $quien, 'autor' => $autor, 'texto' => $contenido];
        } elseif ($mensajes) {
            $mensajes[count($mensajes) - 1]['texto'] .= "\n" . $renglon;
        } else {
            $mensajes[] = ['quien' => 'otro', 'autor' => '', 'texto' => $renglon];
        }
    }
    return $reconocidos > 0 ? $mensajes : [];
}

function hv_conversacion_html($texto)
{
    $mensajes = hv_conversacion_mensajes($texto);
    if (!$mensajes) {
        return '<p class="mb-0" style="white-space: pre-wrap">' . h($texto) . '</p>';
    }
    $html = '<div class="hv-chat">';
    foreach ($mensajes as $m) {
        $html .= '<div class="hv-msg hv-msg-' . $m['quien'] . '">'
               . ($m['autor'] !== '' ? '<div class="hv-msg-autor">' . h($m['autor']) . '</div>' : '')
               . '<div class="hv-msg-texto">' . nl2br(h($m['texto'])) . '</div></div>';
    }
    return $html . '</div>';
}

function hv_tarjeta_retos($retos, $titulo = 'Retos', $textoVacio = 'Sin retos registrados.')
{
    ?>
<section class="hv-tarjeta mb-4">
    <div class="hv-tarjeta-encabezado"><i class="bi bi-flag me-2 text-primary"></i><?php echo h($titulo); ?></div>
    <?php if (!$retos): ?>
        <div class="hv-vacio"><i class="bi bi-controller"></i><div class="fw-bold"><?php echo h($textoVacio); ?></div></div>
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
    <?php
}

// Casos clínicos en secciones desplegables: conversación con el paciente y demás respuestas
function hv_tarjeta_casos($casos, $titulo, $textoVacio, $idAcordeon = 'casos')
{
    $campos = [
        'DescripcionExploracion' => 'Exploración',
        'PresentacionCaso'       => 'Presentación del caso',
        'Paraclinicos'           => 'Paraclínicos',
        'DiagnosticoPrincipal'   => 'Diagnóstico principal',
        'Tratamiento'            => 'Tratamiento',
        'Metacognicion'          => 'Metacognición',
        'Retroalimentacion'      => 'Retroalimentación',
    ];
    ?>
<section class="hv-tarjeta mb-4">
    <div class="hv-tarjeta-encabezado"><i class="bi bi-chat-square-text me-2 text-primary"></i><?php echo h($titulo); ?></div>
    <?php if (!$casos): ?>
        <div class="hv-vacio">
            <i class="bi bi-chat-square-text"></i>
            <div class="fw-bold"><?php echo h($textoVacio); ?></div>
            <div class="small">La conversación y las respuestas se guardan al salir del caso con el botón Regresar.</div>
        </div>
    <?php else: ?>
        <p class="small text-muted px-3 pt-3 mb-0">Se conserva la última partida de cada caso.</p>
        <div class="accordion accordion-flush" id="<?php echo h($idAcordeon); ?>">
            <?php foreach ($casos as $i => $caso): ?>
                <?php
                $idPanel = $idAcordeon . '-' . $i;
                $conConversacion = trim((string)$caso['Interrogatorio']) !== '';
                ?>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo h($idPanel); ?>" aria-expanded="false" aria-controls="<?php echo h($idPanel); ?>">
                            <span class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-semibold"><?php echo h($caso['Titulo'] ?? ('Expediente ' . $caso['NumeroExpediente'])); ?></span>
                                <?php if ($caso['MejorEstrellas'] !== null): ?><?php echo hv_nivel($caso['MejorEstrellas']); ?><?php endif; ?>
                                <?php if ((int)$caso['Resuelto']): ?><span class="hv-chip hv-chip-turquesa">Resuelto</span><?php endif; ?>
                                <?php if ($conConversacion): ?><span class="hv-chip"><i class="bi bi-chat-dots me-1"></i>Conversación</span><?php endif; ?>
                                <span class="text-muted small"><?php echo h(fecha_corta($caso['ActualizadoEn'])); ?></span>
                            </span>
                        </button>
                    </h3>
                    <div id="<?php echo h($idPanel); ?>" class="accordion-collapse collapse" data-bs-parent="#<?php echo h($idAcordeon); ?>">
                        <div class="accordion-body">
                            <?php if ($caso['MejorPuntaje'] !== null): ?>
                                <p class="small text-muted mb-3">
                                    Mejor resultado: <strong><?php echo number_format((int)$caso['MejorPuntaje']); ?></strong> puntos
                                    <?php if ((int)$caso['Intentos'] > 0): ?>· <?php echo (int)$caso['Intentos']; ?> <?php echo (int)$caso['Intentos'] === 1 ? 'intento' : 'intentos'; ?><?php endif; ?>
                                    <?php if ($caso['Sistema']): ?>· <?php echo h(est_nombre_sistema($caso['Sistema'])); ?><?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($conConversacion): ?>
                                <h4 class="h6 text-primary"><i class="bi bi-chat-dots me-1"></i>Conversación con el paciente virtual</h4>
                                <?php echo hv_conversacion_html($caso['Interrogatorio']); ?>
                            <?php endif; ?>
                            <?php foreach ($campos as $columna => $etiqueta): ?>
                                <?php if (trim((string)$caso[$columna]) !== ''): ?>
                                    <h4 class="h6 mt-4 text-primary"><?php echo h($etiqueta); ?></h4>
                                    <p class="mb-0" style="white-space: pre-wrap"><?php echo h($caso[$columna]); ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
    <?php
}
