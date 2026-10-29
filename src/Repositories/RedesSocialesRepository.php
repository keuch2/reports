<?php

declare(strict_types=1);

namespace MisterCo\Reports\Repositories;

use MisterCo\Reports\Core\Database;

/**
 * Analíticas orgánicas de Meta: páginas de Facebook, cuentas de Instagram,
 * sus métricas diarias y publicaciones, la asignación a clientes, las
 * consultas del reporte y el registro de importaciones.
 */
final class RedesSocialesRepository
{
    /** Columnas de métricas diarias permitidas por plataforma. */
    private const COLUMNAS_FACEBOOK = [
        'visualizaciones', 'espectadores', 'interacciones', 'nuevos_seguidores',
        'dejaron_de_seguir', 'visitas_perfil', 'seguidores_total',
    ];
    private const COLUMNAS_INSTAGRAM = [
        'visualizaciones', 'alcance', 'cuentas_interactuaron', 'interacciones', 'me_gusta',
        'comentarios', 'compartidos', 'guardados', 'toques_enlaces', 'nuevos_seguidores', 'dejaron_de_seguir',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    // --- Páginas y cuentas -------------------------------------------------

    public function upsertPagina(string $pageId, string $nombre, ?string $categoria, ?string $fotoUrl, ?int $seguidores): int
    {
        $this->db->execute(
            'INSERT INTO meta_paginas (page_id, nombre, categoria, foto_url, seguidores, ultima_sincronizacion_en)
                  VALUES (:p, :n, :c, :f, :s, NOW())
                  ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), categoria = VALUES(categoria),
                                          foto_url = VALUES(foto_url), seguidores = VALUES(seguidores),
                                          ultima_sincronizacion_en = NOW()',
            ['p' => $pageId, 'n' => $nombre, 'c' => $categoria, 'f' => $fotoUrl, 's' => $seguidores]
        );

        return (int) ($this->db->selectOne('SELECT id FROM meta_paginas WHERE page_id = :p', ['p' => $pageId])['id'] ?? 0);
    }

    public function upsertInstagram(string $igUserId, string $username, ?string $nombre, ?string $fotoUrl, ?int $seguidores, ?int $paginaId): int
    {
        $this->db->execute(
            'INSERT INTO meta_instagram_cuentas (ig_user_id, username, nombre, foto_url, seguidores, pagina_id, ultima_sincronizacion_en)
                  VALUES (:i, :u, :n, :f, :s, :p, NOW())
                  ON DUPLICATE KEY UPDATE username = VALUES(username), nombre = VALUES(nombre),
                                          foto_url = VALUES(foto_url), seguidores = VALUES(seguidores),
                                          pagina_id = VALUES(pagina_id), ultima_sincronizacion_en = NOW()',
            ['i' => $igUserId, 'u' => $username, 'n' => $nombre, 'f' => $fotoUrl, 's' => $seguidores, 'p' => $paginaId]
        );

        return (int) ($this->db->selectOne('SELECT id FROM meta_instagram_cuentas WHERE ig_user_id = :i', ['i' => $igUserId])['id'] ?? 0);
    }

    /** @return list<array<string,mixed>> Páginas con su cuenta de Instagram vinculada (si tiene). */
    public function listarPaginas(): array
    {
        return $this->db->select(
            'SELECT p.id, p.page_id, p.nombre, p.categoria, p.seguidores, p.ultima_sincronizacion_en,
                    ig.id AS ig_id, ig.username AS ig_username, ig.seguidores AS ig_seguidores
               FROM meta_paginas p
          LEFT JOIN meta_instagram_cuentas ig ON ig.pagina_id = p.id
           ORDER BY p.nombre'
        );
    }

    /** @return list<array<string,mixed>> */
    public function listarInstagram(): array
    {
        return $this->db->select(
            'SELECT ig.id, ig.ig_user_id, ig.username, ig.nombre, ig.seguidores, ig.ultima_sincronizacion_en,
                    p.nombre AS pagina_nombre
               FROM meta_instagram_cuentas ig
          LEFT JOIN meta_paginas p ON p.id = ig.pagina_id
           ORDER BY ig.username'
        );
    }

