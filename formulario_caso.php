<?php
// Formulario único de los casos clínicos. Lo incluyen nuevo_caso.php, caso_editar.php y ver_caso.php,
// que antes eran tres copias casi iguales. Antes de incluirlo, la página ya cargó seguridad.php,
// comprobó los permisos y definió:
//   $modo        'nuevo', 'editar' o 'ver'
//   $rolUsuario  rol de la sesión
// En editar y ver, este archivo lee el id de la URL y carga el caso.
if (!isset($modo) || !in_array($modo, ['nuevo', 'editar', 'ver'], true)) {
    http_response_code(500);
    exit('Formulario sin modo.');
}

$idCaso = 0;
$jsonExistente = '{}';

if ($modo !== 'nuevo') {
    $idCaso = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($idCaso <= 0) {
        header("Location: lista_casos.php?error=no_id");
        exit;
    }

    require_once __DIR__ . '/config_bd.php';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT datos_completos FROM casos_clinicos WHERE id = :id");
        $stmt->execute([':id' => $idCaso]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resultado) {
            header("Location: lista_casos.php?error=not_found");
            exit;
        }

        // JSON de la BD que se inyecta (escapado) en el JS del formulario
        $jsonExistente = $resultado['datos_completos'];
    } catch (PDOException $e) {
        die("Error de conexión: " . hv_error_interno($e));
    }
}

require_once __DIR__ . '/plantilla.php';

// Título del caso guardado (editar y ver), para la franja de título
$datosCabecera = json_decode((string)$jsonExistente, true);
$tituloCaso = is_array($datosCabecera) ? trim((string)($datosCabecera['titulo_caso'] ?? '')) : '';
$identificadorCaso = is_array($datosCabecera) ? trim((string)($datosCabecera['identificador'] ?? '')) : '';

$titulosPagina = ['nuevo' => 'Nuevo caso clínico', 'editar' => 'Editar caso clínico', 'ver' => 'Caso clínico'];
$iconosPagina = ['nuevo' => 'bi-plus-circle', 'editar' => 'bi-pencil-square', 'ver' => 'bi-file-earmark-medical'];
if ($modo === 'nuevo') {
    $subtituloPagina = 'Formulario estandarizado para registrar un caso en el banco de Hospital Virtual.';
} else {
    $subtituloPagina = ($identificadorCaso !== '' ? $identificadorCaso . ' · ' : '') . ($tituloCaso !== '' ? $tituloCaso : 'Caso sin título');
}

