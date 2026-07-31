<?php
session_start();

if (!isset($_SESSION['UserId'])) {
    header("Location: index.html");
    exit();
}

$progress = json_decode($_SESSION['Progress'], true);
$statistics = json_decode($_SESSION['Statistics'], true);

// 1. Conexión a la base de datos para obtener el Leaderboard
$host = "localhost";
$db_name = "myhvirtual";
$user = "myhvirtual";
$pass = "uPLtaPntlDJnThpf";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Ejecutar la consulta JSON_EXTRACT
    $leaderboardQuery = "
        SELECT 
            u.Username, 
            CAST(JSON_EXTRACT(p.Statistics, '$.patientsCured') AS UNSIGNED) AS CuredCount
        FROM Users u
        JOIN PlayerData p ON u.UserId = p.UserId
        ORDER BY CuredCount DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($leaderboardQuery);
    $stmt->execute();
    $topPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtenemos el Top 10 en un arreglo

} catch (PDOException $e) {
    $topPlayers = []; // Si hay error, dejamos la tabla vacía
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Hospital - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">🏥 Hospital Virtual</a>
        <span class="navbar-text text-white">
            Dr. <?php echo htmlspecialchars($_SESSION['Username']); ?> | 
            <a href="logout.php" class="text-white text-decoration-none">Cerrar Sesión</a>
        </span>
    </div>
</nav>

<div class="container mt-5">
    <h2>Resumen de tu cuenta</h2>
    <hr>
    
    <!-- Tarjetas de Estadísticas Personales (Se mantienen igual) -->
    <div class="row">
        <!-- Tarjeta de Progreso -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">📈 Progreso Actual</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Nivel:</strong> <?php echo $progress['currentLevel']; ?></li>
                        <li class="list-group-item"><strong>Presupuesto:</strong> $<?php echo number_format($progress['inGameCurrency'], 2); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Estadísticas -->
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">📊 Estadísticas Médicas</div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Pacientes Curados:</strong> <?php echo $statistics['patientsCured']; ?></li>
                        <li class="list-group-item"><strong>Ingresos Históricos:</strong> $<?php echo number_format($statistics['totalMoneyEarned'], 2); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- NUEVA SECCIÓN: LEADERBOARD GLOBAL -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark fw-bold">
                    🏆 Top 10 Global - Doctores con más pacientes curados
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 text-center">
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col">Rango</th>
                                    <th scope="col">Nombre del Doctor</th>
                                    <th scope="col">Pacientes Curados</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($topPlayers) > 0): ?>
                                    <?php foreach ($topPlayers as $index => $player): ?>
                                        <!-- Resaltar al jugador actual si está en el Top 10 -->
                                        <tr class="<?php echo ($player['Username'] === $_SESSION['Username']) ? 'table-success fw-bold' : ''; ?>">
                                            <td>
                                                <?php 
                                                    if ($index == 0) echo '🥇 1';
                                                    elseif ($index == 1) echo '🥈 2';
                                                    elseif ($index == 2) echo '🥉 3';
                                                    else echo $index + 1; 
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($player['Username']); ?></td>
                                            <td><?php echo number_format($player['CuredCount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-muted py-3">No hay datos disponibles en el ranking aún.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>