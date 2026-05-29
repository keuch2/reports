CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_solicitud VARCHAR(45) NULL,
    KEY idx_pr_usuario (usuario_id),
    KEY idx_pr_token (token_hash),
    CONSTRAINT fk_pr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