hv_encabezado($titulosPagina[$modo], $modo === 'nuevo' ? 'nuevo' : 'casos');
hv_cabecera($titulosPagina[$modo] . ($modo === 'nuevo' ? '' : ' #' . $idCaso), $subtituloPagina, $iconosPagina[$modo]);
?>
<main class="container pb-5 position-relative">
<div class="hv-tarjeta hv-formulario-caso p-3 p-md-5 hv-aparecer">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <a href="lista_casos.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= $modo === 'editar' ? 'Cancelar y volver' : 'Volver a la lista' ?></a>
        <?php if ($modo === 'ver' && $rolUsuario !== 'Invitado'): ?>
            <a href="caso_editar.php?id=<?= (int)$idCaso ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Editar este caso</a>
        <?php elseif ($modo === 'ver'): ?>
            <span class="hv-chip"><i class="bi bi-eye me-1"></i>Solo lectura</span>
        <?php endif; ?>
    </div>
    <?php if ($modo === 'ver'): ?>
        <p class="text-muted small mb-0"><i class="bi bi-lock me-1"></i>Vista de solo lectura: los campos no se pueden modificar.</p>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $modo === 'nuevo' ? 'No se pudo guardar el caso' : 'No se pudieron guardar los cambios' ?></h5>
            <p class="mb-0 small">Detalle del error: <code><?= htmlspecialchars($_GET['error']) ?></code></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form id="formulario-caso">
        <div class="row g-4">
            <!-- Índice de pasos (en celular, un menú desplegable) -->
            <div class="col-lg-3">
                <div class="hv-indice-pasos">
                    <select class="form-select d-lg-none control-navegacion" id="selector-pasos" aria-label="Paso del formulario"></select>
                    <nav class="d-none d-lg-block" id="indice-pasos" aria-label="Pasos del formulario"></nav>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2 control-navegacion" id="btn-ver-todo"><i class="bi bi-list-ul me-1"></i>Ver todo</button>
                </div>
            </div>
            <div class="col-lg-9">

        <section class="paso-formulario" data-titulo="Datos del caso" data-icono="bi-card-heading">

        <!-- INFORMACIÓN GENERAL -->
        <h2 class="section-title">INFORMACIÓN GENERAL</h2>
        <div class="mb-3">
            <label class="form-label fw-bold">Identificador del Caso</label>
            <input type="text" 
                   name="identificador" 
                   id="identificador" 
                   class="form-control" 
                   placeholder="<?= $rolUsuario === 'Administrador' ? 'Ej. MED-2026-001' : 'Lo asigna un administrador' ?>"
                   <?= $rolUsuario !== 'Administrador' ? 'disabled' : ($modo === 'nuevo' ? 'required' : '') ?>>

            <?php if ($rolUsuario !== 'Administrador' && $modo !== 'ver'): ?>
                <div class="form-text text-muted small">
                    <i class="bi bi-lock-fill text-warning me-1"></i>
                    <strong><?= htmlspecialchars($rolUsuario) ?></strong>: solo un administrador puede asignar o cambiar este valor.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <label class="form-label fw-bold">Título del Caso Clínico:</label>
            <input type="text" class="form-control" name="titulo_caso" placeholder="Ej. Paciente masculino de 45 años con dolor torácico" required>
        </div>
        
        <div class="mb-4">
            <label class="form-label fw-bold">DEPARTAMENTO(S) QUE ELABORA(N) EL CASO:</label>
            <div class="row g-2">
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Departamento de Anatomía" id="dep1"><label class="form-check-label" for="dep1">Departamento de Anatomía</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Biología Celular y Tisular" id="dep2"><label class="form-check-label" for="dep2">Biología Celular y Tisular</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Bioquímica" id="dep3"><label class="form-check-label" for="dep3">Bioquímica</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Inmunología" id="dep19"><label class="form-check-label" for="dep19">Inmunología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Embriología y Genética" id="dep4"><label class="form-check-label" for="dep4">Embriología y Genética</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Farmacología" id="dep5"><label class="form-check-label" for="dep5">Farmacología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Fisiología" id="dep6"><label class="form-check-label" for="dep6">Fisiología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Microbiología y Parasitología" id="dep7"><label class="form-check-label" for="dep7">Microbiología y Parasitología</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Salud Pública" id="dep8"><label class="form-check-label" for="dep8">Salud Pública</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Departamento de Cirugía" id="dep9"><label class="form-check-label" for="dep9">Departamento de Cirugía</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Historia y Filosofía de la Medicina" id="dep10"><label class="form-check-label" for="dep10">Historia y Filosofía de la Medicina</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Innovación en Material Biológico Humano" id="dep11"><label class="form-check-label" for="dep11">Innovación en Material Biológico Humano</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Integración de Ciencias Médicas" id="dep12"><label class="form-check-label" for="dep12">Integración de Ciencias Médicas</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Psiquiatría y Salud Mental" id="dep13"><label class="form-check-label" for="dep13">Psiquiatría y Salud Mental</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Salud Digital" id="dep14"><label class="form-check-label" for="dep14">Salud Digital</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Áreas de Enseñanza y Servicio Social" id="dep15"><label class="form-check-label" for="dep15">Áreas de Enseñanza y Servicio Social</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="Coordinación de Ciencias Básicas" id="dep16"><label class="form-check-label" for="dep16">Coordinación de Ciencias Básicas</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="SECISS" id="dep17"><label class="form-check-label" for="dep17">SECISS</label></div></div>
                <div class="col-md-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="departamentos" value="SEM" id="dep18"><label class="form-check-label" for="dep18">SEM</label></div></div>
                
                <div class="col-12 mt-2 d-flex align-items-center gap-3">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="checkbox-otro" name="departamentos" value="Otro">
                        <label class="form-check-label fw-bold" for="checkbox-otro">Otro</label>
                    </div>
                    <input type="text" class="form-control form-control-sm w-50 d-none" id="departamento-otro-texto" name="departamento_otro_texto" placeholder="Especificar departamento...">
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">AUTOR(ES):</label>
            <div id="autores-container">
                <div class="input-group mb-2 autor-input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" name="autores" placeholder="Nombre completo del autor">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar autor"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-autor" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otro autor
            </button>
        </div>

        <!-- CLASIFICACIÓN DEL CASO -->
        <h2 class="section-title">CLASIFICACIÓN DEL CASO</h2>
        
        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label fw-bold">SEXO:</label>
                <div class="d-flex gap-3 mt-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sexo" id="sexo_m" value="Masculino">
                        <label class="form-check-label" for="sexo_m">Masculino</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="sexo" id="sexo_f" value="Femenino">
                        <label class="form-check-label" for="sexo_f">Femenino</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label fw-bold" for="edad">EDAD:</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="edad" name="edad" placeholder="Ej. 35" min="0" max="200">
                    <span class="input-group-text">años</span>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold" for="padecimiento">PADECIMIENTO:</label>
                <select class="form-select" id="padecimiento" name="padecimiento">
                    <option value="" selected disabled>-- Selecciona una opción --</option>
                    <option value="Infecciones respiratorias agudas">Infecciones respiratorias agudas</option>
                    <option value="Úlceras, gastritis y duodenitis">Úlceras, gastritis y duodenitis</option>
                    <option value="Accidentes de transporte en vehículos con motor">Accidentes de transporte en vehículos con motor</option>
                    <option value="Neumonías y bronconeumonías">Neumonías y bronconeumonías</option>
                    <option value="Infecciones intestinales por otros organismos y las mal definidas">Infecciones intestinales por otros organismos y las mal definidas</option>
                    <option value="Otitis media aguda">Otitis media aguda</option>
                    <option value="Hiperplasia de la próstata">Hiperplasia de la próstata</option>
                    <option value="Mordeduras por perro">Mordeduras por perro</option>
                    <option value="Infección de vías urinarias">Infección de vías urinarias</option>
                    <option value="Hipertensión arterial">Hipertensión arterial</option>
                    <option value="Dengue no grave">Dengue no grave</option>
                    <option value="Asma">Asma</option>
                    <option value="Gingivitis y enfermedad periodontal">Gingivitis y enfermedad periodontal</option>
                    <option value="Obesidad">Obesidad</option>
                    <option value="Faringitis y amigdalitis estreptocócicas">Faringitis y amigdalitis estreptocócicas</option>
                    <option value="Insuficiencia venosa periférica">Insuficiencia venosa periférica</option>
                    <option value="Conjuntivitis">Conjuntivitis</option>
                    <option value="Diabetes Tipo 2">Diabetes Tipo 2</option>
                    <option value="Intoxicación por picadura de alacrán">Intoxicación por picadura de alacrán</option>
                    <option value="Infección asociada a la atención de la salud">Infección asociada a la atención de la salud</option>
                    <option value="Vulvovaginitis">Vulvovaginitis</option>
                    <option value="Candidiasis urogenital">Candidiasis urogenital</option>
                    <option value="Depresión">Depresión</option>
                    <option value="Otro">Otro (Especificar)</option>
                </select>
                <input type="text" class="form-control mt-2 d-none" id="padecimiento-otro-texto" name="padecimiento_otro_texto" placeholder="Especificar padecimiento...">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Residencia:</label>
            <input type="text" class="form-control" name="residencia" placeholder="Ej. Ciudad de México">
        </div>
        <div class="mb-4">
            <label class="form-label fw-bold">GRUPO DE EDAD (AÑOS):</label>
            <div class="row g-2">
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="<1" id="e1"><label class="form-check-label" for="e1">&lt;1</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="1 a 4" id="e2"><label class="form-check-label" for="e2">1 a 4</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="5 a 9" id="e3"><label class="form-check-label" for="e3">5 a 9</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="10 a 14" id="e4"><label class="form-check-label" for="e4">10 a 14</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="15 a 19" id="e5"><label class="form-check-label" for="e5">15 a 19</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="20 a 24" id="e6"><label class="form-check-label" for="e6">20 a 24</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="25 a 44" id="e7"><label class="form-check-label" for="e7">25 a 44</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="45 a 49" id="e8"><label class="form-check-label" for="e8">45 a 49</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="50 a 59" id="e9"><label class="form-check-label" for="e9">50 a 59</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value="60 a 64" id="e10"><label class="form-check-label" for="e10">60 a 64</label></div></div>
                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="radio" name="rango_edad" value=">65" id="e11"><label class="form-check-label" for="e11">&gt;65</label></div></div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">SISTEMA(S) ANATÓMICO(S) PRINCIPAL(ES) INVOLUCRADO(S):</label>
            <div class="row g-2">
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Circulatorio" id="s1"><label class="form-check-label" for="s1">Circulatorio</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Digestivo" id="s2"><label class="form-check-label" for="s2">Digestivo</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Endócrino" id="s3"><label class="form-check-label" for="s3">Endócrino</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Linfático e inmunológico" id="s4"><label class="form-check-label" for="s4">Linfático e inmunológico</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Muscular" id="s5"><label class="form-check-label" for="s5">Muscular</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Nervioso" id="s6"><label class="form-check-label" for="s6">Nervioso</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Óseo" id="s7"><label class="form-check-label" for="s7">Óseo</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Reproductor femenino" id="s8"><label class="form-check-label" for="s8">Reproductor femenino</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Reproductor masculino" id="s9"><label class="form-check-label" for="s9">Reproductor masculino</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Respiratorio" id="s10"><label class="form-check-label" for="s10">Respiratorio</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Tegumentario" id="s11"><label class="form-check-label" for="s11">Tegumentario</label></div></div>
                <div class="col-md-4 col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="sistemas_anatomicos" value="Urinario" id="s12"><label class="form-check-label" for="s12">Urinario</label></div></div>
            </div>
        </div>

        <!-- ASIGNATURAS RELACIONADAS -->
        <h2 class="section-title">ASIGNATURA(S) RELACIONADA(S) AL CASO</h2>
        
        <div class="row g-4 mb-4">
            <!-- Primer Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded">
                    <h6 class="fw-bold text-primary mb-3">Primer Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Anatomía Humana" id="a1_1"><label class="form-check-label" for="a1_1">Anatomía Humana</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Bioquímica y Biología Molecular" id="a1_2"><label class="form-check-label" for="a1_2">Bioquímica y Biología Molecular</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Biología Celular e Histología Médica" id="a1_3"><label class="form-check-label" for="a1_3">Biología Celular e Histología Médica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Embriología Humana" id="a1_4"><label class="form-check-label" for="a1_4">Embriología Humana</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Básico-Clínica I" id="a1_5"><label class="form-check-label" for="a1_5">Integración Básico-Clínica I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Informática Biomédica I" id="a1_6"><label class="form-check-label" for="a1_6">Informática Biomédica I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Psicología Médica y Salud Mental" id="a1_7"><label class="form-check-label" for="a1_7">Psicología Médica y Salud Mental</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Digital I" id="a1_8"><label class="form-check-label" for="a1_8">Salud Digital I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Pública y Comunidad" id="a1_9"><label class="form-check-label" for="a1_9">Salud Pública y Comunidad</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-1" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-1">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-1-otro-texto" name="asignaturas_otra_1" placeholder="Especificar...">
                    </div>
                </div>
            </div>

            <!-- Segundo Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded h-100">
                    <h6 class="fw-bold text-success mb-3">Segundo Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Farmacología" id="a2_1"><label class="form-check-label" for="a2_1">Farmacología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Fisiología" id="a2_2"><label class="form-check-label" for="a2_2">Fisiología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Inmunología" id="a2_3"><label class="form-check-label" for="a2_3">Inmunología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Básico-Clínica II" id="a2_4"><label class="form-check-label" for="a2_4">Integración Básico-Clínica II</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Introducción a la Cirugía" id="a2_5"><label class="form-check-label" for="a2_5">Introducción a la Cirugía</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Microbiología y Parasitología" id="a2_6"><label class="form-check-label" for="a2_6">Microbiología y Parasitología</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Promoción de la Salud en el Ciclo de Vida" id="a2_7"><label class="form-check-label" for="a2_7">Promoción de la Salud en el Ciclo de Vida</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Digital II" id="a2_8"><label class="form-check-label" for="a2_8">Salud Digital II</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-2" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-2">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-2-otro-texto" name="asignaturas_otra_2" placeholder="Especificar...">
                    </div>
                </div>
            </div>

            <!-- Tercer Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded h-100">
                    <h6 class="fw-bold text-danger mb-3">Tercer Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Anatomía Patológica" id="a3_1"><label class="form-check-label" for="a3_1">Anatomía Patológica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Antropología Médica e Interculturalidad" id="a3_2"><label class="form-check-label" for="a3_2">Antropología Médica e Interculturalidad</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Defensa del Huésped" id="a3_3"><label class="form-check-label" for="a3_3">Defensa del Huésped</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Dinámica Corporal" id="a3_4"><label class="form-check-label" for="a3_4">Dinámica Corporal</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Epidemiología Clínica y Medicina Basada en Evidencias" id="a3_5"><label class="form-check-label" for="a3_5">Epidemiología Clínica y Medicina Basada en Evidencias</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Farmacología Terapéutica" id="a3_6"><label class="form-check-label" for="a3_6">Farmacología Terapéutica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Genética Clínica" id="a3_7"><label class="form-check-label" for="a3_7">Genética Clínica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Homeóstasis Hemodinámica" id="a3_8"><label class="form-check-label" for="a3_8">Homeóstasis Hemodinámica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Iniciación Clínica" id="a3_9"><label class="form-check-label" for="a3_9">Iniciación Clínica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Clínico-básica I" id="a3_10"><label class="form-check-label" for="a3_10">Integración Clínico-básica I</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina Psicológica y Comunicación" id="a3_11"><label class="form-check-label" for="a3_11">Medicina Psicológica y Comunicación</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Metabolismo" id="a3_12"><label class="form-check-label" for="a3_12">Metabolismo</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud Mental" id="a3_13"><label class="form-check-label" for="a3_13">Salud Mental</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-3" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-3">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-3-otro-texto" name="asignaturas_otra_3" placeholder="Especificar...">
                    </div>
                </div>
            </div>

            <!-- Cuarto Año -->
            <div class="col-md-6">
                <div class="p-3 bg-light border rounded h-100">
                    <h6 class="fw-bold text-warning mb-3" style="color: #d39e00 !important;">Cuarto Año</h6>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Ambiente Trabajo y Salud" id="a4_1"><label class="form-check-label" for="a4_1">Ambiente Trabajo y Salud</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Bioética Médica y Profesionalismo" id="a4_2"><label class="form-check-label" for="a4_2">Bioética Médica y Profesionalismo</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Historia y Filosofía de la Medicina" id="a4_3"><label class="form-check-label" for="a4_3">Historia y Filosofía de la Medicina</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Integración Clínico-básica II" id="a4_4"><label class="form-check-label" for="a4_4">Integración Clínico-básica II</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina Clínico-Quirúrgica" id="a4_5"><label class="form-check-label" for="a4_5">Medicina Clínico-Quirúrgica</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina de Urgencias" id="a4_6"><label class="form-check-label" for="a4_6">Medicina de Urgencias</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Medicina Legal" id="a4_7"><label class="form-check-label" for="a4_7">Medicina Legal</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud del Adulto Mayor" id="a4_8"><label class="form-check-label" for="a4_8">Salud del Adulto Mayor</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud de la Mujer" id="a4_9"><label class="form-check-label" for="a4_9">Salud de la Mujer</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="asignaturas" value="Salud del Niño y Adolescente" id="a4_10"><label class="form-check-label" for="a4_10">Salud del Niño y Adolescente</label></div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="checkbox-asig-4" value="Otra">
                        <label class="form-check-label fw-bold" for="checkbox-asig-4">Otra:</label>
                        <input type="text" class="form-control form-control-sm mt-1 d-none" id="asig-4-otro-texto" name="asignaturas_otra_4" placeholder="Especificar...">
                    </div>
                </div>
            </div>
        </div>

        </section>

        <section class="paso-formulario" data-titulo="Historia clínica" data-icono="bi-journal-medical">
        <!-- PRESENTACIÓN DEL CASO -->
        <h2 class="section-title">PRESENTACIÓN DEL CASO</h2>
        
        <div class="mb-3">
            <label for="resumen" class="form-label fw-bold">RESUMEN DEL CASO:</label>
            <textarea class="form-control" id="resumen" name="resumen" rows="3" placeholder="Descripción general: Paciente (Masculino/Femenino) de (__) años de edad, acude a..."></textarea>
        </div>

        <div class="mb-3">
            <label for="hc_padecimiento_actual" class="form-label fw-bold">PADECIMIENTO ACTUAL:</label>
            <textarea class="form-control apartado-historia" id="hc_padecimiento_actual" name="hc_padecimiento_actual" rows="3" data-titulo="Padecimiento actual"></textarea>
            <div class="form-text">Solo si el resumen del caso no cubre algún aspecto del padecimiento (inicio, evolución, semiología).</div>
        </div>

        <!-- HISTORIA CLÍNICA (apartados de la plantilla; el texto de "historia" se arma con ellos al guardar) -->
        <h2 class="section-title">HISTORIA CLÍNICA</h2>
        <p class="text-muted small mb-3">Proporciona únicamente los antecedentes que modifican la probabilidad diagnóstica, la interpretación clínica o el manejo del paciente. Deja vacíos los apartados que no apliquen.</p>

        <div class="mb-4 d-none bloque-formato-anterior" id="bloque-historia-anterior">
            <label for="historia_texto_libre" class="form-label fw-bold"><i class="bi bi-clock-history me-1"></i> Historia clínica en texto libre (formato anterior):</label>
            <textarea class="form-control" id="historia_texto_libre" name="historia_texto_libre" rows="6"></textarea>
            <div class="form-text">Este caso se capturó antes de los apartados. Puedes pasar el texto a los apartados de abajo; lo que quede aquí se conserva al inicio de la historia clínica.</div>
        </div>

        <div id="apartados-historia"></div>

        </section>

        <section class="paso-formulario" data-titulo="Exploración física" data-icono="bi-heart-pulse">
        <!-- EXPLORACIÓN FÍSICA (el texto de "exploracion" se arma con los apartados al guardar) -->
        <h2 class="section-title">EXPLORACIÓN FÍSICA</h2>

        <div class="mb-4 d-none bloque-formato-anterior" id="bloque-exploracion-anterior">
            <label for="exploracion_texto_libre" class="form-label fw-bold"><i class="bi bi-clock-history me-1"></i> Exploración física en texto libre (formato anterior):</label>
            <textarea class="form-control" id="exploracion_texto_libre" name="exploracion_texto_libre" rows="6"></textarea>
            <div class="form-text">Este caso se capturó antes de los apartados. Puedes pasar el texto a los apartados de abajo; lo que quede aquí se conserva al inicio de la exploración física.</div>
        </div>

        <div class="mb-4">
            <label for="ef_aspecto_general" class="form-label fw-bold">ASPECTO GENERAL:</label>
            <textarea class="form-control apartado-exploracion" id="ef_aspecto_general" name="ef_aspecto_general" rows="2" data-titulo="Aspecto general"></textarea>
            <div class="form-text">Estado de conciencia, orientación, cooperación, facies, hidratación, coloración y marcha si aplica.</div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold mb-3">SIGNOS VITALES:</label>
            <div class="row g-3">
                <div class="col-sm-6 col-md-4 col-lg">
                    <label class="form-label small fw-bold text-muted">Tensión Arterial (mmHg)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-heart-pulse text-danger"></i></span>
                        <input type="text" class="form-control" name="signos_ta" placeholder="Ej. 120/80">
                    </div>
                </div>
                <div class="col-sm-6 col-md-4 col-lg">
                    <label class="form-label small fw-bold text-muted">Frec. Cardiaca (lpm)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-activity text-danger"></i></span>
                        <input type="number" class="form-control" name="signos_fc" placeholder="Ej. 80">
                    </div>
                </div>
                <div class="col-sm-6 col-md-4 col-lg">
                    <label class="form-label small fw-bold text-muted">Frec. Respiratoria (rpm)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-lungs text-info"></i></span>
                        <input type="number" class="form-control" name="signos_fr" placeholder="Ej. 16">
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg">
                    <label class="form-label small fw-bold text-muted">Temperatura (°C)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-thermometer-half text-warning"></i></span>
                        <input type="number" step="0.1" class="form-control" name="signos_temp" placeholder="Ej. 36.5">
                    </div>
                </div>
                <div class="col-sm-6 col-md-6 col-lg">
                    <label class="form-label small fw-bold text-muted">Sat. de Oxígeno (%)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-droplet-fill text-primary"></i></span>
                        <input type="number" min="0" max="100" class="form-control" name="signos_sato2" placeholder="Ej. 98">
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold mb-3">SOMATOMETRÍA:</label>
            <div class="row g-3">
                <div class="col-sm-6 col-md-6">
                    <label class="form-label small fw-bold text-muted">Peso (kg)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person-fill text-secondary"></i></span>
                        <input type="number" step="0.1" class="form-control" name="somatometria_peso" placeholder="Ej. 70.5">
                    </div>
                </div>
                <div class="col-sm-6 col-md-6">
                    <label class="form-label small fw-bold text-muted">Talla (cm)</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-rulers text-secondary"></i></span>
                        <input type="number" step="0.1" class="form-control" name="somatometria_talla" placeholder="Ej. 175">
                    </div>
                </div>
            </div>
            <div class="form-text" id="texto-imc"></div>
        </div>

        <label class="form-label fw-bold mb-2" id="titulo-exploracion-segmentaria">EXPLORACIÓN SEGMENTARIA:</label>
        <div id="apartados-exploracion"></div>

        <details class="mb-4 border rounded p-3 bg-light vista-previa-juego" id="vista-previa-textos">
            <summary class="fw-bold text-secondary"><i class="bi bi-eye me-1"></i> Ver el texto que se guardará para el juego (historia clínica y exploración física)</summary>
            <label class="form-label small fw-bold text-muted mt-3">Historia clínica</label>
            <textarea class="form-control form-control-sm mb-3" id="vista-previa-historia" rows="6" readonly></textarea>
            <label class="form-label small fw-bold text-muted">Exploración física</label>
            <textarea class="form-control form-control-sm" id="vista-previa-exploracion" rows="6" readonly></textarea>
        </details>

        </section>

        <section class="paso-formulario" data-titulo="Estudios" data-icono="bi-clipboard2-data">
        <!-- LABORATORIOS, IMÁGENES Y OTROS EXÁMENES (el texto de "laboratorios" se arma con los apartados al guardar) -->
        <h2 class="section-title">LABORATORIOS, IMÁGENES Y OTROS EXÁMENES</h2>
        <p class="text-muted small mb-3">Documenta de forma ordenada los estudios que sustentan el diagnóstico, de los generales a los específicos o confirmatorios. No todo lo que podría pedirse, sino lo que el estudiante necesita para resolver el caso.</p>

        <div class="mb-4 d-none bloque-formato-anterior" id="bloque-laboratorios-anterior">
            <label for="laboratorios_texto_libre" class="form-label fw-bold"><i class="bi bi-clock-history me-1"></i> Laboratorios e imágenes en texto libre (formato anterior):</label>
            <textarea class="form-control" id="laboratorios_texto_libre" name="laboratorios_texto_libre" rows="6"></textarea>
            <div class="form-text">Este caso se capturó antes de los apartados. Puedes pasar el texto a los apartados de abajo; lo que quede aquí se conserva al inicio.</div>
        </div>

        <div id="apartados-laboratorios"></div>

        <div class="mb-4">
            <!-- Componente de Archivos Adjuntos -->
            <div class="border p-3 rounded bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary small fw-bold"><i class="bi bi-paperclip"></i> Archivos adjuntos (Imágenes, PDFs, etc.)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-lab-file">
                        <i class="bi bi-plus-circle me-1"></i> Agregar archivo(s)
                    </button>
                    <!-- Input oculto para manejar el explorador de archivos -->
                    <input type="file" class="d-none" id="input-lab-files" multiple accept="image/*,.pdf,.doc,.docx">
                </div>
                <!-- Contenedor para la lista de archivos -->
                <ul class="list-group list-group-flush mt-2" id="lab-files-list">
                    <!-- Los archivos se renderizan aquí dinámicamente -->
                </ul>
            </div>
        </div>

        </section>

        <section class="paso-formulario" data-titulo="Diagnóstico y tratamiento" data-icono="bi-capsule">
        <!-- DIAGNÓSTICO, TRATAMIENTO Y SEGUIMIENTO (el texto de "diagnostico_tratamiento" se arma con los apartados al guardar) -->
        <h2 class="section-title">DIAGNÓSTICO, TRATAMIENTO Y SEGUIMIENTO</h2>
        <p class="text-muted small mb-3">Integra el diagnóstico final con un plan terapéutico basado en la evidencia y un seguimiento coherente con la evolución esperada del paciente.</p>

        <div class="mb-4 d-none bloque-formato-anterior" id="bloque-diagnostico-anterior">
            <label for="diagnostico_tratamiento_texto_libre" class="form-label fw-bold"><i class="bi bi-clock-history me-1"></i> Diagnóstico, tratamiento y seguimiento en texto libre (formato anterior):</label>
            <textarea class="form-control" id="diagnostico_tratamiento_texto_libre" name="diagnostico_tratamiento_texto_libre" rows="6"></textarea>
            <div class="form-text">Este caso se capturó antes de los apartados. Puedes pasar el texto a los apartados de abajo; lo que quede aquí se conserva al inicio.</div>
        </div>

        <div id="apartados-diagnostico"></div>

        <div class="mb-4">
            <h6 class="fw-bold text-secondary mb-1">Estadificación</h6>
            <div class="form-text mt-0 mb-2">Escalas que apliquen al caso (CURB-65, Wells, CHA₂DS₂-VASc, Glasgow, Child-Pugh, NYHA, Killip-Kimball, GOLD…), siempre con puntaje, interpretación e implicaciones.</div>
            <div id="filas-estadificacion"></div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-estadificacion"><i class="bi bi-plus-circle me-1"></i> Agregar escala</button>
        </div>

        <div id="apartados-tratamiento"></div>

        <div class="mb-4">
            <label class="form-label small fw-bold mb-1">Tratamiento farmacológico</label>
            <div class="form-text mt-0 mb-2">Un medicamento por renglón.</div>
            <div id="filas-farmacologico"></div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-farmacologico"><i class="bi bi-plus-circle me-1"></i> Agregar medicamento</button>
        </div>

        <div id="apartados-tratamiento-2"></div>

        <div id="apartados-seguimiento"></div>

        <details class="mb-4 border rounded p-3 bg-light vista-previa-juego">
            <summary class="fw-bold text-secondary"><i class="bi bi-eye me-1"></i> Ver el texto que se guardará para el juego (laboratorios y diagnóstico, tratamiento y seguimiento)</summary>
            <label class="form-label small fw-bold text-muted mt-3">Laboratorios, imágenes y otros exámenes</label>
            <textarea class="form-control form-control-sm mb-3" id="vista-previa-laboratorios" rows="6" readonly></textarea>
            <label class="form-label small fw-bold text-muted">Diagnóstico, tratamiento y seguimiento</label>
            <textarea class="form-control form-control-sm" id="vista-previa-diagnostico" rows="6" readonly></textarea>
        </details>

        <!-- DIAGNÓSTICOS DIFERENCIALES (Dinámico) -->
        <h2 class="section-title">DIAGNÓSTICOS DIFERENCIALES</h2>
        
        <div id="diagnosticos-container">
            <div class="card card-diagnostic mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Diagnóstico Diferencial 1</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="diag_dif_1" placeholder="Nombre del diagnóstico diferencial...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-success"><i class="bi bi-plus-circle me-1"></i> Puntos a favor:</label>
                            <textarea class="form-control" name="diag_dif_favor_1" rows="2" placeholder="Hallazgos que apoyan..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-danger"><i class="bi bi-dash-circle me-1"></i> Puntos en contra:</label>
                            <textarea class="form-control" name="diag_dif_contra_1" rows="2" placeholder="Hallazgos que descartan..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="button" id="btn-add-diagnostico" class="btn btn-outline-secondary mb-4">
            <i class="bi bi-journal-plus me-1"></i> Agregar otro diagnóstico
        </button>
        
        <!-- SELECTOR DE DIAGNÓSTICO CORRECTO -->
        <div class="card bg-light mt-3 mb-4 shadow-sm border-success">
            <div class="card-body">
                <label for="diagnostico_correcto" class="form-label fw-bold text-success">
                    <i class="bi bi-check-circle-fill me-1"></i> Diagnóstico Correcto
                </label>
                <select name="diagnostico_correcto" id="diagnostico_correcto" class="form-select border-success" required>
                    <option value="">Primero ingresa los diagnósticos diferenciales arriba...</option>
                </select>
                <div class="form-text">
                    Selecciona cuál de las opciones que escribiste arriba es el diagnóstico definitivo.
                </div>
            </div>
        </div>

        </section>

        <section class="paso-formulario" data-titulo="Evaluación" data-icono="bi-patch-question">
        <!-- PREGUNTAS Y RESPUESTAS (Dinámico) -->
        <h2 class="section-title">PREGUNTAS Y POSIBLES RESPUESTAS</h2>
        
        <div id="preguntas-container">
            <div class="card card-question mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Pregunta 1</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Enunciado de la pregunta:</label>
                        <input type="text" class="form-control" name="pregunta_1" placeholder="Escribe la pregunta aquí...">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <!-- Opción 1 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 1</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_1" placeholder="Texto de la opción 1">
                                <textarea class="form-control form-control-sm" name="p1_razon_1" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <!-- Opción 2 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 2</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_2" placeholder="Texto de la opción 2">
                                <textarea class="form-control form-control-sm" name="p1_razon_2" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <!-- Opción 3 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 3</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_3" placeholder="Texto de la opción 3">
                                <textarea class="form-control form-control-sm" name="p1_razon_3" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <!-- Opción 4 -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 4</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p1_opcion_4" placeholder="Texto de la opción 4">
                                <textarea class="form-control form-control-sm" name="p1_razon_4" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-success"><i class="bi bi-check-circle me-1"></i>Respuesta Correcta:</label>
                        <select class="form-select border-success" name="p1_correcta">
                            <option value="" selected disabled>-- Selecciona la opción correcta --</option>
                            <option value="Opción 1">Opción 1</option>
                            <option value="Opción 2">Opción 2</option>
                            <option value="Opción 3">Opción 3</option>
                            <option value="Opción 4">Opción 4</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="button" id="btn-add-pregunta" class="btn btn-outline-primary mb-4">
            <i class="bi bi-patch-question me-1"></i> Agregar otra pregunta
        </button>


        <!-- NOTA MÉDICA Y TABLA SOAP -->
        <h2 class="section-title">NOTA MÉDICA Y TABLA SOAP</h2>

        <div class="mb-4">
            <label for="nota_medica" class="form-label fw-bold">NOTA MÉDICA:</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-file-earmark-medical text-primary"></i></span>
                <textarea class="form-control" id="nota_medica" name="nota_medica" rows="4" placeholder="Redacte la nota médica (ingreso, evolución, indicaciones, etc.)..."></textarea>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold mb-3">TABLA SOAP:</label>
            <div class="row g-3">
                <!-- Subjetivo -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_subjetivo" class="form-label fw-bold text-primary small">S - Subjetivo:</label>
                        <textarea class="form-control" id="soap_subjetivo" name="soap_subjetivo" rows="3" placeholder="Síntomas reportados por el paciente, historia contada..."></textarea>
                    </div>
                </div>
                <!-- Objetivo -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_objetivo" class="form-label fw-bold text-success small">O - Objetivo:</label>
                        <textarea class="form-control" id="soap_objetivo" name="soap_objetivo" rows="3" placeholder="Signos vitales, exploración física, hallazgos de laboratorio y gabinete..."></textarea>
                    </div>
                </div>
                <!-- Análisis -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_analisis" class="form-label fw-bold text-warning small" style="color: #d39e00 !important;">A - Análisis:</label>
                        <textarea class="form-control" id="soap_analisis" name="soap_analisis" rows="3" placeholder="Evaluación del cuadro, razonamiento clínico, impresión diagnóstica..."></textarea>
                    </div>
                </div>
                <!-- Plan -->
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light h-100">
                        <label for="soap_plan" class="form-label fw-bold text-danger small">P - Plan:</label>
                        <textarea class="form-control" id="soap_plan" name="soap_plan" rows="3" placeholder="Tratamiento, seguimiento, estudios adicionales, interconsultas..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        </section>

        <section class="paso-formulario" data-titulo="Paciente virtual" data-icono="bi-person-video">
        <!-- PACIENTE VIRTUAL (JUEGO) -->
        <h2 class="section-title">PACIENTE VIRTUAL (JUEGO) <i class="bi bi-person-video ms-2 text-primary"></i></h2>

        <div class="mb-4">
            <label for="prompts" class="form-label fw-bold">PROMPTS DE IA:</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-robot"></i></span>
                <textarea class="form-control" id="prompts" name="prompts" rows="3" placeholder="Ej. Actúa como un paciente simulado con dolor abdominal..."></textarea>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">TIPO DE INTERROGATORIO AL PACIENTE VIRTUAL:</label>
            <div class="d-flex flex-wrap gap-4 mt-1">
                <div class="form-check">
                    <input class="form-check-input border-primary" type="radio" name="tipo_interrogatorio" id="int_directo" value="direct">
                    <label class="form-check-label" for="int_directo">Interrogatorio Directo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input border-primary" type="radio" name="tipo_interrogatorio" id="int_indirecto" value="indirect">
                    <label class="form-check-label" for="int_indirecto">Interrogatorio Indirecto</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input border-primary" type="radio" name="tipo_interrogatorio" id="int_hibrido" value="hybrid">
                    <label class="form-check-label" for="int_hibrido">Interrogatorio Híbrido</label>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Preguntas para la entrevista al paciente virtual:</label>
            <div id="simulacion-preguntas-container">
                <div class="input-group mb-2 simulacion-input-group">
                    <span class="input-group-text"><i class="bi bi-question-circle"></i></span>
                    <input type="text" class="form-control" name="simulacion_preguntas" placeholder="Escribe una pregunta para la entrevista al paciente virtual...">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-simulacion-pregunta" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otra pregunta
            </button>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Preguntas del evaluador al médico para la metacognición:</label>
            <div id="metacognicion-preguntas-container">
                <div class="input-group mb-2 metacognicion-input-group">
                    <span class="input-group-text"><i class="bi bi-lightbulb text-warning"></i></span>
                    <input type="text" class="form-control" name="metacognicion_preguntas" placeholder="Escribe una pregunta de metacognición...">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-metacognicion-pregunta" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otra pregunta
            </button>
        </div>

        <label class="form-label fw-bold mb-3"><i class="bi bi-robot me-1"></i> SEMIOLOGÍA MÉDICA GENERADA POR IA:</label>
        <div class="row g-3 mb-4">
            <!-- Anamnesis -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_anamnesis" class="form-label fw-bold text-secondary small"><i class="bi bi-chat-left-text me-1"></i> Anamnesis:</label>
                    <textarea class="form-control" id="ia_anamnesis" name="ia_anamnesis" rows="4" placeholder="Información generada por IA referente al interrogatorio e historial del paciente..."></textarea>
                </div>
            </div>
            <!-- Examen Físico -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_examen_fisico" class="form-label fw-bold text-secondary small"><i class="bi bi-activity me-1"></i> Examen Físico:</label>
                    <textarea class="form-control" id="ia_examen_fisico" name="ia_examen_fisico" rows="4" placeholder="Información generada por IA sobre la exploración física..."></textarea>
                </div>
            </div>
            <!-- Impresión Diagnóstica -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_impresion_diagnostica" class="form-label fw-bold text-secondary small"><i class="bi bi-clipboard2-pulse me-1"></i> Impresión Diagnóstica:</label>
                    <textarea class="form-control" id="ia_impresion_diagnostica" name="ia_impresion_diagnostica" rows="4" placeholder="Conclusión y razonamiento diagnóstico generado por la IA..."></textarea>
                </div>
            </div>
            <!-- Plan de Tratamiento -->
            <div class="col-md-6">
                <div class="p-3 border rounded bg-light h-100">
                    <label for="ia_plan_tratamiento" class="form-label fw-bold text-secondary small"><i class="bi bi-capsule me-1"></i> Plan de Tratamiento:</label>
                    <textarea class="form-control" id="ia_plan_tratamiento" name="ia_plan_tratamiento" rows="4" placeholder="Sugerencias de manejo, recetas o intervenciones dadas por la IA..."></textarea>
                </div>
            </div>
        </div>

        <!-- MATERIAL COMPLEMENTARIO -->
        <h2 class="section-title">MATERIAL COMPLEMENTARIO</h2>

        <div class="mb-3">
            <label class="form-label fw-bold">Material que complementa el caso (Enlaces o Referencias):</label>
            <div id="enlaces-container">
                <div class="input-group mb-2 enlace-input-group">
                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                    <input type="text" class="form-control" name="enlaces_material" placeholder="Ej. https://... o referencia bibliográfica">
                    <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar enlace"><i class="bi bi-trash btn-remove"></i></button>
                </div>
            </div>
            <button type="button" id="btn-add-enlace" class="btn btn-sm btn-outline-primary mt-1">
                <i class="bi bi-plus-circle me-1"></i> Agregar otro enlace
            </button>
        </div>

        </section>

        <!-- Navegación entre pasos -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 pt-3 border-top" id="nav-pasos">
            <button type="button" class="btn btn-outline-secondary control-navegacion" id="btn-paso-anterior"><i class="bi bi-arrow-left me-1"></i><span>Anterior</span></button>
            <span class="small text-muted" id="texto-paso"></span>
            <button type="button" class="btn btn-primary control-navegacion" id="btn-paso-siguiente"><span>Siguiente</span><i class="bi bi-arrow-right ms-1"></i></button>
        </div>
            </div>
        </div>

        <div class="hv-barra-guardar mt-5">
            <?php if ($modo === 'nuevo'): ?>
            <a href="lista_casos.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
            <button type="submit" class="btn btn-turquesa btn-lg flex-grow-1" id="btn-guardar">
                <i class="bi bi-cloud-arrow-up me-2"></i>Guardar caso clínico
            </button>
            <?php elseif ($modo === 'editar'): ?>
            <a href="lista_casos.php" class="btn btn-outline-secondary btn-lg">Descartar cambios</a>
            <button type="submit" class="btn btn-turquesa btn-lg flex-grow-1" id="btn-guardar">
                <i class="bi bi-cloud-arrow-up me-2"></i>Guardar cambios
            </button>
            <?php else: ?>
            <a href="lista_casos.php" class="btn btn-outline-secondary btn-lg"><i class="bi bi-arrow-left me-1"></i>Regresar a la lista de casos</a>
            <?php if ($rolUsuario !== 'Invitado'): ?>
                <a href="caso_editar.php?id=<?= (int)$idCaso ?>" class="btn btn-primary btn-lg"><i class="bi bi-pencil me-1"></i>Editar este caso</a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
