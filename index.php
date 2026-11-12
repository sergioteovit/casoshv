<?php
// Portada pública del Gestor de Casos Clínicos (antes de iniciar sesión).
// Solo muestra cifras generales: nunca títulos, diagnósticos ni contenido de los casos.
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();
require_once __DIR__ . '/plantilla.php';

$conSesion = isset($_SESSION['usuario_id']);
$rol = $conSesion ? (string)($_SESSION['rol'] ?? 'Invitado') : '';
$cuentaActiva = $conSesion && $rol !== 'Invitado';

// Cifras generales (si la base no responde, la portada se muestra sin ellas)
$cifras = null;
try {
    require_once __DIR__ . '/config_bd.php';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $departamentos = [];
    $autores = [];
    $totalCasos = 0;
    foreach ($pdo->query('SELECT datos_completos FROM casos_clinicos') as $fila) {
        $totalCasos++;
        $datos = json_decode((string)$fila['datos_completos'], true);
        if (!is_array($datos)) {
            continue;
        }
        foreach ((array)($datos['departamentos'] ?? []) as $dep) {
            $dep = trim((string)$dep);
            if ($dep !== '' && $dep !== 'Otro') {
                $departamentos[$dep] = true;
            }
        }
        foreach ((array)($datos['autores'] ?? []) as $autor) {
            $autor = trim((string)$autor);
            if ($autor !== '') {
                $autores[function_exists('mb_strtolower') ? mb_strtolower($autor, 'UTF-8') : strtolower($autor)] = true;
            }
        }
    }
    $cifras = [
        'casos'         => $totalCasos,
        'departamentos' => count($departamentos),
        'autores'       => count($autores),
        'historicos'    => (int)$pdo->query('SELECT COUNT(*) FROM casos_historicos')->fetchColumn()
                         + (int)$pdo->query('SELECT COUNT(*) FROM tarjetas_hyfm')->fetchColumn(),
    ];
} catch (Throwable $e) {
    hv_error_interno($e);
}

function cifra($cifras, $clave)
{
    return $cifras === null ? '—' : number_format((int)$cifras[$clave]);
}

hv_encabezado('Inicio', 'inicio');
?>
<header class="hv-heroe">
    <svg class="hv-pulso" viewBox="0 0 1200 80" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 45 H380 l18 -32 l24 64 l20 -54 l14 22 H760 l18 -32 l24 64 l20 -54 l14 22 H1200"/>
    </svg>
    <div class="container position-relative">
        <div class="row align-items-end g-4">
            <div class="col-lg-6 pb-4 pb-lg-5 hv-aparecer">
                <span class="hv-etiqueta"><i class="bi bi-clipboard2-pulse"></i> Gestor de Casos Clínicos</span>
                <h1 class="mt-3">Los casos de Hospital Virtual nacen aquí</h1>
                <p class="lead mb-4">
                    Docentes de la Facultad de Medicina elaboran, revisan y enriquecen los casos clínicos
                    que los estudiantes resuelven con pacientes virtuales en Desafío Clínico.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($cuentaActiva): ?>
                        <a href="lista_casos.php" class="btn btn-oro btn-lg"><i class="bi bi-clipboard2-pulse me-1"></i>Ir a los casos</a>
                        <a href="nuevo_caso.php" class="btn btn-outline-light btn-lg"><i class="bi bi-plus-circle me-1"></i>Nuevo caso</a>
                    <?php elseif ($conSesion): ?>
                        <a href="cuenta_pendiente.php" class="btn btn-oro btn-lg"><i class="bi bi-hourglass-split me-1"></i>Ver el estado de mi cuenta</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-oro btn-lg"><i class="bi bi-person-circle me-1"></i>Iniciar sesión</a>
                        <a href="registro.php" class="btn btn-outline-light btn-lg"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
                    <?php endif; ?>
                </div>
                <div class="row g-2 mt-4">
                    <div class="col-6 col-xl-3"><div class="hv-indicador"><div class="hv-numero"><?php echo cifra($cifras, 'casos'); ?></div><div class="hv-rotulo">casos clínicos</div></div></div>
                    <div class="col-6 col-xl-3"><div class="hv-indicador"><div class="hv-numero"><?php echo cifra($cifras, 'departamentos'); ?></div><div class="hv-rotulo">departamentos</div></div></div>
                    <div class="col-6 col-xl-3"><div class="hv-indicador"><div class="hv-numero"><?php echo cifra($cifras, 'autores'); ?></div><div class="hv-rotulo">autores</div></div></div>
                    <div class="col-6 col-xl-3"><div class="hv-indicador"><div class="hv-numero"><?php echo cifra($cifras, 'historicos'); ?></div><div class="hv-rotulo">registros históricos</div></div></div>
                </div>
            </div>
            <div class="col-lg-6 text-center d-none d-md-block hv-aparecer hv-aparecer-2">
                <img class="hv-ilustracion img-fluid" src="assets/img/portada-heroe.png" alt="Dos docentes de medicina diseñan un caso clínico con hologramas de un expediente, una radiografía y estudios de laboratorio">
            </div>
        </div>
    </div>
