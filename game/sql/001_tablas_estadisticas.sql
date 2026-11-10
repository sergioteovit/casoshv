-- Plataforma de estadisticas del juego: tablas nuevas.
-- No modifica Users ni PlayerData y no toca las tablas del gestor de casos.
-- Se puede ejecutar mas de una vez: las tablas existentes no se alteran.

USE myhvirtual;

-- Preferencias del jugador en la web
CREATE TABLE IF NOT EXISTS EstPerfiles (
    UserId CHAR(36) NOT NULL,
    MostrarEnPosiciones TINYINT(1) NOT NULL DEFAULT 1,
    ExcluirDePosiciones TINYINT(1) NOT NULL DEFAULT 0,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (UserId),
    CONSTRAINT fk_est_perfiles_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Grupos
CREATE TABLE IF NOT EXISTS EstGrupos (
    GrupoId INT NOT NULL AUTO_INCREMENT,
    Nombre VARCHAR(120) NOT NULL,
    Descripcion VARCHAR(255) NULL,
    Codigo VARCHAR(20) NOT NULL,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    CreadoPor CHAR(36) NULL,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (GrupoId),
    UNIQUE KEY uq_est_grupos_codigo (Codigo),
    KEY ix_est_grupos_creador (CreadoPor),
    CONSTRAINT fk_est_grupos_creador FOREIGN KEY (CreadoPor) REFERENCES Users (UserId) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS EstGrupoRevisores (
    GrupoId INT NOT NULL,
    UserId CHAR(36) NOT NULL,
    AsignadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (GrupoId, UserId),
    KEY ix_est_revisores_usuario (UserId),
    CONSTRAINT fk_est_revisores_grupo FOREIGN KEY (GrupoId) REFERENCES EstGrupos (GrupoId) ON DELETE CASCADE,
    CONSTRAINT fk_est_revisores_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS EstGrupoAlumnos (
    GrupoId INT NOT NULL,
    UserId CHAR(36) NOT NULL,
    UnidoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (GrupoId, UserId),
    KEY ix_est_alumnos_usuario (UserId),
    CONSTRAINT fk_est_alumnos_grupo FOREIGN KEY (GrupoId) REFERENCES EstGrupos (GrupoId) ON DELETE CASCADE,
    CONSTRAINT fk_est_alumnos_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Catalogos
CREATE TABLE IF NOT EXISTS EstRetos (
    IdReto VARCHAR(100) NOT NULL,
    Tipo ENUM('caso', 'minijuego') NOT NULL DEFAULT 'minijuego',
    Sistema VARCHAR(40) NULL,
    Titulo VARCHAR(200) NULL,
    PuntajeMaximo INT NOT NULL DEFAULT 1000,
    Activo TINYINT(1) NOT NULL DEFAULT 1,
    CreadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (IdReto),
    KEY ix_est_retos_sistema (Sistema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS EstInsignias (
    IdInsignia VARCHAR(100) NOT NULL,
    Nombre VARCHAR(150) NOT NULL,
    Descripcion VARCHAR(500) NULL,
    IdReto VARCHAR(100) NULL,
    Activa TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (IdInsignia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Mejor resultado de cada jugador por reto
CREATE TABLE IF NOT EXISTS EstResultadosReto (
    UserId CHAR(36) NOT NULL,
    IdReto VARCHAR(100) NOT NULL,
    MejorPuntaje INT NOT NULL DEFAULT 0,
    MejorEstrellas TINYINT NOT NULL DEFAULT 0,
    Intentos INT NOT NULL DEFAULT 0,
    Victorias INT NOT NULL DEFAULT 0,
    PrimeraVez DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (UserId, IdReto),
    KEY ix_est_resultados_reto (IdReto, MejorPuntaje),
    CONSTRAINT fk_est_resultados_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE,
    CONSTRAINT fk_est_resultados_reto FOREIGN KEY (IdReto) REFERENCES EstRetos (IdReto) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Cada mejora con su fecha: alimenta el historial y las posiciones por periodo
CREATE TABLE IF NOT EXISTS EstMejoras (
    MejoraId BIGINT NOT NULL AUTO_INCREMENT,
    UserId CHAR(36) NOT NULL,
    IdReto VARCHAR(100) NOT NULL,
    PuntosGanados INT NOT NULL DEFAULT 0,
    PuntajeNuevo INT NOT NULL DEFAULT 0,
    EstrellasNuevas TINYINT NOT NULL DEFAULT 0,
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (MejoraId),
    KEY ix_est_mejoras_fecha (Fecha, UserId),
    KEY ix_est_mejoras_usuario (UserId, Fecha),
    KEY ix_est_mejoras_reto (IdReto),
    CONSTRAINT fk_est_mejoras_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE,
    CONSTRAINT fk_est_mejoras_reto FOREIGN KEY (IdReto) REFERENCES EstRetos (IdReto) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS EstInsigniasJugador (
    UserId CHAR(36) NOT NULL,
    IdInsignia VARCHAR(100) NOT NULL,
    ObtenidaEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (UserId, IdInsignia),
    KEY ix_est_insignias_fecha (ObtenidaEn),
    CONSTRAINT fk_est_insignias_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Respuestas escritas del alumno en cada caso clinico
CREATE TABLE IF NOT EXISTS EstRespuestasCaso (
    UserId CHAR(36) NOT NULL,
    NumeroExpediente VARCHAR(40) NOT NULL,
    Interrogatorio MEDIUMTEXT NULL,
    DescripcionExploracion MEDIUMTEXT NULL,
    PresentacionCaso MEDIUMTEXT NULL,
    Paraclinicos MEDIUMTEXT NULL,
    DiagnosticoPrincipal MEDIUMTEXT NULL,
    Tratamiento MEDIUMTEXT NULL,
    Metacognicion MEDIUMTEXT NULL,
    Retroalimentacion MEDIUMTEXT NULL,
    Resuelto TINYINT(1) NOT NULL DEFAULT 0,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (UserId, NumeroExpediente),
    KEY ix_est_respuestas_caso (NumeroExpediente),
    CONSTRAINT fk_est_respuestas_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Totales listos para las tablas de posiciones
CREATE TABLE IF NOT EXISTS EstResumenJugador (
    UserId CHAR(36) NOT NULL,
    TotalPuntos INT NOT NULL DEFAULT 0,
    TotalEstrellas INT NOT NULL DEFAULT 0,
    Bronce INT NOT NULL DEFAULT 0,
    Plata INT NOT NULL DEFAULT 0,
    Oro INT NOT NULL DEFAULT 0,
    Platino INT NOT NULL DEFAULT 0,
    Diamante INT NOT NULL DEFAULT 0,
    RetosCompletados INT NOT NULL DEFAULT 0,
    TotalInsignias INT NOT NULL DEFAULT 0,
    PuntajeAlcanzadoEn DATETIME NULL,
    ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (UserId),
    KEY ix_est_resumen_posicion (TotalPuntos, TotalEstrellas, PuntajeAlcanzadoEn),
    CONSTRAINT fk_est_resumen_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Resultados rechazados por estar fuera de rango, para revision
CREATE TABLE IF NOT EXISTS EstDatosDescartados (
    DescarteId BIGINT NOT NULL AUTO_INCREMENT,
    UserId CHAR(36) NOT NULL,
    IdReto VARCHAR(100) NULL,
    Motivo VARCHAR(120) NOT NULL,
    Detalle VARCHAR(500) NULL,
    Revisado TINYINT(1) NOT NULL DEFAULT 0,
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (DescarteId),
    KEY ix_est_descartes_revision (Revisado, Fecha),
    KEY ix_est_descartes_usuario (UserId),
    CONSTRAINT fk_est_descartes_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Registro de acciones de administracion
CREATE TABLE IF NOT EXISTS EstBitacora (
    BitacoraId BIGINT NOT NULL AUTO_INCREMENT,
    ActorId CHAR(36) NULL,
    Accion VARCHAR(60) NOT NULL,
    ObjetivoTipo VARCHAR(40) NULL,
    ObjetivoId VARCHAR(100) NULL,
    Detalle VARCHAR(1000) NULL,
    Ip VARCHAR(45) NULL,
    Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (BitacoraId),
    KEY ix_est_bitacora_fecha (Fecha),
    KEY ix_est_bitacora_actor (ActorId),
    CONSTRAINT fk_est_bitacora_actor FOREIGN KEY (ActorId) REFERENCES Users (UserId) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- El rol Editor del juego corresponde a los docentes
UPDATE Roles
SET RoleName = 'Revisor', Description = 'Docente: revisa a los alumnos de sus grupos'
WHERE RoleName = 'Editor';