</div>
</main>
<?php hv_pie(); ?>

<!-- Lógica de JavaScript -->
<script>
// Datos que entrega PHP: modo del formulario, id y caso guardado (vacío en un caso nuevo)
const MODO_FORMULARIO = <?= json_encode($modo) ?>;
const idCasoEditar = <?= (int)$idCaso ?>;
const datosPrecargados = <?= $modo === 'nuevo' ? '{}' : hv_json_para_script($jsonExistente) ?>;

// =================================================================
// APARTADOS DE LA HISTORIA CLÍNICA Y LA EXPLORACIÓN FÍSICA (plantilla para casos clínicos)
// Cada apartado se guarda en su propia clave. Al guardar, "historia" y "exploracion" (las que lee
// el juego) se arman con el texto libre del formato anterior y los apartados que tengan contenido.
// [clave, título, guía]
// =================================================================
const APARTADOS_HISTORIA = [
    { grupo: 'Antecedentes personales patológicos', apartados: [
        ['hc_app', 'Antecedentes personales patológicos (APP)', 'Enfermedades crónicas relevantes; fecha aproximada de diagnóstico (si aporta valor); tratamiento actual; grado de control; complicaciones importantes.'],
        ['hc_quirurgicos', 'Antecedentes quirúrgicos y transfusionales', 'Cirugías o transfusiones que puedan modificar el caso.'],
        ['hc_hospitalizaciones', 'Hospitalizaciones previas', 'Solo las relevantes: hospitalización reciente, infecciones nosocomiales, reingresos, eventos cardiovasculares.'],
        ['hc_medicamentos', 'Medicamentos habituales', 'Nombre del medicamento o terapéutica empleada; dosis (cuando sea relevante); adherencia al tratamiento.'],
        ['hc_alergias', 'Alergias', 'A medicamentos, alimentos, látex o ninguna conocida.']
    ]},
    { grupo: 'Antecedentes heredofamiliares', apartados: [
        ['hc_ahf', 'Antecedentes heredofamiliares (AHF)', 'Los que cambian el riesgo (DM, HAS, cáncer, cardiopatías, enfermedades autoinmunes) en abuelos, padres, tíos, cónyuge, hijos o primos. Investigar: diabetes mellitus, enfermedades tiroideas, hipertensión arterial, cardiopatías, nefropatías, enfermedades broncopulmonares, neurológicas, mentales, infectocontagiosas, reumáticas y neoplásicas.']
    ]},
    { grupo: 'Antecedentes personales no patológicos', apartados: [
        ['hc_habitos', 'Hábitos', 'Solo los relevantes: tabaquismo, alcoholismo, uso de drogas, actividad física, alimentación, sueño, hábitos higiénico-dietéticos.'],
        ['hc_habitacion', 'Habitación', 'Tipo de vivienda (jacal, departamento, vecindad, casa sola); distribución (cuartos, servicios, personas por habitación, convivencia con animales); higiene (iluminación, ventilación); baño intra o extradomiciliario, individual o compartido.'],
        ['hc_epidemiologicos', 'Antecedentes epidemiológicos', 'Viajes recientes; contacto con personas enfermas; animales (zoonosis); alimentación; consumo de agua; ocupación; inmunizaciones; relaciones sexuales (cuando aplique); transfusiones; exposición laboral; contacto con vectores; estancia hospitalaria; antibióticos recientes.']
    ]},
    { grupo: 'Antecedentes ginecoobstétricos', sexo: 'Femenino', apartados: [
        ['hc_ginecoobstetricos', 'Antecedentes ginecoobstétricos', 'Menarca; ciclo menstrual (frecuencia, duración, cantidad, dismenorrea); IVSA y número de parejas; gestas, partos, cesáreas, abortos; MAC; FUM; ITS; menopausia, climaterio; Papanicolaou; lactancia materna.']
    ]},
    { grupo: 'Interrogatorio por aparatos y sistemas', plegable: true, apartados: [
        ['ias_respiratorio', 'Aparato respiratorio', 'Rinorrea, rinolalia, epistaxis, tos, expectoración, disfonía, hemoptisis, vómica, cianosis, dolor torácico, disnea y sibilancias audibles a distancia.'],
        ['ias_digestivo', 'Aparato digestivo', 'Hambre, apetito, masticación y salivación, disfagia, halitosis, náusea, vómito, rumiación, regurgitación, pirosis, aerofagia, eructos, meteorismo, distensión abdominal, flatulencia, hematemesis, ictericia, características de las heces, diarrea, constipación, acolia, hipocolia, melena, rectorragia, parásitos, lientería, esteatorrea, pujo, tenesmo y prurito anal.'],
        ['ias_cardiovascular', 'Aparato cardiovascular', 'Palpitaciones, dolor precordial, disnea de esfuerzo, disnea paroxística, apnea, cianosis, acúfenos, fosfenos, tinnitus, síncope, lipotimias y edema.'],
        ['ias_renal_urinario', 'Aparato renal y urinario', 'Dolor renoureteral, disuria, anuria, oliguria, poliuria, polaquiuria, hematuria, piuria, coluria, urgencia, incontinencia, características del chorro, nictémero, goteo terminal y edema.'],
        ['ias_genital_masculino', 'Aparato genital masculino', 'Libido, práctica sexual, número de parejas, priapismo, alteraciones de la erección y la eyaculación, secreción uretral, dolor testicular, alteraciones escrotales, sensación de cuerpo extraño en el periné e ITS.', 'Masculino'],
        ['ias_genital_femenino', 'Aparato genital femenino', 'Leucorrea, hemorragias transvaginales, alteraciones menstruales, libido, práctica sexual, número de parejas, protección contra ITS, dispareunia, alteraciones sexuales, amenorrea y Papanicolaou.', 'Femenino'],
        ['ias_endocrino', 'Sistema endocrino', 'Intolerancia al frío o al calor, hipo o hiperactividad, aumento de volumen del cuello, polidipsia, polifagia, poliuria, cambios en los caracteres sexuales secundarios, aumento o pérdida de peso.'],
        ['ias_hematopoyetico', 'Sistema hematopoyético y linfático', 'Palidez, disnea, fatigabilidad, astenia, palpitaciones, sangrado, equimosis, petequias y adenomegalias.'],
        ['ias_tegumentario', 'Sistema tegumentario', 'Coloración, pigmentación, prurito, características del pelo y las uñas, lesiones (primarias y secundarias), hiperhidrosis y xerodermia.'],
        ['ias_musculoesqueletico', 'Sistema musculoesquelético', 'Mialgias, dolor óseo, artralgias, alteraciones de la marcha, hipotonía, disminución del volumen muscular, limitación de movimientos y deformidades.'],
        ['ias_nervioso', 'Sistema nervioso', 'Cefalea, paresias, plejias, parálisis, parestesias, movimientos anormales (temblores, tics, corea), alteraciones de la marcha, vértigo y mareos.'],
        ['ias_sentidos', 'Órganos de los sentidos', 'Alteraciones de la visión, la audición, el olfato, el gusto y el tacto; mareo y sensación de líquido en el oído.'],
        ['ias_psiquica', 'Esfera psíquica', 'Tristeza, euforia, alteraciones del sueño, terrores nocturnos, ideaciones (alucinatorias, delirantes, obsesivas, suicidas), miedos exagerados, irritabilidad, apatía; relaciones personales.'],
        ['ias_generales', 'Síntomas generales', 'Fiebre, astenia, adinamia, aumento o pérdida de peso y modificaciones del hambre (hiporexia, anorexia, hiperorexia).']
    ]}
];