</header>

<main class="container py-5">
    <section id="participar">
        <h2 class="text-center mb-1">¿Cómo participar?</h2>
        <p class="text-center text-muted mb-4">Tres pasos para empezar a escribir casos.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="hv-tarjeta hv-tarjeta-animada hv-paso">
                    <span class="hv-icono"><i class="bi bi-person-plus"></i></span>
                    <h3 class="h5">Crea tu cuenta</h3>
                    <p class="text-muted mb-0">Regístrate con tu correo y tu departamento. Tu cuenta queda en espera de activación.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="hv-tarjeta hv-tarjeta-animada hv-paso">
                    <span class="hv-icono hv-icono-turquesa"><i class="bi bi-shield-check"></i></span>
                    <h3 class="h5">Espera la activación</h3>
                    <p class="text-muted mb-0">Un administrador revisa tu registro y te asigna el rol de Editor para que puedas trabajar en los casos.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="hv-tarjeta hv-tarjeta-animada hv-paso">
                    <span class="hv-icono hv-icono-oro"><i class="bi bi-pencil-square"></i></span>
                    <h3 class="h5">Elabora y revisa casos</h3>
                    <p class="text-muted mb-0">Captura casos nuevos, corrige los existentes y adjunta imágenes o estudios de apoyo.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="caso" class="mt-5 pt-2">
        <div class="hv-tarjeta p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-5 text-center order-lg-2">
                    <img class="hv-figura-seccion img-fluid" src="assets/img/equipo-corazon.png" alt="Una médica y un médico estudian juntos el holograma de un corazón">
                </div>
                <div class="col-lg-7 order-lg-1">
                    <span class="hv-chip hv-chip-turquesa mb-2">Estructura de un caso</span>
                    <h2 class="mb-3">Todo lo que necesita un paciente virtual</h2>
                    <p class="text-muted">Cada caso se captura con un formulario estandarizado, para que el juego lo convierta en una consulta completa:</p>
                    <ul class="hv-lista-check">
                        <li><strong>Presentación del caso:</strong> ficha, motivo de consulta, historia clínica, exploración y estudios.</li>
                        <li><strong>Diagnósticos diferenciales</strong> con los argumentos a favor y en contra de cada uno.</li>
                        <li><strong>Preguntas con retroalimentación</strong> para cada opción de respuesta.</li>
                        <li><strong>Clasificación</strong> por sistema, padecimiento, asignaturas y departamentos.</li>
                        <li><strong>Simulación:</strong> lo que el paciente virtual sabe y cómo debe responder en la entrevista.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="roles" class="mt-5 pt-2">
        <h2 class="text-center mb-1">Roles en el gestor</h2>
        <p class="text-center text-muted mb-4">Cada cuenta ve y hace solo lo que le corresponde.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="hv-tarjeta hv-paso">
                    <span class="hv-icono hv-icono-coral"><i class="bi bi-hourglass-split"></i></span>
                    <h3 class="h5">Invitado <?php echo hv_rol('Invitado'); ?></h3>
                    <p class="text-muted mb-0">Cuenta recién creada. No ve casos hasta que un administrador la activa.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="hv-tarjeta hv-paso">
                    <span class="hv-icono hv-icono-turquesa"><i class="bi bi-pencil"></i></span>
                    <h3 class="h5">Editor <?php echo hv_rol('Editor'); ?></h3>
                    <p class="text-muted mb-0">Crea y edita casos clínicos, casos históricos y tarjetas de Historia y Filosofía de la Medicina.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="hv-tarjeta hv-paso">
                    <span class="hv-icono hv-icono-oro"><i class="bi bi-shield-lock"></i></span>
                    <h3 class="h5">Administrador <?php echo hv_rol('Administrador'); ?></h3>
                    <p class="text-muted mb-0">Además asigna los identificadores de los casos, borra registros y gestiona las cuentas de usuario.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-5">
        <div class="hv-llamada p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-md-auto text-center">
                    <img src="assets/img/mascota-trofeo.png" alt="" style="max-height: 9rem;">
                </div>
                <div class="col-md">
                    <h2 class="h3 mb-2">¿Quieres ver los casos en acción?</h2>
                    <p class="mb-0">Consulta las posiciones de los estudiantes, sus logros y descarga Hospital Virtual FacMed · Desafío Clínico.</p>
                </div>
                <div class="col-md-auto d-flex flex-wrap gap-2">
                    <a href="game/" class="btn btn-oro"><i class="bi bi-trophy me-1"></i>Estadísticas del juego</a>
                    <a href="game/index.php#descargar" class="btn btn-outline-light"><i class="bi bi-download me-1"></i>Descargar el juego</a>
                </div>
            </div>
        </div>
    </section>
</main>
<?php hv_pie(); ?>
</body>
</html>
