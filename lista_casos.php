<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();
// Si no hay sesión, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
$rolUsuario = $_SESSION['rol']; // 'Invitado', 'Editor', o 'Administrador'

require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/seguridad.php';
hv_bloquear_invitado();

try {
    // Conexión usando PDO para máxima seguridad
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión a la Base de Datos: " . hv_error_interno($e));
}

// Procesar eliminación si se recibe el ID por parámetro GET
if (isset($_GET['eliminar'])) {
    if ($rolUsuario !== 'Administrador' || !hv_csrf_valido($_GET['csrf'] ?? null)) {
        http_response_code(403);
        die("Acceso denegado: no tienes permiso para eliminar casos.");
    }

    $idEliminar = intval($_GET['eliminar']);
    try {
        // Rutas de los adjuntos antes de borrar el registro; los archivos se borran después de que
        // la base confirme el borrado (si fallara, el caso y sus archivos se quedan como estaban)
        $stmtRutas = $pdo->prepare("SELECT rutas_archivos FROM casos_clinicos WHERE id = :id");
        $stmtRutas->execute([':id' => $idEliminar]);
        $rutasAdjuntos = $stmtRutas->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM casos_clinicos WHERE id = :id");
        $stmt->execute([':id' => $idEliminar]);

        $parametros = 'msg=deleted';
        if ($stmt->rowCount() > 0 && $rutasAdjuntos) {
            require_once __DIR__ . '/subidas.php';
            [$borrados, $noEncontrados, $fallidos] = hv_subidas_borrar($rutasAdjuntos);
            $parametros .= '&adjuntos=' . (int)$borrados . ($fallidos > 0 ? '&adjuntos_fallidos=' . (int)$fallidos : '');
        }
        header("Location: lista_casos.php?" . $parametros);
        exit;
    } catch(Exception $e) {
        $error = "Error al eliminar el registro: " . hv_error_interno($e);
    }
}

// ==========================================
// LÓGICA DE FILTRADO POR DEPARTAMENTO
// ==========================================
$filtro_departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$where_clause = "";
$params = [];

// Si el usuario escribió algo en el buscador, preparamos el filtro SQL
if (!empty($filtro_departamento)) {
    // Usamos LIKE para buscar el término de forma segura dentro del texto JSON
    $where_clause = " WHERE datos_completos LIKE :depto ";
    $params[':depto'] = '%' . $filtro_departamento . '%';
}

// ==========================================
// LÓGICA DE PAGINACIÓN ACTUALIZADA
// ==========================================
$casos_por_pagina = 10;
$pagina_actual = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

// 1. Contar cuántos casos hay (aplicando el filtro si existe)
$sqlTotal = "SELECT COUNT(*) FROM casos_clinicos" . $where_clause;
$stmtTotal = $pdo->prepare($sqlTotal);
if (!empty($filtro_departamento)) {
    $stmtTotal->bindValue(':depto', $params[':depto']);
}
$stmtTotal->execute();
$total_casos = $stmtTotal->fetchColumn();

$total_paginas = ceil($total_casos / $casos_por_pagina);
if ($pagina_actual > $total_paginas && $total_paginas > 0) $pagina_actual = $total_paginas;
$offset = ($pagina_actual - 1) * $casos_por_pagina;

// 2. Traer los casos filtrados
$sql = "SELECT id, datos_completos, fecha_registro FROM casos_clinicos" . $where_clause . " ORDER BY id ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

