-- Plataforma de estadisticas: veces que se inicio cada reto y su ultimo intento terminado.
-- El juego los envia desde la version 26.11.23 (Statistics.ResultadoReto: inicios, ultimoPuntaje,
-- ultimasEstrellas, ultimaVictoria, ultimaFecha). Las filas anteriores quedan con 0 y NULL.
-- Ejecutar ANTES de publicar el estadisticas_sync.php que los usa. Se puede ejecutar de nuevo.

USE myhvirtual;

ALTER TABLE EstResultadosReto
    ADD COLUMN IF NOT EXISTS Inicios INT NOT NULL DEFAULT 0 AFTER Victorias,
    ADD COLUMN IF NOT EXISTS UltimoPuntaje INT NULL AFTER Inicios,
    ADD COLUMN IF NOT EXISTS UltimasEstrellas TINYINT NULL AFTER UltimoPuntaje,
    ADD COLUMN IF NOT EXISTS UltimaVictoria TINYINT(1) NULL AFTER UltimasEstrellas,
    ADD COLUMN IF NOT EXISTS UltimaFecha DATETIME NULL AFTER UltimaVictoria;