    /** @return array<string,mixed>|null */
    public function buscarPagina(int $id): ?array
    {
        return $this->db->selectOne('SELECT id, page_id, nombre FROM meta_paginas WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed>|null Cuenta de Instagram con el page_id de su página (para el token). */
    public function buscarInstagram(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT ig.id, ig.ig_user_id, ig.username, p.page_id
               FROM meta_instagram_cuentas ig
          LEFT JOIN meta_paginas p ON p.id = ig.pagina_id
              WHERE ig.id = :id',
            ['id' => $id]
        );
    }

    public function actualizarSeguidores(string $plataforma, int $id, int $seguidores): void
    {
        $tabla = $plataforma === 'facebook' ? 'meta_paginas' : 'meta_instagram_cuentas';
        $this->db->execute("UPDATE {$tabla} SET seguidores = :s, ultima_sincronizacion_en = NOW() WHERE id = :id", ['s' => $seguidores, 'id' => $id]);
    }

    // --- Métricas y publicaciones ------------------------------------------

    /**
     * Inserta o actualiza las métricas de un día. Solo toca las columnas
     * recibidas: así una métrica que Meta no devolvió no pisa un valor previo.
     *
     * @param 'facebook'|'instagram' $plataforma
     * @param array<string, int|null> $valores
     */
    public function guardarMetricasDia(string $plataforma, int $entidadId, string $fecha, array $valores): void
    {
        [$tabla, $fk, $permitidas] = $plataforma === 'facebook'
            ? ['meta_paginas_metricas_diarias', 'pagina_id', self::COLUMNAS_FACEBOOK]
            : ['meta_instagram_metricas_diarias', 'cuenta_id', self::COLUMNAS_INSTAGRAM];
        $valores = array_intersect_key($valores, array_flip($permitidas));
        if ($valores === []) {
            return;
        }
        $cols = array_keys($valores);
        $this->db->execute(
            "INSERT INTO {$tabla} ({$fk}, fecha, " . implode(', ', $cols) . ')
                  VALUES (:entidad, :fecha, ' . implode(', ', array_map(static fn ($c) => ":{$c}", $cols)) . ')
                  ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn ($c) => "{$c} = VALUES({$c})", $cols)),
            ['entidad' => $entidadId, 'fecha' => $fecha] + $valores
        );
    }

    /** @param 'facebook'|'instagram' $plataforma */
    public function guardarPublicacion(
        string $plataforma,
        int $entidadId,
        string $externoId,
        string $publicadoEn,
        ?string $tipo,
        ?string $texto,
        ?string $imagenUrl,
        ?string $permalink,
        int $meGusta,
        int $comentarios,
        int $compartidos,
    ): void {
        $this->db->execute(
            'INSERT INTO meta_publicaciones
                (plataforma, entidad_id, externo_id, publicado_en, tipo, texto, imagen_url, permalink, me_gusta, comentarios, compartidos)
             VALUES (:pl, :e, :x, :pub, :t, :tx, :img, :link, :mg, :c, :s)
             ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), texto = VALUES(texto), imagen_url = VALUES(imagen_url),
                                     permalink = VALUES(permalink), me_gusta = VALUES(me_gusta),
                                     comentarios = VALUES(comentarios), compartidos = VALUES(compartidos)',
            [
                'pl' => $plataforma, 'e' => $entidadId, 'x' => $externoId, 'pub' => $publicadoEn, 't' => $tipo,
                'tx' => $texto, 'img' => $imagenUrl, 'link' => $permalink, 'mg' => $meGusta, 'c' => $comentarios, 's' => $compartidos,
            ]
        );
    }

    // --- Asignación a clientes ---------------------------------------------

    /** @return list<int> */
    public function idsPaginasAsignadas(int $clienteId): array
    {
        return array_map('intval', array_column($this->db->select(
            'SELECT pagina_id FROM permisos_cliente_meta_pagina WHERE cliente_id = :c', ['c' => $clienteId]
        ), 'pagina_id'));
    }

    /** @return list<int> */
    public function idsInstagramAsignadas(int $clienteId): array
    {
        return array_map('intval', array_column($this->db->select(
            'SELECT cuenta_id FROM permisos_cliente_instagram WHERE cliente_id = :c', ['c' => $clienteId]
        ), 'cuenta_id'));
    }

    /**
     * @param list<int> $paginasIds
     * @param list<int> $instagramIds
     */
    public function reemplazarAsignaciones(int $clienteId, array $paginasIds, array $instagramIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM permisos_cliente_meta_pagina WHERE cliente_id = :c', ['c' => $clienteId]);
            $this->db->execute('DELETE FROM permisos_cliente_instagram WHERE cliente_id = :c', ['c' => $clienteId]);
            foreach ($paginasIds as $id) {
                $this->db->execute('INSERT INTO permisos_cliente_meta_pagina (cliente_id, pagina_id) VALUES (:c, :p)', ['c' => $clienteId, 'p' => $id]);
            }
            foreach ($instagramIds as $id) {
                $this->db->execute('INSERT INTO permisos_cliente_instagram (cliente_id, cuenta_id) VALUES (:c, :i)', ['c' => $clienteId, 'i' => $id]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // --- Consultas del reporte ---------------------------------------------

    /** @return list<array<string,mixed>> */
    public function paginasDelCliente(int $clienteId): array
    {
        return $this->db->select(
            'SELECT p.id, p.nombre, p.seguidores FROM permisos_cliente_meta_pagina pc
               JOIN meta_paginas p ON p.id = pc.pagina_id
              WHERE pc.cliente_id = :c ORDER BY p.nombre',
            ['c' => $clienteId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function instagramDelCliente(int $clienteId): array
    {
        return $this->db->select(
            'SELECT ig.id, ig.username, ig.nombre, ig.seguidores FROM permisos_cliente_instagram pc
               JOIN meta_instagram_cuentas ig ON ig.id = pc.cuenta_id
              WHERE pc.cliente_id = :c ORDER BY ig.username',
            ['c' => $clienteId]
        );
    }

    /** @return list<string> YYYY-MM con datos de Facebook o Instagram, más reciente primero. */
    public function mesesConDatos(int $clienteId): array
    {
        $rows = $this->db->select(
            "SELECT DATE_FORMAT(m.fecha, '%Y-%m') AS mes
               FROM meta_paginas_metricas_diarias m
               JOIN permisos_cliente_meta_pagina pc ON pc.pagina_id = m.pagina_id AND pc.cliente_id = :c1
             UNION
             SELECT DATE_FORMAT(m.fecha, '%Y-%m') AS mes
               FROM meta_instagram_metricas_diarias m
               JOIN permisos_cliente_instagram pc ON pc.cuenta_id = m.cuenta_id AND pc.cliente_id = :c2
           ORDER BY mes DESC",
            ['c1' => $clienteId, 'c2' => $clienteId]
        );

        return array_map(static fn ($r) => (string) $r['mes'], $rows);
    }

    /**
     * Métricas de Facebook del cliente: totales (agrupar = null), por página
     * (agrupar = 'entidad') o por día (agrupar = 'fecha').
     *
     * @return list<array<string,mixed>>
     */
    public function metricasFacebook(int $clienteId, string $desde, string $hasta, ?string $agrupar = null): array
    {
        return $this->metricas('facebook', $clienteId, $desde, $hasta, $agrupar);
    }

    /** @return list<array<string,mixed>> Igual que metricasFacebook, para Instagram. */
    public function metricasInstagram(int $clienteId, string $desde, string $hasta, ?string $agrupar = null): array
    {
        return $this->metricas('instagram', $clienteId, $desde, $hasta, $agrupar);
    }

    /**
     * Publicaciones del período ordenadas por interacciones (me gusta +
     * comentarios + compartidos).
     *
     * @param 'facebook'|'instagram' $plataforma
     * @return list<array<string,mixed>>
     */
    public function publicaciones(string $plataforma, int $clienteId, string $desde, string $hasta, int $limite = 10): array
    {
        [$permisos, $fk, $tablaEntidad, $nombre] = $plataforma === 'facebook'
            ? ['permisos_cliente_meta_pagina', 'pagina_id', 'meta_paginas', 'e.nombre']
            : ['permisos_cliente_instagram', 'cuenta_id', 'meta_instagram_cuentas', "CONCAT('@', e.username)"];

        return $this->db->select(
            "SELECT pub.publicado_en, pub.tipo, pub.texto, pub.imagen_url, pub.permalink,
                    pub.me_gusta, pub.comentarios, pub.compartidos,
                    pub.me_gusta + pub.comentarios + pub.compartidos AS interacciones,
                    {$nombre} AS cuenta
               FROM meta_publicaciones pub
               JOIN {$permisos} pc ON pc.{$fk} = pub.entidad_id AND pc.cliente_id = :c
               JOIN {$tablaEntidad} e ON e.id = pub.entidad_id
              WHERE pub.plataforma = :pl AND pub.publicado_en BETWEEN :d AND :h
           ORDER BY interacciones DESC, pub.publicado_en DESC
              LIMIT " . (int) $limite,
            ['c' => $clienteId, 'pl' => $plataforma, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );
    }

    /** @param 'facebook'|'instagram' $plataforma */
    public function cantidadPublicaciones(string $plataforma, int $clienteId, string $desde, string $hasta): int
    {
        [$permisos, $fk] = $plataforma === 'facebook'
            ? ['permisos_cliente_meta_pagina', 'pagina_id']
            : ['permisos_cliente_instagram', 'cuenta_id'];
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS n FROM meta_publicaciones pub
               JOIN {$permisos} pc ON pc.{$fk} = pub.entidad_id AND pc.cliente_id = :c
              WHERE pub.plataforma = :pl AND pub.publicado_en BETWEEN :d AND :h",
            ['c' => $clienteId, 'pl' => $plataforma, 'd' => $desde . ' 00:00:00', 'h' => $hasta . ' 23:59:59']
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param 'facebook'|'instagram' $plataforma
     * @param 'entidad'|'fecha'|null $agrupar
     * @return list<array<string,mixed>>
     */
    private function metricas(string $plataforma, int $clienteId, string $desde, string $hasta, ?string $agrupar): array
    {
        [$tabla, $fk, $permisos, $tablaEntidad, $nombre, $columnas] = $plataforma === 'facebook'
            ? ['meta_paginas_metricas_diarias', 'pagina_id', 'permisos_cliente_meta_pagina', 'meta_paginas', 'e.nombre', self::COLUMNAS_FACEBOOK]
            : ['meta_instagram_metricas_diarias', 'cuenta_id', 'permisos_cliente_instagram', 'meta_instagram_cuentas', "CONCAT('@', e.username)", self::COLUMNAS_INSTAGRAM];

        // seguidores_total es un saldo, no se suma: se toma el último día con dato.
        $sumas = array_map(
            static fn ($c) => "SUM(m.{$c}) AS {$c}",
            array_diff($columnas, ['seguidores_total'])
        );
        $select = implode(', ', $sumas);
        [$grupo, $orden, $extra] = match ($agrupar) {
            'entidad' => ["GROUP BY e.id, {$nombre}", 'ORDER BY entidad', "e.id AS entidad_id, {$nombre} AS entidad, "],
            'fecha' => ['GROUP BY m.fecha', 'ORDER BY m.fecha', 'm.fecha, '],
            default => ['', '', ''],
        };

        return $this->db->select(
            "SELECT {$extra}{$select}, COUNT(DISTINCT m.fecha) AS dias
               FROM {$tabla} m
               JOIN {$permisos} pc ON pc.{$fk} = m.{$fk} AND pc.cliente_id = :c
               JOIN {$tablaEntidad} e ON e.id = m.{$fk}
              WHERE m.fecha BETWEEN :d AND :h
              {$grupo} {$orden}",
            ['c' => $clienteId, 'd' => $desde, 'h' => $hasta]
        );
    }

    // --- Importaciones -----------------------------------------------------

    /** @param 'facebook'|'instagram' $plataforma */
    public function crearImportacion(string $plataforma, int $referenciaId, int $usuarioId, string $desde, string $hasta): int
    {
        $this->db->execute(
            'INSERT INTO importaciones_redes (plataforma, referencia_id, usuario_id, rango_inicio, rango_fin)
                  VALUES (:p, :r, :u, :d, :h)',
            ['p' => $plataforma, 'r' => $referenciaId, 'u' => $usuarioId, 'd' => $desde, 'h' => $hasta]
        );

        return $this->db->lastInsertId();
    }

    /** @return array<string,mixed>|null Importación con sus avisos acumulados (avisos: array clave => mensaje). */
    public function buscarImportacion(int $id): ?array
    {
        $row = $this->db->selectOne(
            'SELECT id, plataforma, referencia_id, rango_inicio, rango_fin, estado, dias_importados, avisos
               FROM importaciones_redes WHERE id = :id',
            ['id' => $id]
        );
        if ($row !== null) {
            $row['avisos'] = $row['avisos'] !== null ? (array) json_decode((string) $row['avisos'], true) : [];
        }

        return $row;
    }

    /**
     * Suma los días de un tramo y guarda los avisos acumulados.
     *
     * @param array<string,string> $avisos
     */
    public function registrarTramo(int $id, int $dias, int $llamadas, array $avisos): void
    {
        $this->db->execute(
            'UPDATE importaciones_redes
                SET dias_importados = dias_importados + :d, llamadas_meta = llamadas_meta + :l, avisos = :a
              WHERE id = :id',
            ['d' => $dias, 'l' => $llamadas, 'a' => $avisos === [] ? null : json_encode($avisos, JSON_UNESCAPED_UNICODE), 'id' => $id]
        );
    }

    /** Cierra la importación. Los días ya se fueron sumando por tramo. */
    public function finalizarImportacion(int $id, bool $exito, int $publicaciones, int $llamadas, ?string $mensaje): void
    {
        $this->db->execute(
            'UPDATE importaciones_redes
                SET estado = :e, finalizado_en = NOW(), publicaciones = :p,
                    llamadas_meta = llamadas_meta + :l, error_mensaje = :m
              WHERE id = :id',
            [
                'e' => $exito ? 'completada' : 'fallida', 'p' => $publicaciones, 'l' => $llamadas,
                'm' => $mensaje !== null ? mb_substr($mensaje, 0, 5000) : null, 'id' => $id,
            ]
        );
    }

    /** @return list<array<string,mixed>> */
    public function importacionesRecientes(int $limite = 15): array
    {
        return $this->db->select(
            "SELECT i.id, i.plataforma, i.rango_inicio, i.rango_fin, i.iniciado_en,
                    CASE WHEN i.estado = 'en_curso' AND i.iniciado_en < NOW() - INTERVAL 30 MINUTE
                         THEN 'interrumpida' ELSE i.estado END AS estado,
                    i.dias_importados, i.publicaciones, i.error_mensaje,
                    COALESCE(p.nombre, CONCAT('@', ig.username)) AS referencia_nombre
               FROM importaciones_redes i
          LEFT JOIN meta_paginas p ON i.plataforma = 'facebook' AND p.id = i.referencia_id
          LEFT JOIN meta_instagram_cuentas ig ON i.plataforma = 'instagram' AND ig.id = i.referencia_id
           ORDER BY i.iniciado_en DESC, i.id DESC
              LIMIT " . (int) $limite
        );
    }
}
