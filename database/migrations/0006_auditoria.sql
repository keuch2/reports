CREATE TABLE auditoria_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    rol VARCHAR(20) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    accion VARCHAR(100) NOT NULL,
    recurso_tipo VARCHAR(50) NULL,
    recurso_id VARCHAR(50) NULL,
    detalles JSON NULL,
    ocurrido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_usuario_tiempo (usuario_id, ocurrido_en),
    KEY idx_audit_accion_tiempo (accion, ocurrido_en),
    KEY idx_audit_tiempo (ocurrido_en),
    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
