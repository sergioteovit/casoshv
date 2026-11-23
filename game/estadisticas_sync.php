<?php
// Copia la partida guardada a las tablas Est*, que son las que consulta la web.
// Reglas: solo se aceptan retos e insignias del catálogo, un mejor resultado nunca baja
// y el total del jugador se calcula aquí, sin usar el que envía el juego.

function est_entero($valor)
{
    return is_numeric($valor) ? (int)$valor : null;
}

// El juego guarda cada minuto: un mismo problema se registra una sola vez hasta que se revise.
function est_registrar_descarte($conn, $userId, $idReto, $motivo, $detalle)
{
    $stmt = $conn->prepare("
        SELECT 1 FROM EstDatosDescartados
        WHERE UserId = ? AND Motivo = ? AND Revisado = 0 AND IdReto <=> ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $motivo, $idReto]);
    if ($stmt->fetchColumn()) {
        return;
    }

    $stmt = $conn->prepare("INSERT INTO EstDatosDescartados (UserId, IdReto, Motivo, Detalle) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $idReto, $motivo, substr((string)$detalle, 0, 500)]);
}

// Fecha del juego (ISO 8601 en UTC, p. ej. 2026-11-23T14:02:03.1234567Z) en el reloj de MySQL,
// el mismo de las demás fechas de las tablas ($desfase = segundos entre NOW() y UTC_TIMESTAMP()).
// Devuelve null si no es válida o está en el futuro.
function est_fecha_juego($valor, $desfase)
{
    if (!is_string($valor) || !preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $valor, $m)) {
        return null;
    }
    try {
        $fecha = new DateTimeImmutable($m[1] . ' ' . $m[2], new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return null;
    }
    if ($fecha->getTimestamp() > time() + 86400) {
        return null;
    }
    return $fecha->modify(($desfase >= 0 ? '+' : '') . (int)$desfase . ' seconds')->format('Y-m-d H:i:s');
}

