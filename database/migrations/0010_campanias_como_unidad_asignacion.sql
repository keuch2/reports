-- Cambio de modelo: el cliente ya no se asigna a cuentas enteras,
-- se le asignan campañas específicas. La tabla permisos_cliente_campania
-- pasa a ser la fuente de verdad: cualquier fila = "asignada y visible".
--
-- - Mantenemos permisos_cliente_cuenta por compat con BDs existentes,
--   pero el código ya no la lee.
-- - permisos_cliente_anuncio sigue sirviendo para ocultar anuncios específicos
--   dentro de una campaña asignada.
-- - permisos_cliente_metricas sin cambios.
--
-- Si había datos previos en permisos_cliente_cuenta, esta migración los
-- traduce: por cada (cliente, cuenta) asignada, asigna TODAS las campañas
-- actualmente existentes en esa cuenta (preservando visibilidad para clientes
-- que ya tenían acceso). Los registros de permisos_cliente_campania con
-- visible=0 (ocultos por el modelo viejo) NO se asignan.

INSERT IGNORE INTO permisos_cliente_campania (cliente_id, campania_id, visible)
SELECT pcc.cliente_id, c.id, 1
  FROM permisos_cliente_cuenta pcc
  JOIN campanias c ON c.cuenta_publicitaria_id = pcc.cuenta_publicitaria_id
 WHERE NOT EXISTS (
     SELECT 1 FROM permisos_cliente_campania pcm
      WHERE pcm.cliente_id = pcc.cliente_id
        AND pcm.campania_id = c.id
        AND pcm.visible = 0
 );

-- A partir de ahora, presencia en permisos_cliente_campania = asignada y visible.
-- Eliminamos los registros con visible=0 (su semántica vieja ya no aplica;
-- si una campaña no debe ser visible, simplemente no debe estar la fila).
DELETE FROM permisos_cliente_campania WHERE visible = 0;
