<?php
// Funciones comunes de seguridad: sesión, protección CSRF y límite de intentos de acceso.

function hv_iniciar_sesion()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (PHP_VERSION_ID >= 70300) {
        $esHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $esHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

// Registra el detalle de un error en el log del servidor y devuelve un texto para mostrar al usuario
// sin la estructura de la base de datos (con una referencia para encontrarlo en el log).
function hv_error_interno($e)
{
    $referencia = bin2hex(random_bytes(3));
    error_log('[casoshv] ref ' . $referencia . ' ' . get_class($e) . ': ' . $e->getMessage()
        . ' en ' . basename($e->getFile()) . ':' . $e->getLine());
    return 'error interno, avisa al administrador (referencia ' . $referencia . ')';
}

// Las cuentas nuevas (rol Invitado) no ven ningún contenido hasta que un administrador les asigna
// otro rol: se envían a cuenta_pendiente.php, que vuelve a leer el rol de la base de datos.
function hv_bloquear_invitado()
{
    if (($_SESSION['rol'] ?? 'Invitado') === 'Invitado') {
        header('Location: cuenta_pendiente.php');
        exit;
    }
}

// Convierte el JSON guardado de un caso en un literal seguro para escribirlo dentro de <script>:
// se vuelve a codificar con <, >, &, ' y " escapados, así un "</script>" en un campo no cierra la etiqueta.
// Si el JSON guardado no es válido devuelve un objeto vacío.
function hv_json_para_script($jsonGuardado)
{
    $datos = json_decode((string)$jsonGuardado);
    if (!is_object($datos) && !is_array($datos)) {
        $datos = new stdClass();
    }
    $resultado = json_encode($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    return $resultado === false ? '{}' : $resultado;
}

// Comprueba que el texto recibido sea un objeto JSON (lo que envían los formularios de casos).
function hv_json_objeto_valido($texto)
{
    if (!is_string($texto) || $texto === '') {
        return false;
    }
    $datos = json_decode($texto);
    return json_last_error() === JSON_ERROR_NONE && is_object($datos);
}

function hv_token_csrf()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function hv_csrf_valido($tokenRecibido)
{
    $tokenSesion = $_SESSION['csrf_token'] ?? '';
    return $tokenSesion !== '' && is_string($tokenRecibido) && hash_equals($tokenSesion, $tokenRecibido);
}

// Los intentos fallidos se guardan en archivos temporales para no requerir tablas nuevas.
// Si la carpeta temporal no se puede escribir, el límite no se aplica y el acceso sigue funcionando.
function hv_limite_ruta($clave)
{
    $carpeta = sys_get_temp_dir() . '/hv_limites';
    if (!is_dir($carpeta)) {
        @mkdir($carpeta, 0700, true);
    }
    return $carpeta . '/' . hash('sha256', $clave) . '.json';
}

function hv_limite_filtrar($fallos, $ventanaSegundos)
{
    if (!is_array($fallos)) {
        return [];
    }
    $desde = time() - $ventanaSegundos;
    return array_values(array_filter($fallos, function ($momento) use ($desde) {
        return is_int($momento) && $momento > $desde;
    }));
}

function hv_limite_excedido($clave, $maximo, $ventanaSegundos)
{
    $contenido = @file_get_contents(hv_limite_ruta($clave));
    if ($contenido === false) {
        return false;
    }
    return count(hv_limite_filtrar(json_decode($contenido, true), $ventanaSegundos)) >= $maximo;
}

function hv_limite_registrar_fallo($clave, $ventanaSegundos)
{
    $archivo = @fopen(hv_limite_ruta($clave), 'c+');
    if ($archivo === false) {
        return;
    }

    if (flock($archivo, LOCK_EX)) {
        $fallos = hv_limite_filtrar(json_decode(stream_get_contents($archivo), true), $ventanaSegundos);
        $fallos[] = time();
        ftruncate($archivo, 0);
        rewind($archivo);
        fwrite($archivo, json_encode($fallos));
        flock($archivo, LOCK_UN);
    }

    fclose($archivo);
}

function hv_limite_reiniciar($clave)
{
    @unlink(hv_limite_ruta($clave));
}
