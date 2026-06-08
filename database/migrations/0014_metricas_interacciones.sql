-- Columna interacciones para campañas con objetivo OUTCOME_ENGAGEMENT
-- o POST_ENGAGEMENT. Suma reactions + comments + shares + saves + post_engagement.

ALTER TABLE metricas_snapshots
    ADD COLUMN interacciones INT UNSIGNED NULL AFTER leads;

-- Backfill: extrae los action_types de engagement desde metricas_extendidas.actions
-- (que el importer ya guarda como JSON con todos los action_types crudos).
UPDATE metricas_snapshots
   SET interacciones = (
       SELECT COALESCE(SUM(CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)), 0)
         FROM JSON_TABLE(
            JSON_EXTRACT(metricas_extendidas, '$.actions'),
            '$[*]' COLUMNS (action JSON PATH '$')
         ) AS actions_t
        WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) IN (
            'post_engagement', 'page_engagement',
            'post_reaction', 'like',
            'comment', 'post_save', 'post'
        )
   )
 WHERE metricas_extendidas IS NOT NULL
   AND JSON_EXTRACT(metricas_extendidas, '$.actions') IS NOT NULL;
