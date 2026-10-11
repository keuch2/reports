-- Google Ads + Google Analytics 4: misma lógica que Meta.
-- El admin conecta una cuenta de Google (OAuth), importa on-demand cuentas de
-- Google Ads (campañas + métricas diarias) y propiedades GA4 (métricas diarias
-- totales y por canal), y asigna campañas/propiedades a cada cliente.

CREATE TABLE google_ads_cuentas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(20) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    moneda VARCHAR(10) NULL,
    zona_horaria VARCHAR(50) NULL,
    -- Cuenta administradora (MCC) por la que se accede; va en el header login-customer-id.
    login_customer_id VARCHAR(20) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_sincronizacion_en DATETIME NULL,
    UNIQUE KEY uq_gads_cuentas_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE google_ads_campanias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_campaign_id VARCHAR(30) NOT NULL,
    cuenta_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    estado VARCHAR(30) NULL,
    tipo_canal VARCHAR(50) NULL,
    presupuesto_diario DECIMAL(15,2) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gads_campanias (cuenta_id, google_campaign_id),
    CONSTRAINT fk_gads_campanias_cuenta FOREIGN KEY (cuenta_id) REFERENCES google_ads_cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE google_ads_metricas_diarias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campania_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    gasto DECIMAL(15,2) NOT NULL DEFAULT 0,
    impresiones BIGINT UNSIGNED NOT NULL DEFAULT 0,
    clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
    conversiones DECIMAL(15,2) NOT NULL DEFAULT 0,
    valor_conversiones DECIMAL(15,2) NOT NULL DEFAULT 0,
    importado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gads_metricas (campania_id, fecha),
    KEY idx_gads_metricas_fecha (fecha),
    CONSTRAINT fk_gads_metricas_campania FOREIGN KEY (campania_id) REFERENCES google_ads_campanias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ga4_propiedades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id VARCHAR(30) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    cuenta_nombre VARCHAR(200) NULL,
    moneda VARCHAR(10) NULL,
    zona_horaria VARCHAR(50) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_sincronizacion_en DATETIME NULL,
    UNIQUE KEY uq_ga4_propiedades (property_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Totales diarios de la propiedad. Usuarios no son aditivos entre días: la suma
-- de un período es una aproximación (igual que el alcance de Meta).
CREATE TABLE ga4_metricas_diarias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    sesiones INT UNSIGNED NOT NULL DEFAULT 0,
    usuarios INT UNSIGNED NOT NULL DEFAULT 0,
    usuarios_nuevos INT UNSIGNED NOT NULL DEFAULT 0,
    vistas INT UNSIGNED NOT NULL DEFAULT 0,
    sesiones_con_interaccion INT UNSIGNED NOT NULL DEFAULT 0,
    duracion_interaccion_seg BIGINT UNSIGNED NOT NULL DEFAULT 0,
    eventos_clave DECIMAL(15,2) NOT NULL DEFAULT 0,
    ingresos DECIMAL(15,2) NOT NULL DEFAULT 0,
    importado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ga4_metricas (propiedad_id, fecha),
    CONSTRAINT fk_ga4_metricas_propiedad FOREIGN KEY (propiedad_id) REFERENCES ga4_propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ga4_metricas_canal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    canal VARCHAR(100) NOT NULL,
    sesiones INT UNSIGNED NOT NULL DEFAULT 0,
    usuarios INT UNSIGNED NOT NULL DEFAULT 0,
    eventos_clave DECIMAL(15,2) NOT NULL DEFAULT 0,
    ingresos DECIMAL(15,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_ga4_canal (propiedad_id, fecha, canal),
    CONSTRAINT fk_ga4_canal_propiedad FOREIGN KEY (propiedad_id) REFERENCES ga4_propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_google_ads_campania (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    campania_id INT UNSIGNED NOT NULL,
    otorgado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pcgads (cliente_id, campania_id),
    CONSTRAINT fk_pcgads_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcgads_campania FOREIGN KEY (campania_id) REFERENCES google_ads_campanias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_ga4_propiedad (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    propiedad_id INT UNSIGNED NOT NULL,
    otorgado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pcga4 (cliente_id, propiedad_id),
    CONSTRAINT fk_pcga4_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcga4_propiedad FOREIGN KEY (propiedad_id) REFERENCES ga4_propiedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE importaciones_google (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fuente ENUM('google_ads','ga4') NOT NULL,
    -- google_ads_cuentas.id o ga4_propiedades.id según la fuente.
    referencia_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    rango_inicio DATE NOT NULL,
    rango_fin DATE NOT NULL,
    estado ENUM('en_curso','completada','fallida') NOT NULL DEFAULT 'en_curso',
    iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_en DATETIME NULL,
    entidades_afectadas INT UNSIGNED NOT NULL DEFAULT 0,
    filas_afectadas INT UNSIGNED NOT NULL DEFAULT 0,
    error_mensaje TEXT NULL,
    KEY idx_imp_google_fecha (iniciado_en),
    CONSTRAINT fk_imp_google_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
