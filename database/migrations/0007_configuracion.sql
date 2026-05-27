CREATE TABLE configuracion (
    clave VARCHAR(100) NOT NULL PRIMARY KEY,
    valor_cifrado TEXT NOT NULL,
    actualizado_por INT UNSIGNED NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_config_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
