<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;

/**
 * Reglas de visibilidad de entidades por cliente.
 *
 * Modelo:
 * - Una cuenta publicitaria asignada al cliente significa "todo es visible por default".
 * - permisos_cliente_campania.visible=0 oculta una campaña específica (excepción).
 * - permisos_cliente_anuncio.visible=0 oculta un anuncio específico.
 * - permisos_cliente_metricas.habilitada=0 oculta una métrica del catálogo.
 *
 * Es decir: ausencia de fila = visible. Fila con visible=0 = oculto.
 * Esto permite "default open, exception-based hide" — el admin solo necesita
 * marcar lo que quiere ocultar, no todo lo que quiere mostrar.
 */
final class PermisosService
{
    public function __construct(private readonly Database $db)
    {
    }

    // ----- Campañas -----

    /** @return list<int> IDs de campañas explícitamente ocultas al cliente */
    public function campaniasOcultas(int $clienteId, int $cuentaId): array
    {
        $rows = $this->db->select(
            'SELECT p.campania_id
               FROM permisos_cliente_campania p
               JOIN campanias c ON c.id = p.campania_id
              WHERE p.cliente_id = :cli
                AND p.visible = 0
                AND c.cuenta_publicitaria_id = :cuenta',
            ['cli' => $clienteId, 'cuenta' => $cuentaId]
        );

        return array_map(static fn ($r) => (int) $r['campania_id'], $rows);
    }

    /** @param list<int> $ocultos campañas marcadas ocultas (mantienen visible=0); el resto se borra */
    public function reemplazarCampaniasOcultas(int $clienteId, int $cuentaId, array $ocultos): void
    {
        $this->db->beginTransaction();
        try {
            // Limpiar registros previos solo dentro de las campañas de esta cuenta.
            $this->db->execute(
                'DELETE p FROM permisos_cliente_campania p
                   JOIN campanias c ON c.id = p.campania_id
                  WHERE p.cliente_id = :cli AND c.cuenta_publicitaria_id = :cuenta',
                ['cli' => $clienteId, 'cuenta' => $cuentaId]
            );
            foreach ($ocultos as $cid) {
                $this->db->execute(
                    'INSERT INTO permisos_cliente_campania (cliente_id, campania_id, visible)
                          VALUES (:cli, :cid, 0)',
                    ['cli' => $clienteId, 'cid' => (int) $cid]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ----- Anuncios -----

    /** @return list<int> IDs de anuncios ocultos para el cliente dentro de una campaña */
    public function anunciosOcultosDeCampania(int $clienteId, int $campaniaId): array
    {
        $rows = $this->db->select(
            'SELECT p.anuncio_id
               FROM permisos_cliente_anuncio p
               JOIN anuncios a ON a.id = p.anuncio_id
               JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
              WHERE p.cliente_id = :cli
                AND p.visible = 0
                AND cs.campania_id = :cam',
            ['cli' => $clienteId, 'cam' => $campaniaId]
        );

        return array_map(static fn ($r) => (int) $r['anuncio_id'], $rows);
    }

    /** @param list<int> $ocultos anuncios marcados ocultos para esta campaña */
    public function reemplazarAnunciosOcultos(int $clienteId, int $campaniaId, array $ocultos): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'DELETE p FROM permisos_cliente_anuncio p
                   JOIN anuncios a ON a.id = p.anuncio_id
                   JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
                  WHERE p.cliente_id = :cli AND cs.campania_id = :cam',
                ['cli' => $clienteId, 'cam' => $campaniaId]
            );
            foreach ($ocultos as $aid) {
                $this->db->execute(
                    'INSERT INTO permisos_cliente_anuncio (cliente_id, anuncio_id, visible)
                          VALUES (:cli, :aid, 0)',
                    ['cli' => $clienteId, 'aid' => (int) $aid]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ----- Métricas -----

    /** @return list<string> códigos de métricas explícitamente deshabilitadas */
    public function metricasDeshabilitadas(int $clienteId): array
    {
        $rows = $this->db->select(
            'SELECT m.codigo
               FROM permisos_cliente_metricas p
               JOIN catalogo_metricas m ON m.id = p.metrica_id
              WHERE p.cliente_id = :cli AND p.habilitada = 0',
            ['cli' => $clienteId]
        );

        return array_map(static fn ($r) => (string) $r['codigo'], $rows);
    }

    public function metricaEstaHabilitada(int $clienteId, string $codigo): bool
    {
        return !in_array($codigo, $this->metricasDeshabilitadas($clienteId), true);
    }

    /** @param list<int> $deshabilitadas IDs del catalogo_metricas */
    public function reemplazarMetricasDeshabilitadas(int $clienteId, array $deshabilitadas): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'DELETE FROM permisos_cliente_metricas WHERE cliente_id = :cli',
                ['cli' => $clienteId]
            );
            foreach ($deshabilitadas as $mid) {
                $this->db->execute(
                    'INSERT INTO permisos_cliente_metricas (cliente_id, metrica_id, habilitada)
                          VALUES (:cli, :mid, 0)',
                    ['cli' => $clienteId, 'mid' => (int) $mid]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Verifica que el anuncio sea visible para el cliente */
    public function clientePuedeVerAnuncio(int $clienteId, int $anuncioId): bool
    {
        $row = $this->db->selectOne(
            'SELECT 1
               FROM anuncios a
               JOIN conjuntos_anuncios cs ON cs.id = a.conjunto_anuncios_id
               JOIN campanias c ON c.id = cs.campania_id
               JOIN permisos_cliente_cuenta pcc ON pcc.cuenta_publicitaria_id = c.cuenta_publicitaria_id
                                              AND pcc.cliente_id = :cli
          LEFT JOIN permisos_cliente_campania pcam ON pcam.cliente_id = :cli AND pcam.campania_id = c.id
          LEFT JOIN permisos_cliente_anuncio pa ON pa.cliente_id = :cli AND pa.anuncio_id = a.id
              WHERE a.id = :aid
                AND (pcam.visible IS NULL OR pcam.visible = 1)
                AND (pa.visible IS NULL OR pa.visible = 1)
              LIMIT 1',
            ['cli' => $clienteId, 'aid' => $anuncioId]
        );

        return $row !== null;
    }
}
