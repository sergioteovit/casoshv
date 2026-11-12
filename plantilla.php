<?php
// Encabezado, barra de navegación y pie comunes del Gestor de Casos Clínicos.
// Mismo diseño que la plataforma de estadísticas (game/), con estilos propios en assets/css/gestor.css.
// Uso: require plantilla.php; hv_encabezado('Título', 'clave_activa'); ...contenido...; hv_pie();
// La página que la usa ya cargó seguridad.php e inició la sesión.

require_once __DIR__ . '/seguridad.php';

// Subir este número al cambiar gestor.css para que los navegadores no usen la copia anterior
define('GESTOR_VERSION_ESTILOS', '3');

if (!function_exists('h')) {
    function h($texto)
    {
        return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
    }
}

function hv_iniciales($nombre)
{
    $nombre = trim((string)$nombre);
    if ($nombre === '') {
        return '?';
    }
    $partes = preg_split('/[\s._-]+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY);
    $iniciales = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $iniciales .= function_exists('mb_substr') ? mb_substr($parte, 0, 1, 'UTF-8') : substr($parte, 0, 1);
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($iniciales, 'UTF-8') : strtoupper($iniciales);
}

// Etiqueta de color para un rol
function hv_rol($rol)
{
    $rol = (string)$rol;
    $clase = in_array($rol, ['Administrador', 'Editor', 'Invitado'], true) ? $rol : 'Invitado';
    return '<span class="hv-rol hv-rol-' . $clase . '">' . h($rol) . '</span>';
}

// Franja de título de las páginas internas; $imagen es una ilustración opcional a la derecha
function hv_cabecera($titulo, $subtitulo = '', $icono = '', $imagen = '')
{
    ?>
<header class="hv-cabecera<?php echo $imagen !== '' ? ' hv-cabecera-con-figura' : ''; ?>">
    <?php if ($imagen !== ''): ?>
        <img class="hv-cabecera-figura d-none d-md-block" src="<?php echo h($imagen); ?>" alt="">
    <?php endif; ?>
    <div class="container hv-aparecer">
        <h1 class="h2"><?php if ($icono): ?><i class="bi <?php echo h($icono); ?> me-2"></i><?php endif; ?><?php echo h($titulo); ?></h1>
        <?php if ($subtitulo !== ''): ?><p><?php echo h($subtitulo); ?></p><?php endif; ?>
    </div>
</header>
    <?php
}

// $opciones: 'barra' => false para ocultar la barra (acceso), 'clase' => clase del <body>
function hv_encabezado($titulo, $activa = '', $opciones = [])
{
    $conSesion = isset($_SESSION['usuario_id']);
    // El rol de la sesión solo decide qué enlaces se muestran; cada página vuelve a revisar los permisos
    $rol = $conSesion ? (string)($_SESSION['rol'] ?? 'Invitado') : '';
    $esAdmin = $rol === 'Administrador';
    $activo = $conSesion && $rol !== 'Invitado';
    $nombre = $conSesion ? (string)($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? '') : '';

    $enlaces = ['inicio' => ['index.php', 'Inicio', 'bi-house']];
    if ($activo) {
        $enlaces['casos'] = ['lista_casos.php', 'Casos', 'bi-clipboard2-pulse'];
        $enlaces['nuevo'] = ['nuevo_caso.php', 'Nuevo caso', 'bi-plus-circle'];
        $enlaces['historicos'] = ['casos_historicos.php', 'Históricos', 'bi-journal-medical'];
        $enlaces['hyfm'] = ['hyfm.php', 'Historia y FM', 'bi-collection'];
        if ($esAdmin) {
            $enlaces['usuarios'] = ['gestion_usuarios.php', 'Usuarios', 'bi-people'];
            $enlaces['verificador'] = ['verificador_casos.php', 'Verificador', 'bi-ui-checks-grid'];
        }
    }
    $mostrarBarra = $opciones['barra'] ?? true;
    $clase = $opciones['clase'] ?? '';
    $version = GESTOR_VERSION_ESTILOS;
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestor de Casos Clínicos de Hospital Virtual FacMed: docentes de la Facultad de Medicina de la UNAM elaboran y revisan los casos que se juegan en Desafío Clínico.">
    <title><?php echo h($titulo); ?> · Gestor de Casos Clínicos</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/gestor.css?v=<?php echo $version; ?>" rel="stylesheet">
</head>
<body class="<?php echo h($clase); ?>">
    <?php if ($mostrarBarra): ?>
<nav class="navbar navbar-expand-xl navbar-dark hv-barra sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo-hv-blanco.png" alt="Hospital Virtual FacMed · Gestor de Casos Clínicos">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#hvMenu" aria-controls="hvMenu" aria-expanded="false" aria-label="Abrir el menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="hvMenu">
            <ul class="navbar-nav mx-auto gap-1 my-2 my-xl-0">
                <?php foreach ($enlaces as $clave => $enlace): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $clave === $activa ? ' active' : ''; ?>" href="<?php echo h($enlace[0]); ?>"<?php echo $clave === $activa ? ' aria-current="page"' : ''; ?>>
                            <i class="bi <?php echo h($enlace[2]); ?> me-1"></i><?php echo h($enlace[1]); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 pb-2 pb-xl-0">
                <?php if ($conSesion): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-usuario dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="hv-avatar" aria-hidden="true"><?php echo h(hv_iniciales($nombre)); ?></span>
                            <span class="hv-usuario"><?php echo h($nombre); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted">Rol: <?php echo hv_rol($rol); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person-gear me-2"></i>Mi perfil</a></li>
                            <li><a class="dropdown-item text-danger" href="logout.php?csrf=<?php echo h(hv_token_csrf()); ?>"><i class="bi bi-box-arrow-right me-2"></i>Salir</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="registro.php" class="btn btn-outline-light btn-sm">Crear cuenta</a>
                    <a href="login.php" class="btn btn-oro btn-sm"><i class="bi bi-person-circle me-1"></i>Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
    <?php endif; ?>
<div class="hv-principal">
    <?php
}

// Páginas de acceso (inicio de sesión, registro, cuenta pendiente): tarjeta de dos columnas sobre fondo morado.
// hv_acceso_inicio(...) abre la página y la columna del formulario; hv_acceso_fin() la cierra.
function hv_acceso_inicio($tituloPagina, $tituloMarca, $textoMarca, $imagen, $altImagen = '')
{
    hv_encabezado($tituloPagina, '', ['barra' => false, 'clase' => 'hv-acceso']);
    ?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="hv-tarjeta hv-acceso-tarjeta hv-aparecer">
                <div class="row g-0">
                    <div class="col-lg-6 hv-acceso-marca p-4 p-md-5 d-flex flex-column">
                        <a href="index.php"><img class="hv-logo img-fluid" src="assets/img/logo-hv-blanco.png" alt="Hospital Virtual FacMed · Ir a la página de inicio"></a>
                        <span class="hv-chip mt-4 align-self-start">Gestor de Casos Clínicos</span>
                        <h1 class="h3 mt-2"><?php echo h($tituloMarca); ?></h1>
                        <p class="mb-4"><?php echo h($textoMarca); ?></p>
                        <div class="text-center mt-auto d-none d-lg-block">
                            <img class="hv-figura img-fluid" src="<?php echo h($imagen); ?>" alt="<?php echo h($altImagen); ?>">
                        </div>
                    </div>
                    <div class="col-lg-6 p-4 p-md-5 bg-white">
    <?php
}

function hv_acceso_fin()
{
    ?>
                    </div>
                </div>
            </div>
            <div class="hv-acceso-pie text-center mt-4">
                <div class="d-flex justify-content-center align-items-center gap-3">
                    <img src="assets/img/escudo-unam-blanco.png" alt="Universidad Nacional Autónoma de México">
                    <img src="assets/img/escudo-fm-blanco-web.png" alt="Facultad de Medicina">
                </div>
                <p class="small mt-2 mb-0">Facultad de Medicina, Universidad Nacional Autónoma de México · <a href="index.php">Página de inicio</a></p>
            </div>
        </div>
    </div>
</div>
    <?php
    hv_pie(['pie' => false]);
    ?>
<script>
    // Mostrar u ocultar contraseñas: botones con data-mostrar="id-del-campo"
    document.querySelectorAll('[data-mostrar]').forEach(boton => {
        boton.addEventListener('click', () => {
            const campo = document.getElementById(boton.dataset.mostrar);
            const icono = boton.querySelector('i');
            const ver = campo.type === 'password';
            campo.type = ver ? 'text' : 'password';
            icono.classList.toggle('bi-eye', !ver);
            icono.classList.toggle('bi-eye-slash', ver);
            boton.title = ver ? 'Ocultar contraseña' : 'Mostrar contraseña';
        });
    });
</script>
    <?php
}

// $opciones: 'pie' => false para ocultar el pie
function hv_pie($opciones = [])
{
    $mostrarPie = $opciones['pie'] ?? true;
    $conSesion = isset($_SESSION['usuario_id']);
    ?>
</div>
    <?php if ($mostrarPie): ?>
<footer class="hv-pie">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-md-auto d-flex align-items-center gap-3">
                <img src="assets/img/escudo-unam-blanco.png" alt="Universidad Nacional Autónoma de México">
                <img src="assets/img/escudo-fm-blanco-web.png" alt="Facultad de Medicina">
            </div>
            <div class="col-md">
                <div class="fw-bold text-white">Hospital Virtual FacMed · Gestor de Casos Clínicos</div>
                <div>Facultad de Medicina, Universidad Nacional Autónoma de México</div>
                <div class="small mt-1">Proyecto apoyado por la Secihti en 2025.</div>
            </div>
            <div class="col-md-auto small">
                <a href="index.php">Inicio</a> · <a href="game/">Estadísticas del juego</a>
                <?php if (!$conSesion): ?> · <a href="login.php">Iniciar sesión</a><?php endif; ?>
            </div>
        </div>
    </div>
</footer>
    <?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php
}