const APARTADOS_EXPLORACION = [
    { grupo: 'Exploración segmentaria', apartados: [
        ['ef_cabeza', 'Cabeza', 'Solo si aporta.'],
        ['ef_cuello', 'Cuello', 'Solo si aporta.'],
        ['ef_cardiovascular', 'Cardiovascular', 'Ruidos, soplos, pulsos, llenado capilar.'],
        ['ef_respiratorio', 'Respiratorio', 'Inspección, palpación, percusión, auscultación.'],
        ['ef_abdomen', 'Abdomen', 'Inspección, auscultación, percusión, palpación.'],
        ['ef_extremidades', 'Extremidades', 'Edema, pulsos, fuerza, movilidad (derecha / izquierda).'],
        ['ef_neurologico', 'Neurológico', 'Glasgow, pares craneales, sensibilidad, fuerza, reflejos, cerebelo.'],
        ['ef_tegumentario', 'Tegumentario', 'Solo si aporta.']
    ]},
    { grupo: 'Hallazgos', apartados: [
        ['ef_hallazgos_positivos', 'Hallazgos positivos', 'Hallazgos positivos relevantes para el diagnóstico.'],
        ['ef_hallazgos_negativos', 'Hallazgos negativos', 'Hallazgos negativos relevantes para el diagnóstico.']
    ]}
];

function hvEscaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

// Dibuja los apartados en el contenedor. Los grupos "plegables" se muestran cerrados (se abren solos si tienen texto).
function construirApartados(idContenedor, grupos, clase) {
    const contenedor = document.getElementById(idContenedor);
    grupos.forEach(grupo => {
        const bloque = document.createElement('div');
        bloque.className = 'mb-4 grupo-apartados';
        if (grupo.sexo) bloque.dataset.sexo = grupo.sexo;
        let html = grupo.grupo && (grupos.length > 1 || grupo.plegable || grupo.conTitulo) ? `<h6 class="fw-bold text-secondary mb-2">${hvEscaparHtml(grupo.grupo)}</h6>` : '';
        grupo.apartados.forEach(([clave, titulo, guia, sexo]) => {
            const campo = `<textarea class="form-control ${clase}" id="${clave}" name="${clave}" rows="2" data-titulo="${hvEscaparHtml(titulo)}"></textarea>`
                        + `<div class="form-text">${hvEscaparHtml(guia)}</div>`;
            const atributoSexo = sexo ? ` data-sexo="${sexo}"` : '';
            if (grupo.plegable) {
                html += `<details class="border rounded px-3 py-2 mb-2 apartado-plegable"${atributoSexo}>`
                      + `<summary class="fw-bold">${hvEscaparHtml(titulo)} <span class="badge rounded-pill bg-success ms-2 d-none indicador-contenido">con información</span></summary>`
                      + `<div class="pt-2">${campo}</div></details>`;
            } else {
                html += `<div class="mb-3"${atributoSexo}><label for="${clave}" class="form-label small fw-bold">${hvEscaparHtml(titulo)}</label>${campo}</div>`;
            }
        });
        bloque.innerHTML = html;
        contenedor.appendChild(bloque);
    });
}

