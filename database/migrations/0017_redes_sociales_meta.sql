-- Analíticas orgánicas de Meta: páginas de Facebook y cuentas de Instagram.
-- Misma lógica que el resto: el admin sincroniza las páginas/cuentas que ve el
-- token de Meta, importa métricas diarias on-demand y las asigna a cada cliente.

CREATE TABLE meta_paginas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    categoria VARCHAR(150) NULL,
    foto_url TEXT NULL,
    seguidores INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_sincronizacion_en DATETIME NULL,
    UNIQUE KEY uq_meta_paginas (page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta_instagram_cuentas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ig_user_id VARCHAR(50) NOT NULL,
    username VARCHAR(100) NOT NULL,
    nombre VARCHAR(200) NULL,
    foto_url TEXT NULL,
    seguidores INT UNSIGNED NULL,
    -- Página de Facebook vinculada: su token se usa para consultar Instagram.
    pagina_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_sincronizacion_en DATETIME NULL,
    UNIQUE KEY uq_meta_ig (ig_user_id),
    CONSTRAINT fk_meta_ig_pagina FOREIGN KEY (pagina_id) REFERENCES meta_paginas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Métricas diarias de la página. NULL = Meta no devolvió esa métrica.
-- Espectadores son únicos por día: la suma de un período es una aproximación.
CREATE TABLE meta_paginas_metricas_diarias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pagina_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    visualizaciones INT UNSIGNED NULL,
    espectadores INT UNSIGNED NULL,
    interacciones INT UNSIGNED NULL,
    nuevos_seguidores INT UNSIGNED NULL,
    dejaron_de_seguir INT UNSIGNED NULL,
    visitas_perfil INT UNSIGNED NULL,
    seguidores_total INT UNSIGNED NULL,
    importado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_meta_pag_metricas (pagina_id, fecha),
    CONSTRAINT fk_meta_pag_metricas FOREIGN KEY (pagina_id) REFERENCES meta_paginas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Métricas diarias de la cuenta de Instagram. Alcance y cuentas que
-- interactuaron son únicos por día: la suma de un período es una aproximación.
CREATE TABLE meta_instagram_metricas_diarias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cuenta_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL,
    visualizaciones INT UNSIGNED NULL,
    alcance INT UNSIGNED NULL,
    cuentas_interactuaron INT UNSIGNED NULL,
    interacciones INT UNSIGNED NULL,
    me_gusta INT UNSIGNED NULL,
    comentarios INT UNSIGNED NULL,
    compartidos INT UNSIGNED NULL,
    guardados INT UNSIGNED NULL,
    toques_enlaces INT UNSIGNED NULL,
    nuevos_seguidores INT UNSIGNED NULL,
    dejaron_de_seguir INT UNSIGNED NULL,
    importado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_meta_ig_metricas (cuenta_id, fecha),
    CONSTRAINT fk_meta_ig_metricas FOREIGN KEY (cuenta_id) REFERENCES meta_instagram_cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publicaciones orgánicas (Facebook e Instagram) con sus interacciones al
-- momento de importar. entidad_id = meta_paginas.id o meta_instagram_cuentas.id.
CREATE TABLE meta_publicaciones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plataforma ENUM('facebook','instagram') NOT NULL,
    entidad_id INT UNSIGNED NOT NULL,
    externo_id VARCHAR(80) NOT NULL,
    publicado_en DATETIME NOT NULL,
    tipo VARCHAR(40) NULL,
    texto TEXT NULL,
    imagen_url TEXT NULL,
    permalink TEXT NULL,
    me_gusta INT UNSIGNED NOT NULL DEFAULT 0,
    comentarios INT UNSIGNED NOT NULL DEFAULT 0,
    compartidos INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_meta_publicaciones (plataforma, externo_id),
    KEY idx_meta_publicaciones_entidad (plataforma, entidad_id, publicado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_meta_pagina (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    pagina_id INT UNSIGNED NOT NULL,
    otorgado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pcmp (cliente_id, pagina_id),
    CONSTRAINT fk_pcmp_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcmp_pagina FOREIGN KEY (pagina_id) REFERENCES meta_paginas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permisos_cliente_instagram (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NOT NULL,
    cuenta_id INT UNSIGNED NOT NULL,
    otorgado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pcig (cliente_id, cuenta_id),
    CONSTRAINT fk_pcig_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_pcig_cuenta FOREIGN KEY (cuenta_id) REFERENCES meta_instagram_cuentas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE importaciones_redes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plataforma ENUM('facebook','instagram') NOT NULL,
    referencia_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    rango_inicio DATE NOT NULL,
    rango_fin DATE NOT NULL,
    estado ENUM('en_curso','completada','fallida') NOT NULL DEFAULT 'en_curso',
    iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_en DATETIME NULL,
    dias_importados INT UNSIGNED NOT NULL DEFAULT 0,
    publicaciones INT UNSIGNED NOT NULL DEFAULT 0,
    llamadas_meta INT UNSIGNED NOT NULL DEFAULT 0,
    error_mensaje TEXT NULL,
    KEY idx_imp_redes_fecha (iniciado_en),
    CONSTRAINT fk_imp_redes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
