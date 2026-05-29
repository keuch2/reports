<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

final class MetricaCatalogoRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string,mixed>> Catálogo completo agrupado por categoría */
    public function listar(): array
    {
        return $this->db->select(
            'SELECT id, codigo, etiqueta, descripcion, unidad, categoria, orden
               FROM catalogo_metricas WHERE activa = 1 ORDER BY categoria, orden, etiqueta'
        );
    }

    /** @return array<string, list<array<string,mixed>>> mapa categoria → métricas */
    public function listarPorCategoria(): array
    {
        $agrupadas = [];
        foreach ($this->listar() as $m) {
            $agrupadas[(string) $m['categoria']][] = $m;
        }

        return $agrupadas;
    }
}
