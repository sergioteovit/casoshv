<?php
// Reglas del identificador de los casos clínicos (es el nombre del caso_<identificador>.json que usa el juego):
// - Solo el Administrador lo asigna o lo cambia. Si guarda otro rol, se conserva el que ya tenía el caso
//   (en un caso nuevo queda vacío hasta que un administrador lo asigne).
// - Si el formulario no lo envía (campo bloqueado), se conserva el que ya tenía el caso.
// - No puede repetirse: si otro caso ya lo usa, no se guarda.

function hv_identificador_guardado(PDO $pdo, $idCaso)
{
    if (!$idCaso) {
        return '';
    }
    $stmt = $pdo->prepare('SELECT datos_completos FROM casos_clinicos WHERE id = :id');
    $stmt->execute([':id' => $idCaso]);
    $datos = json_decode((string)$stmt->fetchColumn());
    return (is_object($datos) && isset($datos->identificador)) ? trim((string)$datos->identificador) : '';
}

// Devuelve ['json' => texto listo para guardar, 'error' => null] o ['json' => null, 'error' => 'mensaje'].
// $idCaso es null al crear un caso nuevo.
function hv_identificador_aplicar(PDO $pdo, $datosJson, $rol, $idCaso)
{
    $datos = json_decode($datosJson);
    if (!is_object($datos)) {
        return ['json' => null, 'error' => 'Los datos del caso no tienen un formato válido.'];
    }

    $enviado = property_exists($datos, 'identificador');
    if ($rol === 'Administrador' && $enviado) {
        $identificador = trim((string)$datos->identificador);
        if ($idCaso === null && $identificador === '') {
            return ['json' => null, 'error' => 'El identificador del caso es obligatorio.'];
        }
    } else {
        $identificador = hv_identificador_guardado($pdo, $idCaso);
    }

    if ($identificador !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, JSON_UNQUOTE(JSON_EXTRACT(datos_completos, '$.titulo_caso')) AS titulo
             FROM casos_clinicos
             WHERE TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos_completos, '$.identificador'))) = :identificador
               AND id <> :id
             LIMIT 1"
        );
        $stmt->execute([':identificador' => $identificador, ':id' => (int)$idCaso]);
        $otro = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($otro) {
            $titulo = $otro['titulo'] !== null && $otro['titulo'] !== '' ? $otro['titulo'] : 'sin título';
            return ['json' => null, 'error' => "El identificador \"$identificador\" ya lo usa otro caso (\"$titulo\"). Usa otro identificador. No se guardó nada."];
        }
    }

    $datos->identificador = $identificador;
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return ['json' => null, 'error' => 'No se pudieron preparar los datos del caso.'];
    }
    return ['json' => $json, 'error' => null];
}
