CREATE TABLE catalogo_metricas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    etiqueta VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    unidad VARCHAR(20) NULL,
    categoria ENUM('entrega','costo','interaccion','conversion','video') NOT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_metricas_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_cuenta (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    cuenta_publicitaria_id INT UNSIGNED NOT NULL,
    otorgado_por_usuario_id INT UNSIGNED NULL,
    otorgado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permisos_cliente_cuenta (cliente_id, cuenta_publicitaria_id),
    KEY idx_permisos_cuenta (cuenta_publicitaria_id),
    CONSTRAINT fk_pcc_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcc_cuenta FOREIGN KEY (cuenta_publicitaria_id) REFERENCES cuentas_publicitarias(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcc_otorgado FOREIGN KEY (otorgado_por_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_campania (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    campania_id INT UNSIGNED NOT NULL,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permisos_cliente_campania (cliente_id, campania_id),
    CONSTRAINT fk_pccam_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pccam_campania FOREIGN KEY (campania_id) REFERENCES campanias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_anuncio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    anuncio_id INT UNSIGNED NOT NULL,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permisos_cliente_anuncio (cliente_id, anuncio_id),
    CONSTRAINT fk_pcan_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcan_anuncio FOREIGN KEY (anuncio_id) REFERENCES anuncios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_metricas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    metrica_id INT UNSIGNED NOT NULL,
    habilitada TINYINT(1) NOT NULL DEFAULT 1,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permisos_cliente_metrica (cliente_id, metrica_id),
    CONSTRAINT fk_pcm_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcm_metrica FOREIGN KEY (metrica_id) REFERENCES catalogo_metricas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