// Texto "Título: contenido" de los apartados con información, separados por una línea en blanco
function armarTextoApartados(clase) {
    return Array.from(document.querySelectorAll('textarea.' + clase))
        .filter(campo => campo.value.trim() !== '')
        .map(campo => campo.dataset.titulo + ': ' + campo.value.trim())
        .join('\n\n');
}

function valorCampo(nombre) {
    const campo = document.querySelector(`[name="${nombre}"]`);
    return campo ? campo.value.trim() : '';
}

// Línea de signos vitales y somatometría para la exploración física armada
function lineaSignosVitales() {
    const partes = [];
    const agregar = (nombre, prefijo, unidad) => { const v = valorCampo(nombre); if (v) partes.push(prefijo + ' ' + v + (unidad ? ' ' + unidad : '')); };
    agregar('signos_ta', 'TA', 'mmHg');
    agregar('signos_fc', 'FC', 'lpm');
    agregar('signos_fr', 'FR', 'rpm');
    agregar('signos_temp', 'Temperatura', '°C');
    agregar('signos_sato2', 'SatO2', '%');
    agregar('somatometria_peso', 'Peso', 'kg');
    agregar('somatometria_talla', 'Talla', 'cm');
    const imc = calcularImc();
    if (imc) partes.push('IMC ' + imc + ' kg/m²');
    return partes.length ? 'Signos vitales y somatometría: ' + partes.join(', ') + '.' : '';
}

function calcularImc() {
    const peso = parseFloat(valorCampo('somatometria_peso'));
    const tallaCm = parseFloat(valorCampo('somatometria_talla'));
    if (!(peso > 0) || !(tallaCm > 0)) return '';
    return (peso / Math.pow(tallaCm / 100, 2)).toFixed(1);
}

// Textos que se guardan en "historia" y "exploracion". Si ningún apartado tiene información,
// queda solo el texto libre del formato anterior (así un caso anterior se guarda sin cambios).
function armarHistoria() {
    const apartados = armarTextoApartados('apartado-historia');
    const libre = document.getElementById('historia_texto_libre').value;
    if (apartados === '') return libre; // sin apartados, el texto anterior se guarda exactamente igual
    return [libre.trim(), apartados].filter(t => t !== '').join('\n\n');
}

function armarExploracion() {
    const apartados = armarTextoApartados('apartado-exploracion');
    let armado = '';
    if (apartados !== '') {
        // El aspecto general va primero, luego los signos vitales y después la exploración segmentaria
        const aspecto = valorCampo('ef_aspecto_general');
        const resto = Array.from(document.querySelectorAll('textarea.apartado-exploracion'))
            .filter(campo => campo.name !== 'ef_aspecto_general' && campo.value.trim() !== '')
            .map(campo => campo.dataset.titulo + ': ' + campo.value.trim());
        armado = [aspecto ? 'Aspecto general: ' + aspecto : '', lineaSignosVitales(), ...resto].filter(t => t !== '').join('\n\n');
    }
    const libre = document.getElementById('exploracion_texto_libre').value;
    if (armado === '') return libre; // sin apartados, el texto anterior se guarda exactamente igual
    return [libre.trim(), armado].filter(t => t !== '').join('\n\n');
}

// =================================================================
// LABORATORIOS Y DIAGNÓSTICO, TRATAMIENTO Y SEGUIMIENTO
// =================================================================
const APARTADOS_LABORATORIOS = [
    { grupo: 'Laboratorios básicos', apartados: [
        ['lab_bh', 'Biometría hemática', 'Hb, Hto, leucocitos, fórmula diferencial, plaquetas.'],
        ['lab_qs', 'Química sanguínea', 'Glucosa, urea/BUN, creatinina, electrolitos y otros según el caso.'],
        ['lab_reactantes', 'Reactantes inflamatorios', 'PCR, PCT, VSG, ferritina, dímero D.'],
        ['lab_funcion_organica', 'Pruebas de función orgánica', 'PFH, EGO, coagulación, enzimas cardiacas, perfil lipídico.']
    ]},
    { grupo: 'Estudios microbiológicos y patológicos (solo si el caso lo requiere)', plegable: true, apartados: [
        ['micro_calidad', 'Calidad de la muestra', 'Adecuada o inadecuada, o criterios utilizados.'],
        ['micro_directo', 'Examen directo', 'Gram, KOH, Ziehl-Neelsen, gota gruesa, en fresco, tinta china.'],
        ['micro_cultivos', 'Cultivos', 'Medio utilizado, morfología colonial, tiempo de crecimiento.'],
        ['micro_identificacion', 'Identificación', 'Pruebas bioquímicas, MALDI-TOF, PCR, ELISA, inmunofluorescencia.'],
        ['micro_susceptibilidad', 'Susceptibilidad', 'Antibiograma, CIM, resistencias relevantes.']
    ]},
    { grupo: 'Imagenología (solo si el caso lo requiere)', plegable: true, apartados: [
        ['img_rx', 'Radiografía', 'Descripción radiográfica.'],
        ['img_us', 'Ultrasonido', 'Descripción de los hallazgos.'],
        ['img_tac', 'Tomografía (TAC)', 'Descripción de los hallazgos.'],
        ['img_rm', 'Resonancia magnética (RM)', 'Descripción de los hallazgos.'],
        ['img_nuclear', 'Medicina nuclear', 'Descripción de los hallazgos.']
    ]},
    { grupo: 'Otros estudios', apartados: [
        ['est_funcionales', 'Estudios funcionales', 'ECG, espirometría, gasometría, ecocardiograma, EEG, EMG.'],
        ['est_especiales', 'Estudios especiales', 'Broncoscopia, colonoscopia, biopsia, LCR, citología.']
    ]},
    { grupo: 'Integración', apartados: [
        ['lab_integracion', 'Integración diagnóstica de los estudios', 'Integra lo que aportan los estudios anteriores al diagnóstico.']
    ]}
];

const APARTADOS_DIAGNOSTICO = [
    { grupo: 'Diagnósticos', conTitulo: true, apartados: [
        ['dx_sindromatico', 'Diagnóstico sindromático', ''],
        ['dx_anatomico', 'Diagnóstico anatómico', ''],
        ['dx_clinico', 'Diagnóstico clínico', ''],
        ['dx_etiologico', 'Diagnóstico etiológico', ''],
        ['dx_definitivo', 'Diagnóstico definitivo', '']
    ]}
];
const APARTADOS_TRATAMIENTO = [
    { grupo: 'Tratamiento', conTitulo: true, apartados: [
        ['tx_medidas_generales', 'Medidas generales', 'Hospitalización, dieta, oxígeno, hidratación, aislamiento.']
    ]}
];
const APARTADOS_TRATAMIENTO_2 = [
    { grupo: '', apartados: [
        ['tx_no_farmacologico', 'Tratamiento no farmacológico', 'Rehabilitación, ejercicio, curaciones, educación, suspensión de hábitos (modificaciones en el estilo de vida).'],
        ['tx_procedimientos', 'Procedimientos', 'Cirugía, drenajes, biopsias, intubación, broncoscopia.']
    ]}
];
const APARTADOS_SEGUIMIENTO = [
    { grupo: 'Seguimiento', conTitulo: true, apartados: [
        ['seg_proxima_cita', 'Próxima cita', 'Si es necesaria: cuándo y con qué objetivo.'],
        ['seg_estudios', 'Estudios para la próxima cita', '']
    ]}
];

// Tablas (listas de objetos): [campo, etiqueta, ejemplo, ancho de columna]
const COLUMNAS_TABLAS = {
    'filas-estadificacion': [['escala', 'Escala', 'Ej. CURB-65', 3], ['puntaje', 'Puntaje', 'Ej. 2', 2], ['interpretacion', 'Interpretación', '', 3], ['implicaciones', 'Implicaciones', '', 3]],
    'filas-farmacologico': [['medicamento', 'Medicamento', 'Ej. Ceftriaxona', 3], ['dosis', 'Dosis', 'Ej. 1 g', 2], ['via', 'Vía', 'Ej. IV', 2], ['frecuencia', 'Frecuencia', 'Ej. cada 24 h', 2], ['duracion', 'Duración', 'Ej. 7 días', 2]]
};

function agregarFila(idContenedor, valores) {
    const fila = document.createElement('div');
    fila.className = 'row g-2 align-items-end mb-2 fila-tabla';
    let html = '';
    COLUMNAS_TABLAS[idContenedor].forEach(([campo, etiqueta, ejemplo, ancho]) => {
        html += `<div class="col-12 col-md-${ancho}"><label class="form-label small text-muted mb-0">${hvEscaparHtml(etiqueta)}</label>`
              + `<input type="text" class="form-control form-control-sm" data-campo="${campo}" placeholder="${hvEscaparHtml(ejemplo)}"></div>`;
    });
    html += '<div class="col-12 col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100 btn-quitar-fila" title="Quitar renglón"><i class="bi bi-trash"></i></button></div>';
    fila.innerHTML = html;
    fila.querySelectorAll('input').forEach(entrada => {
        const valor = valores ? valores[entrada.dataset.campo] : '';
        entrada.value = (valor === undefined || valor === null) ? '' : String(valor);
    });
    fila.querySelector('.btn-quitar-fila').addEventListener('click', () => { fila.remove(); actualizarApartados(); });
    document.getElementById(idContenedor).appendChild(fila);
}

// Renglones con algún dato, como lista de objetos
function leerFilas(idContenedor) {
    return Array.from(document.querySelectorAll(`#${idContenedor} .fila-tabla`)).map(fila => {
        const objeto = {};
        fila.querySelectorAll('input').forEach(entrada => { objeto[entrada.dataset.campo] = entrada.value.trim(); });
        return objeto;
    }).filter(objeto => Object.values(objeto).some(valor => valor !== ''));
}

function textoApartado(clave) {
    const campo = document.getElementById(clave);
    return campo && campo.value.trim() !== '' ? campo.dataset.titulo + ': ' + campo.value.trim() : '';
}

// Texto libre del formato anterior + texto armado; sin texto armado, el anterior se guarda exactamente igual
function conTextoLibre(idLibre, armado) {
    const libre = document.getElementById(idLibre).value;
    if (armado === '') return libre;
    return [libre.trim(), armado].filter(t => t !== '').join('\n\n');
}

function armarLaboratorios() {
    return conTextoLibre('laboratorios_texto_libre', armarTextoApartados('apartado-laboratorios'));
}

function armarDiagnosticoTratamiento() {
    const estadificacion = leerFilas('filas-estadificacion');
    const farmacologico = leerFilas('filas-farmacologico');
    const textoEstadificacion = estadificacion.length === 0 ? '' : 'Estadificación:\n' + estadificacion.map(f => '- ' + [
        f.escala, f.puntaje ? 'Puntaje: ' + f.puntaje : '', f.interpretacion ? 'Interpretación: ' + f.interpretacion : '', f.implicaciones ? 'Implicaciones: ' + f.implicaciones : ''
    ].filter(t => t !== '').join('. ')).join('\n');
    const textoFarmacologico = farmacologico.length === 0 ? '' : 'Tratamiento farmacológico:\n' + farmacologico.map(f => '- ' + [
        f.medicamento, f.dosis, f.via, f.frecuencia, f.duracion ? 'durante ' + f.duracion : ''
    ].filter(t => t !== '').join(', ')).join('\n');
    const armado = [
        ...['dx_sindromatico', 'dx_anatomico', 'dx_clinico', 'dx_etiologico', 'dx_definitivo'].map(textoApartado),
        textoEstadificacion,
        textoApartado('tx_medidas_generales'),
        textoFarmacologico,
        textoApartado('tx_no_farmacologico'),
        textoApartado('tx_procedimientos'),
        textoApartado('seg_proxima_cita'),
        textoApartado('seg_estudios')
    ].filter(t => t !== '').join('\n\n');
    return conTextoLibre('diagnostico_tratamiento_texto_libre', armado);
}

// Estado visual: apartados plegables abiertos o marcados si tienen texto, apartados según el sexo,
// IMC y vista previa de los textos armados
function actualizarApartados() {
    document.querySelectorAll('details.apartado-plegable').forEach(detalle => {
        const tieneTexto = detalle.querySelector('textarea').value.trim() !== '';
        detalle.querySelector('.indicador-contenido').classList.toggle('d-none', !tieneTexto);
    });
    const sexo = (document.querySelector('input[name="sexo"]:checked') || {}).value || '';
    document.querySelectorAll('#formulario-caso [data-sexo]').forEach(elemento => {
        const tieneTexto = Array.from(elemento.querySelectorAll('textarea')).some(t => t.value.trim() !== '');
        elemento.classList.toggle('d-none', sexo !== '' && sexo !== elemento.dataset.sexo && !tieneTexto);
    });
    const imc = calcularImc();
    document.getElementById('texto-imc').textContent = imc ? 'IMC calculado: ' + imc + ' kg/m²' : '';
    document.getElementById('vista-previa-historia').value = armarHistoria();
    document.getElementById('vista-previa-exploracion').value = armarExploracion();
    document.getElementById('vista-previa-laboratorios').value = armarLaboratorios();
    document.getElementById('vista-previa-diagnostico').value = armarDiagnosticoTratamiento();
}

