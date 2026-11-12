<?php
// Reporte de varios casos clínicos (los seleccionados en la lista o todos los de la búsqueda) en un solo
// documento para imprimir o guardar como PDF. Solo administradores.
// Cada caso lo arma reporte_caso.php?incrustado=1 en un marco invisible y envía aquí su reporte, de modo
// que un caso se ve igual en el reporte individual y en el de varios.
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
hv_bloquear_invitado();

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Solo un administrador puede generar reportes de casos.');
}

header('Cache-Control: no-store');
require_once __DIR__ . '/config_bd.php';

if (!function_exists('h')) {
    function h($texto)
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

const REPORTE_MAX_CASOS = 500;

// Casos pedidos: POST con los seleccionados en la lista, o GET ?todos=1 (con el mismo filtro de búsqueda)
$seleccion = null;
$filtro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hv_csrf_valido($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('La solicitud no es válida. Vuelve a la lista de casos e inténtalo de nuevo.');
    }
    $seleccion = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), function ($id) {
        return $id > 0;
    })));
    if (!$seleccion) {
        header('Location: lista_casos.php');
        exit;
    }
} elseif (!empty($_GET['todos'])) {
    $filtro = trim((string)($_GET['departamento'] ?? ''));
} else {
    header('Location: lista_casos.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($seleccion !== null) {
        $seleccion = array_slice($seleccion, 0, REPORTE_MAX_CASOS);
        $marcas = implode(',', array_fill(0, count($seleccion), '?'));
        $stmt = $pdo->prepare("SELECT id, datos_completos, fecha_registro FROM casos_clinicos WHERE id IN ($marcas) ORDER BY id ASC");
        $stmt->execute($seleccion);
    } elseif ($filtro !== '') {
        // Mismo filtro que la búsqueda de lista_casos.php
        $stmt = $pdo->prepare("SELECT id, datos_completos, fecha_registro FROM casos_clinicos WHERE datos_completos LIKE :filtro ORDER BY id ASC LIMIT " . REPORTE_MAX_CASOS);
        $stmt->execute([':filtro' => '%' . $filtro . '%']);
    } else {
        $stmt = $pdo->query("SELECT id, datos_completos, fecha_registro FROM casos_clinicos ORDER BY id ASC LIMIT " . REPORTE_MAX_CASOS);
    }
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('No se pudieron leer los casos: ' . hv_error_interno($e));
}

$casos = [];
foreach ($filas as $fila) {
    $datos = json_decode((string)$fila['datos_completos'], true);
    $datos = is_array($datos) ? $datos : [];
    $casos[] = [
        'id'            => (int)$fila['id'],
        'identificador' => trim((string)($datos['identificador'] ?? '')),
        'titulo'        => trim((string)($datos['titulo_caso'] ?? '')),
        'sistemas'      => implode(', ', array_filter(array_map('strval', (array)($datos['sistemas_anatomicos'] ?? [])), 'strlen')),
        'registro'      => $fila['fecha_registro'] ? date('d/m/Y', strtotime($fila['fecha_registro'])) : '',
    ];
}

