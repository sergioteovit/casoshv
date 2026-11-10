<?php
// Página TEMPORAL: copia las partidas existentes a las tablas Est*. Solo para administradores.
// Se puede ejecutar más de una vez sin duplicar datos. Borrarla del servidor al terminar.

require_once __DIR__ . '/seguridad.php';
hv_iniciar_sesion();
require_once __DIR__ . '/config_bd.php';
require_once __DIR__ . '/estadisticas_sync.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos.');
}

$stmt = $conn->prepare("
    SELECT r.RoleName
    FROM Users u
    JOIN Roles r ON u.RoleId = r.RoleId
    WHERE u.UserId = ?
");
$stmt->execute([$_SESSION['UserId'] ?? '']);
if ($stmt->fetchColumn() !== 'Administrator') {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hv_csrf_valido($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Solicitud no válida. Recarga la página.');
    }

    set_time_limit(120);

    $partidas = $conn->query("SELECT UserId, Statistics, CasesDataBase FROM PlayerData")->fetchAll(PDO::FETCH_ASSOC);
    $procesadas = 0;
    $conError = 0;
    $conDiferencia = 0;
    $puntosSinRespaldo = 0;

    $resumen = $conn->prepare("SELECT TotalPuntos FROM EstResumenJugador WHERE UserId = ?");

    foreach ($partidas as $partida) {
        try {
            est_sincronizar($conn, $partida['UserId'], $partida['Statistics'], $partida['CasesDataBase'], false);
            $procesadas++;

            $datos = json_decode((string)$partida['Statistics'], true);
            $puntosJuego = is_array($datos) ? (int)($datos['totalPoints'] ?? 0) : 0;
            $resumen->execute([$partida['UserId']]);
            $diferencia = $puntosJuego - (int)$resumen->fetchColumn();
            if ($diferencia > 0) {
                $conDiferencia++;
                $puntosSinRespaldo += $diferencia;
            }
        } catch (Throwable $e) {
            $conError++;
            error_log('[estadisticas] Migración: ' . $e->getMessage());
        }
    }

    $contar = function ($tabla) use ($conn) {
        return (int)$conn->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
    };

    $resultado = [
        'Partidas procesadas'                          => $procesadas,
        'Partidas con error'                           => $conError,
        'Resultados por reto'                          => $contar('EstResultadosReto'),
        'Insignias de jugadores'                       => $contar('EstInsigniasJugador'),
        'Casos con respuestas'                         => $contar('EstRespuestasCaso'),
        'Resúmenes de jugador'                         => $contar('EstResumenJugador'),
        'Datos descartados para revisión'              => $contar('EstDatosDescartados'),
        'Cuentas con puntos antiguos sin reto asociado' => $conDiferencia,
        'Puntos antiguos sin reto asociado'            => $puntosSinRespaldo,
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración de estadísticas</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        td { border: 1px solid #ccc; padding: 8px; }
        td:last-child { text-align: right; font-weight: bold; }
        button { padding: 10px 18px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Migración de estadísticas</h1>
    <?php if ($resultado === null): ?>
        <p>Copia las partidas existentes a las tablas nuevas. No modifica las partidas y se puede repetir sin duplicar datos.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo hv_token_csrf(); ?>">
            <button type="submit">Copiar datos</button>
        </form>
    <?php else: ?>
        <p>Proceso terminado.</p>
        <table>
            <?php foreach ($resultado as $nombre => $valor): ?>
                <tr><td><?php echo htmlspecialchars($nombre, ENT_QUOTES); ?></td><td><?php echo (int)$valor; ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>
