<?php
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

// 1. SEGURIDAD ESTRICTA: Solo Administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: lista_casos.php?error=acceso_denegado");
    exit;
}

$rolUsuario = $_SESSION['rol'];

// Configuración de la BD
require_once __DIR__ . '/config_bd.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, datos_completos, fecha_registro FROM casos_clinicos ORDER BY id DESC");
    $casos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión: " . hv_error_interno($e));
}

// 2. SECCIONES QUE SE VERIFICAN
// Cada sección se mide con los campos que necesita el caso (los que lee el juego y los básicos del gestor).
// La historia clínica, la exploración, los laboratorios y el diagnóstico se miden por su texto completo,
// que en los casos nuevos se arma con los apartados y en los anteriores es texto libre; así ningún caso
// baja de porcentaje por no usar los apartados. El ART y los campos opcionales no cuentan.
$secciones = [
    'Información general'        => ['identificador', 'titulo_caso', 'departamentos', 'autores'],
    'Clasificación'              => ['sexo', 'edad', 'rango_edad', 'padecimiento', 'residencia', 'sistemas_anatomicos', 'asignaturas'],
    'Presentación'               => ['resumen'],
    'Historia clínica'           => ['historia'],
    'Exploración física'         => ['exploracion', 'signos_ta', 'signos_fc', 'signos_fr', 'signos_temp', 'signos_sato2', 'somatometria_peso', 'somatometria_talla'],
    'Laboratorios e imágenes'    => ['laboratorios'],
    'Diagnóstico y tratamiento'  => ['diagnostico_tratamiento'],
    'Diagnósticos diferenciales' => ['diag_dif_1', 'diag_dif_favor_1', 'diag_dif_contra_1', 'diagnostico_correcto'],
    'Preguntas'                  => ['pregunta_1', 'p1_opcion_1', 'p1_opcion_2', 'p1_opcion_3', 'p1_opcion_4', 'p1_correcta'],
    'Nota médica y SOAP'         => ['nota_medica', 'soap_subjetivo', 'soap_objetivo', 'soap_analisis', 'soap_plan'],
    'Paciente virtual'           => ['prompts', 'tipo_interrogatorio'],
];

// Apartados del formulario por secciones (solo informativo: cuántos tiene llenos el caso)
$prefijosApartados = [
    'Historia clínica'          => '/^(hc_|ias_)/',
    'Exploración física'        => '/^ef_/',
    'Laboratorios e imágenes'   => '/^(lab_|micro_|img_|est_)/',
    'Diagnóstico y tratamiento' => '/^(dx_|tx_|seg_|estadificacion$)/',
];

// 3. FUNCIÓN PARA EVALUAR SI UN DATO ESTÁ VACÍO
function esta_vacio($valor) {
    if (!is_array($valor)) {
        return ($valor === null || trim((string)$valor) === '');
    }
    foreach ($valor as $v) {
        if (!esta_vacio($v)) {
            return false;
        }
    }
    return true;
}

// 4. VERIFICACIÓN DE CADA CASO
$casos_procesados = [];
$total_requisitos = array_sum(array_map('count', $secciones));
$casos_completos = 0;
$suma_porcentajes = 0;

foreach ($casos_raw as $fila) {
    $datos = json_decode($fila['datos_completos'], true);
    if (!is_array($datos)) $datos = [];

    $resultado = [];
    $llenos_caso = 0;
    foreach ($secciones as $nombre => $campos) {
        $faltan = [];
        foreach ($campos as $campo) {
            if (!isset($datos[$campo]) || esta_vacio($datos[$campo])) {
                $faltan[] = $campo;
            }
        }
        $apartados = 0;
        if (isset($prefijosApartados[$nombre])) {
            foreach ($datos as $clave => $valor) {
                if (preg_match($prefijosApartados[$nombre], $clave) && !esta_vacio($valor)) {
                    $apartados++;
                }
            }
        }
        $llenos = count($campos) - count($faltan);
        $llenos_caso += $llenos;
        $resultado[$nombre] = ['llenos' => $llenos, 'total' => count($campos), 'faltan' => $faltan, 'apartados' => $apartados];
    }

    $porcentaje = $total_requisitos > 0 ? (int)round($llenos_caso / $total_requisitos * 100) : 0;
    if ($llenos_caso === $total_requisitos) $casos_completos++;
    $suma_porcentajes += $porcentaje;

    $casos_procesados[] = [
        'id' => $fila['id'],
        'identificador' => isset($datos['identificador']) && trim((string)$datos['identificador']) !== '' ? (string)$datos['identificador'] : '',
        'secciones' => $resultado,
        'porcentaje' => $porcentaje,
    ];
}

