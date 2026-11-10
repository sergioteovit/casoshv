<?php
session_start();

// Validar que el usuario esté logueado (Cualquier rol tiene acceso)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php"); // O tu página de login
    exit;
}

$rolUsuario = $_SESSION['rol'];

// Lista fija e inmutable de periodos históricos
$periodos_disponibles = [
    "Prehistoria y Antigüedad Oriental. Prehistoria - siglo VI a. C.",
    "Medicina Náhuatl Prehispánica. Preclásico 2500 a.C. - Periodo Posclásico 1521 d.C.",
    "Medicina Antigua Clásica. Siglo VI a. C. al siglo II d. C.",
    "Edad Media y Mundo Cristiano/Islámico. Siglos V d. C. al XIV d. C.",
    "Humanismo y Renacimiento. Siglos XV y XVI",
    "Medicina Novohispana Siglo XVI. Siglo XVI",
    "Barroco. Siglo XVII",
    "Novohispano. Siglo XVII",
    "Ilustración. Siglo XVIII",
    "Medicina Novohispana. Siglo XVIII",
    "Era Científica y Positivista. Siglo XIX",
    "La Medicina Mexicana decimonónica. Siglo XIX",
    "Era Contemporánea y Prospectiva. Siglos XX, XXI y Futuro",
    "La medicina en México. Siglos XX y XXI"
];

// Estilos de color (fondo y texto) personalizados por periodo histórico
$estilos_periodos = [
    "Prehistoria y Antigüedad Oriental. Prehistoria - siglo VI a. C."=> "background-color: #8D6E63; color: #ffffff;",
    "Medicina Náhuatl Prehispánica. Preclásico 2500 a.C. - Periodo Posclásico 1521 d.C."=> "background-color: #E65100; color: #ffffff;",
    "Medicina Antigua Clásica. Siglo VI a. C. al siglo II d. C."=> "background-color: #D4AC0D; color: #1C2833;",
    "Edad Media y Mundo Cristiano/Islámico. Siglos V d. C. al XIV d. C."=> "background-color: #5D4037; color: #ffffff;",
    "Humanismo y Renacimiento. Siglos XV y XVI"=> "background-color: #27AE60; color: #ffffff;",
    "Medicina Novohispana Siglo XVI. Siglo XVI"=> "background-color: #A569BD; color: #ffffff;",
    "Barroco. Siglo XVII"=> "background-color: #C0392B; color: #ffffff;",
    "Novohispano. Siglo XVII" => "background-color: #8E44AD; color: #ffffff;",
    "Ilustración. Siglo XVIII" => "background-color: #F1C40F; color: #1C2833;",
    "Medicina Novohispana. Siglo XVIII"=> "background-color: #16A085; color: #ffffff;",
    "Era Científica y Positivista. Siglo XIX"=> "background-color: #2980B9; color: #ffffff;",
    "La Medicina Mexicana decimonónica. Siglo XIX"=> "background-color: #D35400; color: #ffffff;",
    "Era Contemporánea y Prospectiva. Siglos XX, XXI y Futuro" => "background-color: #2C3E50; color: #ffffff;",
    "La medicina en México. Siglos XX y XXI" => "background-color: #00838F; color: #ffffff;"
];

