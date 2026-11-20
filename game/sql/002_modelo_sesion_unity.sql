-- Ejecutar en la base del portal, después de game/sql/003_sesiones_juego.sql.
-- Cursor de sincronización de cada cliente. El modelo del jugador vive en PlayerData.Progress.
ALTER TABLE EstSesionesJuego
    ADD COLUMN IF NOT EXISTS UltimoModeloCliente VARCHAR(16) DEFAULT NULL;
