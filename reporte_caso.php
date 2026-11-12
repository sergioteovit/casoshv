<?php
// Reporte de un caso clínico para imprimir o guardar como PDF. Solo administradores.
// Carga el mismo formulario (modo 'ver') para leer todos los campos con sus etiquetas; el reporte lo arma
// en el navegador assets/js/reporte_caso.js. Con ?incrustado=1 lo pide reporte_casos.php (varios casos)
// en un marco invisible y el reporte se le envía en lugar de mostrarse.
require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
hv_bloquear_invitado();

if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Solo un administrador puede generar reportes de casos.');
}

header('Cache-Control: no-store');

// Imágenes adjuntas que se incrustan en el reporte (uploads/ no es accesible desde la web)
const REPORTE_MAX_BYTES_IMAGEN = 6 * 1024 * 1024;
const REPORTE_MAX_BYTES_CASO = 20 * 1024 * 1024;

// Datos que el reporte necesita además del formulario. Lo llama formulario_caso.php en modo reporte.
function hv_reporte_datos($idCaso, $datosCaso, $rutasJson, $fechaRegistro)
{
    $datos = is_array($datosCaso) ? $datosCaso : [];

    // Nombres originales de los adjuntos (la ruta en el servidor tiene un nombre aleatorio)
    $nombres = [];
    foreach (['archivos_adjuntos', 'archivos_adjuntos_previos'] as $clave) {
        foreach ((array)($datos[$clave] ?? []) as $nombre) {
            if (is_string($nombre) && trim($nombre) !== '' && !in_array($nombre, $nombres, true)) {
                $nombres[] = trim($nombre);
            }
        }
    }
    $rutas = json_decode((string)$rutasJson, true);
    $rutas = is_array($rutas) ? array_values(array_filter($rutas, 'is_string')) : [];
    $usarNombres = count($nombres) === count($rutas);

    $adjuntos = [];
    $bytesUsados = 0;
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    foreach ($rutas as $i => $ruta) {
        $nombre = $usarNombres ? $nombres[$i] : basename($ruta);
        $adjunto = ['nombre' => $nombre, 'imagen' => null, 'nota' => ''];
        // Solo archivos directamente dentro de uploads/
        $archivo = __DIR__ . '/uploads/' . basename($ruta);
        if (strpos($ruta, 'uploads/') !== 0 || !is_file($archivo)) {
            $adjunto['nota'] = 'no se encontró en el servidor';
        } else {
            $tipo = $finfo ? finfo_file($finfo, $archivo) : '';
            $tamano = (int)filesize($archivo);
            if (in_array($tipo, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                if ($tamano <= REPORTE_MAX_BYTES_IMAGEN && $bytesUsados + $tamano <= REPORTE_MAX_BYTES_CASO) {
                    $adjunto['imagen'] = 'data:' . $tipo . ';base64,' . base64_encode((string)file_get_contents($archivo));
                    $bytesUsados += $tamano;
                } else {
                    $adjunto['nota'] = 'imagen demasiado grande para el reporte';
                }
            } elseif ($tipo === 'application/pdf') {
                $adjunto['nota'] = 'PDF';
            } else {
                $adjunto['nota'] = 'documento';
            }
        }
        $adjuntos[] = $adjunto;
    }
    if ($finfo) {
        finfo_close($finfo);
    }

    return [
        'id'              => (int)$idCaso,
        'identificador'   => trim((string)($datos['identificador'] ?? '')),
        'titulo'          => trim((string)($datos['titulo_caso'] ?? '')),
        'fechaRegistro'   => $fechaRegistro ? date('d/m/Y', strtotime($fechaRegistro)) : '',
        'generadoPor'     => (string)($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? ''),
        'fechaGeneracion' => date('d/m/Y \a \l\a\s H:i'),
        'incrustado'      => !empty($_GET['incrustado']),
        'imprimirAlAbrir' => !empty($_GET['imprimir']),
        'adjuntos'        => $adjuntos,
    ];
}

$rolUsuario = $_SESSION['rol'];
$modo = 'ver';
$modoReporte = true;
require __DIR__ . '/formulario_caso.php';
