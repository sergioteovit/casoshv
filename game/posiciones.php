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
            'tipo'    => 'mejora',
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
            'tipo'    => 'insignia',
            'edad'    => max(0, (int)$f['edad']),
        ];
    }

    usort($logros, function ($a, $b) {
        return $a['edad'] - $b['edad'];
    });

    // Cifras del encabezado: solo jugadores que aparecen en las posiciones
    $resumen = $conn->query("
        SELECT
            (SELECT COUNT(*) FROM EstResumenJugador s JOIN Users u ON u.UserId = s.UserId $unirPublico
             WHERE s.TotalPuntos > 0 AND $esPublico) AS jugadores,
            (SELECT COUNT(*) FROM EstRetos WHERE Activo = 1) AS retos,
            (SELECT COUNT(*) FROM EstInsigniasJugador ij JOIN Users u ON u.UserId = ij.UserId $unirPublico
             WHERE $esPublico) AS insignias,
            (SELECT COUNT(*) FROM EstMejoras m JOIN Users u ON u.UserId = m.UserId $unirPublico
             WHERE m.Fecha >= DATE_FORMAT(NOW(), '%Y-%m-01') AND $esPublico) AS mejorasMes
    ")->fetch(PDO::FETCH_ASSOC);

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
        'resumen'    => array_map('intval', $resumen),
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
require_once __DIR__ . '/plantilla.php';
hv_encabezado('Posiciones', 'posiciones');
?>
<header class="hv-heroe">
    <svg class="hv-pulso" viewBox="0 0 1200 80" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 45 H380 l18 -32 l24 64 l20 -54 l14 22 H760 l18 -32 l24 64 l20 -54 l14 22 H1200"/>
    </svg>
    <div class="container position-relative">
        <div class="row align-items-end g-4">
            <div class="col-lg-7 pb-4 pb-lg-5 hv-aparecer">
                <span class="hv-etiqueta"><i class="bi bi-heart-pulse"></i> Desafío Clínico</span>
                <h1 class="mt-3">Cada caso te hace mejor médico</h1>
                <p class="lead mb-4">
                    Resuelve casos clínicos con pacientes virtuales, supera los retos de cada sistema
                    y gana estrellas e insignias. Aquí se celebra a quienes más avanzan.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($conSesion): ?>
                        <a href="mi_avance.php" class="btn btn-oro btn-lg"><i class="bi bi-graph-up-arrow me-1"></i>Ver mi avance</a>
                        <a href="mis_grupos.php" class="btn btn-outline-light btn-lg"><i class="bi bi-people me-1"></i>Mis grupos</a>
                    <?php else: ?>
                        <a href="acceso.php" class="btn btn-oro btn-lg"><i class="bi bi-person-circle me-1"></i>Iniciar sesión</a>
                        <a href="#como" class="btn btn-outline-light btn-lg">¿Cómo se juega?</a>
                    <?php endif; ?>
                </div>
                <div class="row g-2 mt-4">
                    <div class="col-6 col-md-3"><div class="hv-indicador"><div class="hv-numero" id="datoJugadores">—</div><div class="hv-rotulo">jugadores en la tabla</div></div></div>
                    <div class="col-6 col-md-3"><div class="hv-indicador"><div class="hv-numero" id="datoRetos">—</div><div class="hv-rotulo">retos disponibles</div></div></div>
                    <div class="col-6 col-md-3"><div class="hv-indicador"><div class="hv-numero" id="datoInsignias">—</div><div class="hv-rotulo">insignias ganadas</div></div></div>
                    <div class="col-6 col-md-3"><div class="hv-indicador"><div class="hv-numero" id="datoMejoras">—</div><div class="hv-rotulo">mejoras este mes</div></div></div>
                </div>
            </div>
            <div class="col-lg-5 text-center d-none d-md-block hv-aparecer hv-aparecer-2">
                <img class="hv-ilustracion img-fluid" src="assets/img/medicos.png" alt="">
            </div>
        </div>
    </div>
</header>

<main class="container mt-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="hv-tarjeta p-3 p-md-4 hv-aparecer hv-aparecer-2" aria-labelledby="tituloPosiciones">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h4 mb-0" id="tituloPosiciones"><i class="bi bi-trophy-fill text-warning me-2"></i>Tablas de posiciones</h2>
                    <ul class="nav hv-pestanas" role="tablist">
                        <li class="nav-item"><button type="button" class="nav-link active" data-vista="general">General</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-vista="sistema">Por sistema</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-vista="mes">Del mes</button></li>
                    </ul>
                </div>

                <div id="filtroSistema" class="mb-3" hidden>
                    <label for="selectorSistema" class="form-label fw-bold small">Sistema</label>
                    <select id="selectorSistema" class="form-select"></select>
                </div>
                <p id="descripcionVista" class="text-muted small"></p>

                <div id="podio" class="hv-podio mb-4" aria-label="Tres primeros lugares"></div>

                <div id="contenedorTabla" class="table-responsive">
                    <table class="table table-hover align-middle hv-tabla">
                        <thead><tr id="encabezados"></tr></thead>
                        <tbody id="filas"></tbody>
                    </table>
                </div>

                <p class="text-muted small mt-3 mb-0" id="estado" role="status">Cargando…</p>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="hv-tarjeta mb-4 hv-aparecer hv-aparecer-3">
                <div class="hv-tarjeta-encabezado"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Logros recientes</div>
                <ul class="hv-logros" id="logros"></ul>
            </section>

            <section class="hv-tarjeta p-4 hv-aparecer hv-aparecer-3">
                <h2 class="h6 mb-3"><i class="bi bi-stars me-2 text-warning"></i>Niveles de cada reto</h2>
                <div class="d-flex flex-column gap-2 small">
                    <div><span class="hv-nivel hv-nivel-1">★ Bronce</span> <span class="text-muted ms-1">1 estrella</span></div>
                    <div><span class="hv-nivel hv-nivel-2">★★ Plata</span> <span class="text-muted ms-1">2 estrellas</span></div>
                    <div><span class="hv-nivel hv-nivel-3">★★★ Oro</span> <span class="text-muted ms-1">3 estrellas</span></div>
                    <div><span class="hv-nivel hv-nivel-4">★★★★ Platino</span> <span class="text-muted ms-1">4 estrellas</span></div>
                    <div><span class="hv-nivel hv-nivel-5">★★★★★ Diamante</span> <span class="text-muted ms-1">5 estrellas</span></div>
                </div>
            </section>
        </div>
    </div>

    <section id="como" class="mt-5 pt-2">
        <h2 class="text-center mb-1">¿Cómo se juega?</h2>
        <p class="text-center text-muted mb-4">Tres ideas para subir en la tabla.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="hv-tarjeta hv-tarjeta-animada hv-paso">
                    <span class="hv-icono"><i class="bi bi-clipboard2-pulse"></i></span>
                    <h3 class="h5">Resuelve casos clínicos</h3>
                    <p class="text-muted mb-0">Interroga al paciente virtual, explora, propón un diagnóstico y razona tu tratamiento.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="hv-tarjeta hv-tarjeta-animada hv-paso">
                    <span class="hv-icono hv-icono-turquesa"><i class="bi bi-arrow-up-right-circle"></i></span>
                    <h3 class="h5">Supera tu mejor marca</h3>
                    <p class="text-muted mb-0">Cada reto cuenta una sola vez, con tu mejor resultado. Repetirlo y mejorar suma la diferencia.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="hv-tarjeta hv-tarjeta-animada hv-paso">
                    <span class="hv-icono hv-icono-oro"><i class="bi bi-award"></i></span>
                    <h3 class="h5">Colecciona estrellas e insignias</h3>
                    <p class="text-muted mb-0">Llega hasta Diamante en cada reto y desbloquea insignias por tu precisión y tu razonamiento clínico.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php hv_pie(); ?>
<script>
    const SEGUNDOS_ACTUALIZACION = 60;
    const COLUMNAS = {
        general: [['Puntos', 'puntos'], ['Estrellas', 'estrellas'], ['Retos', 'retos'], ['Insignias', 'insignias']],
        sistema: [['Puntos', 'puntos'], ['Estrellas', 'estrellas'], ['Retos', 'retos']],
        mes: [['Puntos ganados', 'puntos'], ['Retos mejorados', 'retos']]
    };

    let datos = null;
    let vista = 'general';
    const selector = document.getElementById('selectorSistema');

    function elemento(etiqueta, texto, clase) {
        const e = document.createElement(etiqueta);
        if (texto !== undefined && texto !== null) e.textContent = texto;
        if (clase) e.className = clase;
        return e;
    }

    function iniciales(nombre) {
        const partes = String(nombre).trim().split(/[\s._-]+/).filter(Boolean);
        return (partes.slice(0, 2).map(p => p[0]).join('') || '?').toUpperCase();
    }

    function numero(n) {
        return Number(n).toLocaleString('es-MX');
    }

    function filasDeVista() {
        if (vista === 'general') return datos.general;
        if (vista === 'mes') return datos.mes;
        return datos.porSistema[selector.value] || [];
    }

    function pintarPodio(filas) {
        const podio = document.getElementById('podio');
        if (filas.length === 0) {
            const vacio = elemento('div', null, 'hv-vacio w-100');
            const mascota = elemento('img');
            mascota.src = 'assets/img/mascota-trofeo.png';
            mascota.alt = '';
            vacio.append(
                mascota,
                elemento('div', 'Todavía no hay resultados aquí.', 'fw-bold'),
                elemento('div', '¡El primer lugar te está esperando!', 'small')
            );
            podio.replaceChildren(vacio);
            return;
        }

        podio.replaceChildren(...filas.slice(0, 3).map((f, i) => {
            const lugar = elemento('div', null, 'hv-podio-lugar hv-podio-' + (i + 1));
            if (i === 0) lugar.append(elemento('span', '👑', 'hv-corona'));
            lugar.append(
                elemento('span', iniciales(f.jugador), 'hv-avatar'),
                elemento('div', f.jugador, 'hv-podio-nombre'),
                elemento('div', numero(f.puntos) + ' pts', 'hv-podio-puntos'),
                elemento('div', String(i + 1), 'hv-podio-base')
            );
            return lugar;
        }));
    }

    function pintarTabla() {
        const columnas = COLUMNAS[vista];
        const filas = filasDeVista();

        document.getElementById('filtroSistema').hidden = vista !== 'sistema';
        document.getElementById('descripcionVista').textContent =
            vista === 'mes' ? 'Puntos ganados en ' + datos.nombreMes + ' al mejorar resultados.' : '';

        pintarPodio(filas);

        const resto = filas.slice(3);
        document.getElementById('contenedorTabla').hidden = resto.length === 0;

        document.getElementById('encabezados').replaceChildren(
            elemento('th', 'Lugar', 'col-posicion'),
            elemento('th', 'Jugador'),
            ...columnas.map(c => elemento('th', c[0], 'col-numero'))
        );

        document.getElementById('filas').replaceChildren(...resto.map((f, i) => {
            const fila = document.createElement('tr');
            const lugar = elemento('td', null, 'col-posicion');
            lugar.append(elemento('span', String(i + 4), 'hv-lugar'));
            const jugador = elemento('td');
            const contenido = elemento('div', null, 'd-flex align-items-center gap-2');
            contenido.append(elemento('span', iniciales(f.jugador), 'hv-avatar'), elemento('span', f.jugador, 'fw-semibold'));
            jugador.append(contenido);
            fila.append(lugar, jugador, ...columnas.map(c => elemento('td', numero(f[c[1]]), 'col-numero')));
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
            const vacio = elemento('li', null, 'hv-vacio d-block');
            vacio.append(elemento('i', null, 'bi bi-stars'), elemento('div', 'Los primeros logros aparecerán aquí.'));
            lista.replaceChildren(vacio);
            return;
        }

        lista.replaceChildren(...datos.logros.map(l => {
            const item = document.createElement('li');
            const icono = elemento('span', null, 'hv-icono ' + (l.tipo === 'insignia' ? 'hv-icono-turquesa' : 'hv-icono-oro'));
            icono.append(elemento('i', null, 'bi ' + (l.tipo === 'insignia' ? 'bi-award-fill' : 'bi-star-fill')));
            const texto = elemento('div');
            texto.append(
                elemento('strong', l.jugador),
                document.createTextNode(' ' + l.texto),
                elemento('div', tiempoRelativo(l.edad), 'hv-logro-tiempo')
            );
            item.append(icono, texto);
            return item;
        }));
    }

    function pintarSistemas() {
        const elegido = selector.value;
        selector.replaceChildren(...datos.sistemas.map(s => {
            const opcion = elemento('option', s.nombre);
            opcion.value = s.clave;
            return opcion;
        }));
        if (datos.sistemas.some(s => s.clave === elegido)) selector.value = elegido;
    }

    function pintarResumen() {
        const r = datos.resumen || {};
        document.getElementById('datoJugadores').textContent = numero(r.jugadores || 0);
        document.getElementById('datoRetos').textContent = numero(r.retos || 0);
        document.getElementById('datoInsignias').textContent = numero(r.insignias || 0);
        document.getElementById('datoMejoras').textContent = numero(r.mejorasMes || 0);
    }

    async function actualizar() {
        const estado = document.getElementById('estado');
        try {
            const respuesta = await fetch('posiciones.php?datos=1', { cache: 'no-store' });
            const recibido = await respuesta.json();
            if (!recibido.ok) throw new Error('sin datos');

            datos = recibido;
            pintarResumen();
            pintarSistemas();
            pintarTabla();
            pintarLogros();
            estado.textContent = 'Actualizado a las ' + new Date().toLocaleTimeString('es-MX') + '. Se actualiza cada minuto.';
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
