CREATE TABLE configuraciones_dashboard_cliente (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    widgets JSON NOT NULL,
    rango_default VARCHAR(50) NOT NULL DEFAULT 'ultimos_30_dias',
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dashboard_cliente (cliente_id),
    CONSTRAINT fk_dash_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_pdf_plantillas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    estructura JSON NOT NULL,
    cliente_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_plantillas_cliente (cliente_id),
    CONSTRAINT fk_plantillas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reportes_pdf_historico (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    plantilla_id INT UNSIGNED NULL,
    generado_por_usuario_id INT UNSIGNED NOT NULL,
    rango_inicio DATE NOT NULL,
    rango_fin DATE NOT NULL,
    archivo_ruta VARCHAR(500) NOT NULL,
    archivo_tamanio INT UNSIGNED NULL,
    comentarios JSON NULL,
    generado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reportes_cliente_fecha (cliente_id, generado_en),
    CONSTRAINT fk_reportes_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_reportes_plantilla FOREIGN KEY (plantilla_id) REFERENCES reportes_pdf_plantillas(id) ON DELETE SET NULL,
    CONSTRAINT fk_reportes_usuario FOREIGN KEY (generado_por_usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