$total_casos = count($casos_procesados);
$promedio = $total_casos > 0 ? (int)round($suma_porcentajes / $total_casos) : 0;

// 5. LÓGICA DE PAGINACIÓN (10 casos por página)
$casos_por_pagina = 10;
$total_paginas = (int)ceil($total_casos / $casos_por_pagina);

$pagina_actual = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
if ($pagina_actual > $total_paginas && $total_paginas > 0) $pagina_actual = $total_paginas;

$offset = ($pagina_actual - 1) * $casos_por_pagina;
$casos_mostrar = array_slice($casos_procesados, $offset, $casos_por_pagina);

?>
<?php
require_once __DIR__ . '/plantilla.php';
hv_encabezado('Verificador', 'verificador');
hv_cabecera('Verificador de integridad', 'Revisa qué secciones de cada caso clínico están completas.', 'bi-ui-checks-grid');
?>

<div class="container-fluid px-3 px-lg-4 pb-5 position-relative">
    <section class="hv-tarjeta p-3 p-md-4 mb-4 hv-aparecer">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="hv-icono"><i class="bi bi-ui-checks-grid"></i></span>
            <div class="me-auto">
                <div class="hv-titulo h5 mb-0">Análisis de completitud por secciones</div>
                <div class="text-muted small">Página <?= (int)$pagina_actual ?> de <?= (int)($total_paginas ?: 1) ?> · <?= (int)$total_casos ?> casos · <?= (int)$casos_completos ?> completos · promedio <?= (int)$promedio ?> %</div>
            </div>
        </div>
        <div class="small text-muted mt-3">
            <span class="badge bg-success">Completa</span>
            <span class="badge bg-warning text-dark">Incompleta (llenos / necesarios)</span>
            <span class="badge bg-danger">Vacía</span>
            · Pasa el cursor sobre una sección para ver los campos que faltan. "apartados" indica cuántos apartados del formato nuevo tiene llenos.
        </div>
    </section>

    <div class="hv-tarjeta overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle hv-tabla">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 80px;">ID</th>
                            <th style="width: 150px;">Identificador</th>
                            <th>Secciones</th>
                            <th class="text-center" style="width: 110px;">Completitud</th>
                            <th class="text-center" style="width: 80px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($casos_mostrar as $caso): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= (int)$caso['id'] ?></td>
                                <td>
                                    <?php if ($caso['identificador'] !== ''): ?>
                                        <span class="badge bg-primary text-wrap"><?= htmlspecialchars($caso['identificador']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($caso['secciones'] as $nombre => $s): ?>
                                        <?php
                                            if ($s['llenos'] === $s['total']) {
                                                $clase = 'bg-success';
                                                $icono = 'bi-check';
                                            } elseif ($s['llenos'] === 0) {
                                                $clase = 'bg-danger';
                                                $icono = 'bi-x';
                                            } else {
                                                $clase = 'bg-warning text-dark';
                                                $icono = 'bi-exclamation';
                                            }
                                            $titulo = empty($s['faltan']) ? 'Completa' : 'Falta: ' . implode(', ', $s['faltan']);
                                        ?>
                                        <span class="badge <?= $clase ?> badge-variable" title="<?= htmlspecialchars($titulo) ?>">
                                            <i class="bi <?= $icono ?> me-1"></i><?= htmlspecialchars($nombre) ?><?php if ($s['llenos'] !== $s['total'] && $s['total'] > 1): ?> <?= (int)$s['llenos'] ?>/<?= (int)$s['total'] ?><?php endif; ?><?php if ($s['apartados'] > 0): ?> · <?= (int)$s['apartados'] ?> apartados<?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-center fw-bold fs-5">
                                    <?php $color = $caso['porcentaje'] > 80 ? 'text-success' : ($caso['porcentaje'] > 50 ? 'text-warning' : 'text-danger'); ?>
                                    <span class="<?= $color ?>"><?= (int)$caso['porcentaje'] ?>%</span>
                                </td>
                                <td class="text-center align-middle">
                                    <a href="caso_editar.php?id=<?= (int)$caso['id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="Editar este caso">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($casos_mostrar)): ?>
                            <tr><td colspan="5" class="text-center py-4">No hay casos clínicos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Navegación de páginas de auditoría">
        <ul class="pagination justify-content-center flex-wrap">

            <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?p=<?= $pagina_actual - 1 ?>">Anterior</a>
            </li>

            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <li class="page-item <?= ($i === $pagina_actual) ? 'active' : '' ?>">
                    <a class="page-link" href="?p=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="?p=<?= $pagina_actual + 1 ?>">Siguiente</a>
            </li>

        </ul>
    </nav>
    <?php endif; ?>

</div>
<?php hv_pie(); ?>
</body>
</html>
