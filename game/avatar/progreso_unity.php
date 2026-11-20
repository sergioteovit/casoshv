<?php
declare(strict_types=1);

function avatar_modelo_cliente($progress): string
{
    $modelo = json_decode((string)$progress)->gender ?? '';
    return in_array($modelo, ['Masculino', 'Femenino', 'Neutro'], true) ? $modelo : '';
}

// Se invoca dentro de la transacción del guardado. El valor de la sesión
// recuerda lo que vio Unity, incluso si el jugador cambió su modelo en la web.
function avatar_conservar_modelo(PDO $conn, string $userId, string $token, string $progress): bool
{
    $hash = hash('sha256', $token);
    $leer = $conn->prepare('SELECT UltimoModeloCliente FROM EstSesionesJuego
        WHERE TokenHash = ? AND UserId = ? FOR UPDATE');
    $leer->execute([$hash, $userId]);
    $anterior = $leer->fetchColumn();
    $recibido = avatar_modelo_cliente($progress);
    if ($anterior === false || $recibido === '') {
        return true;
    }
    $guardar = $conn->prepare('UPDATE EstSesionesJuego SET UltimoModeloCliente = ?
        WHERE TokenHash = ? AND UserId = ?');
    $guardar->execute([$recibido, $hash, $userId]);
    // Un token anterior a la migración aprende su referencia en el primer guardado.
    return $anterior === null || $anterior === $recibido;
}
