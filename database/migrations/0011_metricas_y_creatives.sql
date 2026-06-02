-- Métricas nuevas: conversaciones (mensajes iniciados, típico WhatsApp click-to-message)
-- y visitas a la página de destino. Vienen del array `actions` de Meta Insights.
ALTER TABLE metricas_snapshots
    ADD COLUMN conversaciones INT UNSIGNED NULL AFTER conversiones,
    ADD COLUMN landing_page_views INT UNSIGNED NULL AFTER conversaciones;

-- Datos del creative para mostrar el anuncio al cliente (post/imagen + copy + link).
-- Se pueblan en la importación cuando Meta los devuelve dentro del creative del ad.
ALTER TABLE anuncios
    ADD COLUMN cuerpo TEXT NULL AFTER thumbnail_url,
    ADD COLUMN titulo VARCHAR(500) NULL AFTER cuerpo,
    ADD COLUMN link_url TEXT NULL AFTER titulo,
    ADD COLUMN image_url TEXT NULL AFTER link_url,
    ADD COLUMN call_to_action VARCHAR(50) NULL AFTER image_url,
    ADD COLUMN permalink_url TEXT NULL AFTER call_to_action;

-- Catálogo: 2 métricas nuevas para que aparezcan como widgets configurables.
INSERT INTO catalogo_metricas (codigo, etiqueta, descripcion, unidad, categoria, orden) VALUES
('conversaciones', 'Conversaciones', 'Mensajes iniciados (típicamente clicks a WhatsApp/Messenger).', 'nro', 'conversion', 30),
('landing_page_views', 'Visitas a página de destino', 'Cargas exitosas de la landing page tras el click.', 'nro', 'interaccion', 40);
