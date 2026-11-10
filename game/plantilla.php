<?php
// Encabezado, barra de navegación y pie comunes de las páginas de estadísticas.
// Uso: hv_encabezado('Título', 'clave_activa'); ...contenido...; hv_pie();

require_once __DIR__ . '/estadisticas_comun.php';

// Subir este número al cambiar hv.css para que los navegadores no usen la copia anterior
define('HV_VERSION_ESTILOS', '5');

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

// Etiqueta de nivel (Bronce a Diamante) según las estrellas de un reto
function hv_nivel($estrellas)
{
    $estrellas = max(0, min(5, (int)$estrellas));
    $nombre = est_nombre_nivel($estrellas);
    if ($nombre === null) {
        return '<span class="hv-nivel hv-nivel-0">Sin estrellas</span>';
    }
    return '<span class="hv-nivel hv-nivel-' . $estrellas . '">' . str_repeat('★', $estrellas) . ' ' . h($nombre) . '</span>';
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
    $conSesion = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['UserId']);
    // El rol de la sesión solo decide qué enlaces se muestran; cada página lo vuelve a revisar en la base
    $esAdmin = $conSesion && ($_SESSION['Role'] ?? '') === 'Administrator';
    $nombre = $conSesion ? (string)($_SESSION['Username'] ?? '') : '';

    $enlaces = ['posiciones' => ['index.php', 'Posiciones', 'bi-trophy']];
    if ($conSesion) {
        $enlaces['avance'] = ['mi_avance.php', 'Mi avance', 'bi-graph-up-arrow'];
        $enlaces['grupos'] = ['mis_grupos.php', 'Grupos', 'bi-people'];
        $enlaces['panel'] = ['dashboard.php', 'Mi panel', 'bi-grid'];
        if ($esAdmin) {
            $enlaces['admin'] = ['administracion.php', 'Administración', 'bi-shield-lock'];
        }
    }
    $mostrarBarra = $opciones['barra'] ?? true;
    $clase = $opciones['clase'] ?? '';
    $version = HV_VERSION_ESTILOS;
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Posiciones, logros y avance de Hospital Virtual FacMed · Desafío Clínico, el juego de casos clínicos de la Facultad de Medicina de la UNAM.">
    <meta name="csrf-token" content="<?php echo $conSesion ? h(hv_token_csrf()) : ''; ?>">
    <title><?php echo h($titulo); ?> · Hospital Virtual FacMed</title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@600;700;800&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/hv.css?v=<?php echo $version; ?>" rel="stylesheet">
</head>
<body class="<?php echo h($clase); ?>">
    <?php if ($mostrarBarra): ?>
<nav class="navbar navbar-expand-lg navbar-dark hv-barra sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo-hv-blanco.png" alt="Hospital Virtual FacMed · Desafío Clínico">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#hvMenu" aria-controls="hvMenu" aria-expanded="false" aria-label="Abrir el menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="hvMenu">
            <ul class="navbar-nav mx-auto gap-1 my-2 my-lg-0">
                <?php foreach ($enlaces as $clave => $enlace): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $clave === $activa ? ' active' : ''; ?>" href="<?php echo h($enlace[0]); ?>"<?php echo $clave === $activa ? ' aria-current="page"' : ''; ?>>
                            <i class="bi <?php echo h($enlace[2]); ?> me-1"></i><?php echo h($enlace[1]); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-center gap-2 pb-2 pb-lg-0">
                <?php if ($conSesion): ?>
                    <span class="hv-avatar" aria-hidden="true"><?php echo h(hv_iniciales($nombre)); ?></span>
                    <span class="hv-usuario me-2"><?php echo h($nombre); ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
                <?php else: ?>
                    <a href="acceso.php" class="btn btn-oro btn-sm"><i class="bi bi-person-circle me-1"></i>Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
    <?php endif; ?>
<div class="hv-principal">
    <?php
}

function hv_pie($opciones = [])
{
    $mostrarPie = $opciones['pie'] ?? true;
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
                <div class="fw-bold text-white">Hospital Virtual FacMed · Desafío Clínico</div>
                <div>Facultad de Medicina, Universidad Nacional Autónoma de México</div>
            </div>
            <div class="col-md-auto small">
                <a href="index.php">Posiciones</a> · <a href="acceso.php">Iniciar sesión</a>
            </div>
        </div>
    </div>
</footer>
    <?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php
}
