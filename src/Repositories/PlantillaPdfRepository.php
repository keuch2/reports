<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class PlantillaPdfRepository
{
    /** Secciones disponibles para componer una plantilla. */
    public const SECCIONES_DISPONIBLES = [
        'resumen_ejecutivo' => 'Resumen ejecutivo (KPIs)',
        'resultados_por_tipo' => 'Resultados por tipo (conversaciones, leads…)',
        'tabla_campanias' => 'Tabla de campañas',
        'tabla_anuncios' => 'Tabla de anuncios',
        'evolucion_diaria' => 'Evolución diaria',
        'costos' => 'Costos del período (comisión + IVA)',
        'comentarios' => 'Comentarios estratégicos',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function listar(): array
    {
        return $this->db->select(
            'SELECT p.id, p.nombre, p.descripcion, p.estructura, p.cliente_id, p.creado_en,
                    c.nombre_comercial AS cliente_nombre
               FROM reportes_pdf_plantillas p
          LEFT JOIN clientes c ON c.id = p.cliente_id
           ORDER BY p.nombre'
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT id, nombre, descripcion, estructura, cliente_id FROM reportes_pdf_plantillas WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Plantilla por defecto aplicable a un cliente (específica o genérica). */
    public function paraCliente(int $clienteId): ?array
    {
        return $this->db->selectOne(
            'SELECT id, nombre, estructura, cliente_id
               FROM reportes_pdf_plantillas
              WHERE cliente_id = :c OR cliente_id IS NULL
           ORDER BY cliente_id IS NULL ASC
              LIMIT 1',
            ['c' => $clienteId]
        );
    }

    /**
     * @param list<string> $secciones
     */
    public function crear(string $nombre, ?string $descripcion, array $secciones, ?int $clienteId): int
    {
        $this->db->execute(
            'INSERT INTO reportes_pdf_plantillas (nombre, descripcion, estructura, cliente_id)
                  VALUES (:n, :d, :e, :c)',
            ['n' => $nombre, 'd' => $descripcion, 'e' => json_encode(['secciones' => $secciones]), 'c' => $clienteId]
        );

        return $this->db->lastInsertId();
    }

    /** @param list<string> $secciones */
    public function actualizar(int $id, string $nombre, ?string $descripcion, array $secciones, ?int $clienteId): void
    {
        $this->db->execute(
            'UPDATE reportes_pdf_plantillas
                SET nombre = :n, descripcion = :d, estructura = :e, cliente_id = :c
              WHERE id = :id',
            ['n' => $nombre, 'd' => $descripcion, 'e' => json_encode(['secciones' => $secciones]), 'c' => $clienteId, 'id' => $id]
        );
    }

    public function eliminar(int $id): void
    {
        $this->db->execute('DELETE FROM reportes_pdf_plantillas WHERE id = :id', ['id' => $id]);
    }

    /**
     * Decodifica la estructura JSON a lista de secciones.
     *
     * @param array<string,mixed> $plantilla
     * @return list<string>
     */
    public static function seccionesDe(array $plantilla): array
    {
        $estructura = json_decode((string) ($plantilla['estructura'] ?? '{}'), true);
        $secciones = $estructura['secciones'] ?? [];

        return is_array($secciones)
            ? array_values(array_intersect($secciones, array_keys(self::SECCIONES_DISPONIBLES)))
            : [];
    }
}
