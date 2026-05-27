INSERT INTO catalogo_metricas (codigo, etiqueta, descripcion, unidad, categoria, orden) VALUES
('impresiones', 'Impresiones', 'Veces que se mostró el anuncio.', 'nro', 'entrega', 10),
('alcance', 'Alcance', 'Personas únicas que vieron el anuncio.', 'nro', 'entrega', 20),
('frecuencia', 'Frecuencia', 'Veces promedio que cada persona vio el anuncio.', 'ratio', 'entrega', 30),
('gasto', 'Gasto', 'Inversión total en el período.', 'monto', 'costo', 10),
('cpc', 'CPC', 'Costo por click.', 'monto', 'costo', 20),
('cpm', 'CPM', 'Costo por mil impresiones.', 'monto', 'costo', 30),
('costo_por_resultado', 'Costo por resultado', 'Costo promedio por resultado obtenido.', 'monto', 'costo', 40),
('clicks_totales', 'Clicks totales', 'Total de clicks en el anuncio.', 'nro', 'interaccion', 10),
('clicks_enlace', 'Clicks al enlace', 'Clicks únicos al enlace de destino.', 'nro', 'interaccion', 20),
('ctr', 'CTR', 'Click-through rate.', 'porcentaje', 'interaccion', 30),
('resultados', 'Resultados', 'Resultados según el objetivo configurado.', 'nro', 'conversion', 10),
('conversiones', 'Conversiones', 'Conversiones reportadas.', 'nro', 'conversion', 20);
