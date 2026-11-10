-- Sesiones del juego: el login entrega un token y cada guardado lo envía.
-- Solo se guarda la huella SHA-256 del token, nunca el token.

USE myhvirtual;

CREATE TABLE IF NOT EXISTS EstSesionesJuego (
    TokenHash CHAR(64) NOT NULL,
    UserId CHAR(36) NOT NULL,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UltimoUso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ExpiraEn DATETIME NOT NULL,
    PRIMARY KEY (TokenHash),
    KEY ix_est_sesiones_usuario (UserId, CreadoEn),
    KEY ix_est_sesiones_expira (ExpiraEn),
    CONSTRAINT fk_est_sesiones_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
