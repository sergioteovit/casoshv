<?php
session_start();
// Si no hay sesión, redirigir al login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}
$rolUsuario = $_SESSION['rol']; // 'Invitado', 'Editor', o 'Administrador'

$host = 'localhost';
$dbname = 'myhvirtual';
$user = 'myhvirtual';
$pass = 'uPLtaPntlDJnThpf';

try {
    // Conexión usando PDO para máxima seguridad
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión a la Base de Datos: " . $e->getMessage());
}

// Procesar eliminación si se recibe el ID por parámetro GET
if (isset($_GET['eliminar'])) {
    $idEliminar = intval($_GET['eliminar']);
    try {
        $stmt = $pdo->prepare("DELETE FROM casos_clinicos WHERE id = :id");
        $stmt->execute([':id' => $idEliminar]);
        header("Location: lista_casos.php?msg=deleted");
        exit;
    } catch(Exception $e) {
        $error = "Error al eliminar el registro: " . $e->getMessage();
    }
}

// Obtener todos los casos clínicos organizados del más reciente al más antiguo
$stmt = $pdo->query("SELECT id, datos_completos, fecha_registro FROM casos_clinicos ORDER BY fecha_registro DESC");
$casos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- ESTADÍSTICA: Contar el número total de casos en el arreglo ---
$totalCasos = count($casos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Casos Clínicos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f4f7f6;
        }
        .badge-autor {
            background-color: #e9ecef;
            color: #495057;
            border: 1px solid #ced4da;
            margin-right: 4px;
            margin-bottom: 4px;
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 500;
            border-radius: 4px;
        }
        .card-stat {
            border-left: 5px solid #0d6efd; /* Borde dinámico azul de Bootstrap */
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-3px);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="lista_casos.php">Casos Clínicos</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link active" href="lista_casos.php">Lista de Casos</a>
        </li>
        <?php if ($rolUsuario === 'Administrador'): ?>
        <li class="nav-item">
          <a class="nav-link" href="gestion_usuarios.php">Gestión de Usuarios</a>
        </li>
        <li class="nav-item">
            <a class="nav-link text-warning" href="verificador_casos.php">
                <i class="bi bi-ui-checks-grid me-1"></i> Auditoría de Variables
            </a>
        </li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center text-white">
          <span class="me-3 small">
              <i class="bi bi-person-circle"></i> <?= $_SESSION['nombre_completo'] ?> (<?= $rolUsuario ?>)
          </span>
          <a href="perfil.php" class="btn btn-sm btn-outline-light me-2">Mi Perfil</a>
          <a href="logout.php" class="btn btn-sm btn-danger">Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container my-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-folder-fill text-primary me-2"></i> Panel de Casos Clínicos</h2>
        <?php if ($rolUsuario !== 'Invitado'): ?>
            <a href="nuevo_caso.php" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i> Nuevo Caso</a>
        <?php endif; ?>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="card shadow-sm card-stat">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small mb-1 fw-bold">Total de Casos</h6>
                        <h2 class="display-6 fw-bold text-dark mb-0"><?= $totalCasos ?></h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-database-fill text-primary fs-3 lh-1"></i>
                    </div>
                </div>
            </div>
        </div>
        </div>
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-pencil-square me-2"></i> ¡El caso clínico ha sido actualizado correctamente en el sistema!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> ¡El nuevo caso clínico ha sido registrado y guardado con éxito!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            El caso clínico ha sido eliminado correctamente de la base de datos.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <!--th class="ps-3" style="width: 5%">IDBD</!--th-->
                        <th class="ps-3" style="width: 10%">Identificador</th>
                        <th style="width: 40%">Descripción</th>
                        <th style="width: 20%">Autores</th>
                        <th style="width: 10%">Fecha de Registro</th>
                        <th class="text-center" style="width: 20%">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($casos) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No hay casos clínicos registrados en la base de datos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($casos as $row): 
                            // Decodificamos el JSON guardado de la BD
                            $datos = json_decode($row['datos_completos'], true);
                    
                            $identificador = isset($datos['identificador']) ? $datos['identificador'] : 'Desconocido';
                            
                            // Extraer el título del caso de manera segura
                            $titulo = isset($datos['titulo_caso']) ? htmlspecialchars($datos['titulo_caso']) : 'Sin título';
                            
                            // Procesar la lista de autores
                            $listaAutoresHTML = '';
                            if (isset($datos['autores'])) {
                                if (is_array($datos['autores'])) {
                                    foreach ($datos['autores'] as $autor) {
                                        if (trim($autor) !== '') {
                                            $listaAutoresHTML .= '<span class="badge-autor"><i class="bi bi-person-fill text-secondary me-1"></i>' . htmlspecialchars($autor) . '</span>';
                                        }
                                    }
                                } else {
                                    if (trim($datos['autores']) !== '') {
                                        $listaAutoresHTML = '<span class="badge-autor"><i class="bi bi-person-fill text-secondary me-1"></i>' . htmlspecialchars($datos['autores']) . '</span>';
                                    }
                                }
                            }
                            
                            if (empty($listaAutoresHTML)) {
                                $listaAutoresHTML = '<span class="text-muted italic small">No especificados</span>';
                            }
                        ?>
                            <tr>
                                <!--td class="ps-3 fw-bold"><?= $row['id'] ?></!--td-->
                                <td>
                                    <span class="badge bg-primary">
                                        <?= htmlspecialchars($identificador) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-secondary text-wrap"><?= $titulo ?></div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap">
                                        <?= $listaAutoresHTML ?>
                                    </div>
                                </td>
                                <td class="text-muted"><?= date('d/m/Y H:i', strtotime($row['fecha_registro'])) ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-info btn-descargar-json" 
                                                data-titulo="<?= pathinfo($identificador, PATHINFO_FILENAME); ?>"
                                                data-json='<?= htmlspecialchars($row['datos_completos'], ENT_QUOTES, 'UTF-8'); ?>'
                                                title="Descargar JSON del Caso">
                                            <i class="bi bi-download"></i> JSON
                                        </button>
                                        <a href="descargar_xml.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info" title="Descargar XML">
                                            <i class="bi bi-filetype-xml"></i> XML
                                        </a>
                                        <?php if ($rolUsuario !== 'Invitado'): ?>
                                            <a href="caso_editar.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil-square"></i> Editar
                                            </a>
                                            <a href="lista_casos.php?eliminar=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Seguro?');">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-descargar-json').forEach(boton => {
        boton.addEventListener('click', function () {
            try {
                const rawJson = this.getAttribute('data-json');
                const objetoJson = JSON.parse(rawJson);
                
                let tituloCaso = this.getAttribute('data-titulo') || 'caso_clinico';
                tituloCaso = tituloCaso.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]/gi, '_');

                const jsonFormateado = JSON.stringify(objetoJson, null, 4);
                const blob = new Blob([jsonFormateado], { type: 'application/json' });
                
                const enlaceTemporal = document.createElement('a');
                enlaceTemporal.href = URL.createObjectURL(blob);
                enlaceTemporal.download = `caso_${tituloCaso}.json`;

                document.body.appendChild(enlaceTemporal);
                enlaceTemporal.click();
                document.body.removeChild(enlaceTemporal);
                URL.revokeObjectURL(enlaceTemporal.href);

            } catch (error) {
                console.error("Error al procesar el archivo JSON:", error);
                alert("Ocurrió un error inesperado al estructurar el archivo de descarga.");
            }
        });
    });
});
</script>
</body>
</html>