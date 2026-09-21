<?php

declare(strict_types=1);

namespace MisterCo\Reports\Domain;

use MisterCo\Reports\Core\Request;

/**
 * Período de un dashboard o reporte: un mes calendario con datos (YYYY-MM) o
 * un rango de fechas personalizado (desde/hasta).
 *
 * Regla única para dashboard, detalle de campaña y PDF (cliente y admin), así
 * el PDF SIEMPRE cubre el mismo período que la pantalla desde la que se exportó:
 *  1. Si llega un `mes` disponible, gana el mes calendario completo.
 *  2. Si no, y llegan `desde`/`hasta` válidos, se usa ese rango personalizado.
 *  3. Si no, el mes más reciente con datos (o el mes actual si no hay ninguno).
 */
final class PeriodoReporte
{
    private function __construct(
        public readonly string $desde,
        public readonly string $hasta,
        /** Mes seleccionado (YYYY-MM); null si es rango personalizado o no hay meses. */
        public readonly ?string $mes,
        public readonly bool $esRango,
    ) {
    }

    /** @param list<string> $mesesDisponibles YYYY-MM, del más reciente al más antiguo. */
    public static function desdeRequest(Request $request, array $mesesDisponibles): self
    {
        return self::resolver(
            (string) $request->input('mes', ''),
            (string) $request->input('desde', ''),
            (string) $request->input('hasta', ''),
            $mesesDisponibles,
        );
    }

    /** @param list<string> $mesesDisponibles */
    public static function resolver(string $mes, string $desde, string $hasta, array $mesesDisponibles): self
    {
        if ($mes !== '' && in_array($mes, $mesesDisponibles, true)) {
            return self::delMes($mes);
        }

        if (self::esFecha($desde) && self::esFecha($hasta)) {
            // Rango invertido: lo damos vuelta en lugar de devolver un reporte vacío.
            if ($desde > $hasta) {
                [$desde, $hasta] = [$hasta, $desde];
            }

            return new self($desde, $hasta, null, true);
        }

        if ($mesesDisponibles !== []) {
            return self::delMes($mesesDisponibles[0]);
        }

        return new self(date('Y-m-01'), date('Y-m-t'), null, false);
    }

    /** Query string que reproduce este período en otra pantalla (sin `?`). */
    public function query(): string
    {
        if ($this->esRango) {
            return http_build_query(['desde' => $this->desde, 'hasta' => $this->hasta]);
        }

        return $this->mes !== null ? http_build_query(['mes' => $this->mes]) : '';
    }

    private static function delMes(string $yyyymm): self
    {
        $ts = strtotime($yyyymm . '-01');

        return new self(date('Y-m-01', $ts), date('Y-m-t', $ts), $yyyymm, false);
    }

    private static function esFecha(string $valor): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
