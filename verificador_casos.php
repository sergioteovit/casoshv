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

    // Obtener todos los casos para construir la lista maestra de variables
    $stmt = $pdo->query("SELECT id, datos_completos, fecha_registro FROM casos_clinicos ORDER BY id DESC");
    $casos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión: " . hv_error_interno($e));
}

// 2. PROCESAMIENTO DE JSON Y LISTA MAESTRA DE VARIABLES
$master_keys = []; 
$casos_procesados = [];

foreach ($casos_raw as $fila) {
    $datos = json_decode($fila['datos_completos'], true);
    if (!is_array($datos)) $datos = [];

    // Recolectar claves únicas
    foreach (array_keys($datos) as $key) {
        $master_keys[$key] = true;
    }

    $casos_procesados[] = [
        'id' => $fila['id'],
        'fecha' => $fila['fecha_registro'],
        'datos' => $datos
    ];
}

// Ordenar la lista maestra alfabéticamente
$master_keys = array_keys($master_keys);
sort($master_keys);

// 3. FUNCIÓN PARA EVALUAR SI UN DATO ESTÁ VACÍO
function esta_vacio($valor) {
    if (!is_array($valor)) {
        return ($valor === null || trim((string)$valor) === '');
    }
    $vacio = true;
    foreach ($valor as $v) {
        if (!esta_vacio($v)) {
            $vacio = false;
            break;
        }
    }
    return $vacio;
}

// 4. LÓGICA DE PAGINACIÓN (10 casos por página)
$casos_por_pagina = 10;
$total_casos = count($casos_procesados);
$total_paginas = ceil($total_casos / $casos_por_pagina);

// Obtener página actual por URL (por defecto 1)
$pagina_actual = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
if ($pagina_actual > $total_paginas && $total_paginas > 0) $pagina_actual = $total_paginas;

// Recortar el arreglo general para mostrar solo los 10 correspondientes a esta página
$offset = ($pagina_actual - 1) * $casos_por_pagina;
$casos_mostrar = array_slice($casos_procesados, $offset, $casos_por_pagina);

?>
<?php
require_once __DIR__ . '/plantilla.php';
hv_encabezado('Verificador', 'verificador');
hv_cabecera('Verificador de integridad', 'Revisa qué campos están llenos o vacíos en cada caso clínico.', 'bi-ui-checks-grid');
?>

<div class="container-fluid px-3 px-lg-4 pb-5 position-relative">
    <section class="hv-tarjeta p-3 p-md-4 mb-4 hv-aparecer">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="hv-icono"><i class="bi bi-ui-checks-grid"></i></span>
            <div class="me-auto">
                <div class="hv-titulo h5 mb-0">Análisis de completitud</div>
                <div class="text-muted small">Página <?= (int)$pagina_actual ?> de <?= (int)($total_paginas ?: 1) ?> · <?= (int)$total_casos ?> casos</div>
            </div>
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
                            <th class="col-variables bg-success text-white border-success">Variables Llenas</th>
                            <th class="col-variables bg-danger text-white border-danger">Variables Vacías o Faltantes</th>
                            <th class="text-center" style="width: 100px;">Completitud</th>
                            <th class="text-center" style="width: 80px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($casos_mostrar as $caso): ?>
                            <?php 
                                $identificador = $caso['datos']['identificador'] ?? 'SIN-ID';
                                $variables_llenas = [];
                                $variables_vacias = [];
                                
                                // Clasificar variables
                                foreach ($master_keys as $key) {
                                    if (!isset($caso['datos'][$key]) || esta_vacio($caso['datos'][$key])) {
                                        $variables_vacias[] = $key;
                                    } else {
                                        $variables_llenas[] = $key;
                                    }
                                }

                                $total_variables = count($master_keys);
                                $conteo_llenas = count($variables_llenas);
                            ?>
                            <tr>
                                <td class="text-center fw-bold"><?= $caso['id'] ?></td>
                                <td><span class="badge bg-primary text-wrap"><?= htmlspecialchars($identificador) ?></span></td>
                                
                                <td class="border-end border-success">
                                    <?php if(empty($variables_llenas)): ?>
                                        <span class="text-muted small">Ninguna</span>
                                    <?php else: ?>
                                        <?php foreach($variables_llenas as $var): ?>
                                            <span class="badge bg-success badge-variable"><i class="bi bi-check me-1"></i><?= htmlspecialchars($var) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if(empty($variables_vacias)): ?>
                                        <span class="text-muted small">Ninguna (100% completo)</span>
                                    <?php else: ?>
                                        <?php foreach($variables_vacias as $var): ?>
                                            <span class="badge bg-danger badge-variable"><i class="bi bi-x me-1"></i><?= htmlspecialchars($var) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center fw-bold fs-5 border-start">
                                    <?php 
                                        $porcentaje = $total_variables > 0 ? round(($conteo_llenas / $total_variables) * 100) : 0;
                                        $color = $porcentaje > 80 ? 'text-success' : ($porcentaje > 50 ? 'text-warning' : 'text-danger');
                                    ?>
                                    <span class="<?= $color ?>"><?= $porcentaje ?>%</span>
                                </td>
                                
                                <td class="text-center align-middle">
                                    <a href="caso_editar.php?id=<?= $caso['id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="Editar este caso">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($casos_mostrar)): ?>
                            <tr><td colspan="6" class="text-center py-4">No hay casos clínicos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Navegación de páginas de auditoría">
        <ul class="pagination justify-content-center">
            
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