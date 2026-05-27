CREATE TABLE cuentas_publicitarias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meta_account_id VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    business_manager_id VARCHAR(50) NULL,
    estado VARCHAR(50) NULL,
    moneda VARCHAR(10) NULL,
    zona_horaria VARCHAR(50) NULL,
    accesible_con_token TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_sincronizacion_en DATETIME NULL,
    UNIQUE KEY uq_cuentas_meta_id (meta_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campanias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meta_campaign_id VARCHAR(50) NOT NULL,
    cuenta_publicitaria_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    objetivo VARCHAR(100) NULL,
    estado VARCHAR(50) NULL,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    presupuesto_diario DECIMAL(15,2) NULL,
    presupuesto_total DECIMAL(15,2) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_campanias_meta_id (meta_campaign_id),
    KEY idx_campanias_cuenta (cuenta_publicitaria_id),
    CONSTRAINT fk_campanias_cuenta FOREIGN KEY (cuenta_publicitaria_id) REFERENCES cuentas_publicitarias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conjuntos_anuncios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meta_adset_id VARCHAR(50) NOT NULL,
    campania_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    estado VARCHAR(50) NULL,
    segmentacion JSON NULL,
    presupuesto_diario DECIMAL(15,2) NULL,
    presupuesto_total DECIMAL(15,2) NULL,
    optimization_goal VARCHAR(100) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_adsets_meta_id (meta_adset_id),
    KEY idx_adsets_campania (campania_id),
    CONSTRAINT fk_adsets_campania FOREIGN KEY (campania_id) REFERENCES campanias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE anuncios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meta_ad_id VARCHAR(50) NOT NULL,
    conjunto_anuncios_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    creative_id VARCHAR(50) NULL,
    tipo VARCHAR(50) NULL,
    preview_url TEXT NULL,
    thumbnail_url TEXT NULL,
    estado VARCHAR(50) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_anuncios_meta_id (meta_ad_id),
    KEY idx_anuncios_adset (conjunto_anuncios_id),
    CONSTRAINT fk_anuncios_adset FOREIGN KEY (conjunto_anuncios_id) REFERENCES conjuntos_anuncios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