if (!empty($filtro_departamento)) {
    $stmt->bindValue(':depto', $params[':depto']);
}
$stmt->bindValue(':limit', $casos_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los casos clínicos organizados del más reciente al más antiguo
//$stmt = $pdo->query("SELECT id, datos_completos, fecha_registro FROM casos_clinicos ORDER BY fecha_registro DESC");
//$casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- ESTADÍSTICA: Contar el número total de casos en el arreglo ---
//$totalCasos = count($casos);
$totalCasos = $total_casos;

// Total sin filtro, para mostrar cuántos casos hay en el banco
$total_general = $filtro_departamento !== '' ? (int)$pdo->query("SELECT COUNT(*) FROM casos_clinicos")->fetchColumn() : (int)$total_casos;

require_once __DIR__ . '/plantilla.php';

$mensajes = [
    'success' => ['success', 'bi-check-circle-fill', '¡El nuevo caso clínico se registró y guardó con éxito!'],
    'updated' => ['success', 'bi-pencil-square', '¡El caso clínico se actualizó correctamente!'],
    'deleted' => ['success', 'bi-trash', 'El caso clínico se eliminó de la base de datos.'],
];
$mensaje = $mensajes[$_GET['msg'] ?? ''] ?? null;
// Al eliminar: cuántos archivos adjuntos se borraron y si alguno no se pudo borrar
if (($_GET['msg'] ?? '') === 'deleted' && isset($_GET['adjuntos'])) {
    $numAdjuntos = (int)$_GET['adjuntos'];
    if ($numAdjuntos > 0) {
        $mensaje[2] .= ' También se borraron ' . ($numAdjuntos === 1 ? '1 archivo adjunto.' : $numAdjuntos . ' archivos adjuntos.');
    }
    if ((int)($_GET['adjuntos_fallidos'] ?? 0) > 0) {
        $mensaje[0] = 'warning';
        $mensaje[2] .= ' No se pudieron borrar ' . (int)$_GET['adjuntos_fallidos'] . ' archivo(s) adjunto(s); revisa la carpeta uploads/ en el servidor.';
    }
}
$esAdmin = $rolUsuario === 'Administrador';

hv_encabezado('Casos clínicos', 'casos');
hv_cabecera('Casos clínicos', 'Banco de casos que se juegan en Hospital Virtual FacMed.', 'bi-clipboard2-pulse', 'assets/img/cabecera-casos.png');
?>
<main class="container pb-5 position-relative">

    <section class="hv-tarjeta p-3 p-md-4 mb-4 hv-aparecer">
        <div class="row g-3 align-items-center">
            <div class="col-lg-auto d-flex align-items-center gap-3 me-lg-auto">
                <span class="hv-icono"><i class="bi bi-folder2-open"></i></span>
                <div>
                    <div class="hv-titulo h4 mb-0"><?= number_format($total_casos) ?> <?= $total_casos == 1 ? 'caso' : 'casos' ?></div>
                    <div class="text-muted small">
                        <?php if ($filtro_departamento !== ''): ?>
                            con «<?= h($filtro_departamento) ?>» de <?= number_format($total_general) ?> en total
                        <?php else: ?>
                            registrados en el banco
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <form action="lista_casos.php" method="GET" class="m-0" role="search">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" name="departamento" class="form-control" placeholder="Buscar por departamento, autor o palabra…" aria-label="Buscar casos" value="<?= h($filtro_departamento) ?>">
                        <?php if ($filtro_departamento !== ''): ?>
                            <a href="lista_casos.php" class="btn btn-outline-secondary" title="Quitar el filtro" aria-label="Quitar el filtro"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-auto d-flex flex-wrap gap-2">
                <a href="nuevo_caso.php" class="btn btn-turquesa"><i class="bi bi-plus-circle me-1"></i>Nuevo caso</a>
                <?php if ($esAdmin): ?>
                    <a href="descargar_backup.php" class="btn btn-outline-primary" title="Descargar todos los casos en un ZIP (respaldo)"><i class="bi bi-file-earmark-zip me-1"></i>Respaldo ZIP</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($esAdmin && $total_casos > 0): ?>
            <!-- Reportes en PDF (solo administradores): los seleccionados (se recuerdan al cambiar de página) o todos -->
            <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
                <span class="small fw-bold text-muted me-1"><i class="bi bi-file-earmark-pdf me-1"></i>Reportes PDF:</span>
                <form action="reporte_casos.php" method="POST" target="_blank" id="form-reporte-seleccion" class="m-0">
                    <input type="hidden" name="csrf" value="<?= h(hv_token_csrf()) ?>">
                    <button type="submit" class="btn btn-sm btn-primary" id="btn-reporte-seleccion" disabled>
                        <i class="bi bi-check2-square me-1"></i>De los seleccionados (<span id="conteo-seleccion">0</span>)
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-link text-decoration-none d-none" id="btn-limpiar-seleccion">Quitar la selección</button>
                <a href="reporte_casos.php?todos=1<?= $filtro_departamento !== '' ? '&amp;departamento=' . h(urlencode($filtro_departamento)) : '' ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"
                   title="Un solo documento con <?= $filtro_departamento !== '' ? 'los casos de la búsqueda' : 'todos los casos del banco' ?>">
                    <i class="bi bi-files me-1"></i><?= $filtro_departamento !== '' ? 'De los ' . number_format($total_casos) . ' de la búsqueda' : 'De todos (' . number_format($total_casos) . ')' ?>
                </a>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($mensaje !== null): ?>
        <div class="alert alert-<?= $mensaje[0] ?> alert-dismissible fade show" role="alert">
            <i class="bi <?= $mensaje[1] ?> me-2"></i><?= h($mensaje[2]) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= h($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <section class="hv-tarjeta overflow-hidden hv-aparecer hv-aparecer-2">
        <?php if (count($casos) === 0): ?>
            <div class="hv-vacio">
                <img src="assets/img/mascota-busqueda.png" alt="">
                <?php if ($filtro_departamento !== ''): ?>
                    <p class="fw-bold mb-1">No encontramos casos con «<?= h($filtro_departamento) ?>».</p>
                    <p class="mb-3">Prueba con otra palabra o quita el filtro.</p>
                    <a href="lista_casos.php" class="btn btn-outline-primary btn-sm">Ver todos los casos</a>
                <?php else: ?>
                    <p class="fw-bold mb-1">Aún no hay casos clínicos registrados.</p>
                    <a href="nuevo_caso.php" class="btn btn-turquesa btn-sm mt-2"><i class="bi bi-plus-circle me-1"></i>Crear el primero</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle hv-tabla">
                <thead>
                    <tr>
                        <?php if ($esAdmin): ?>
                            <th class="text-center" style="width: 2.5rem">
                                <input class="form-check-input" type="checkbox" id="seleccionar-pagina" title="Seleccionar los casos de esta página" aria-label="Seleccionar los casos de esta página">
                            </th>
                        <?php endif; ?>
                        <th>Identificador</th>
                        <th style="min-width: 16rem">Caso</th>
                        <th>Departamentos</th>
                        <th>Autores</th>
                        <th>Registro</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($casos as $row):
                        $datos = json_decode($row['datos_completos'], true);
                        if (!is_array($datos)) {
                            $datos = [];
                        }
                        $identificador = isset($datos['identificador']) && trim((string)$datos['identificador']) !== '' ? (string)$datos['identificador'] : '';
                        $titulo = isset($datos['titulo_caso']) && trim((string)$datos['titulo_caso']) !== '' ? (string)$datos['titulo_caso'] : 'Sin título';

                        $autores = array_filter(array_map('trim', array_map('strval', (array)($datos['autores'] ?? []))), 'strlen');

                        $departamentos = $datos['departamentos'] ?? null;
                        if (empty($departamentos)) {
                            $departamentos = $datos['departamento'] ?? [];
                        }
                        $departamentos = array_filter(array_map('trim', array_map('strval', (array)$departamentos)), 'strlen');
                    ?>
                        <tr>
                            <?php if ($esAdmin): ?>
                                <td class="text-center">
                                    <input class="form-check-input seleccion-caso" type="checkbox" value="<?= (int)$row['id'] ?>" aria-label="Incluir el caso <?= h($identificador !== '' ? $identificador : '#' . $row['id']) ?> en el reporte">
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($identificador !== ''): ?>
                                    <span class="hv-chip"><?= h($identificador) ?></span>
                                <?php else: ?>
                                    <span class="hv-chip hv-chip-pendiente" title="Lo asigna un administrador">Sin asignar</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?= h($titulo) ?></td>
                            <td>
                                <?php if ($departamentos): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($departamentos as $dep): ?>
                                            <span class="hv-chip hv-chip-turquesa"><?= h($dep) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">No asignado</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($autores): ?>
                                    <?php foreach ($autores as $autor): ?>
                                        <div><i class="bi bi-person text-muted me-1"></i><?= h($autor) ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No especificados</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small text-nowrap"><?= h(date('d/m/Y', strtotime($row['fecha_registro']))) ?></td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group hv-acciones" role="group" aria-label="Acciones del caso">
                                    <a href="ver_caso.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver el caso"><i class="bi bi-eye"></i><span class="visually-hidden">Ver</span></a>
                                    <a href="caso_editar.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar el caso"><i class="bi bi-pencil"></i><span class="visually-hidden">Editar</span></a>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-descargar-json"
                                            data-titulo="<?= h(pathinfo($identificador !== '' ? $identificador : 'caso_' . $row['id'], PATHINFO_FILENAME)) ?>"
                                            data-json="<?= h($row['datos_completos']) ?>"
                                            title="Descargar el JSON del caso"><i class="bi bi-filetype-json"></i><span class="visually-hidden">JSON</span></button>
                                    <a href="descargar_xml.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Descargar el XML del caso"><i class="bi bi-filetype-xml"></i><span class="visually-hidden">XML</span></a>
                                    <?php if ($esAdmin): ?>
                                        <a href="lista_casos.php?eliminar=<?= (int)$row['id'] ?>&amp;csrf=<?= h(hv_token_csrf()) ?>" class="btn btn-sm btn-outline-danger" title="Eliminar el caso"
                                           onclick="return confirm('¿Eliminar este caso clínico? No se puede deshacer.');"><i class="bi bi-trash"></i><span class="visually-hidden">Eliminar</span></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($total_paginas > 1):
        $url_filtro = $filtro_departamento !== '' ? '&departamento=' . urlencode($filtro_departamento) : '';
    ?>
        <nav aria-label="Páginas de casos clínicos" class="mt-4">
            <ul class="pagination hv-paginacion justify-content-center flex-wrap">
                <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $pagina_actual - 1 ?><?= h($url_filtro) ?>" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?= ($i === $pagina_actual) ? 'active' : '' ?>">
                        <a class="page-link" href="?p=<?= $i ?><?= h($url_filtro) ?>"<?= $i === $pagina_actual ? ' aria-current="page"' : '' ?>><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?p=<?= $pagina_actual + 1 ?><?= h($url_filtro) ?>" aria-label="Página siguiente"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>
<?php hv_pie(); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Selección para el reporte PDF (solo administradores). Se guarda en esta pestaña para que no se
    // pierda al cambiar de página o al buscar.
    const formReporte = document.getElementById('form-reporte-seleccion');
    if (formReporte) {
        const CLAVE = 'hv_reporte_seleccion';
        const leer = () => { try { return JSON.parse(sessionStorage.getItem(CLAVE) || '[]').filter(n => Number.isInteger(n)); } catch (e) { return []; } };
        const escribir = lista => { try { sessionStorage.setItem(CLAVE, JSON.stringify(lista)); } catch (e) { /* sin almacenamiento */ } };
        let seleccion = new Set(leer());
        const casillas = Array.from(document.querySelectorAll('.seleccion-caso'));
        const todasPagina = document.getElementById('seleccionar-pagina');
        const boton = document.getElementById('btn-reporte-seleccion');
        const limpiar = document.getElementById('btn-limpiar-seleccion');

        function pintar() {
            casillas.forEach(c => { c.checked = seleccion.has(parseInt(c.value, 10)); });
            if (todasPagina) {
                const marcadas = casillas.filter(c => c.checked).length;
                todasPagina.checked = casillas.length > 0 && marcadas === casillas.length;
                todasPagina.indeterminate = marcadas > 0 && marcadas < casillas.length;
            }
            document.getElementById('conteo-seleccion').textContent = seleccion.size;
            boton.disabled = seleccion.size === 0;
            limpiar.classList.toggle('d-none', seleccion.size === 0);
            escribir(Array.from(seleccion));
        }

        casillas.forEach(c => c.addEventListener('change', () => {
            const id = parseInt(c.value, 10);
            if (c.checked) seleccion.add(id); else seleccion.delete(id);
            pintar();
        }));
        if (todasPagina) todasPagina.addEventListener('change', () => {
            casillas.forEach(c => { const id = parseInt(c.value, 10); if (todasPagina.checked) seleccion.add(id); else seleccion.delete(id); });
            pintar();
        });
        limpiar.addEventListener('click', () => { seleccion = new Set(); pintar(); });

        // Al enviar, los ids seleccionados viajan como campos ocultos
        formReporte.addEventListener('submit', () => {
            formReporte.querySelectorAll('input[name="ids[]"]').forEach(e => e.remove());
            seleccion.forEach(id => {
                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = 'ids[]';
                campo.value = id;
                formReporte.appendChild(campo);
            });
        });
        pintar();
    }

    // Descargar el JSON de un caso desde la lista
    document.querySelectorAll('.btn-descargar-json').forEach(boton => {
        boton.addEventListener('click', function () {
            try {
                const objetoJson = JSON.parse(this.getAttribute('data-json'));
                let tituloCaso = this.getAttribute('data-titulo') || 'caso_clinico';
                tituloCaso = tituloCaso.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]/gi, '_');

                const blob = new Blob([JSON.stringify(objetoJson, null, 4)], { type: 'application/json' });
                const enlaceTemporal = document.createElement('a');
                enlaceTemporal.href = URL.createObjectURL(blob);
                enlaceTemporal.download = `caso_${tituloCaso}.json`;
                document.body.appendChild(enlaceTemporal);
                enlaceTemporal.click();
                document.body.removeChild(enlaceTemporal);
                URL.revokeObjectURL(enlaceTemporal.href);
            } catch (error) {
                console.error("Error al procesar el archivo JSON:", error);
                alert("Ocurrió un error inesperado al preparar la descarga.");
            }
        });
    });
});
</script>
</body>
</html>
