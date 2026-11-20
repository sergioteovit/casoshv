-- Ejecutar con la base de datos del portal casoshv seleccionada.
-- El identificador debe usar el mismo tipo y colación que Users.UserId.
SET @columna_usuario = (
    SELECT CONCAT(COLUMN_TYPE, ' CHARACTER SET ', CHARACTER_SET_NAME, ' COLLATE ', COLLATION_NAME)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Users' AND COLUMN_NAME = 'UserId'
);

SET @crear_avatar = CONCAT(
    'CREATE TABLE IF NOT EXISTS WebAvatares (',
    'UserId ', @columna_usuario, ' NOT NULL, ',
    'Configuracion JSON NOT NULL, ',
    'ActualizadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, ',
    'PRIMARY KEY (UserId), ',
    'CONSTRAINT fk_web_avatar_usuario FOREIGN KEY (UserId) REFERENCES Users (UserId) ON DELETE CASCADE',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
PREPARE crear_avatar FROM @crear_avatar;
EXECUTE crear_avatar;
DEALLOCATE PREPARE crear_avatar;