// Configuración de la BD
require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/seguridad.php';
hv_bloquear_invitado();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ==========================================
    // PROCESAR CREACIÓN DE NUEVA TARJETA
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_tarjeta') {
        
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para crear o modificar tarjetas.");
        }
        
        $nombre = trim($_POST['nombre_personaje']);
        $periodo = trim($_POST['periodo'] ?? '');
        $ano_nac = trim($_POST['ano_nacimiento']);
        $ano_muerte = trim($_POST['ano_muerte']);
        $importancia = trim($_POST['importancia_historica']);

        if (!in_array($periodo, $periodos_disponibles)) {
            $periodo = $periodos_disponibles[0];
        }

        $fechas_vida = $ano_nac;
        if (!empty($ano_muerte)) {
            $fechas_vida .= ' - ' . $ano_muerte;
        }

        $numero_tarjeta = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        $nueva_tarjeta = [
            'numero' => $numero_tarjeta,
            'nombre_personaje' => $nombre,
            'periodo' => $periodo,
            'fecha_nacimiento' => $fechas_vida,
            'ano_nacimiento' => $ano_nac,
            'ano_muerte' => $ano_muerte,
            'importancia_historica' => $importancia
        ];

        $json_datos = json_encode($nueva_tarjeta, JSON_UNESCAPED_UNICODE);

        try {
            $stmtInsert = $pdo->prepare("INSERT INTO tarjetas_hyfm (datos_tarjeta) VALUES (:datos)");
            $stmtInsert->execute([':datos' => $json_datos]);
            
            header("Location: hyfm.php?msg=creado");
            exit;
        } catch (PDOException $e) {
            $error_creacion = "Error al guardar la tarjeta: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // PROCESAR ELIMINACIÓN DE TARJETA
    // ==========================================
    if (isset($_GET['eliminar_id']) && is_numeric($_GET['eliminar_id'])) {
        
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para eliminar tarjetas.");
        }
        
        if (!hv_csrf_valido($_GET['csrf'] ?? null)) {
            http_response_code(403);
            die("Solicitud no válida. Recarga la página e inténtalo de nuevo.");
        }

        $id_eliminar = intval($_GET['eliminar_id']);
        
        try {
            $stmtDel = $pdo->prepare("DELETE FROM tarjetas_hyfm WHERE id = :id");
            $stmtDel->execute([':id' => $id_eliminar]);
            
            header("Location: hyfm.php?msg=eliminado");
            exit;
        } catch (PDOException $e) {
            $error_eliminacion = "Error al eliminar la tarjeta: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // PROCESAR DESCARGA DE TARJETA EN JSON
    // ==========================================
    if (isset($_GET['descargar_id']) && is_numeric($_GET['descargar_id'])) {
        $id_descargar = intval($_GET['descargar_id']);
        
        try {
            $stmtDesc = $pdo->prepare("SELECT datos_tarjeta FROM tarjetas_hyfm WHERE id = :id");
            $stmtDesc->execute([':id' => $id_descargar]);
            $json_crudo = $stmtDesc->fetchColumn();

            if ($json_crudo) {
                $datos_arreglo = json_decode($json_crudo, true);
                
                if (!isset($datos_arreglo['periodo']) || empty($datos_arreglo['periodo'])) {
                    $datos_arreglo['periodo'] = $periodos_disponibles[0];
                }

                $nombre_personaje = isset($datos_arreglo['nombre_personaje']) ? $datos_arreglo['nombre_personaje'] : 'tarjeta';
                $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_personaje);
                $nombre_archivo = "tarjeta_" . $nombre_limpio . ".json";

                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                
                echo json_encode($datos_arreglo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                die("Error: La tarjeta solicitada no existe.");
            }
        } catch (PDOException $e) {
            die("Error al procesar la descarga: " . $e->getMessage());
        }
    }
    
    // ==========================================
    // PROCESAR DESCARGA DE TODOS LOS JSON EN UN ZIP
    // ==========================================
    if (isset($_GET['descargar_todos']) && $_GET['descargar_todos'] === '1') {
        try {
            $stmtTodos = $pdo->query("SELECT datos_tarjeta FROM tarjetas_hyfm");
            $tarjetas_db = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

            if (count($tarjetas_db) > 0) {
                $nombreZip = 'personajes_historicos.zip';
                $zip = new ZipArchive;
                
                $archivo_temporal = tempnam(sys_get_temp_dir(), 'zip');

                if ($zip->open($archivo_temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    foreach ($tarjetas_db as $index => $fila) {
                        $json_crudo = $fila['datos_tarjeta'];
                        $datos_arreglo = json_decode($json_crudo, true);
                        
                        if (!isset($datos_arreglo['periodo']) || empty($datos_arreglo['periodo'])) {
                            $datos_arreglo['periodo'] = $periodos_disponibles[0];
                        }

                        $nombre_personaje = isset($datos_arreglo['nombre_personaje']) ? $datos_arreglo['nombre_personaje'] : 'tarjeta_' . $index;
                        $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_personaje);
                        $nombre_archivo = "tarjeta_" . $nombre_limpio . "_" . $index . ".json"; 
                        
                        $contenido_json = json_encode($datos_arreglo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        $zip->addFromString($nombre_archivo, $contenido_json);
                    }
                    $zip->close();

                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $nombreZip . '"');
                    header('Content-Length: ' . filesize($archivo_temporal));
                    header('Pragma: public');
                    header('Cache-Control: must-revalidate');
                    
                    readfile($archivo_temporal);
                    unlink($archivo_temporal);
                    exit; 
                } else {
                    $error_descarga = "No se pudo crear el archivo ZIP en el servidor.";
                }
            } else {
                $error_descarga = "La colección está vacía. No hay tarjetas para descargar.";
            }
        } catch (PDOException $e) {
            $error_descarga = "Error de base de datos al generar el ZIP: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // PROCESAR EDICIÓN DE TARJETA
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_tarjeta') {
        
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para editar tarjetas.");
        }
        
        $id_editar = intval($_POST['id_tarjeta']);
        $nombre = trim($_POST['nombre_personaje']);
        $periodo = trim($_POST['periodo'] ?? '');
        $ano_nac = trim($_POST['ano_nacimiento']);
        $ano_muerte = trim($_POST['ano_muerte']);
        $importancia = trim($_POST['importancia_historica']);
        $numero_existente = trim($_POST['numero_tarjeta']);

        if (!in_array($periodo, $periodos_disponibles)) {
            $periodo = $periodos_disponibles[0];
        }

        $fechas_vida = $ano_nac;
        if (!empty($ano_muerte)) {
            $fechas_vida .= ' - ' . $ano_muerte;
        }

        $tarjeta_actualizada = [
            'numero' => $numero_existente,
            'nombre_personaje' => $nombre,
            'periodo' => $periodo,
            'fecha_nacimiento' => $fechas_vida,
            'ano_nacimiento' => $ano_nac,
            'ano_muerte' => $ano_muerte,
            'importancia_historica' => $importancia
        ];

        $json_datos = json_encode($tarjeta_actualizada, JSON_UNESCAPED_UNICODE);

        try {
            $stmtUpdate = $pdo->prepare("UPDATE tarjetas_hyfm SET datos_tarjeta = :datos WHERE id = :id");
            $stmtUpdate->execute([
                ':datos' => $json_datos,
                ':id' => $id_editar
            ]);
            
            header("Location: hyfm.php?msg=actualizado");
            exit;
        } catch (PDOException $e) {
            $error_edicion = "Error al actualizar la tarjeta: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // CONSULTA DE TARJETAS (CON PAGINACIÓN Y ORDENAMIENTO)
    // ==========================================
    $orden_actual = isset($_GET['orden']) ? $_GET['orden'] : 'defecto';

    $limite = 10;
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) { $pagina = 1; }
    $offset = ($pagina - 1) * $limite;

    $stmtCount = $pdo->query("SELECT COUNT(*) FROM tarjetas_hyfm");
    $total_registros = $stmtCount->fetchColumn();
    $total_paginas = ceil($total_registros / $limite);

    if ($orden_actual === 'nacimiento') {
        $sql = "SELECT id, datos_tarjeta, fecha_registro, fecha_actualizacion 
                FROM tarjetas_hyfm 
                ORDER BY 
                    CASE 
                        WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento'))) LIKE '%a.c%' 
                          OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento'))) LIKE '%a. c%'
                        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento')) AS SIGNED) * -1
                        ELSE CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento')) AS SIGNED)
                    END ASC 
                LIMIT :limite OFFSET :offset";
    } elseif ($orden_actual === 'periodo') {
        // Generar lista entrecomillada para función FIELD de MySQL
        $lista_periodos_sql = implode(',', array_map(function($p) use ($pdo) {
            return $pdo->quote($p);
        }, $periodos_disponibles));

        $sql = "SELECT id, datos_tarjeta, fecha_registro, fecha_actualizacion 
                FROM tarjetas_hyfm 
                ORDER BY 
                    CASE 
                        WHEN FIELD(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.periodo')), $lista_periodos_sql) = 0 THEN 999
                        ELSE FIELD(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.periodo')), $lista_periodos_sql)
                    END ASC, id ASC 
                LIMIT :limite OFFSET :offset";
    } else {
        $sql = "SELECT id, datos_tarjeta, fecha_registro, fecha_actualizacion 
                FROM tarjetas_hyfm 
                ORDER BY id ASC 
                LIMIT :limite OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión o consulta: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Colección HyFM - Tarjetas Históricas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="lista_casos.php">
        <i class="bi bi-heart-pulse-fill text-danger me-2"></i>Sistema Base
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuNavegacion">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="menuNavegacion">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="lista_casos.php">Lista de Casos</a></li>
        <li class="nav-item"><a class="nav-link active text-info fw-bold" href="hyfm.php"><i class="bi bi-collection-fill me-1"></i> HyFM</a></li>
        <li class="nav-item"><a class="nav-link active text-info fw-bold" href="casos_historicos.php"><i class="bi bi-collection-fill me-1"></i> Casos Históricos</a></li>
      </ul>
      <div class="d-flex align-items-center text-white">
          <span class="me-3 small"><i class="bi bi-person-circle text-primary me-1"></i> <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?></span>
          <a href="logout.php" class="btn btn-sm btn-danger">Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 my-4">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'creado'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ¡La nueva tarjeta ha sido añadida a la colección!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_creacion)): ?>
        <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_creacion) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-trash3-fill me-2"></i> La tarjeta ha sido eliminada permanentemente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_descarga)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_descarga) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_eliminacion)): ?>
        <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_eliminacion) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'actualizado'): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> ¡La tarjeta ha sido actualizada correctamente!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_edicion)): ?>
        <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_edicion) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h2 class="d-flex align-items-center mb-1">
                <i class="bi bi-collection text-info me-2"></i>
                Colección de Tarjetas HyFM
                <span class="badge bg-info text-white ms-3 rounded-pill fs-6 shadow-sm">
                    <?= $total_registros ?> tarjetas en total
                </span>
            </h2>
            <p class="text-muted mb-0">Directorio de personajes históricos extraídos de estructura JSON.</p>
        </div>
        
        <div class="d-flex gap-2 mt-3 mt-md-0 flex-wrap">
            <a href="hyfm.php?descargar_todos=1" class="btn btn-outline-success shadow-sm text-nowrap" title="Descargar todos los JSON en ZIP">
                <i class="bi bi-file-earmark-zip-fill me-1"></i> Descargar Colección
            </a>
            
            <a href="hyfm.php?orden=nacimiento" class="btn <?= ($orden_actual === 'nacimiento') ? 'btn-primary' : 'btn-outline-primary' ?> shadow-sm text-nowrap">
                <i class="bi bi-sort-numeric-down me-1"></i> Nacimiento
            </a>

            <a href="hyfm.php?orden=periodo" class="btn <?= ($orden_actual === 'periodo') ? 'btn-primary' : 'btn-outline-primary' ?> shadow-sm text-nowrap">
                <i class="bi bi-hourglass-split me-1"></i> Periodo
            </a>

            <?php if ($orden_actual !== 'defecto'): ?>
                <a href="hyfm.php" class="btn btn-secondary shadow-sm text-nowrap" title="Volver al orden original">
                    <i class="bi bi-x-circle me-1"></i> Restablecer Orden
                </a>
            <?php endif; ?>

            <?php if ($_SESSION['rol'] !== 'Invitado'): ?>
                <button type="button" class="btn btn-info text-white shadow-sm fw-bold text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNuevaTarjeta">
                    <i class="bi bi-plus-circle me-1"></i> Nueva Tarjeta
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 80px;">Número</th>
                            <th style="width: 220px;">Personaje Histórico</th>
                            <th style="width: 200px;">Periodo</th>
                            <th style="width: 180px;">Fechas de vida</th>
                            <th>Importancia Histórica</th>
                            <th style="width: 180px;">Última Actualización</th>
                            <th class="text-center" style="width: 100px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarjetas as $tarjeta): 
                            $datos = json_decode($tarjeta['datos_tarjeta'], true);
                            if (!is_array($datos)) $datos = [];

                            $numero = isset($datos['numero']) ? $datos['numero'] : '-';
                            $nombre = isset($datos['nombre_personaje']) ? $datos['nombre_personaje'] : 'Desconocido';
                            
                            $periodo = (isset($datos['periodo']) && !empty($datos['periodo'])) ? $datos['periodo'] : $periodos_disponibles[0];
                            
                            $estilo_badge = isset($estilos_periodos[$periodo]) ? $estilos_periodos[$periodo] : "background-color: #6c757d; color: #ffffff;";

                            $fecha_nac = isset($datos['fecha_nacimiento']) ? $datos['fecha_nacimiento'] : 'No especificada';
                            $importancia = isset($datos['importancia_historica']) ? $datos['importancia_historica'] : 'Sin descripción';
                            
                            $creado = $tarjeta['fecha_registro'];
                            $actualizado = $tarjeta['fecha_actualizacion'];
                        ?>
                            <tr>
                                <td class="text-center">
                                    <span class="badge bg-secondary fs-6"><?= htmlspecialchars($numero) ?></span>
                                </td>
                                <td class="fw-bold text-primary fs-5">
                                    <?= htmlspecialchars($nombre) ?>
                                </td>
                                <td>
                                    <span class="badge text-wrap text-start shadow-sm" style="<?= $estilo_badge ?> padding: 0.5em 0.75em; font-size: 0.82rem; border-radius: 6px;">
                                        <?= htmlspecialchars($periodo) ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 text-muted me-1"></i> <?= htmlspecialchars($fecha_nac) ?>
                                </td>
                                <td>
                                    <?= nl2br(htmlspecialchars($importancia)) ?>
                                </td>
                                <td>
                                    <?php if ($creado === $actualizado): ?>
                                        <small class="text-muted d-block">
                                            <i class="bi bi-clock me-1"></i> Creado: <?= date('d/m/Y H:i', strtotime($creado)) ?>
                                        </small>
                                        <span class="badge bg-light text-dark border mt-1 font-monospace" style="font-size:0.75rem;">Original</span>
                                    <?php else: ?>
                                        <small class="text-muted d-block" style="font-size:0.8rem;">
                                            Creado: <?= date('d/m/Y', strtotime($creado)) ?>
                                        </small>
                                        <strong class="text-success d-block small mt-1">
                                            <i class="bi bi-pencil-fill me-1"></i> Modificado:<br>
                                            <?= date('d/m/Y H:i', strtotime($actualizado)) ?>
                                        </strong>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group" role="group" aria-label="Acciones de tarjeta">
                                        <?php if ($_SESSION['rol'] !== 'Invitado'): 
                                            $a_nac = isset($datos['ano_nacimiento']) ? $datos['ano_nacimiento'] : '';
                                            $a_mue = isset($datos['ano_muerte']) ? $datos['ano_muerte'] : '';
                                        ?>
                                            <a href="?descargar_id=<?= $tarjeta['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary shadow-sm" 
                                               title="Descargar datos en formato JSON">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-warning shadow-sm btn-editar-tarjeta"
                                                    data-id="<?= $tarjeta['id'] ?>"
                                                    data-numero="<?= htmlspecialchars($numero) ?>"
                                                    data-nombre="<?= htmlspecialchars($nombre) ?>"
                                                    data-periodo="<?= htmlspecialchars($periodo) ?>"
                                                    data-nacimiento="<?= htmlspecialchars($a_nac) ?>"
                                                    data-muerte="<?= htmlspecialchars($a_mue) ?>"
                                                    data-importancia="<?= htmlspecialchars($importancia) ?>"
                                                    title="Editar tarjeta">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?eliminar_id=<?= (int)$tarjeta['id'] ?>&amp;csrf=<?= hv_token_csrf() ?>"
                                               class="btn btn-sm btn-outline-danger shadow-sm" 
                                               onclick="return confirm('¿Estás seguro de que deseas eliminar la tarjeta de <?= addslashes($nombre) ?> permanentemente?');" 
                                               title="Eliminar tarjeta">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><i class="bi bi-dash"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($tarjetas)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Aún no hay tarjetas registradas en la colección.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- CONTROLES DE PAGINACIÓN -->
    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Navegación de páginas" class="mt-4 mb-4">
        <ul class="pagination justify-content-center shadow-sm">
            <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina - 1 ?><?= ($orden_actual !== 'defecto') ? '&orden='.$orden_actual : '' ?>">
                    <i class="bi bi-chevron-left"></i> Anterior
                </a>
            </li>
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <li class="page-item <?= ($pagina == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?><?= ($orden_actual !== 'defecto') ? '&orden='.$orden_actual : '' ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina + 1 ?><?= ($orden_actual !== 'defecto') ? '&orden='.$orden_actual : '' ?>">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php if ($_SESSION['rol'] !== 'Invitado'): ?>

