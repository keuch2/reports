<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Core\Database;
use MisterCo\Reports\Domain\Usuario;

/**
 * Registro cronológico de acciones sensibles. Append-only en `auditoria_eventos`.
 *
 * Convenciones de `accion`: dominio.verbo en snake_case
 *   auth.login_ok, auth.login_fallido, auth.logout, auth.password_recuperada
 *   meta.token_conectado, meta.token_desconectado
 *   importacion.iniciada, importacion.completada, importacion.fallida
 *   permisos.campanias_actualizados, permisos.anuncios_actualizados, permisos.metricas_actualizadas
 *   pdf.generado
 *   cliente.creado, cliente.cuenta_asignada, cliente.cuenta_desasignada
 */
final class AuditService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed>|null $detalles JSON con contexto adicional
     */
    public function registrar(
        string $accion,
        ?Usuario $usuario = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $recursoTipo = null,
        ?string $recursoId = null,
        ?array $detalles = null,
    ): void {
        $this->db->execute(
            'INSERT INTO auditoria_eventos
                (usuario_id, rol, ip, user_agent, accion, recurso_tipo, recurso_id, detalles)
             VALUES (:uid, :rol, :ip, :ua, :acc, :rt, :ri, :det)',
            [
                'uid' => $usuario?->id,
                'rol' => $usuario?->rol,
                'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
                'ua' => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
                'acc' => mb_substr($accion, 0, 100),
                'rt' => $recursoTipo !== null ? mb_substr($recursoTipo, 0, 50) : null,
                'ri' => $recursoId !== null ? mb_substr($recursoId, 0, 50) : null,
                'det' => $detalles === null ? null : json_encode($detalles, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * Listado con filtros.
     *
     * @param array{usuario_id?:int, accion?:string, rol?:string, desde?:string, hasta?:string, buscar?:string} $filtros
     * @return list<array<string, mixed>>
     */
    public function listar(array $filtros = [], int $limit = 100, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['usuario_id'])) {
            $where[] = 'ae.usuario_id = :uid';
            $params['uid'] = (int) $filtros['usuario_id'];
        }
        if (!empty($filtros['accion'])) {
            $where[] = 'ae.accion = :acc';
            $params['acc'] = (string) $filtros['accion'];
        }
        if (!empty($filtros['rol'])) {
            $where[] = 'ae.rol = :rol';
            $params['rol'] = (string) $filtros['rol'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 'ae.ocurrido_en >= :desde';
            $params['desde'] = (string) $filtros['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 'ae.ocurrido_en <= :hasta';
            $params['hasta'] = (string) $filtros['hasta'] . ' 23:59:59';
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(ae.accion LIKE :q OR ae.recurso_tipo LIKE :q OR ae.recurso_id LIKE :q OR ae.detalles LIKE :q)';
            $params['q'] = '%' . (string) $filtros['buscar'] . '%';
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        return $this->db->select(
            "SELECT ae.id, ae.usuario_id, ae.rol, ae.ip, ae.accion, ae.recurso_tipo, ae.recurso_id,
                    ae.detalles, ae.ocurrido_en, u.correo AS usuario_correo, u.nombre_completo AS usuario_nombre
               FROM auditoria_eventos ae
          LEFT JOIN usuarios u ON u.id = ae.usuario_id
              {$whereSql}
           ORDER BY ae.ocurrido_en DESC
              LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    /** @return list<string> Lista de acciones distintas para popular el filtro */
    public function accionesDistintas(): array
    {
        $rows = $this->db->select('SELECT DISTINCT accion FROM auditoria_eventos ORDER BY accion');

        return array_map(static fn ($r) => (string) $r['accion'], $rows);
    }
}
