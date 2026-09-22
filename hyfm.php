<?php
session_start();

// Validar que el usuario esté logueado (Cualquier rol tiene acceso)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php"); // O tu página de login
    exit;
}

$rolUsuario = $_SESSION['rol'];

// Configuración de la BD
$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ==========================================
    // PROCESAR CREACIÓN DE NUEVA TARJETA
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_tarjeta') {
        
        // VALIDACIÓN: Bloquear si es Invitado
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para crear o modificar tarjetas.");
        }
        
        $nombre = trim($_POST['nombre_personaje']);
        $ano_nac = trim($_POST['ano_nacimiento']);
        $ano_muerte = trim($_POST['ano_muerte']);
        $importancia = trim($_POST['importancia_historica']);

        // Unificar las fechas para mantener compatibilidad con la tabla
        $fechas_vida = $ano_nac;
        if (!empty($ano_muerte)) {
            $fechas_vida .= ' - ' . $ano_muerte;
        }

        // Crear el número de tarjeta aleatorio (ej: #042) o puedes dejarlo fijo
        $numero_tarjeta = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        // Construir el arreglo que se convertirá en JSON
        $nueva_tarjeta = [
            'numero' => $numero_tarjeta,
            'nombre_personaje' => $nombre,
            'fecha_nacimiento' => $fechas_vida,
            'ano_nacimiento' => $ano_nac,
            'ano_muerte' => $ano_muerte,
            'importancia_historica' => $importancia
        ];

        $json_datos = json_encode($nueva_tarjeta, JSON_UNESCAPED_UNICODE);

        try {
            $stmtInsert = $pdo->prepare("INSERT INTO tarjetas_hyfm (datos_tarjeta) VALUES (:datos)");
            $stmtInsert->execute([':datos' => $json_datos]);
            
            // Recargar la página con mensaje de éxito para evitar que se duplique al recargar (F5)
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
        
        // 🔒 SEGURIDAD: Solo Administradores y Editores pueden eliminar
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para eliminar tarjetas.");
        }
        
        $id_eliminar = intval($_GET['eliminar_id']);
        
        try {
            $stmtDel = $pdo->prepare("DELETE FROM tarjetas_hyfm WHERE id = :id");
            $stmtDel->execute([':id' => $id_eliminar]);
            
            // Redirigir para limpiar la URL y mostrar mensaje de éxito
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
            // Consultar únicamente el campo JSON de la tarjeta solicitada
            $stmtDesc = $pdo->prepare("SELECT datos_tarjeta FROM tarjetas_hyfm WHERE id = :id");
            $stmtDesc->execute([':id' => $id_descargar]);
            $json_crudo = $stmtDesc->fetchColumn();

            if ($json_crudo) {
                // Decodificar para limpiar y estructurar correctamente el archivo
                $datos_arreglo = json_decode($json_crudo, true);
                
                // Crear un nombre de archivo amigable basado en el personaje
                $nombre_personaje = isset($datos_arreglo['nombre_personaje']) ? $datos_arreglo['nombre_personaje'] : 'tarjeta';
                // Limpiar caracteres especiales del nombre para evitar errores en el sistema operativo
                $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_personaje);
                $nombre_archivo = "tarjeta_" . $nombre_limpio . ".json";

                // Enviar las cabeceras HTTP necesarias para forzar la descarga del archivo
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                
                // Imprimir el JSON con formato indentado (lindo) y caracteres legibles
                echo json_encode($datos_arreglo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit; // Detener la ejecución para que no se descargue el resto del HTML de la página
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
            // Obtener todas las tarjetas de la base de datos
            $stmtTodos = $pdo->query("SELECT datos_tarjeta FROM tarjetas_hyfm");
            $tarjetas_db = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

            if (count($tarjetas_db) > 0) {
                $nombreZip = 'personajes_historicos.zip';
                $zip = new ZipArchive;
                
                // Creamos un archivo temporal en la memoria/disco del servidor
                $archivo_temporal = tempnam(sys_get_temp_dir(), 'zip');

                if ($zip->open($archivo_temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                    foreach ($tarjetas_db as $index => $fila) {
                        $json_crudo = $fila['datos_tarjeta'];
                        $datos_arreglo = json_decode($json_crudo, true);
                        
                        $nombre_personaje = isset($datos_arreglo['nombre_personaje']) ? $datos_arreglo['nombre_personaje'] : 'tarjeta_' . $index;
                        $nombre_limpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nombre_personaje);
                        
                        // Añadimos el index al final para evitar que nombres repetidos se sobreescriban
                        $nombre_archivo = "tarjeta_" . $nombre_limpio . "_" . $index . ".json"; 
                        
                        $contenido_json = json_encode($datos_arreglo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        
                        // Añadimos el archivo al ZIP directamente desde el texto, sin guardarlo en disco
                        $zip->addFromString($nombre_archivo, $contenido_json);
                    }
                    $zip->close();

                    // Cabeceras para forzar la descarga del ZIP
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="' . $nombreZip . '"');
                    header('Content-Length: ' . filesize($archivo_temporal));
                    header('Pragma: public');
                    header('Cache-Control: must-revalidate');
                    
                    readfile($archivo_temporal);
                    unlink($archivo_temporal); // Borramos el archivo temporal para no ocupar espacio
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
        
        // 🔒 SEGURIDAD: Bloquear si es Invitado
        if ($_SESSION['rol'] === 'Invitado') {
            die("Acceso denegado: Los invitados no tienen permiso para editar tarjetas.");
        }
        
        $id_editar = intval($_POST['id_tarjeta']);
        $nombre = trim($_POST['nombre_personaje']);
        $ano_nac = trim($_POST['ano_nacimiento']);
        $ano_muerte = trim($_POST['ano_muerte']);
        $importancia = trim($_POST['importancia_historica']);
        $numero_existente = trim($_POST['numero_tarjeta']); // Mantenemos su número original

        // Unificar las fechas
        $fechas_vida = $ano_nac;
        if (!empty($ano_muerte)) {
            $fechas_vida .= ' - ' . $ano_muerte;
        }

        // Reconstruir el arreglo estructurado JSON
        $tarjeta_actualizada = [
            'numero' => $numero_existente,
            'nombre_personaje' => $nombre,
            'fecha_nacimiento' => $fechas_vida,
            'ano_nacimiento' => $ano_nac,
            'ano_muerte' => $ano_muerte,
            'importancia_historica' => $importancia
        ];

        $json_datos = json_encode($tarjeta_actualizada, JSON_UNESCAPED_UNICODE);

        try {
            // Actualizamos el JSON. La columna fecha_actualizacion cambiará sola gracias a MySQL
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
    // CONSULTA DE TARJETAS (CON ORDEN CRONOLÓGICO A.C. / D.C.)
    // ==========================================
    $orden_actual = isset($_GET['orden']) ? $_GET['orden'] : 'defecto';

    if ($orden_actual === 'nacimiento') {
        // Extraer el año. Si contiene "a.C." o "a. C." se multiplica por -1 para tratarlo como negativo.
        $sql = "SELECT id, datos_tarjeta, fecha_registro, fecha_actualizacion 
                FROM tarjetas_hyfm 
                ORDER BY 
                    CASE 
                        WHEN LOWER(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento'))) LIKE '%a.c%' 
                          OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento'))) LIKE '%a. c%'
                        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento')) AS SIGNED) * -1

                        ELSE CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_tarjeta, '$.ano_nacimiento')) AS SIGNED)
                    END ASC";
    } else {
        // Orden predeterminado (por orden de registro / ID)
        $sql = "SELECT id, datos_tarjeta, fecha_registro, fecha_actualizacion 
                FROM tarjetas_hyfm 
                ORDER BY id ASC";
    }

    $stmt = $pdo->query($sql);
    $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consultar todas las tarjetas ordenadas por su ID
    // $stmt = $pdo->query("SELECT id, datos_tarjeta, fecha_registro, fecha_actualizacion FROM tarjetas_hyfm ORDER BY id ASC");
    // $tarjetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <?= count($tarjetas) ?> tarjetas
                </span>
            </h2>
            <p class="text-muted mb-0">Directorio de personajes históricos extraídos de estructura JSON.</p>
        </div>
        
        <div class="d-flex gap-2 mt-3 mt-md-0">
            
            <!-- NUEVO BOTÓN PARA DESCARGAR EL ZIP -->
            <a href="hyfm.php?descargar_todos=1" class="btn btn-outline-success shadow-sm text-nowrap" title="Descargar todos los JSON en ZIP">
                <i class="bi bi-file-earmark-zip-fill me-1"></i> Descargar Colección
            </a>
            
            <?php if ($orden_actual === 'nacimiento'): ?>
                <a href="hyfm.php" class="btn btn-secondary shadow-sm text-nowrap" title="Volver al orden original">
                    <i class="bi bi-x-circle me-1"></i> Quitar Orden
                </a>
            <?php else: ?>
                <a href="hyfm.php?orden=nacimiento" class="btn btn-outline-primary shadow-sm text-nowrap">
                    <i class="bi bi-sort-numeric-down me-1"></i> Ordenar por Nacimiento
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
                            <th style="width: 250px;">Personaje Histórico</th>
                            <th style="width: 200px;">Fecha de nacimiento y muerte (si aplica)</th>
                            <th>Importancia Histórica</th>
                            <th style="width: 220px;">Última Actualización</th>
                            <th class="text-center" style="width: 100px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarjetas as $tarjeta): 
                            // Decodificar el JSON de la tarjeta
                            $datos = json_decode($tarjeta['datos_tarjeta'], true);
                            if (!is_array($datos)) $datos = [];

                            // Extraer las variables con valores por defecto
                            $numero = isset($datos['numero']) ? $datos['numero'] : '-';
                            $nombre = isset($datos['nombre_personaje']) ? $datos['nombre_personaje'] : 'Desconocido';
                            $fecha_nac = isset($datos['fecha_nacimiento']) ? $datos['fecha_nacimiento'] : 'No especificada';
                            $importancia = isset($datos['importancia_historica']) ? $datos['importancia_historica'] : 'Sin descripción';
                            
                            // Formatear las fechas de la BD
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
                                            // Obtenemos año de nacimiento y muerte individuales del JSON para el formulario
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
                                                    data-nacimiento="<?= htmlspecialchars($a_nac) ?>"
                                                    data-muerte="<?= htmlspecialchars($a_mue) ?>"
                                                    data-importancia="<?= htmlspecialchars($importancia) ?>"
                                                    title="Editar tarjeta">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?eliminar_id=<?= $tarjeta['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger shadow-sm" 
                                               onclick="return confirm('¿Estás seguro de que deseas eliminar la tarjeta de <?= addslashes($nombre) ?> permanentemente? Esta acción no se puede deshacer.');" 
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
                                <td colspan="6" class="text-center py-5 text-muted">
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
</div>

<?php if ($_SESSION['rol'] !== 'Invitado'): ?>
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
              <div class="col-12">
                  <label class="form-label fw-bold text-secondary">Nombre del Personaje Histórico</label>
                  <input type="text" name="nombre_personaje" class="form-control" required placeholder="Ej. Marie Curie, Isaac Newton...">
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
              <div class="col-12">
                  <label class="form-label fw-bold text-secondary">Nombre del Personaje Histórico</label>
                  <input type="text" name="nombre_personaje" id="edit_nombre" class="form-control" required>
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
    // 1. Capturar todos los botones que tengan la clase 'btn-editar-tarjeta'
    const botonesEditar = document.querySelectorAll('.btn-editar-tarjeta');
    
    // 2. Inicializar de forma manual el modal de Bootstrap para poder controlarlo
    const modalElemento = document.getElementById('modalEditarTarjeta');
    // Validamos que exista en el HTML (no existirá si el rol es Invitado)
    if (modalElemento) {
        const modalEditar = new bootstrap.Modal(modalElemento);

        botonesEditar.forEach(boton => {
            boton.addEventListener('click', function () {
                // 3. Extraer los datos desde los atributos 'data-*' del botón presionado
                const id = this.getAttribute('data-id');
                const numero = this.getAttribute('data-numero');
                const nombre = this.getAttribute('data-nombre');
                const nacimiento = this.getAttribute('data-nacimiento');
                const muerte = this.getAttribute('data-muerte');
                const importancia = this.getAttribute('data-importancia');

                // 4. Inyectar los valores extraídos directamente en los inputs del Formulario
                document.getElementById('edit_id_tarjeta').value = id;
                document.getElementById('edit_numero_tarjeta').value = numero;
                document.getElementById('edit_nombre').value = nombre;
                document.getElementById('edit_nacimiento').value = nacimiento;
                document.getElementById('edit_muerte').value = muerte;
                document.getElementById('edit_importancia').value = importancia;

                // 5. Mostrar el panel flotante en pantalla
                modalEditar.show();
            });
        });
    }
});
</script>    
    
</body>
</html>