-- La importación de Facebook/Instagram pasa a hacerse por tramos (una petición
-- corta por tramo) para no chocar con el timeout del servidor web en rangos
-- largos. Los avisos se acumulan entre tramos: además de mostrarse al final,
-- sirven para no volver a pedir métricas que Meta ya rechazó.
ALTER TABLE importaciones_redes
    ADD COLUMN avisos JSON NULL AFTER error_mensaje;