document.addEventListener('DOMContentLoaded', () => {

    construirApartados('apartados-historia', APARTADOS_HISTORIA, 'apartado-historia');
    construirApartados('apartados-exploracion', APARTADOS_EXPLORACION, 'apartado-exploracion');
    construirApartados('apartados-laboratorios', APARTADOS_LABORATORIOS, 'apartado-laboratorios');
    construirApartados('apartados-diagnostico', APARTADOS_DIAGNOSTICO, 'apartado-diagnostico');
    construirApartados('apartados-tratamiento', APARTADOS_TRATAMIENTO, 'apartado-diagnostico');
    construirApartados('apartados-tratamiento-2', APARTADOS_TRATAMIENTO_2, 'apartado-diagnostico');
    construirApartados('apartados-seguimiento', APARTADOS_SEGUIMIENTO, 'apartado-diagnostico');

    // Tablas de estadificación y tratamiento farmacológico: renglones guardados (o uno vacío para empezar)
    [['estadificacion', 'filas-estadificacion', 'btn-add-estadificacion'],
     ['tx_farmacologico', 'filas-farmacologico', 'btn-add-farmacologico']].forEach(([clave, idContenedor, idBoton]) => {
        const guardadas = Array.isArray(datosPrecargados[clave]) ? datosPrecargados[clave] : [];
        guardadas.forEach(fila => { if (fila && typeof fila === 'object') agregarFila(idContenedor, fila); });
        if (guardadas.length === 0 && MODO_FORMULARIO !== 'ver') agregarFila(idContenedor);
        document.getElementById(idBoton).addEventListener('click', () => agregarFila(idContenedor));
    });

    // Contadores para asegurar names únicos
    let contadorPreguntas = 1;
    let contadorDiagnosticos = 1;

    // Referencias al DOM principales
    const btnAddPregunta = document.getElementById('btn-add-pregunta');
    const btnAddDiagnostico = document.getElementById('btn-add-diagnostico');
    const btnAddAutor = document.getElementById('btn-add-autor');
    const btnAddEnlace = document.getElementById('btn-add-enlace');
    const btnAddSimulacion = document.getElementById('btn-add-simulacion-pregunta');
    const btnAddMetacognicion = document.getElementById('btn-add-metacognicion-pregunta');
    const containerPreguntas = document.getElementById('preguntas-container');
    const containerDiagnosticos = document.getElementById('diagnosticos-container');
    const containerAutores = document.getElementById('autores-container');
    const containerEnlaces = document.getElementById('enlaces-container');
    const containerSimulacion = document.getElementById('simulacion-preguntas-container');
    const containerMetacognicion = document.getElementById('metacognicion-preguntas-container');
    const formulario = document.getElementById('formulario-caso');
    const selectorCorrecto = document.getElementById('diagnostico_correcto');

    // Lógica para el select "Otro" en Padecimiento
    const selectPadecimiento = document.getElementById('padecimiento');
    const inputPadecimientoOtro = document.getElementById('padecimiento-otro-texto');
    selectPadecimiento.addEventListener('change', (e) => {
        if(e.target.value === 'Otro') {
            inputPadecimientoOtro.classList.remove('d-none');
            inputPadecimientoOtro.focus();
        } else {
            inputPadecimientoOtro.classList.add('d-none');
            inputPadecimientoOtro.value = ''; // Limpiar si se desmarca
        }
    });

    // Lógica para el checkbox "Otro" en departamentos
    const checkboxOtro = document.getElementById('checkbox-otro');
    const inputOtroTexto = document.getElementById('departamento-otro-texto');
    checkboxOtro.addEventListener('change', (e) => {
        if(e.target.checked) {
            inputOtroTexto.classList.remove('d-none');
            inputOtroTexto.focus();
        } else {
            inputOtroTexto.classList.add('d-none');
            inputOtroTexto.value = ''; // Limpiar si se desmarca
        }
    });

    // Lógica dinámica para checkboxes "Otra" en Asignaturas
    for (let i = 1; i <= 4; i++) {
        const checkAsig = document.getElementById(`checkbox-asig-${i}`);
        const textAsig = document.getElementById(`asig-${i}-otro-texto`);
        if (checkAsig && textAsig) {
            checkAsig.addEventListener('change', (e) => {
                if(e.target.checked) {
                    textAsig.classList.remove('d-none');
                    textAsig.focus();
                } else {
                    textAsig.classList.add('d-none');
                    textAsig.value = '';
                }
            });
        }
    }
    

    // Añadir Autor dinámicamente
    btnAddAutor.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 autor-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" name="autores" placeholder="Nombre completo del autor">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar autor"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerAutores.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Pregunta dinámicamente
    btnAddPregunta.addEventListener('click', () => {
        contadorPreguntas++;
        const html = `
            <div class="card card-question mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Pregunta ${contadorPreguntas}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Enunciado de la pregunta:</label>
                        <input type="text" class="form-control" name="pregunta_${contadorPreguntas}" placeholder="Escribe la pregunta aquí...">
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 1</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_1" placeholder="Texto de la opción 1">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_1" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 2</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_2" placeholder="Texto de la opción 2">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_2" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 3</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_3" placeholder="Texto de la opción 3">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_3" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-white">
                                <label class="form-label fw-bold small text-primary">Opción 4</label>
                                <input type="text" class="form-control form-control-sm mb-2" name="p${contadorPreguntas}_opcion_4" placeholder="Texto de la opción 4">
                                <textarea class="form-control form-control-sm" name="p${contadorPreguntas}_razon_4" rows="2" placeholder="Razonamiento (por qué es correcta o incorrecta)..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-success"><i class="bi bi-check-circle me-1"></i>Respuesta Correcta:</label>
                        <select class="form-select border-success" name="p${contadorPreguntas}_correcta">
                            <option value="" selected disabled>-- Selecciona la opción correcta --</option>
                            <option value="Opción 1">Opción 1</option>
                            <option value="Opción 2">Opción 2</option>
                            <option value="Opción 3">Opción 3</option>
                            <option value="Opción 4">Opción 4</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
        containerPreguntas.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Diagnóstico Diferencial dinámicamente
    btnAddDiagnostico.addEventListener('click', () => {
        contadorDiagnosticos++;
        const html = `
            <div class="card card-diagnostic mb-3 animate-fade question-block">
                <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-bottom-0 pt-3">
                    <h6 class="mb-0 fw-bold text-secondary">Diagnóstico Diferencial ${contadorDiagnosticos}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove"><i class="bi bi-trash btn-remove"></i></button>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <input type="text" class="form-control" name="diag_dif_${contadorDiagnosticos}" placeholder="Nombre del diagnóstico diferencial...">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-success"><i class="bi bi-plus-circle me-1"></i> Puntos a favor:</label>
                            <textarea class="form-control" name="diag_dif_favor_${contadorDiagnosticos}" rows="2" placeholder="Hallazgos que apoyan..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-danger"><i class="bi bi-dash-circle me-1"></i> Puntos en contra:</label>
                            <textarea class="form-control" name="diag_dif_contra_${contadorDiagnosticos}" rows="2" placeholder="Hallazgos que descartan..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        containerDiagnosticos.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Enlace dinámicamente
    btnAddEnlace.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 enlace-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                <input type="text" class="form-control" name="enlaces_material" placeholder="Ej. https://... o referencia bibliográfica">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar enlace"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerEnlaces.insertAdjacentHTML('beforeend', html);
    });
    
    // Función que lee los inputs y actualiza el select
    let diagnosticoGuardadoAplicado = false;
    function sincronizarSelector() {
        // Magia aquí: el selector [name^="diag_dif_"] atrapa diag_dif_1, diag_dif_2, diag_dif_99...
        const inputsDiagnosticos = document.querySelectorAll('input[name^="diag_dif_"]'); 
        
        // Guardamos el valor que el usuario ya había seleccionado para no borrarlo al escribir
        // (la primera vez, el diagnóstico correcto guardado en el caso; después, lo que el usuario eligió)
        let valorActual = selectorCorrecto.value;
        if (!diagnosticoGuardadoAplicado) {
            diagnosticoGuardadoAplicado = true;
            if (datosPrecargados.diagnostico_correcto) {
                valorActual = datosPrecargados.diagnostico_correcto;
            }
        }
        
        // Limpiamos el selector
        selectorCorrecto.innerHTML = '<option value="">Seleccione el diagnóstico correcto...</option>';
        
        let hayOpciones = false;

        // Recorremos cada campo dinámico encontrado
        inputsDiagnosticos.forEach(input => {
            const texto = input.value.trim();
            
            // Si el campo no está vacío, lo agregamos como opción
            if (texto !== '') {
                hayOpciones = true;
                const opcion = document.createElement('option');
                opcion.value = texto; // Esto es lo que viajará a PHP al guardar
                opcion.textContent = texto;
                
                // Si este era el que estaba seleccionado, lo mantenemos
                if (texto === valorActual) {
                    opcion.selected = true;
                }
                
                selectorCorrecto.appendChild(opcion);
            }
        });

        // Mensaje por defecto si borran todo
        if (!hayOpciones) {
            selectorCorrecto.innerHTML = '<option value="">Primero ingresa los diagnósticos diferenciales arriba...</option>';
        }
        
        
    }

    // EVENTO 1: Detectar cuando el usuario ESCRIBE en los campos dinámicos
    document.body.addEventListener('input', function(e) {
        // Verifica si el campo donde escribió tiene un name que empiece con "diag_dif_"
        if (e.target && e.target.name && e.target.name.startsWith('diag_dif_')) {
            sincronizarSelector();
        }
    });

    // EVENTO 2: Detectar cuando el usuario AGREGA o ELIMINA un campo dinámico
    // Escuchamos los clics en todo el documento
    document.body.addEventListener('click', function(e) {
        // Si el elemento clicado es un botón (como "Agregar diagnóstico" o "Eliminar")
        // le damos un brevísimo tiempo (100ms) para que el campo nuevo aparezca 
        // o el viejo desaparezca del HTML, y luego actualizamos la lista.
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
            setTimeout(sincronizarSelector, 100);
        }
    });

    // Añadir Pregunta de Simulación dinámicamente
    btnAddSimulacion.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 simulacion-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-question-circle"></i></span>
                <input type="text" class="form-control" name="simulacion_preguntas" placeholder="Escribe una pregunta para la entrevista al paciente virtual...">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerSimulacion.insertAdjacentHTML('beforeend', html);
    });

    // Añadir Pregunta de Metacognición dinámicamente
    btnAddMetacognicion.addEventListener('click', () => {
        const html = `
            <div class="input-group mb-2 metacognicion-input-group animate-fade">
                <span class="input-group-text"><i class="bi bi-lightbulb text-warning"></i></span>
                <input type="text" class="form-control" name="metacognicion_preguntas" placeholder="Escribe una pregunta de metacognición...">
                <button class="btn btn-outline-danger btn-remove" type="button" title="Eliminar pregunta"><i class="bi bi-trash btn-remove"></i></button>
            </div>
        `;
        containerMetacognicion.insertAdjacentHTML('beforeend', html);
    });

    // Delegación de Eventos para ELIMINAR bloques dinámicos
    document.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('btn-remove')) {
            // Maneja si hicieron clic en el ícono o en el botón
            let btn = e.target.closest('.btn-remove');
            
            // Busca el contenedor padre (pregunta, diagnóstico, autor, enlace, simulación o metacognición)
            let bloque = btn.closest('.question-block') || btn.closest('.autor-input-group') || btn.closest('.enlace-input-group') || btn.closest('.simulacion-input-group') || btn.closest('.metacognicion-input-group');

            if (bloque) {
                bloque.style.transition = "opacity 0.2s ease-out, transform 0.2s ease-out";
                bloque.style.opacity = "0";
                bloque.style.transform = "scale(0.98)";
                setTimeout(() => bloque.remove(), 200);
            }
        }
    });

    // ==========================================
    // LÓGICA DE ARCHIVOS ADJUNTOS (LABORATORIOS)
    // ==========================================
    const btnAddLabFile = document.getElementById('btn-add-lab-file');
    const inputLabFiles = document.getElementById('input-lab-files');
    const labFilesList = document.getElementById('lab-files-list');
    let archivosLaboratorio = []; // Array en memoria para los archivos

    if(btnAddLabFile && inputLabFiles) {
        // Dispara el clic del input oculto
        btnAddLabFile.addEventListener('click', () => inputLabFiles.click());

        // Detecta cuando el usuario selecciona archivos
        inputLabFiles.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                // Evita duplicados comparando nombre y tamaño
                if (!archivosLaboratorio.some(f => f.name === file.name && f.size === file.size)) {
                    archivosLaboratorio.push(file);
                }
            });
            renderizarListaArchivos();
            inputLabFiles.value = ''; // Resetea para permitir volver a elegir el mismo si se borró
        });
    }

    // Función para dibujar la lista de archivos en el HTML
    function renderizarListaArchivos() {
        labFilesList.innerHTML = '';
        if (archivosLaboratorio.length === 0) {
            labFilesList.innerHTML = '<li class="list-group-item bg-transparent text-muted small text-center border-0 py-1">No hay archivos seleccionados</li>';
            return;
        }

        archivosLaboratorio.forEach((file, index) => {
            // Icono dinámico según tipo
            let icon = 'bi-file-earmark';
            if(file.type.startsWith('image/')) icon = 'bi-file-image text-primary';
            else if(file.type === 'application/pdf') icon = 'bi-file-pdf text-danger';
            
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2); // Tamaño en MB

            const li = document.createElement('li');
            li.className = 'list-group-item bg-transparent d-flex justify-content-between align-items-center px-2 py-1 animate-fade border-bottom';
            li.innerHTML = `
                <div class="d-flex align-items-center text-truncate pe-2">
                    <i class="bi ${icon} fs-5 me-2"></i>
                    <span class="text-truncate small fw-medium" title="${file.name}">${file.name}</span>
                    <span class="text-muted ms-2 small" style="font-size: 0.75rem;">(${sizeMB} MB)</span>
                </div>
                <button type="button" class="btn btn-sm text-danger btn-remove-file p-0 ms-2" data-index="${index}" title="Quitar archivo">
                    <i class="bi bi-x-circle fs-5"></i>
                </button>
            `;
            labFilesList.appendChild(li);
        });

        // Asignar evento de borrado a los nuevos botones "x"
        document.querySelectorAll('.btn-remove-file').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                archivosLaboratorio.splice(index, 1); // Borra del array
                renderizarListaArchivos(); // Redibuja
            });
        });
    }
    
    // Inicializa la vista vacía
    if(labFilesList) renderizarListaArchivos();


    // ==========================================
    // CAPTURA Y ENVÍO DE DATOS
    // ==========================================
    formulario.addEventListener('submit', (e) => {
        e.preventDefault();
        if (MODO_FORMULARIO === 'ver') return; // solo lectura

        const esNuevo = (MODO_FORMULARIO === 'nuevo');
        const datosParaEnviar = new FormData(formulario);
        const datosDelCaso = {};
        
        const btnSubmit = document.getElementById('btn-guardar');
        const originalHTML = btnSubmit.innerHTML;

        // Aplicamos la misma lógica estructurada exacta del index.html para compilar los datos limpios
        for (let [key, value] of datosParaEnviar.entries()) {
            if (value instanceof File) continue;

            if (key.endsWith('[]') || key === 'departamentos' || key === 'sistemas_anatomicos' || key === 'asignaturas' || key === 'preguntas' || key === 'autores') {
                if (!datosDelCaso[key]) {
                    datosDelCaso[key] = [];
                }
                if (value.trim() !== "") {
                    datosDelCaso[key].push(value);
                }
            } else {
                if (datosDelCaso[key]) {
                    if (!Array.isArray(datosDelCaso[key])) {
                        datosDelCaso[key] = [datosDelCaso[key]];
                    }
                    datosDelCaso[key].push(value);
                } else {
                    datosDelCaso[key] = value;
                }
            }
        }

        // Textos que lee el juego, armados con el texto libre del formato anterior y los apartados
        datosDelCaso.historia = armarHistoria();
        datosDelCaso.exploracion = armarExploracion();
        datosDelCaso.laboratorios = armarLaboratorios();
        datosDelCaso.diagnostico_tratamiento = armarDiagnosticoTratamiento();
        datosDelCaso.estadificacion = leerFilas('filas-estadificacion');
        datosDelCaso.tx_farmacologico = leerFilas('filas-farmacologico');

        // Claves guardadas que el formulario ya no muestra (p. ej. el ART, que ahora se califica en el juego):
        // se conservan tal cual para no perder datos al editar. Las que el formulario maneja (aunque el autor
        // las haya vaciado o quitado) no se recuperan.
        if (!esNuevo) {
            const camposDelFormulario = new Set(Array.from(formulario.elements).map(el => el.name).filter(Boolean));
            ['autores', 'enlaces_material', 'simulacion_preguntas', 'metacognicion_preguntas',
             'departamentos', 'departamentos_19', 'sistemas_anatomicos', 'asignaturas',
             'archivos_adjuntos', 'archivos_adjuntos_previos'].forEach(c => camposDelFormulario.add(c));
            const camposDinamicos = /^(diag_dif_|pregunta_\d+$|p\d+_)/;
            for (const clave in datosPrecargados) {
                if (camposDelFormulario.has(clave) || camposDinamicos.test(clave) || clave in datosDelCaso) continue;
                datosDelCaso[clave] = datosPrecargados[clave];
            }
        }

        // Nombres de los archivos adjuntos: los que ya tenía el caso (también los de la forma anterior,
        // archivos_adjuntos_previos) más los que se agregan ahora
        const nombresAdjuntos = [];
        [datosPrecargados.archivos_adjuntos, datosPrecargados.archivos_adjuntos_previos].forEach(lista => {
            if (Array.isArray(lista)) {
                lista.forEach(nombre => { if (!nombresAdjuntos.includes(nombre)) nombresAdjuntos.push(nombre); });
            }
        });
        archivosLaboratorio.forEach(f => nombresAdjuntos.push(f.name));
        if (nombresAdjuntos.length > 0) {
            datosDelCaso["archivos_adjuntos"] = nombresAdjuntos;
        }

        // Animación de carga
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + (esNuevo ? 'Guardando...' : 'Actualizando Caso...');
        btnSubmit.disabled = true;

        const payloadFinal = new FormData();
        if (!esNuevo) {
            payloadFinal.append('id_caso', idCasoEditar); // Enviamos el ID obligatorio para la consulta SQL UPDATE
        }
        payloadFinal.append('datos_json', JSON.stringify(datosDelCaso));
        payloadFinal.append('csrf_token', '<?= hv_token_csrf() ?>');

        // Archivos nuevos seleccionados en este formulario
        archivosLaboratorio.forEach((file) => {
            payloadFinal.append('archivos[]', file);
        });

        // Nuevo caso → guardar_caso.php; edición → actualizar_caso.php
        fetch(esNuevo ? 'guardar_caso.php' : 'actualizar_caso.php', {
            method: 'POST',
            body: payloadFinal
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                btnSubmit.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> ' + (esNuevo ? '¡Guardado con éxito!' : '¡Cambios guardados!');
                setTimeout(() => {
                    window.location.href = esNuevo ? "lista_casos.php?msg=success" : "lista_casos.php?msg=updated";
                }, esNuevo ? 3000 : 2000);
            } else {
                alert((esNuevo ? "Error del servidor: " : "Error en la actualización: ") + data.message);
                btnSubmit.innerHTML = originalHTML;
                btnSubmit.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error de red:', error);
            alert("Error de comunicación con el servidor remoto.");
            btnSubmit.innerHTML = originalHTML;
            btnSubmit.disabled = false;
        });
    });

    // =================================================================
    // CARGA DEL CASO GUARDADO (modos editar y ver)
    // =================================================================
    if (MODO_FORMULARIO !== 'nuevo') {
            // --- RECONSTRUCCIÓN DE DIAGNÓSTICOS DIFERENCIALES DINÁMICOS ---
            // 1. Averiguamos cuántos diagnósticos diferenciales se guardaron en total
            let maxDiagnosticos = 1;
            
            // Recorremos las llaves buscando el número de índice más alto guardado (ej: diag_dif_3 -> 3)
            for (const key in datosPrecargados) {
                if (key.startsWith('diag_dif_') && !key.startsWith('diag_dif_favor_') && !key.startsWith('diag_dif_contra_')) {
                    const numeroIdx = parseInt(key.replace('diag_dif_', ''));
                    if (numeroIdx > maxDiagnosticos) {
                        maxDiagnosticos = numeroIdx;
                    }
                }
            }
            
            // 2. Forzamos los clics necesarios en el botón "Agregar" para crear las tarjetas en el HTML
            // Empezamos desde 2 ya que la tarjeta 1 ya existe por defecto en el HTML de tu formulario
            for (let i = 2; i <= maxDiagnosticos; i++) {
                const btnAgregarDiag = document.getElementById('btn-add-diagnostico') || document.querySelector('[onclick*="diagnostico"]');
                if (btnAgregarDiag) {
                    // Tu función nativa al hacer clic clona la tarjeta e incrementa los name="diag_dif_X" automáticamente
                    btnAgregarDiag.click(); 
                }
            }
            
            // 3. Una vez creadas todas las tarjetas necesarias en el DOM, las poblamos con sus respectivos valores
            for (let i = 1; i <= maxDiagnosticos; i++) {
                const campoNombre = document.querySelector(`[name="diag_dif_${i}"]`);
                const campoFavor = document.querySelector(`[name="diag_dif_favor_${i}"]`);
                const campoContra = document.querySelector(`[name="diag_dif_contra_${i}"]`);

                if (campoNombre && datosPrecargados[`diag_dif_${i}`]) {
                    campoNombre.value = datosPrecargados[`diag_dif_${i}`];
                }
                if (campoFavor && datosPrecargados[`diag_dif_favor_${i}`]) {
                    campoFavor.value = datosPrecargados[`diag_dif_favor_${i}`];
                }
                if (campoContra && datosPrecargados[`diag_dif_contra_${i}`]) {
                    campoContra.value = datosPrecargados[`diag_dif_contra_${i}`];
                }
            }
            
            // =================================================================
            // 2. RECONSTRUCCIÓN DE PREGUNTAS DE DISCUSIÓN DINÁMICAS INDEXADAS
            // =================================================================
            let maxPreguntas = 1;
            // Buscamos el índice numérico más alto guardado para las preguntas (ej: pregunta_3 -> 3)
            for (const key in datosPrecargados) {
                if (key.startsWith('pregunta_')) {
                    const numeroIdx = parseInt(key.replace('pregunta_', ''));
                    if (numeroIdx > maxPreguntas) {
                        maxPreguntas = numeroIdx;
                    }
                }
            }

            // Forzamos la creación de los bloques de preguntas en el DOM (la pregunta_1 ya existe en tu HTML)
            for (let i = 2; i <= maxPreguntas; i++) {
                const btnAgregarPregunta = document.getElementById('btn-add-pregunta') || document.querySelector('[onclick*="pregunta"]');
                if (btnAgregarPregunta) {
                    btnAgregarPregunta.click(); // Esto clona el diseño nativo de tu index.html y genera 'pregunta_i'
                }
            }

            // Una vez que todas las preguntas están creadas en la interfaz, asignamos sus textos correspondientes
            for (let i = 1; i <= maxPreguntas; i++) {
                const campoPregunta = document.querySelector(`[name="pregunta_${i}"]`);
                if (campoPregunta && datosPrecargados[`pregunta_${i}`]) {
                    campoPregunta.value = datosPrecargados[`pregunta_${i}`];
                }
            }
            
            // =================================================================
            // 5. RECONSTRUCCIÓN DE ENLACES O MATERIAL COMPLEMENTARIO (COMO ARREGLO)
            // =================================================================
            if (datosPrecargados.enlaces_material && Array.isArray(datosPrecargados.enlaces_material)) {
                datosPrecargados.enlaces_material.forEach((enlaceText, index) => {
                    // Seleccionamos todos los inputs destinados a 'enlaces_material' en el formulario
                    let inputsEnlaces = document.querySelectorAll('input[name="enlaces_material"]');
                    
                    // Si el índice actual supera los inputs que hay en pantalla, creamos una nueva fila
                    if (index >= inputsEnlaces.length) {
                        // Buscamos tu botón para agregar enlaces por su ID o clase
                        const btnAgregarEnlace = document.getElementById('btn-add-material') || 
                                                 document.getElementById('btn-add-enlace') || 
                                                 document.querySelector('[onclick*="enlaces_material"]');
                        if (btnAgregarEnlace) {
                            btnAgregarEnlace.click(); // Forzamos la clonación nativa de la fila
                            // Volvemos a escanear el DOM para incluir la nueva fila creada
                            inputsEnlaces = document.querySelectorAll('input[name="enlaces_material"]');
                        }
                    }
                    
                    // Si el input existe en esa posición, le asignamos el string del enlace correspondiente
                    if (inputsEnlaces[index]) {
                        inputsEnlaces[index].value = enlaceText;
                    }
                });
            }

            // =================================================================
            // 3. CONTINUACIÓN DEL PROCESAMIENTO PARA LOS DEMÁS CAMPOS FIJOS/MÚLTIPLES
            // =================================================================
            for (const key in datosPrecargados) {
                // Saltamos diagnósticos y preguntas porque ya fueron tratados de manera precisa arriba
                if (key.startsWith('diag_dif_') || key.startsWith('pregunta_') || key === 'enlaces_material') continue;

                const valor = datosPrecargados[key];
                if (valor === undefined || valor === null) continue;

                // Forma anterior del departamento "Otro" (casos guardados desde Editar antes de unificar)
                if (key === 'departamentos_19') {
                    if (valor === 'Otro') {
                        const checkboxOtroGuardado = document.getElementById('checkbox-otro');
                        checkboxOtroGuardado.checked = true;
                        checkboxOtroGuardado.dispatchEvent(new Event('change'));
                    }
                    continue;
                }

                // Procesar Checkboxes múltiples (departamentos, sistemas_anatomicos, asignaturas)
                if (Array.isArray(valor) && (key === 'departamentos'
                                             || key === 'sistemas_anatomicos'
                                             || key === 'asignaturas')) {
                    valor.forEach(val => {
                        const checkbox = document.querySelector(`input[type="checkbox"][name="${key}"][value="${CSS.escape(String(val))}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    });
                    continue;
                }
                
                // Procesar Autores en formato arreglo plano secundario (si aplica)
                if (key === 'autores' && Array.isArray(valor)) {
                    valor.forEach((autor, index) => {
                        let inputsAutores = document.querySelectorAll('input[name="autores"]');
                        if (index >= inputsAutores.length) {
                            const btnAgregar = document.getElementById('btn-add-autor');
                            if (btnAgregar) {
                                btnAgregar.click();
                                inputsAutores = document.querySelectorAll('input[name="autores"]');
                            }
                        }
                        if (inputsAutores[index]) inputsAutores[index].value = autor;
                    });
                    continue;
                }
                
                // Procesar Preguntas de simulación
                if (key === 'simulacion_preguntas' && Array.isArray(valor)) {
                    valor.forEach((preguntasim, index) => {
                        let inputsPsim = document.querySelectorAll('input[name="simulacion_preguntas"]');
                        if (index >= inputsPsim.length) {
                            const btnAgregar = document.getElementById('btn-add-simulacion-pregunta');
                            if (btnAgregar) {
                                btnAgregar.click();
                                inputsPsim = document.querySelectorAll('input[name="simulacion_preguntas"]');
                            }
                        }
                        if (inputsPsim[index]) inputsPsim[index].value = preguntasim;
                    });
                    continue;
                }
                
                // Procesar Preguntas de metacognición
                if (key === 'metacognicion_preguntas' && Array.isArray(valor)) {
                    valor.forEach((metap, index) => {
                        let inputsMetaP = document.querySelectorAll('input[name="metacognicion_preguntas"]');
                        if (index >= inputsMetaP.length) {
                            const btnAgregar = document.getElementById('btn-add-metacognicion-pregunta');
                            if (btnAgregar) {
                                btnAgregar.click();
                                inputsMetaP = document.querySelectorAll('input[name="metacognicion_preguntas"]');
                            }
                        }
                        if (inputsMetaP[index]) inputsMetaP[index].value = metap;
                    });
                    continue;
                }

                // Procesar Elementos de formulario estándar restantes (Texto, Selects, Radios)
                const elementoForm = document.querySelector(`[name="${key}"]`);
                if (elementoForm) {
                    if (elementoForm.type === 'radio') {
                        const radioEspecifico = document.querySelector(`input[type="radio"][name="${key}"][value="${CSS.escape(String(valor))}"]`);
                        if (radioEspecifico) {
                            radioEspecifico.checked = true;
                            radioEspecifico.dispatchEvent(new Event('change'));
                        }
                    } else {
                        elementoForm.value = valor;
                        elementoForm.dispatchEvent(new Event('change'));
                    }
                }
                
            }
    }

    // Formato anterior: si el caso no tiene todavía la clave del texto libre, su "historia" y su "exploracion"
    // guardadas pasan al cuadro de texto libre (los apartados nuevos empiezan vacíos)
    [['historia', 'historia_texto_libre', 'bloque-historia-anterior'],
     ['exploracion', 'exploracion_texto_libre', 'bloque-exploracion-anterior'],
     ['laboratorios', 'laboratorios_texto_libre', 'bloque-laboratorios-anterior'],
     ['diagnostico_tratamiento', 'diagnostico_tratamiento_texto_libre', 'bloque-diagnostico-anterior']].forEach(([claveJuego, claveLibre, idBloque]) => {
        const campoLibre = document.getElementById(claveLibre);
        if (!(claveLibre in datosPrecargados) && typeof datosPrecargados[claveJuego] === 'string') {
            campoLibre.value = datosPrecargados[claveJuego];
        }
        document.getElementById(idBloque).classList.toggle('d-none', campoLibre.value.trim() === '');
    });

    // Apartados plegables con información: abiertos al cargar
    document.querySelectorAll('details.apartado-plegable').forEach(detalle => {
        if (detalle.querySelector('textarea').value.trim() !== '') detalle.open = true;
    });
    actualizarApartados();
    formulario.addEventListener('input', actualizarApartados);
    formulario.addEventListener('change', actualizarApartados);

    // Con el caso ya cargado, se arma la lista del diagnóstico correcto con el valor guardado
    sincronizarSelector();

    // Modo ver: todo el formulario es de solo lectura
    if (MODO_FORMULARIO === 'ver') {
        formulario.querySelectorAll('input, select:not(.control-navegacion), textarea, button:not(.control-navegacion)').forEach(control => {
            control.disabled = true;
        });
        prepararLecturaApartados();
    }

    iniciarPasos();
});

// =================================================================
// PASOS DEL FORMULARIO: una sección a la vez, con índice y estado de avance
// =================================================================
// Campos que indican el avance de cada paso (los que revisa el verificador). "@campo" es un texto
// que se arma con los apartados o con el texto libre del formato anterior.
const CAMPOS_POR_PASO = [
    ['titulo_caso', 'departamentos', 'autores', 'sexo', 'edad', 'rango_edad', 'padecimiento', 'sistemas_anatomicos', 'asignaturas'],
    ['resumen', '@historia'],
    ['@exploracion', 'signos_ta', 'signos_fc', 'signos_fr', 'signos_temp', 'signos_sato2', 'somatometria_peso', 'somatometria_talla'],
    ['@laboratorios'],
    ['@diagnostico_tratamiento', 'diag_dif_1', 'diagnostico_correcto'],
    ['pregunta_1', 'p1_opcion_1', 'p1_opcion_2', 'p1_opcion_3', 'p1_opcion_4', 'p1_correcta', 'nota_medica', 'soap_subjetivo', 'soap_objetivo', 'soap_analisis', 'soap_plan'],
    ['prompts', 'tipo_interrogatorio']
];
let pasosFormulario = [];
let pasoActual = 0;
let viendoTodo = false;

function campoConValor(nombre) {
    if (nombre === '@historia') return armarHistoria().trim() !== '';
    if (nombre === '@exploracion') return armarExploracion().trim() !== '';
    if (nombre === '@laboratorios') return armarLaboratorios().trim() !== '';
    if (nombre === '@diagnostico_tratamiento') return armarDiagnosticoTratamiento().trim() !== '';
    return Array.from(document.querySelectorAll(`#formulario-caso [name="${nombre}"]`)).some(control =>
        (control.type === 'checkbox' || control.type === 'radio') ? control.checked : String(control.value || '').trim() !== '');
}

function iniciarPasos() {
    pasosFormulario = Array.from(document.querySelectorAll('.paso-formulario'));
    const indice = document.getElementById('indice-pasos');
    const selector = document.getElementById('selector-pasos');
    pasosFormulario.forEach((paso, i) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'hv-paso-indice control-navegacion';
        boton.innerHTML = `<span class="hv-paso-numero">${i + 1}</span><span class="hv-paso-texto">`
            + `<span class="hv-paso-titulo"><i class="bi ${hvEscaparHtml(paso.dataset.icono)} me-1"></i>${hvEscaparHtml(paso.dataset.titulo)}</span>`
            + `<span class="hv-paso-estado"></span></span>`;
        boton.addEventListener('click', () => mostrarPaso(i));
        indice.appendChild(boton);
        const opcion = document.createElement('option');
        opcion.value = i;
        selector.appendChild(opcion);
    });
    selector.addEventListener('change', () => mostrarPaso(parseInt(selector.value, 10)));
    document.getElementById('btn-paso-anterior').addEventListener('click', () => mostrarPaso(pasoActual - 1));
    document.getElementById('btn-paso-siguiente').addEventListener('click', () => mostrarPaso(pasoActual + 1));
    document.getElementById('btn-ver-todo').addEventListener('click', () => viendoTodo ? mostrarPaso(pasoActual) : mostrarTodo());

    const formulario = document.getElementById('formulario-caso');
    formulario.addEventListener('input', actualizarIndicePasos);
    formulario.addEventListener('change', actualizarIndicePasos);

    // Al guardar con un campo obligatorio vacío en un paso oculto, se abre ese paso y se señala el campo
    const botonGuardar = document.getElementById('btn-guardar');
    if (botonGuardar) {
        botonGuardar.addEventListener('click', (evento) => {
            if (formulario.checkValidity()) return;
            evento.preventDefault();
            const invalido = formulario.querySelector(':invalid');
            const paso = invalido ? invalido.closest('.paso-formulario') : null;
            if (paso && paso.classList.contains('d-none')) mostrarPaso(pasosFormulario.indexOf(paso), false);
            setTimeout(() => formulario.reportValidity(), 60);
        });
    }

    const enlace = /^#paso-(\d+)$/.exec(window.location.hash);
    actualizarIndicePasos();
    mostrarPaso(enlace ? parseInt(enlace[1], 10) - 1 : 0, false);
}

function actualizarIndicePasos() {
    const botones = document.querySelectorAll('#indice-pasos .hv-paso-indice');
    const opciones = document.querySelectorAll('#selector-pasos option');
    pasosFormulario.forEach((paso, i) => {
        const campos = CAMPOS_POR_PASO[i] || [];
        const llenos = campos.filter(campoConValor).length;
        const estado = llenos === 0 ? 'vacio' : (llenos === campos.length ? 'completo' : 'progreso');
        const texto = estado === 'completo' ? 'Completo' : (estado === 'vacio' ? 'Sin llenar' : `${llenos} de ${campos.length}`);
        if (botones[i]) {
            botones[i].dataset.estado = estado;
            botones[i].querySelector('.hv-paso-estado').textContent = texto;
        }
        if (opciones[i]) opciones[i].textContent = `${i + 1}. ${paso.dataset.titulo} · ${texto}`;
    });
}

function mostrarPaso(indice, desplazar = true) {
    viendoTodo = false;
    pasoActual = Math.max(0, Math.min(pasosFormulario.length - 1, indice));
    pasosFormulario.forEach((paso, i) => paso.classList.toggle('d-none', i !== pasoActual));
    document.querySelectorAll('#indice-pasos .hv-paso-indice').forEach((boton, i) => boton.classList.toggle('activo', i === pasoActual));
    document.getElementById('selector-pasos').value = String(pasoActual);

    const anterior = document.getElementById('btn-paso-anterior');
    const siguiente = document.getElementById('btn-paso-siguiente');
    anterior.classList.toggle('invisible', pasoActual === 0);
    siguiente.classList.toggle('invisible', pasoActual === pasosFormulario.length - 1);
    if (pasoActual > 0) anterior.querySelector('span').textContent = pasosFormulario[pasoActual - 1].dataset.titulo;
    if (pasoActual < pasosFormulario.length - 1) siguiente.querySelector('span').textContent = 'Siguiente: ' + pasosFormulario[pasoActual + 1].dataset.titulo;
    document.getElementById('texto-paso').textContent = `Paso ${pasoActual + 1} de ${pasosFormulario.length}`;
    document.getElementById('nav-pasos').classList.remove('d-none');
    document.getElementById('btn-ver-todo').innerHTML = '<i class="bi bi-list-ul me-1"></i>Ver todo';

    if (window.history && history.replaceState) history.replaceState(null, '', '#paso-' + (pasoActual + 1));
    if (MODO_FORMULARIO === 'ver') ajustarAlturasLectura(pasosFormulario[pasoActual]);
    if (desplazar) {
        const inicio = document.getElementById('formulario-caso').getBoundingClientRect().top + window.scrollY - 90;
        window.scrollTo({ top: Math.max(0, inicio), behavior: 'smooth' });
    }
}

// Todos los pasos de corrido (para leer el caso completo o buscar con Ctrl+F)
function mostrarTodo() {
    viendoTodo = true;
    pasosFormulario.forEach(paso => paso.classList.remove('d-none'));
    document.querySelectorAll('#indice-pasos .hv-paso-indice').forEach(boton => boton.classList.remove('activo'));
    document.getElementById('nav-pasos').classList.add('d-none');
    document.getElementById('btn-ver-todo').innerHTML = '<i class="bi bi-signpost-split me-1"></i>Ver por pasos';
    if (MODO_FORMULARIO === 'ver') ajustarAlturasLectura(document.getElementById('formulario-caso'));
}

// Modo ver: los cuadros con texto crecen a su contenido (solo se puede medir cuando están visibles)
function ajustarAlturasLectura(contenedor) {
    contenedor.querySelectorAll('textarea').forEach(campo => {
        if (campo.value.trim() !== '' && campo.offsetParent !== null) {
            campo.style.height = 'auto';
            campo.style.height = (campo.scrollHeight + 4) + 'px';
        }
    });
}

// Modo ver: el caso se lee como la plantilla. Se ocultan los apartados vacíos, los grupos y tablas
// sin información, las guías de captura y las vistas previas; los cuadros con texto crecen a su contenido.
function prepararLecturaApartados() {
    const vacio = campo => campo.value.trim() === '';
    document.querySelectorAll('textarea.apartado-historia, textarea.apartado-exploracion, textarea.apartado-laboratorios, textarea.apartado-diagnostico').forEach(campo => {
        const contenedor = campo.closest('details') || campo.closest('div.mb-3, div.mb-4');
        if (contenedor) contenedor.classList.toggle('d-none', vacio(campo));
    });
    document.querySelectorAll('.grupo-apartados').forEach(grupo => {
        const conTexto = Array.from(grupo.querySelectorAll('textarea')).some(campo => !vacio(campo));
        grupo.classList.toggle('d-none', !conTexto);
    });
    const segmentaria = document.getElementById('apartados-exploracion');
    document.getElementById('titulo-exploracion-segmentaria').classList.toggle('d-none',
        !Array.from(segmentaria.querySelectorAll('textarea')).some(campo => !vacio(campo)));
    ['filas-estadificacion', 'filas-farmacologico'].forEach(id => {
        const contenedor = document.getElementById(id);
        if (contenedor.children.length === 0) contenedor.closest('div.mb-4').classList.add('d-none');
    });
    document.querySelectorAll('.btn-quitar-fila').forEach(boton => boton.closest('div').classList.add('d-none'));
    ['btn-add-estadificacion', 'btn-add-farmacologico'].forEach(id => document.getElementById(id).classList.add('d-none'));
    document.querySelectorAll('.vista-previa-juego').forEach(detalle => detalle.classList.add('d-none'));
    document.querySelectorAll('textarea.apartado-historia + .form-text, textarea.apartado-exploracion + .form-text, textarea.apartado-laboratorios + .form-text, textarea.apartado-diagnostico + .form-text').forEach(guia => guia.classList.add('d-none'));
    // La altura de los cuadros con texto se ajusta al mostrar cada paso (ajustarAlturasLectura)
}
</script>

</body>
</html>
