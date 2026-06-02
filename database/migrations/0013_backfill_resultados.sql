-- Backfill: rellenar metricas_snapshots.resultados con leads/conversaciones según
-- el objetivo de la campaña, cuando Meta no devolvió el campo `results` durante
-- la importación. Las importaciones nuevas ya lo aplican en el código del importer.

UPDATE metricas_snapshots ms
JOIN anuncios a ON a.id = ms.entidad_id AND ms.nivel = 'ad'
JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
JOIN campanias c ON c.id = cs.campania_id
SET ms.resultados = CASE
    WHEN c.objetivo IN ('OUTCOME_LEADS', 'LEAD_GENERATION') THEN COALESCE(ms.leads, 0)
    WHEN c.objetivo IN ('MESSAGES', 'OUTCOME_ENGAGEMENT') THEN COALESCE(ms.conversaciones, 0)
    WHEN c.objetivo IN ('OUTCOME_TRAFFIC', 'LINK_CLICKS') THEN COALESCE(ms.landing_page_views, 0)
    ELSE ms.resultados
END
WHERE (ms.resultados IS NULL OR ms.resultados = 0)
  AND c.objetivo IN (
    'OUTCOME_LEADS', 'LEAD_GENERATION',
    'MESSAGES', 'OUTCOME_ENGAGEMENT',
    'OUTCOME_TRAFFIC', 'LINK_CLICKS'
  );