$total = count($casos);
$generadoPor = (string)($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '');
if ($seleccion !== null) {
    $origen = 'Casos seleccionados en la lista';
} elseif ($filtro !== '') {
    $origen = 'Casos de la búsqueda «' . $filtro . '»';
} else {
    $origen = 'Todos los casos del banco';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de <?= $total ?> casos clínicos · Gestor de Casos Clínicos</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/gestor.css" rel="stylesheet">
    <link href="assets/css/reporte.css?v=2" rel="stylesheet">
</head>
<body class="hv-modo-reporte">

<div class="rep-herramientas">
    <div class="rep-herramientas-contenido">
        <div class="rep-herramientas-texto">
            <strong>Reporte de <?= $total ?> <?= $total === 1 ? 'caso' : 'casos' ?></strong>
            <span id="estado">Preparando los casos…</span>
        </div>
        <div class="rep-progreso" aria-hidden="true"><div id="barra"></div></div>
        <div class="d-flex gap-2">
            <a href="lista_casos.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver a la lista</a>
            <button type="button" class="btn btn-oro btn-sm" id="btn-imprimir" disabled><i class="bi bi-file-earmark-pdf me-1"></i>Imprimir o guardar como PDF</button>
        </div>
    </div>
</div>

<div class="rep-documento" id="documento">
    <section class="rep-portada-lote">
        <div class="rep-banda">
            <div class="rep-logos">
                <img src="assets/img/escudo-unam-blanco.png" alt="UNAM">
                <img src="assets/img/escudo-fm-blanco-web.png" alt="Facultad de Medicina">
            </div>
            <div class="rep-institucion">
                <div class="rep-institucion-1">Universidad Nacional Autónoma de México · Facultad de Medicina</div>
                <div class="rep-institucion-2">Hospital Virtual FacMed · Banco de casos clínicos</div>
            </div>
        </div>
        <div class="rep-tipo">Reporte de casos clínicos</div>
        <h1 class="rep-lote-titulo"><?= $total ?> <?= $total === 1 ? 'caso clínico' : 'casos clínicos' ?></h1>
        <p class="rep-lote-subtitulo"><?= h($origen) ?> · generado el <?= h(date('d/m/Y \a \l\a\s H:i')) ?><?= $generadoPor !== '' ? ' por ' . h($generadoPor) : '' ?></p>
        <p class="rep-confidencial mb-4">Documento de uso académico interno: contiene los diagnósticos y las respuestas de los casos. No lo compartas con los estudiantes.</p>

        <?php if ($total === 0): ?>
            <p>No hay casos que incluir.</p>
        <?php else: ?>
            <h2 class="h5">Índice</h2>
            <table class="rep-indice">
                <thead>
                    <tr><th class="rep-num">#</th><th>Identificador</th><th>Caso</th><th>Sistema(s)</th><th>Registro</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($casos as $i => $caso): ?>
                        <tr>
                            <td class="rep-num"><?= $i + 1 ?></td>
                            <td><?= h($caso['identificador'] !== '' ? $caso['identificador'] : 'Sin asignar') ?></td>
                            <td><?= h($caso['titulo'] !== '' ? $caso['titulo'] : 'Caso sin título') ?></td>
                            <td><?= h($caso['sistemas']) ?></td>
                            <td><?= h($caso['registro']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php foreach ($casos as $caso): ?>
        <article class="rep-caso rep-pendiente" data-id="<?= $caso['id'] ?>">
            <p class="text-muted mb-0"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Preparando el caso #<?= $caso['id'] ?>…</p>
        </article>
    <?php endforeach; ?>
</div>

<script>
(function () {
    'use strict';
    const ids = <?= json_encode(array_column($casos, 'id')) ?>;
    const SIMULTANEOS = 3;           // marcos que se cargan a la vez
    const ESPERA_MAXIMA_MS = 90000;  // por caso
    const estado = document.getElementById('estado');
    const barra = document.getElementById('barra');
    const boton = document.getElementById('btn-imprimir');
    const pendientes = ids.slice();
    const enCurso = new Map();       // id → {marco, temporizador}
    let terminados = 0;
    const fallidos = [];

    boton.addEventListener('click', () => window.print());

    function actualizar() {
        const porcentaje = ids.length ? Math.round(terminados * 100 / ids.length) : 100;
        barra.style.width = porcentaje + '%';
        if (terminados < ids.length) {
            estado.textContent = 'Preparando ' + (terminados + 1) + ' de ' + ids.length + '… (puede tardar un poco)';
            return;
        }
        boton.disabled = false;
        estado.textContent = fallidos.length
            ? 'Listo, pero ' + fallidos.length + ' caso(s) no se pudieron generar: #' + fallidos.join(', #') + '.'
            : 'Listo. En el diálogo de impresión elige «Guardar como PDF».';
    }

    function colocar(id, html) {
        const lugar = document.querySelector('.rep-pendiente[data-id="' + id + '"]');
        if (!lugar) return;
        const plantilla = document.createElement('template');
        plantilla.innerHTML = html;
        const reporte = plantilla.content.firstElementChild;
        if (reporte) lugar.replaceWith(reporte); else lugar.classList.remove('rep-pendiente');
    }

    function terminar(id, html) {
        const carga = enCurso.get(id);
        if (!carga) return;
        clearTimeout(carga.temporizador);
        carga.marco.remove();
        enCurso.delete(id);
        if (html) {
            colocar(id, html);
        } else {
            fallidos.push(id);
            colocar(id, '<article class="rep-caso rep-error"><p>No se pudo generar el reporte del caso #' + id + '. Ábrelo desde la lista para revisarlo.</p></article>');
        }
        terminados++;
        actualizar();
        siguiente();
    }

    function siguiente() {
        while (enCurso.size < SIMULTANEOS && pendientes.length) {
            const id = pendientes.shift();
            const marco = document.createElement('iframe');
            marco.setAttribute('aria-hidden', 'true');
            marco.tabIndex = -1;
            marco.style.cssText = 'position:absolute;width:1200px;height:800px;left:-9999px;top:0;border:0;';
            marco.src = 'reporte_caso.php?incrustado=1&id=' + encodeURIComponent(id);
            const temporizador = setTimeout(() => terminar(id, null), ESPERA_MAXIMA_MS);
            enCurso.set(id, { marco, temporizador });
            // El reporte se envía antes de que el marco termine de cargar; si cargó y no llegó nada
            // (caso inexistente, sesión vencida), no se espera el tiempo máximo
            marco.addEventListener('load', () => setTimeout(() => {
                const carga = enCurso.get(id);
                if (carga && carga.marco === marco) terminar(id, null);
            }, 5000));
            document.body.appendChild(marco);
        }
    }

    window.addEventListener('message', (evento) => {
        if (evento.origin !== window.location.origin) return;
        const datos = evento.data;
        if (!datos || datos.tipo !== 'hv-reporte-caso') return;
        const id = parseInt(datos.id, 10);
        const carga = enCurso.get(id);
        if (!carga || evento.source !== carga.marco.contentWindow) return;
        terminar(id, typeof datos.html === 'string' ? datos.html : null);
    });

    actualizar();
    siguiente();
})();
</script>
</body>
</html>