function est_sincronizar_retos($conn, $userId, $resultados, $registrarMejoras)
{
    $maximos = $conn->query("SELECT IdReto, PuntajeMaximo FROM EstRetos")->fetchAll(PDO::FETCH_KEY_PAIR);
    $desfase = (int)$conn->query("SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())")->fetchColumn();

    $consulta = $conn->prepare("
        SELECT MejorPuntaje, MejorEstrellas, Intentos, Victorias,
               Inicios, UltimoPuntaje, UltimasEstrellas, UltimaVictoria, UltimaFecha
        FROM EstResultadosReto
        WHERE UserId = ? AND IdReto = ?
        FOR UPDATE
    ");
    $guardar = $conn->prepare("
        INSERT INTO EstResultadosReto (UserId, IdReto, MejorPuntaje, MejorEstrellas, Intentos, Victorias,
                                       Inicios, UltimoPuntaje, UltimasEstrellas, UltimaVictoria, UltimaFecha)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            MejorPuntaje = VALUES(MejorPuntaje),
            MejorEstrellas = VALUES(MejorEstrellas),
            Intentos = VALUES(Intentos),
            Victorias = VALUES(Victorias),
            Inicios = VALUES(Inicios),
            UltimoPuntaje = VALUES(UltimoPuntaje),
            UltimasEstrellas = VALUES(UltimasEstrellas),
            UltimaVictoria = VALUES(UltimaVictoria),
            UltimaFecha = VALUES(UltimaFecha)
    ");
    $mejora = $conn->prepare("
        INSERT INTO EstMejoras (UserId, IdReto, PuntosGanados, PuntajeNuevo, EstrellasNuevas)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($resultados as $r) {
        if (!is_array($r)) {
            continue;
        }

        $idReto = is_string($r['idReto'] ?? null) ? $r['idReto'] : '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $idReto)) {
            est_registrar_descarte($conn, $userId, null, 'Identificador de reto inválido', $idReto);
            continue;
        }
        if (!isset($maximos[$idReto])) {
            // El dato sigue en la partida: se copiará cuando el reto se agregue al catálogo
            est_registrar_descarte($conn, $userId, $idReto, 'Reto fuera del catálogo', '');
            continue;
        }

        $puntaje   = est_entero($r['mejorPuntaje'] ?? null);
        $estrellas = est_entero($r['mejorEstrellas'] ?? null);
        $intentos  = est_entero($r['intentos'] ?? null);
        $victorias = est_entero($r['victorias'] ?? null);

        if ($puntaje === null || $estrellas === null || $intentos === null || $victorias === null
            || $puntaje < 0 || $puntaje > (int)$maximos[$idReto]
            || $estrellas < 0 || $estrellas > 5
            || $intentos < 0 || $victorias < 0 || $victorias > $intentos) {
            est_registrar_descarte($conn, $userId, $idReto, 'Resultado fuera de rango', json_encode($r));
            continue;
        }

        $consulta->execute([$userId, $idReto]);
        $previo = $consulta->fetch(PDO::FETCH_ASSOC);
        $consulta->closeCursor();

        $puntajePrevio   = $previo ? (int)$previo['MejorPuntaje'] : 0;
        $estrellasPrevia = $previo ? (int)$previo['MejorEstrellas'] : 0;
        $intentosPrevios = $previo ? (int)$previo['Intentos'] : 0;
        $victoriasPrevia = $previo ? (int)$previo['Victorias'] : 0;

        $puntajeNuevo   = max($puntajePrevio, $puntaje);
        $estrellasNueva = max($estrellasPrevia, $estrellas);
        $intentosNuevos = max($intentosPrevios, $intentos);
        $victoriasNueva = max($victoriasPrevia, $victorias);

        // Inicios y último intento (juego 26.11.23 o posterior; las versiones anteriores no los envían).
        // Los inicios nunca bajan; el último intento solo se reemplaza por uno más reciente y válido.
        $iniciosPrevios = $previo ? (int)$previo['Inicios'] : 0;
        $inicios = est_entero($r['inicios'] ?? null);
        $iniciosNuevos = ($inicios !== null && $inicios > $iniciosPrevios) ? $inicios : $iniciosPrevios;

        $ultimo = [
            $previo ? $previo['UltimoPuntaje'] : null,
            $previo ? $previo['UltimasEstrellas'] : null,
            $previo ? $previo['UltimaVictoria'] : null,
            $previo ? $previo['UltimaFecha'] : null,
        ];
        $fechaUltimo = est_fecha_juego($r['ultimaFecha'] ?? null, $desfase);
        $puntajeUltimo = est_entero($r['ultimoPuntaje'] ?? null);
        $estrellasUltimo = est_entero($r['ultimasEstrellas'] ?? null);
        if ($fechaUltimo !== null && $puntajeUltimo !== null && $estrellasUltimo !== null
            && $puntajeUltimo >= 0 && $puntajeUltimo <= (int)$maximos[$idReto]
            && $estrellasUltimo >= 0 && $estrellasUltimo <= 5
            && ($ultimo[3] === null || $fechaUltimo > $ultimo[3])) {
            $ultimo = [$puntajeUltimo, $estrellasUltimo, !empty($r['ultimaVictoria']) ? 1 : 0, $fechaUltimo];
        }
        $ultimoCambio = !$previo || $ultimo[3] !== $previo['UltimaFecha'];

        if ($previo && $puntajeNuevo === $puntajePrevio && $estrellasNueva === $estrellasPrevia
            && $intentosNuevos === $intentosPrevios && $victoriasNueva === $victoriasPrevia
            && $iniciosNuevos === $iniciosPrevios && !$ultimoCambio) {
            continue;
        }

        $guardar->execute(array_merge(
            [$userId, $idReto, $puntajeNuevo, $estrellasNueva, $intentosNuevos, $victoriasNueva, $iniciosNuevos],
            $ultimo
        ));

        if ($registrarMejoras && ($puntajeNuevo > $puntajePrevio || $estrellasNueva > $estrellasPrevia)) {
            $mejora->execute([$userId, $idReto, $puntajeNuevo - $puntajePrevio, $puntajeNuevo, $estrellasNueva]);
        }
    }
}

function est_sincronizar_insignias($conn, $userId, $insignias)
{
    $catalogo = array_flip($conn->query("SELECT IdInsignia FROM EstInsignias")->fetchAll(PDO::FETCH_COLUMN));
    $guardar = $conn->prepare("INSERT IGNORE INTO EstInsigniasJugador (UserId, IdInsignia) VALUES (?, ?)");

    foreach ($insignias as $id) {
        if (!is_string($id) || $id === '') {
            continue;
        }
        if (!isset($catalogo[$id])) {
            est_registrar_descarte($conn, $userId, null, 'Insignia fuera del catálogo', $id);
            continue;
        }
        $guardar->execute([$userId, $id]);
    }
}

function est_sincronizar_respuestas($conn, $userId, $casos)
{
    // Columna de la tabla => campo del JSON del juego
    $campos = [
        'Interrogatorio'         => 'interrogatorio',
        'DescripcionExploracion' => 'descripcionExploracion',
        'PresentacionCaso'       => 'presentacionCaso',
        'Paraclinicos'           => 'paraclinicos',
        'DiagnosticoPrincipal'   => 'diagnosticoPrincipal',
        'Tratamiento'            => 'tratamiento',
        'Metacognicion'          => 'metacognicion',
        'Retroalimentacion'      => 'retroalimentacion',
    ];

    $columnas = implode(', ', array_keys($campos));
    $marcas = implode(', ', array_fill(0, count($campos), '?'));
    $actualizar = [];
    foreach (array_keys($campos) as $columna) {
        // Una respuesta vacía no borra la que ya estaba guardada
        $actualizar[] = "$columna = COALESCE(NULLIF(VALUES($columna), ''), $columna)";
    }
    $actualizar[] = 'Resuelto = GREATEST(Resuelto, VALUES(Resuelto))';

    $guardar = $conn->prepare("
        INSERT INTO EstRespuestasCaso (UserId, NumeroExpediente, $columnas, Resuelto)
        VALUES (?, ?, $marcas, ?)
        ON DUPLICATE KEY UPDATE " . implode(', ', $actualizar));

    foreach ($casos as $caso) {
        if (!is_array($caso)) {
            continue;
        }

        $expediente = is_string($caso['numeroExpediente'] ?? null) ? $caso['numeroExpediente'] : '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $expediente)) {
            continue;
        }

        $valores = [];
        $tieneTexto = false;
        foreach ($campos as $campo) {
            $texto = is_string($caso[$campo] ?? null) ? $caso[$campo] : '';
            $tieneTexto = $tieneTexto || trim($texto) !== '';
            $valores[] = $texto;
        }
        $resuelto = !empty($caso['resuelto']);

        // La plantilla del juego trae todos los casos vacíos: solo se guardan los que tienen algo
        if (!$tieneTexto && !$resuelto) {
            continue;
        }

        $guardar->execute(array_merge([$userId, $expediente], $valores, [$resuelto ? 1 : 0]));
    }
}

function est_actualizar_resumen($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(MejorPuntaje), 0) AS puntos,
            COALESCE(SUM(MejorEstrellas), 0) AS estrellas,
            COALESCE(SUM(MejorEstrellas = 1), 0) AS bronce,
            COALESCE(SUM(MejorEstrellas = 2), 0) AS plata,
            COALESCE(SUM(MejorEstrellas = 3), 0) AS oro,
            COALESCE(SUM(MejorEstrellas = 4), 0) AS platino,
            COALESCE(SUM(MejorEstrellas = 5), 0) AS diamante,
            COALESCE(SUM(MejorEstrellas > 0), 0) AS completados
        FROM EstResultadosReto
        WHERE UserId = ?
    ");
    $stmt->execute([$userId]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM EstInsigniasJugador WHERE UserId = ?");
    $stmt->execute([$userId]);
    $insignias = (int)$stmt->fetchColumn();

    // El momento se conserva si el total no cambió: sirve para desempatar
    $stmt = $conn->prepare("
        INSERT INTO EstResumenJugador
            (UserId, TotalPuntos, TotalEstrellas, Bronce, Plata, Oro, Platino, Diamante,
             RetosCompletados, TotalInsignias, PuntajeAlcanzadoEn)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            PuntajeAlcanzadoEn = IF(VALUES(TotalPuntos) <> TotalPuntos, NOW(), PuntajeAlcanzadoEn),
            TotalPuntos = VALUES(TotalPuntos),
            TotalEstrellas = VALUES(TotalEstrellas),
            Bronce = VALUES(Bronce),
            Plata = VALUES(Plata),
            Oro = VALUES(Oro),
            Platino = VALUES(Platino),
            Diamante = VALUES(Diamante),
            RetosCompletados = VALUES(RetosCompletados),
            TotalInsignias = VALUES(TotalInsignias)
    ");
    $stmt->execute([
        $userId, $t['puntos'], $t['estrellas'], $t['bronce'], $t['plata'], $t['oro'],
        $t['platino'], $t['diamante'], $t['completados'], $insignias,
    ]);
}

// Lanza una excepción si algo falla, después de revertir. Quien la llama decide qué hacer:
// el guardado de la partida nunca debe fallar por esta copia.
// $registrarMejoras es falso al migrar datos antiguos, porque no se sabe cuándo ocurrieron.
function est_sincronizar($conn, $userId, $statisticsJson, $casesJson, $registrarMejoras = true)
{
    $estadisticas = json_decode((string)$statisticsJson, true);
    if (!is_array($estadisticas)) {
        return;
    }

    $casos = null;
    if ($casesJson !== null) {
        $datosCasos = json_decode((string)$casesJson, true);
        if (is_array($datosCasos) && isset($datosCasos['casosClinicos']) && is_array($datosCasos['casosClinicos'])) {
            $casos = $datosCasos['casosClinicos'];
        }
    }

    $conn->beginTransaction();
    try {
        if (isset($estadisticas['resultadosRetos']) && is_array($estadisticas['resultadosRetos'])) {
            est_sincronizar_retos($conn, $userId, $estadisticas['resultadosRetos'], $registrarMejoras);
        }
        if (isset($estadisticas['insigniasObtenidas']) && is_array($estadisticas['insigniasObtenidas'])) {
            est_sincronizar_insignias($conn, $userId, $estadisticas['insigniasObtenidas']);
        }
        if ($casos !== null) {
            est_sincronizar_respuestas($conn, $userId, $casos);
        }
        est_actualizar_resumen($conn, $userId);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
