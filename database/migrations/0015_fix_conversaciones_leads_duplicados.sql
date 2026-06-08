-- Backfill: corrige el conteo de conversaciones y leads que estaba inflado
-- porque sumábamos varios action_types que son aliases del mismo evento con
-- distintas ventanas de atribución (p.ej. messaging_conversation_started_7d,
-- total_messaging_connection y messaging_first_reply son la misma conversación).
--
-- Estrategia: para cada snapshot, tomar el PRIMER action_type encontrado en
-- el orden de prioridad, usando JSON_TABLE para descomponer metricas_extendidas.actions.

-- Conversaciones: prioridad 1) messaging_conversation_started_7d
--                            2) total_messaging_connection
--                            3) messaging_first_reply
UPDATE metricas_snapshots ms
   SET ms.conversaciones = COALESCE(
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(
                JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                '$[*]' COLUMNS (action JSON PATH '$')
          ) AS at1
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'onsite_conversion.messaging_conversation_started_7d'
         LIMIT 1),
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(
                JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                '$[*]' COLUMNS (action JSON PATH '$')
          ) AS at2
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'onsite_conversion.total_messaging_connection'
         LIMIT 1),
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(
                JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                '$[*]' COLUMNS (action JSON PATH '$')
          ) AS at3
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'onsite_conversion.messaging_first_reply'
         LIMIT 1),
       0
   )
 WHERE metricas_extendidas IS NOT NULL
   AND JSON_EXTRACT(metricas_extendidas, '$.actions') IS NOT NULL;

-- Leads: prioridad 1) lead, 2) leadgen_grouped, 3) leadgen.other, 4) onsite_conversion.lead_grouped
UPDATE metricas_snapshots ms
   SET ms.leads = COALESCE(
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                          '$[*]' COLUMNS (action JSON PATH '$')) AS at1
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'lead' LIMIT 1),
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                          '$[*]' COLUMNS (action JSON PATH '$')) AS at2
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'leadgen_grouped' LIMIT 1),
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                          '$[*]' COLUMNS (action JSON PATH '$')) AS at3
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'leadgen.other' LIMIT 1),
       (SELECT CAST(JSON_EXTRACT(action, '$.value') AS UNSIGNED)
          FROM JSON_TABLE(JSON_EXTRACT(ms.metricas_extendidas, '$.actions'),
                          '$[*]' COLUMNS (action JSON PATH '$')) AS at4
         WHERE JSON_UNQUOTE(JSON_EXTRACT(action, '$.action_type')) = 'onsite_conversion.lead_grouped' LIMIT 1),
       0
   )
 WHERE metricas_extendidas IS NOT NULL
   AND JSON_EXTRACT(metricas_extendidas, '$.actions') IS NOT NULL;
