-- Métricas adicionales que Meta retorna vía `actions` o `cost_per_action_type`:
-- - leads: "clientes potenciales" (formularios lead gen, leads + leadgen_grouped).
-- - costo_por_conversacion: costo dividido por conversaciones iniciadas por mensaje.
-- También guardamos el array `actions` raw en metricas_extendidas para no perder nada
-- (ya existe la columna, solo cambia la convención de uso).

ALTER TABLE metricas_snapshots
    ADD COLUMN leads INT UNSIGNED NULL AFTER landing_page_views,
    ADD COLUMN costo_por_conversacion DECIMAL(15,4) NULL AFTER leads;

INSERT INTO catalogo_metricas (codigo, etiqueta, descripcion, unidad, categoria, orden) VALUES
('leads', 'Clientes potenciales (lead)', 'Formularios de lead gen completados.', 'nro', 'conversion', 25),
('costo_por_conversacion', 'Costo por conversación', 'Costo promedio por cada mensaje/conversación iniciada.', 'monto', 'costo', 50);
