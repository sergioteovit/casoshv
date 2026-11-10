<?php
// Funciones que comparten las páginas de estadísticas.

function h($texto)
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function fecha_corta($fecha)
{
    return $fecha ? date('d/m/Y', strtotime($fecha)) : '';
}

function est_bitacora($conn, $actorId, $accion, $objetivoTipo, $objetivoId, $detalle)
{
    $stmt = $conn->prepare("
        INSERT INTO EstBitacora (ActorId, Accion, ObjetivoTipo, ObjetivoId, Detalle, Ip)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $actorId, $accion, $objetivoTipo, (string)$objetivoId,
        substr((string)$detalle, 0, 1000), $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function est_nombre_sistema($clave)
{
    $nombres = [
        'circulatorio'         => 'Circulatorio',
        'digestivo'            => 'Digestivo',
        'endocrino'            => 'Endocrino',
        'linfatico'            => 'Linfático',
        'muscular'             => 'Muscular',
        'nervioso'             => 'Nervioso',
        'oseo'                 => 'Óseo',
        'reproductorfemenino'  => 'Reproductor femenino',
        'reproductormasculino' => 'Reproductor masculino',
        'respiratorio'         => 'Respiratorio',
        'tegumentario'         => 'Tegumentario',
        'urinario'             => 'Urinario',
    ];
    return $nombres[$clave] ?? ucfirst((string)$clave);
}

function est_nombre_nivel($estrellas)
{
    $niveles = [1 => 'Bronce', 2 => 'Plata', 3 => 'Oro', 4 => 'Platino', 5 => 'Diamante'];
    return $niveles[(int)$estrellas] ?? null;
}
