<?php
session_start();

// Validar inicio de sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$rolUsuario = $_SESSION['rol'] ?? 'Invitado';

// Configuración de la BD
$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // PROCESAR DESCARGA MASIVA EN ZIP
    // ==========================================
    if (isset($_GET['descargar_todos']) && $_GET['descargar_todos'] === '1') {
        try {
            $stmtTodos = $pdo->query("SELECT id, descripcion_caso FROM casos_historicos ORDER BY id ASC");
            $casos_db = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

            if (count($casos_db) > 0) {
                $nombreZip = 'casos_clinicos_historicos.zip';
                $archivo_temporal = tempnam(sys_get_temp_dir(), 'zip');
                $zip = new ZipArchive;

                if ($zip->open($archivo_temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    foreach ($casos_db as $fila) {
                        $datos_arreglo = json_decode($fila['descripcion_caso'], true);
                        if (!is_array($datos_arreglo)) $datos_arreglo = [];

                        $titulo = $datos_arreglo['titulo_caso'] ?? 'caso_' . $fila['id'];
                        $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $titulo);
                        $nombre_archivo = "caso_id_" . $fila['id'] . "_" . $nombre_limpio . ".json";

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
                    $error_descarga = "No se pudo generar el archivo ZIP en el servidor.";
                }
            } else {
                $error_descarga = "No hay casos históricos para descargar.";
            }
        } catch (PDOException $e) {
            $error_descarga = "Error al exportar casos: " . $e->getMessage();
        }
    }

    // ==========================================
    // PROCESAR DESCARGA INDIVIDUAL JSON
    // ==========================================
    if (isset($_GET['descargar_id']) && is_numeric($_GET['descargar_id'])) {
        $id_descargar = intval($_GET['descargar_id']);

        try {
            $stmtDesc = $pdo->prepare("SELECT descripcion_caso FROM casos_historicos WHERE id = :id");
            $stmtDesc->execute([':id' => $id_descargar]);
            $json_crudo = $stmtDesc->fetchColumn();

            if ($json_crudo) {
                $datos_arreglo = json_decode($json_crudo, true);
                $titulo = $datos_arreglo['titulo_caso'] ?? 'caso';
                $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $titulo);
                $nombre_archivo = "caso_id_" . $id_descargar . "_" . $nombre_limpio . ".json";

                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');

                echo json_encode($datos_arreglo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                die("Error: El caso solicitado no existe.");
            }
        } catch (PDOException $e) {
            die("Error al descargar el caso: " . $e->getMessage());
        }
    }

    // ==========================================
    // PROCESAR ELIMINACIÓN DE CASO
    // ==========================================
    if (isset($_GET['eliminar_id']) && is_numeric($_GET['eliminar_id'])) {
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para eliminar casos.");
        }

        $id_eliminar = intval($_GET['eliminar_id']);

        try {
            $stmtDel = $pdo->prepare("DELETE FROM casos_historicos WHERE id = :id");
            $stmtDel->execute([':id' => $id_eliminar]);

            header("Location: casos_historicos.php?msg=eliminado");
            exit;
        } catch (PDOException $e) {
            $error_eliminacion = "Error al eliminar el caso: " . $e->getMessage();
        }
    }

    // ==========================================
    // PROCESAR CREACIÓN / EDICIÓN DE CASO
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para realizar esta acción.");
        }

        $titulo = trim($_POST['titulo_caso'] ?? '');
        $enunciado = trim($_POST['enunciado'] ?? '');
        $fuentes = trim($_POST['fuentes_bibliograficas'] ?? '');

        // Únicamente se valida que el título no esté vacío
        if (empty($titulo)) {
            if ($_POST['accion'] === 'crear_caso') {
                $error_creacion = "El título del caso es obligatorio.";
            } else {
                $error_edicion = "El título del caso es obligatorio.";
            }
        } else {
            // Construir arreglo estructurado de las 5 preguntas
            $preguntas = [];
            if (isset($_POST['preguntas']) && is_array($_POST['preguntas'])) {
                foreach ($_POST['preguntas'] as $p) {
                    $opciones = [];
                    $retro = [];
                    for ($i = 0; $i < 4; $i++) {
                        $opciones[] = trim($p['opciones'][$i] ?? '');
                        $retro[] = trim($p['retroalimentaciones'][$i] ?? '');
                    }

                    $preguntas[] = [
                        'enunciado' => trim($p['enunciado'] ?? ''),
                        'opciones' => $opciones,
                        'opcion_correcta' => intval($p['opcion_correcta'] ?? 0),
                        'retroalimentaciones' => $retro
                    ];
                }
            }

            $nuevo_caso = [
                'titulo_caso' => $titulo,
                'enunciado' => $enunciado,
                'preguntas' => $preguntas,
                'fuentes_bibliograficas' => $fuentes
            ];

            $json_datos = json_encode($nuevo_caso, JSON_UNESCAPED_UNICODE);

            if ($_POST['accion'] === 'crear_caso') {
                try {
                    $stmtInsert = $pdo->prepare("INSERT INTO casos_historicos (descripcion_caso) VALUES (:datos)");
                    $stmtInsert->execute([':datos' => $json_datos]);

                    header("Location: casos_historicos.php?msg=creado");
                    exit;
                } catch (PDOException $e) {
                    $error_creacion = "Error al guardar el caso en la base de datos: " . $e->getMessage();
                }
            } elseif ($_POST['accion'] === 'editar_caso') {
                $id_editar = intval($_POST['id_caso']);

                try {
                    $stmtUpdate = $pdo->prepare("UPDATE casos_historicos SET descripcion_caso = :datos WHERE id = :id");
                    $stmtUpdate->execute([
                        ':datos' => $json_datos,
                        ':id' => $id_editar
                    ]);

                    header("Location: casos_historicos.php?msg=actualizado");
                    exit;
                } catch (PDOException $e) {
                    $error_edicion = "Error al actualizar el caso en la base de datos: " . $e->getMessage();
                }
            }
        }
    }

    // ==========================================
    // CONSULTA CON PAGINACIÓN (MÁXIMO 10 POR PÁGINA)
    // ==========================================
    $limite = 10;
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $offset = ($pagina - 1) * $limite;

    $stmtCount = $pdo->query("SELECT COUNT(*) FROM casos_historicos");
    $total_registros = $stmtCount->fetchColumn();
    $total_paginas = max(1, ceil($total_registros / $limite));

    $stmt = $pdo->prepare("SELECT id, descripcion_caso, fecha_registro, fecha_actualizacion FROM casos_historicos ORDER BY id ASC LIMIT :limite OFFSET :offset");
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión o consulta a la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casos Clínicos Históricos</title>
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
        <li class="nav-item"><a class="nav-link" href="hyfm.php">HyFM</a></li>
        <li class="nav-item"><a class="nav-link active text-info fw-bold" href="casos_historicos.php"><i class="bi bi-journal-medical me-1"></i> Casos Históricos</a></li>
      </ul>
      <div class="d-flex align-items-center text-white">
          <span class="me-3 small"><i class="bi bi-person-circle text-primary me-1"></i> <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?></span>
          <a href="logout.php" class="btn btn-sm btn-danger">Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 my-4">

    <!-- MENSAJES Y ALERTAS -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'creado'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ¡El nuevo caso clínico histórico ha sido guardado exitosamente!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'actualizado'): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-info-circle-fill me-2"></i> ¡El caso clínico ha sido actualizado correctamente!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-trash3-fill me-2"></i> El caso clínico ha sido eliminado.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_creacion)): ?><div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_creacion) ?></div><?php endif; ?>
    <?php if (isset($error_edicion)): ?><div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_edicion) ?></div><?php endif; ?>
    <?php if (isset($error_eliminacion)): ?><div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_eliminacion) ?></div><?php endif; ?>
    <?php if (isset($error_descarga)): ?><div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_descarga) ?></div><?php endif; ?>

    <!-- ENCABEZADO Y ACCIONES GENERALES -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h2 class="d-flex align-items-center mb-1">
                <i class="bi bi-journal-bookmark-fill text-info me-2"></i>
                Casos Clínicos Históricos
                <span class="badge bg-info text-white ms-3 rounded-pill fs-6 shadow-sm">
                    <?= $total_registros ?> casos en total
                </span>
            </h2>
            <p class="text-muted mb-0">Gestión de casos históricos guardados en formato JSON estructurado.</p>
        </div>

        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="casos_historicos.php?descargar_todos=1" class="btn btn-outline-success shadow-sm text-nowrap" title="Descargar todos los casos en un archivo ZIP">
                <i class="bi bi-file-earmark-zip-fill me-1"></i> Descargar Colección (ZIP)
            </a>

            <?php if ($_SESSION['rol'] !== 'Invitado'): ?>
                <button type="button" class="btn btn-info text-white shadow-sm fw-bold text-nowrap" data-bs-toggle="modal" data-bs-target="#modalNuevoCaso">
                    <i class="bi bi-plus-circle me-1"></i> Nuevo Caso
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLA DE CASOS -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 80px;"># ID</th>
                            <th style="width: 250px;">Título del Caso</th>
                            <th>Enunciado General</th>
                            <th style="width: 120px;" class="text-center">Preguntas</th>
                            <th style="width: 180px;">Fechas</th>
                            <th class="text-center" style="width: 130px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($casos as $caso): 
                            $datos = json_decode($caso['descripcion_caso'], true);
                            if (!is_array($datos)) $datos = [];

                            $titulo = $datos['titulo_caso'] ?? 'Sin Título';
                            $enunciado = $datos['enunciado'] ?? 'Sin enunciado registrado...';
                            $numPreguntas = isset($datos['preguntas']) && is_array($datos['preguntas']) ? count($datos['preguntas']) : 0;
                            
                            $creado = $caso['fecha_registro'];
                            $actualizado = $caso['fecha_actualizacion'];
                        ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary">
                                    #<?= $caso['id'] ?>
                                </td>
                                <td class="fw-bold text-primary">
                                    <?= htmlspecialchars($titulo) ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(mb_strimwidth($enunciado, 0, 110, "...")) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $numPreguntas ?> preguntas</span>
                                </td>
                                <td>
                                    <small class="text-muted d-block">Reg: <?= date('d/m/Y', strtotime($creado)) ?></small>
                                    <small class="text-success d-block">Mod: <?= date('d/m/Y H:i', strtotime($actualizado)) ?></small>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group" role="group">
                                        <a href="?descargar_id=<?= $caso['id'] ?>" class="btn btn-sm btn-outline-primary" title="Descargar en formato JSON">
                                            <i class="bi bi-download"></i>
                                        </a>

                                        <?php if ($_SESSION['rol'] !== 'Invitado'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-warning btn-editar-caso" 
                                                    data-id="<?= $caso['id'] ?>"
                                                    data-json="<?= htmlspecialchars($caso['descripcion_caso'], ENT_QUOTES, 'UTF-8') ?>"
                                                    title="Editar Caso">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <a href="?eliminar_id=<?= $caso['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('¿Deseas eliminar permanentemente este caso clínico?');" 
                                               title="Eliminar Caso">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($casos)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Aún no hay casos clínicos históricos registrados.
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
        <nav aria-label="Navegación de páginas" class="mt-4">
            <ul class="pagination justify-content-center shadow-sm">
                <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $pagina - 1 ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
                </li>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <li class="page-item <?= ($pagina == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $pagina + 1 ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php if ($_SESSION['rol'] !== 'Invitado'): ?>

<!-- MODAL PARA CREAR CASO -->
<div class="modal fade" id="modalNuevoCaso" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <form action="casos_historicos.php" method="POST">
        <input type="hidden" name="accion" value="crear_caso">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Crear Nuevo Caso Clínico Histórico</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4 bg-light">
          
          <ul class="nav nav-pills mb-3 bg-white p-2 rounded border" id="pills-tab-nuevo" role="tablist">
            <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#nuevo-general" type="button">Datos Generales</button></li>
            <?php for($q=0; $q<5; $q++): ?>
                <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#nuevo-p<?= $q ?>" type="button">Pregunta <?= $q+1 ?></button></li>
            <?php endfor; ?>
          </ul>

          <div class="tab-content bg-white p-3 border rounded shadow-sm">
            <!-- TAB DATOS GENERALES -->
            <div class="tab-pane fade show active" id="nuevo-general">
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Título del Caso *</label>
                    <input type="text" name="titulo_caso" class="form-control" required placeholder="Ej: Caso clínico de fiebre tifoidea en el siglo XIX">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Enunciado General del Caso</label>
                    <textarea name="enunciado" class="form-control" rows="5" placeholder="Describe la historia clínica, antecedentes, sintomatología y evolución..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Fuentes Bibliográficas</label>
                    <textarea name="fuentes_bibliograficas" class="form-control" rows="3" placeholder="Referencias médicas o históricas utilizadas..."></textarea>
                </div>
            </div>

            <!-- TABS PREGUNTAS (1 A 5) -->
            <?php for($q=0; $q<5; $q++): ?>
                <div class="tab-pane fade" id="nuevo-p<?= $q ?>">
                    <h6 class="fw-bold text-info border-bottom pb-2 mb-3">Configuración de la Pregunta <?= $q+1 ?></h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Enunciado de la Pregunta <?= $q+1 ?></label>
                        <input type="text" name="preguntas[<?= $q ?>][enunciado]" class="form-control" placeholder="Escribe la pregunta...">
                    </div>

                    <div class="row">
                        <?php 
                        $letras = ['A', 'B', 'C', 'D'];
                        for($o=0; $o<4; $o++): 
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="p-2 border rounded bg-light">
                                    <label class="form-label fw-bold text-primary">Opción <?= $letras[$o] ?></label>
                                    <input type="text" name="preguntas[<?= $q ?>][opciones][<?= $o ?>]" class="form-control mb-2" placeholder="Texto opción <?= $letras[$o] ?>">
                                    <label class="form-label small fw-bold text-muted">Retroalimentación Opción <?= $letras[$o] ?></label>
                                    <textarea name="preguntas[<?= $q ?>][retroalimentaciones][<?= $o ?>]" class="form-control form-control-sm" rows="2" placeholder="Explicación si elige <?= $letras[$o] ?>"></textarea>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Opción Correcta de la Pregunta <?= $q+1 ?></label>
                        <select name="preguntas[<?= $q ?>][opcion_correcta]" class="form-select border-success">
                            <option value="0">Opción A</option>
                            <option value="1">Opción B</option>
                            <option value="2">Opción C</option>
                            <option value="3">Opción D</option>
                        </select>
                    </div>
                </div>
            <?php endfor; ?>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-info text-white fw-bold"><i class="bi bi-save me-1"></i> Guardar Caso</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL PARA EDITAR CASO -->
<div class="modal fade" id="modalEditarCaso" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <form action="casos_historicos.php" method="POST">
        <input type="hidden" name="accion" value="editar_caso">
        <input type="hidden" name="id_caso" id="edit_id_caso">

        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Caso Clínico Histórico</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4 bg-light">
          
          <ul class="nav nav-pills mb-3 bg-white p-2 rounded border" id="pills-tab-editar" role="tablist">
            <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="pill" data-bs-target="#edit-general" type="button">Datos Generales</button></li>
            <?php for($q=0; $q<5; $q++): ?>
                <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="pill" data-bs-target="#edit-p<?= $q ?>" type="button">Pregunta <?= $q+1 ?></button></li>
            <?php endfor; ?>
          </ul>

          <div class="tab-content bg-white p-3 border rounded shadow-sm">
            <!-- TAB DATOS GENERALES EDICIÓN -->
            <div class="tab-pane fade show active" id="edit-general">
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Título del Caso *</label>
                    <input type="text" name="titulo_caso" id="edit_titulo_caso" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Enunciado General del Caso</label>
                    <textarea name="enunciado" id="edit_enunciado" class="form-control" rows="5"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-secondary">Fuentes Bibliográficas</label>
                    <textarea name="fuentes_bibliograficas" id="edit_fuentes" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <!-- TABS PREGUNTAS EDICIÓN (1 A 5) -->
            <?php for($q=0; $q<5; $q++): ?>
                <div class="tab-pane fade" id="edit-p<?= $q ?>">
                    <h6 class="fw-bold text-warning border-bottom pb-2 mb-3">Editar Pregunta <?= $q+1 ?></h6>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Enunciado de la Pregunta <?= $q+1 ?></label>
                        <input type="text" name="preguntas[<?= $q ?>][enunciado]" id="edit_p<?= $q ?>_enunciado" class="form-control">
                    </div>

                    <div class="row">
                        <?php 
                        $letras = ['A', 'B', 'C', 'D'];
                        for($o=0; $o<4; $o++): 
                        ?>
                            <div class="col-md-6 mb-3">
                                <div class="p-2 border rounded bg-light">
                                    <label class="form-label fw-bold text-primary">Opción <?= $letras[$o] ?></label>
                                    <input type="text" name="preguntas[<?= $q ?>][opciones][<?= $o ?>]" id="edit_p<?= $q ?>_opcion_<?= $o ?>" class="form-control mb-2">
                                    <label class="form-label small fw-bold text-muted">Retroalimentación Opción <?= $letras[$o] ?></label>
                                    <textarea name="preguntas[<?= $q ?>][retroalimentaciones][<?= $o ?>]" id="edit_p<?= $q ?>_retro_<?= $o ?>" class="form-control form-control-sm" rows="2"></textarea>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Opción Correcta de la Pregunta <?= $q+1 ?></label>
                        <select name="preguntas[<?= $q ?>][opcion_correcta]" id="edit_p<?= $q ?>_correcta" class="form-select border-success">
                            <option value="0">Opción A</option>
                            <option value="1">Opción B</option>
                            <option value="2">Opción C</option>
                            <option value="3">Opción D</option>
                        </select>
                    </div>
                </div>
            <?php endfor; ?>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-bold"><i class="bi bi-check-circle me-1"></i> Actualizar Caso</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEditarElem = document.getElementById('modalEditarCaso');
    if (modalEditarElem) {
        const modalEditar = new bootstrap.Modal(modalEditarElem);
        const botonesEditar = document.querySelectorAll('.btn-editar-caso');

        botonesEditar.forEach(btn => {
            btn.addEventListener('click', function () {
                const idCaso = this.getAttribute('data-id');
                const rawJson = this.getAttribute('data-json');

                try {
                    const data = JSON.parse(rawJson);

                    document.getElementById('edit_id_caso').value = idCaso;
                    document.getElementById('edit_titulo_caso').value = data.titulo_caso || '';
                    document.getElementById('edit_enunciado').value = data.enunciado || '';
                    document.getElementById('edit_fuentes').value = data.fuentes_bibliograficas || '';

                    const preguntas = data.preguntas || [];
                    for (let q = 0; q < 5; q++) {
                        const preguntaObj = preguntas[q] || {};
                        
                        const elEnunciado = document.getElementById(`edit_p${q}_enunciado`);
                        if (elEnunciado) elEnunciado.value = preguntaObj.enunciado || '';

                        const elCorrecta = document.getElementById(`edit_p${q}_correcta`);
                        if (elCorrecta) elCorrecta.value = preguntaObj.opcion_correcta !== undefined ? preguntaObj.opcion_correcta : 0;

                        const opciones = preguntaObj.opciones || [];
                        const retros = preguntaObj.retroalimentaciones || [];

                        for (let o = 0; o < 4; o++) {
                            const elOpcion = document.getElementById(`edit_p${q}_opcion_${o}`);
                            const elRetro = document.getElementById(`edit_p${q}_retro_${o}`);

                            if (elOpcion) elOpcion.value = opciones[o] || '';
                            if (elRetro) elRetro.value = retros[o] || '';
                        }
                    }

                    modalEditar.show();
                } catch (e) {
                    console.error('Error al decodificar el JSON del caso:', e);
                    alert('Ocurrió un error al cargar la información del caso.');
                }
            });
        });
    }
});
</script>

</body>
</html>