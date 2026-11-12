<?php
// Archivos adjuntos de los casos clínicos (imágenes, PDF y Word).
// Solo se aceptan las extensiones de la lista, se comprueba el contenido real del archivo
// y se guardan con un nombre aleatorio. La carpeta uploads/ no es accesible desde la web (.htaccess).

const HV_SUBIDAS_TAMANO_MAXIMO = 20 * 1024 * 1024; // 20 MB por archivo

// Extensión permitida => tipos de contenido aceptados (los de Word varían según la versión del servidor).
const HV_SUBIDAS_TIPOS = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
];

const HV_SUBIDAS_HTACCESS = "# Archivos adjuntos de los casos: no se sirven ni se ejecutan desde la web.\n"
    . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
    . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";

function hv_subidas_carpeta()
{
    $carpeta = __DIR__ . '/uploads/';
    if (!is_dir($carpeta)) {
        @mkdir($carpeta, 0755, true);
    }
    if (is_dir($carpeta) && !is_file($carpeta . '.htaccess')) {
        @file_put_contents($carpeta . '.htaccess', HV_SUBIDAS_HTACCESS);
    }
    return $carpeta;
}

// Revisa todos los archivos de $_FILES[$campo] antes de guardar nada.
// Devuelve ['archivos' => [[tmp, extension], ...], 'error' => null] o ['archivos' => [], 'error' => 'mensaje'].
function hv_subidas_validar($campo)
{
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo]['tmp_name'] ?? null)) {
        return ['archivos' => [], 'error' => null];
    }

    $info = $_FILES[$campo];
    $tiposPermitidos = HV_SUBIDAS_TIPOS;
    $detector = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $validos = [];

    foreach ($info['tmp_name'] as $i => $tmp) {
        $codigo = $info['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($codigo === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $nombre = basename((string)($info['name'][$i] ?? ''));

        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return ['archivos' => [], 'error' => "El archivo \"$nombre\" es demasiado grande."];
        }
        if ($codigo !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            return ['archivos' => [], 'error' => "No se pudo recibir el archivo \"$nombre\" (código $codigo)."];
        }
        if (filesize($tmp) > HV_SUBIDAS_TAMANO_MAXIMO) {
            return ['archivos' => [], 'error' => "El archivo \"$nombre\" supera el límite de 20 MB."];
        }

        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!isset($tiposPermitidos[$extension])) {
            return ['archivos' => [], 'error' => "El archivo \"$nombre\" no es de un tipo permitido (imagen, PDF o Word)."];
        }

        if ($detector !== null) {
            $tipo = $detector->file($tmp);
            if (!in_array($tipo, $tiposPermitidos[$extension], true)) {
                return ['archivos' => [], 'error' => "El contenido de \"$nombre\" no corresponde a su extensión."];
            }
        }

        $validos[] = [$tmp, $extension];
    }

    return ['archivos' => $validos, 'error' => null];
}

// Mueve a uploads/ los archivos ya validados. Devuelve las rutas relativas que se guardan en la BD,
// o null si alguno no se pudo guardar (en ese caso se borran los que sí se movieron y no se guarda el caso).
function hv_subidas_guardar(array $archivos)
{
    if (empty($archivos)) {
        return [];
    }

    $carpeta = hv_subidas_carpeta();
    $rutas = [];

    foreach ($archivos as list($tmp, $extension)) {
        $nombreFinal = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        if (!is_writable($carpeta) || !@move_uploaded_file($tmp, $carpeta . $nombreFinal)) {
            error_log('[casoshv] No se pudo guardar un archivo en uploads/ (¿permisos de escritura?)');
            foreach ($rutas as $ruta) {
                @unlink(__DIR__ . '/' . $ruta);
            }
            return null;
        }
        $rutas[] = 'uploads/' . $nombreFinal;
    }

    return $rutas;
}

// Borra de uploads/ los archivos de un caso eliminado. $rutasJson es el valor de rutas_archivos
// (lista JSON de rutas "uploads/<nombre>"). Solo se borran archivos directamente dentro de uploads/,
// con nombre sin carpetas ni "..", y nunca .htaccess. Devuelve [borrados, no encontrados, fallidos].
function hv_subidas_borrar($rutasJson)
{
    $rutas = json_decode((string)$rutasJson, true);
    $resultado = [0, 0, 0];
    if (!is_array($rutas)) {
        return $resultado;
    }

    $carpeta = realpath(__DIR__ . '/uploads');
    foreach ($rutas as $ruta) {
        if (!is_string($ruta) || !preg_match('#^uploads/([A-Za-z0-9][A-Za-z0-9._-]*)$#', $ruta, $m) || strtolower($m[1]) === '.htaccess') {
            error_log('[casoshv] Ruta de adjunto no válida, no se borra: ' . (is_string($ruta) ? $ruta : gettype($ruta)));
            $resultado[2]++;
            continue;
        }
        $archivo = __DIR__ . '/uploads/' . $m[1];
        if (!is_file($archivo)) {
            $resultado[1]++;
            continue;
        }
        if ($carpeta === false || dirname(realpath($archivo)) !== $carpeta || !@unlink($archivo)) {
            error_log('[casoshv] No se pudo borrar el adjunto ' . $ruta);
            $resultado[2]++;
            continue;
        }
        $resultado[0]++;
    }
    return $resultado;
}

const HV_SUBIDAS_ERROR_CARPETA = 'No se pudieron guardar los archivos adjuntos en el servidor (la carpeta uploads/ no permite escribir). No se guardaron los cambios.';