<!-- MODAL CREAR TARJETA -->
<div class="modal fade" id="modalNuevaTarjeta" tabindex="-1" aria-labelledby="modalNuevaTarjetaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form action="hyfm.php" method="POST">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title fw-bold" id="modalNuevaTarjetaLabel">
              <i class="bi bi-person-badge-fill me-2"></i>Crear Nueva Tarjeta Coleccionable
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        
        <div class="modal-body p-4 bg-light">
          <input type="hidden" name="accion" value="crear_tarjeta">
          
          <div class="row mb-3">
              <div class="col-md-6 mb-3 mb-md-0">
                  <label class="form-label fw-bold text-secondary">Nombre del Personaje Histórico</label>
                  <input type="text" name="nombre_personaje" class="form-control" required placeholder="Ej. Marie Curie, Isaac Newton...">
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary">Periodo Histórico</label>
                  <select name="periodo" class="form-select" required>
                      <?php foreach ($periodos_disponibles as $p): ?>
                          <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>
          
          <div class="row mb-3">
              <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary">Año de Nacimiento</label>
                  <input type="text" name="ano_nacimiento" class="form-control" required placeholder="Ej. 1867">
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary">Año de Muerte <small class="text-muted fw-normal">(Opcional)</small></label>
                  <input type="text" name="ano_muerte" class="form-control" placeholder="Ej. 1934 (Dejar en blanco si sigue vivo)">
              </div>
          </div>
          
          <div class="row mb-3">
              <div class="col-12">
                  <label class="form-label fw-bold text-secondary">Importancia Histórica</label>
                  <textarea name="importancia_historica" class="form-control" rows="4" required placeholder="Describe los logros más importantes o el impacto histórico del personaje..."></textarea>
              </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info text-white fw-bold">
              <i class="bi bi-save-fill me-1"></i> Guardar Tarjeta
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL EDITAR TARJETA -->
<div class="modal fade" id="modalEditarTarjeta" tabindex="-1" aria-labelledby="modalEditarTarjetaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form action="hyfm.php" method="POST">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold" id="modalEditarTarjetaLabel">
              <i class="bi bi-pencil-square me-2"></i>Editar Tarjeta Coleccionable
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        
        <div class="modal-body p-4 bg-light">
          <input type="hidden" name="accion" value="editar_tarjeta">
          <input type="hidden" name="id_tarjeta" id="edit_id_tarjeta">
          <input type="hidden" name="numero_tarjeta" id="edit_numero_tarjeta">
          
          <div class="row mb-3">
              <div class="col-md-6 mb-3 mb-md-0">
                  <label class="form-label fw-bold text-secondary">Nombre del Personaje Histórico</label>
                  <input type="text" name="nombre_personaje" id="edit_nombre" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary">Periodo Histórico</label>
                  <select name="periodo" id="edit_periodo" class="form-select" required>
                      <?php foreach ($periodos_disponibles as $p): ?>
                          <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>
          
          <div class="row mb-3">
              <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary">Año de Nacimiento</label>
                  <input type="text" name="ano_nacimiento" id="edit_nacimiento" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary">Año de Muerte <small class="text-muted fw-normal">(Opcional)</small></label>
                  <input type="text" name="ano_muerte" id="edit_muerte" class="form-control">
              </div>
          </div>
          
          <div class="row mb-3">
              <div class="col-12">
                  <label class="form-label fw-bold text-secondary">Importancia Histórica</label>
                  <textarea name="importancia_historica" id="edit_importancia" class="form-control" rows="4" required></textarea>
              </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-bold">
              <i class="bi bi-check-circle-fill me-1"></i> Guardar Cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
    
<?php endif; ?>
    
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
<script>
document.addEventListener('DOMContentLoaded', function () {
    const botonesEditar = document.querySelectorAll('.btn-editar-tarjeta');
    const modalElemento = document.getElementById('modalEditarTarjeta');

    if (modalElemento) {
        const modalEditar = new bootstrap.Modal(modalElemento);

        botonesEditar.forEach(boton => {
            boton.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const numero = this.getAttribute('data-numero');
                const nombre = this.getAttribute('data-nombre');
                const periodo = this.getAttribute('data-periodo');
                const nacimiento = this.getAttribute('data-nacimiento');
                const muerte = this.getAttribute('data-muerte');
                const importancia = this.getAttribute('data-importancia');

                document.getElementById('edit_id_tarjeta').value = id;
                document.getElementById('edit_numero_tarjeta').value = numero;
                document.getElementById('edit_nombre').value = nombre;
                document.getElementById('edit_periodo').value = periodo || 'Prehistoria y Antigüedad Oriental';
                document.getElementById('edit_nacimiento').value = nacimiento;
                document.getElementById('edit_muerte').value = muerte;
                document.getElementById('edit_importancia').value = importancia;

                modalEditar.show();
            });
        });
    }
});
</script>    
    
</body>
</html>