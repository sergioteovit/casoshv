<?php
// Portada pública con las tablas de posiciones. No usa sesión ni muestra correos.
// Los títulos de los casos revelan el diagnóstico: nunca deben salir de esta página.

require_once __DIR__ . '/estadisticas_comun.php';

function pos_nombre_publico($nombre)
{
    // Hay cuentas que pudieron registrar su correo como nombre de usuario
    $arroba = strpos($nombre, '@');
    if ($arroba === false) {
        return $nombre;
    }
    return $arroba > 0 ? substr($nombre, 0, $arroba) : 'Jugador';
}

function pos_consultar_datos($conn)
{
    // Solo alumnos que no pidieron ocultarse y que no son cuentas de prueba
    $unirPublico = "
        JOIN Roles ro ON ro.RoleId = u.RoleId AND ro.RoleName = 'Player'
        LEFT JOIN EstPerfiles pf ON pf.UserId = u.UserId
    ";
    $esPublico = "COALESCE(pf.MostrarEnPosiciones, 1) = 1 AND COALESCE(pf.ExcluirDePosiciones, 0) = 0";

    $general = [];
    foreach ($conn->query("
        SELECT u.Username, s.TotalPuntos, s.TotalEstrellas, s.RetosCompletados, s.TotalInsignias
        FROM EstResumenJugador s
        JOIN Users u ON u.UserId = s.UserId
        $unirPublico
        WHERE s.TotalPuntos > 0 AND $esPublico
        ORDER BY s.TotalPuntos DESC, s.TotalEstrellas DESC, s.PuntajeAlcanzadoEn ASC, u.Username
        LIMIT 50
    ") as $f) {
        $general[] = [
            'jugador'   => pos_nombre_publico($f['Username']),
            'puntos'    => (int)$f['TotalPuntos'],
            'estrellas' => (int)$f['TotalEstrellas'],
            'retos'     => (int)$f['RetosCompletados'],
            'insignias' => (int)$f['TotalInsignias'],
        ];
    }

    $sistemas = [];
    $porSistema = [];
    foreach ($conn->query("SELECT DISTINCT Sistema FROM EstRetos WHERE Activo = 1 AND Sistema IS NOT NULL ORDER BY Sistema")
             ->fetchAll(PDO::FETCH_COLUMN) as $clave) {
        $sistemas[] = ['clave' => $clave, 'nombre' => est_nombre_sistema($clave)];
        $porSistema[$clave] = [];
    }

    // Para desempatar: quien llegó antes. Los resultados migrados no tienen fecha y van primero.
    foreach ($conn->query("
        SELECT t.Sistema, u.Username,
               SUM(r.MejorPuntaje) AS puntos,
               SUM(r.MejorEstrellas) AS estrellas,
               SUM(r.MejorEstrellas > 0) AS retos,
               (SELECT MAX(m.Fecha)
                FROM EstMejoras m
                JOIN EstRetos t2 ON t2.IdReto = m.IdReto
                WHERE m.UserId = r.UserId AND t2.Sistema = t.Sistema) AS ultimaMejora
        FROM EstResultadosReto r
        JOIN EstRetos t ON t.IdReto = r.IdReto
        JOIN Users u ON u.UserId = r.UserId
        $unirPublico
        WHERE t.Activo = 1 AND t.Sistema IS NOT NULL AND $esPublico
        GROUP BY t.Sistema, r.UserId, u.Username
        HAVING puntos > 0
        ORDER BY t.Sistema, puntos DESC, estrellas DESC, ultimaMejora ASC, u.Username
    ") as $f) {
        if (isset($porSistema[$f['Sistema']]) && count($porSistema[$f['Sistema']]) < 20) {
            $porSistema[$f['Sistema']][] = [
                'jugador'   => pos_nombre_publico($f['Username']),
                'puntos'    => (int)$f['puntos'],
                'estrellas' => (int)$f['estrellas'],
                'retos'     => (int)$f['retos'],
            ];
        }
    }

    $mes = [];
    foreach ($conn->query("
        SELECT u.Username, SUM(m.PuntosGanados) AS puntos, COUNT(DISTINCT m.IdReto) AS retos, MAX(m.Fecha) AS ultimaMejora
        FROM EstMejoras m
        JOIN Users u ON u.UserId = m.UserId
        $unirPublico
        WHERE m.Fecha >= DATE_FORMAT(NOW(), '%Y-%m-01') AND $esPublico
        GROUP BY m.UserId, u.Username
        HAVING puntos > 0
        ORDER BY puntos DESC, retos DESC, ultimaMejora ASC, u.Username
        LIMIT 50
    ") as $f) {
        $mes[] = [
            'jugador' => pos_nombre_publico($f['Username']),
            'puntos'  => (int)$f['puntos'],
            'retos'   => (int)$f['retos'],
        ];
    }

    $logros = [];

    foreach ($conn->query("
        SELECT u.Username, t.Tipo, t.Sistema, t.Titulo, m.PuntajeNuevo, m.EstrellasNuevas,
               TIMESTAMPDIFF(SECOND, m.Fecha, NOW()) AS edad
        FROM EstMejoras m
        JOIN EstRetos t ON t.IdReto = m.IdReto
        JOIN Users u ON u.UserId = m.UserId
        $unirPublico
        WHERE $esPublico
        ORDER BY m.Fecha DESC, m.MejoraId DESC
        LIMIT 10
    ") as $f) {
        $lugar = $f['Tipo'] === 'minijuego'
            ? 'en ' . $f['Titulo']
            : 'en un caso de ' . est_nombre_sistema((string)$f['Sistema']);
        $nivel = est_nombre_nivel($f['EstrellasNuevas']);
        $logros[] = [
            'jugador' => pos_nombre_publico($f['Username']),
            'texto'   => 'logró ' . (int)$f['PuntajeNuevo'] . ' puntos' . ($nivel ? " ($nivel) " : ' ') . $lugar,
            'edad'    => max(0, (int)$f['edad']),
        ];
    }

    foreach ($conn->query("
        SELECT u.Username, i.Nombre, TIMESTAMPDIFF(SECOND, ij.ObtenidaEn, NOW()) AS edad
        FROM EstInsigniasJugador ij
        JOIN EstInsignias i ON i.IdInsignia = ij.IdInsignia
        JOIN Users u ON u.UserId = ij.UserId
        $unirPublico
        WHERE i.Activa = 1 AND $esPublico
        ORDER BY ij.ObtenidaEn DESC
        LIMIT 10
    ") as $f) {
        $logros[] = [
            'jugador' => pos_nombre_publico($f['Username']),
            'texto'   => 'ganó la insignia ' . $f['Nombre'],
            'edad'    => max(0, (int)$f['edad']),
        ];
    }

    usort($logros, function ($a, $b) {
        return $a['edad'] - $b['edad'];
    });

    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
              'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fecha = $conn->query("SELECT MONTH(NOW()) AS m, YEAR(NOW()) AS a")->fetch(PDO::FETCH_ASSOC);

    return [
        'ok'         => true,
        'general'    => $general,
        'sistemas'   => $sistemas,
        'porSistema' => $porSistema,
        'mes'        => $mes,
        'nombreMes'  => $meses[(int)$fecha['m'] - 1] . ' de ' . (int)$fecha['a'],
        'logros'     => array_slice($logros, 0, 10),
    ];
}

if (isset($_GET['datos'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    try {
        require_once __DIR__ . '/config_bd.php';
        $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo json_encode(pos_consultar_datos($conn), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('[posiciones] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false]);
    }
    exit();
}

// Solo se abre la sesión si el visitante ya tiene una, para no crear sesiones vacías
$conSesion = false;
if (isset($_COOKIE[session_name()])) {
    require_once __DIR__ . '/seguridad.php';
    hv_iniciar_sesion();
    $conSesion = isset($_SESSION['UserId']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Virtual - Estadísticas del juego</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .col-numero { text-align: right; font-variant-numeric: tabular-nums; }
        .col-posicion { width: 4.5rem; text-align: center; }
        .logro-tiempo { font-size: .8rem; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">🏥 Hospital Virtual</span>
        <?php if ($conSesion): ?>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Mi panel</a>
        <?php else: ?>
            <a href="acceso.php" class="btn btn-outline-light btn-sm">Iniciar sesión</a>
        <?php endif; ?>
    </div>
</nav>

<main class="container mt-4 mb-5">
    <section class="p-4 mb-4 bg-white rounded shadow-sm">
        <h1 class="h3">Estadísticas de Hospital Virtual</h1>
        <p class="mb-3">
            Hospital Virtual Challenge es el juego de casos clínicos y retos de la Facultad de Medicina.
            Aquí se publican las posiciones de los jugadores y sus logros más recientes.
        </p>
        <?php if ($conSesion): ?>
            <a href="mi_avance.php" class="btn btn-primary">Ver mi avance</a>
        <?php else: ?>
            <a href="acceso.php" class="btn btn-primary">Iniciar sesión</a>
            <span class="text-muted small ms-2">para ver tu avance y unirte a un grupo.</span>
        <?php endif; ?>
    </section>

    <h2 class="h4">Tablas de posiciones</h2>
    <p class="text-muted">Cada reto cuenta una sola vez, con tu mejor resultado.</p>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item"><button type="button" class="nav-link active" data-vista="general">General</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-vista="sistema">Por sistema</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-vista="mes">Del mes</button></li>
                    </ul>
                </div>
                <div class="card-body pb-0">
                    <div id="filtroSistema" class="mb-3" hidden>
                        <label for="selectorSistema" class="form-label">Sistema</label>
                        <select id="selectorSistema" class="form-select"></select>
                    </div>
                    <p id="descripcionVista" class="text-muted small"></p>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-dark"><tr id="encabezados"></tr></thead>
                        <tbody id="filas"></tbody>
                    </table>
                </div>
                <div class="card-footer text-muted small" id="estado" role="status">Cargando…</div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Logros recientes</div>
                <ul class="list-group list-group-flush" id="logros"></ul>
            </div>
        </div>
    </div>
</main>

<script>
    const SEGUNDOS_ACTUALIZACION = 60;
    const MEDALLAS = ['🥇', '🥈', '🥉'];
    const COLUMNAS = {
        general: [['Puntos', 'puntos'], ['Estrellas', 'estrellas'], ['Retos', 'retos'], ['Insignias', 'insignias']],
        sistema: [['Puntos', 'puntos'], ['Estrellas', 'estrellas'], ['Retos', 'retos']],
        mes: [['Puntos ganados', 'puntos'], ['Retos mejorados', 'retos']]
    };

    let datos = null;
    let vista = 'general';

    const selector = document.getElementById('selectorSistema');

    function celda(etiqueta, texto, clase) {
        const elemento = document.createElement(etiqueta);
        elemento.textContent = texto;
        if (clase) elemento.className = clase;
        return elemento;
    }

    function filasDeVista() {
        if (vista === 'general') return datos.general;
        if (vista === 'mes') return datos.mes;
        return datos.porSistema[selector.value] || [];
    }

    function pintarTabla() {
        const columnas = COLUMNAS[vista];

        document.getElementById('encabezados').replaceChildren(
            celda('th', 'Lugar', 'col-posicion'),
            celda('th', 'Jugador'),
            ...columnas.map(c => celda('th', c[0], 'col-numero'))
        );

        document.getElementById('filtroSistema').hidden = vista !== 'sistema';
        document.getElementById('descripcionVista').textContent =
            vista === 'mes' ? 'Puntos ganados en ' + datos.nombreMes + ' al mejorar resultados.' : '';

        const filas = filasDeVista();
        const cuerpo = document.getElementById('filas');

        if (filas.length === 0) {
            const vacio = celda('td', 'Todavía no hay resultados aquí.', 'text-center text-muted py-4');
            vacio.colSpan = columnas.length + 2;
            const fila = document.createElement('tr');
            fila.append(vacio);
            cuerpo.replaceChildren(fila);
            return;
        }

        cuerpo.replaceChildren(...filas.map((f, i) => {
            const fila = document.createElement('tr');
            fila.append(
                celda('td', MEDALLAS[i] || String(i + 1), 'col-posicion'),
                celda('td', f.jugador, i < 3 ? 'fw-bold' : ''),
                ...columnas.map(c => celda('td', f[c[1]].toLocaleString('es-MX'), 'col-numero'))
            );
            return fila;
        }));
    }

    function tiempoRelativo(segundos) {
        if (segundos < 60) return 'hace un momento';
        const minutos = Math.floor(segundos / 60);
        if (minutos < 60) return 'hace ' + minutos + (minutos === 1 ? ' minuto' : ' minutos');
        const horas = Math.floor(minutos / 60);
        if (horas < 24) return 'hace ' + horas + (horas === 1 ? ' hora' : ' horas');
        const dias = Math.floor(horas / 24);
        return 'hace ' + dias + (dias === 1 ? ' día' : ' días');
    }

    function pintarLogros() {
        const lista = document.getElementById('logros');

        if (datos.logros.length === 0) {
            lista.replaceChildren(celda('li', 'Todavía no hay logros.', 'list-group-item text-muted'));
            return;
        }

        lista.replaceChildren(...datos.logros.map(l => {
            const elemento = document.createElement('li');
            elemento.className = 'list-group-item';
            elemento.append(
                celda('strong', l.jugador),
                document.createTextNode(' ' + l.texto),
                celda('div', tiempoRelativo(l.edad), 'text-muted logro-tiempo')
            );
            return elemento;
        }));
    }

    function pintarSistemas() {
        const elegido = selector.value;
        selector.replaceChildren(...datos.sistemas.map(s => {
            const opcion = celda('option', s.nombre);
            opcion.value = s.clave;
            return opcion;
        }));
        if (datos.sistemas.some(s => s.clave === elegido)) selector.value = elegido;
    }

    async function actualizar() {
        const estado = document.getElementById('estado');
        try {
            const respuesta = await fetch('posiciones.php?datos=1', { cache: 'no-store' });
            const recibido = await respuesta.json();
            if (!recibido.ok) throw new Error('sin datos');

            datos = recibido;
            pintarSistemas();
            pintarTabla();
            pintarLogros();
            estado.textContent = 'Actualizado a las ' + new Date().toLocaleTimeString('es-MX')
                + '. Se actualiza cada minuto.';
        } catch (error) {
            estado.textContent = 'No se pudieron actualizar las posiciones. Se volverá a intentar en un minuto.';
        }
    }

    document.querySelectorAll('[data-vista]').forEach(boton => {
        boton.addEventListener('click', () => {
            vista = boton.dataset.vista;
            document.querySelectorAll('[data-vista]').forEach(b => b.classList.toggle('active', b === boton));
            if (datos) pintarTabla();
        });
    });

    selector.addEventListener('change', () => { if (datos) pintarTabla(); });

    actualizar();
    setInterval(() => { if (!document.hidden) actualizar(); }, SEGUNDOS_ACTUALIZACION * 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) actualizar(); });
</script>
</body>
</html>
