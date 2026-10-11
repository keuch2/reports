<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Domain\ObjetivoCampania;
use MisterCo\Reports\Domain\PeriodoReporte;
use MisterCo\Reports\Repositories\EntidadesMetaRepository;
use MisterCo\Reports\Repositories\GoogleAdsRepository;

/**
 * Reporte comparativo: dos "lados", cada uno una campaña (Meta o Google Ads)
 * en un rango de fechas. Sirve tanto para la misma campaña en dos períodos
 * como para dos campañas distintas.
 *
 * Los totales salen de las mismas consultas que el detalle de campaña
 * (DashboardService / GoogleAdsRepository), así los números coinciden.
 * Las campañas se identifican con una clave "meta:{id}" o "gads:{id}" y solo
 * se aceptan las asignadas al cliente.
 */
final class ComparativoService
{
    /**
     * Métricas comparadas: [etiqueta, formato, menor es mejor].
     * `null` en la tercera posición = sin juicio (más inversión no es mejor ni peor).
     */
    private const METRICAS = [
        'gasto' => ['Inversión', 'moneda', null],
        'impresiones' => ['Impresiones', 'num', false],
        'alcance' => ['Alcance', 'num', false],
        'clicks' => ['Clics', 'num', false],
        'ctr' => ['CTR', 'pct', false],
        'cpc' => ['CPC', 'moneda', true],
        'cpm' => ['CPM', 'moneda', true],
        'resultados' => ['Resultados', 'dec', false],
        'costo_por_resultado' => ['Costo por resultado', 'moneda', true],
    ];

    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly EntidadesMetaRepository $entidades,
        private readonly GoogleAdsRepository $googleAds,
    ) {
    }

    /**
     * Campañas que el cliente puede comparar, indexadas por clave.
     *
     * @return array<string, array{clave:string, nombre:string, plataforma:string, cuenta:string, moneda:string}>
     */
    public function campaniasDisponibles(int $clienteId): array
    {
        $opciones = [];
        foreach ($this->dashboard->campaniasDelCliente($clienteId) as $c) {
            $clave = 'meta:' . (int) $c['id'];
            $opciones[$clave] = [
                'clave' => $clave, 'nombre' => (string) $c['campania'], 'plataforma' => 'Meta Ads',
                'cuenta' => (string) $c['cuenta_nombre'], 'moneda' => (string) ($c['moneda'] ?? ''),
            ];
        }
        foreach ($this->googleAds->campaniasDelCliente($clienteId) as $c) {
            $clave = 'gads:' . (int) $c['id'];
            $opciones[$clave] = [
                'clave' => $clave, 'nombre' => (string) $c['campania'], 'plataforma' => 'Google Ads',
                'cuenta' => (string) $c['cuenta_nombre'], 'moneda' => (string) ($c['moneda'] ?? ''),
            ];
        }

        return $opciones;
    }

    /**
     * Compara dos lados. Devuelve null si alguna campaña no es del cliente.
     *
     * @param array{clave:string, desde:string, hasta:string} $specA
     * @param array{clave:string, desde:string, hasta:string} $specB
     * @return array{a: array<string,mixed>, b: array<string,mixed>, filas: list<array<string,mixed>>, misma_moneda: bool}|null
     */
    public function comparar(int $clienteId, array $specA, array $specB): ?array
    {
        $disponibles = $this->campaniasDisponibles($clienteId);
        $a = $this->lado($clienteId, $disponibles, $specA);
        $b = $this->lado($clienteId, $disponibles, $specB);
        if ($a === null || $b === null) {
            return null;
        }

        $filas = [];
        foreach (self::METRICAS as $clave => [$etiqueta, $formato, $menorEsMejor]) {
            $va = $a['metricas'][$clave];
            $vb = $b['metricas'][$clave];
            // Alcance solo existe en Meta: si un lado no lo tiene, no se compara.
            if ($clave === 'alcance' && ($va === null || $vb === null)) {
                continue;
            }
            if ($clave === 'resultados' && $a['label_resultado'] === $b['label_resultado']) {
                $etiqueta = $a['label_resultado'];
            }
            $filas[] = [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'formato' => $formato,
                'a' => $va,
                'b' => $vb,
                'variacion' => self::variacion($va, $vb),
                'mejor' => self::mejor($va, $vb, $menorEsMejor),
            ];
        }

        return ['a' => $a, 'b' => $b, 'filas' => $filas, 'misma_moneda' => $a['moneda'] === $b['moneda']];
    }

    /** Variación porcentual de B respecto de A (A es la referencia). */
    public static function variacion(?float $a, ?float $b): ?float
    {
        if ($a === null || $b === null || $a == 0.0) {
            return null;
        }

        return ($b - $a) / abs($a) * 100;
    }

    /** @return 'a'|'b'|null Lado con mejor valor, o null si no aplica o empatan. */
    private static function mejor(?float $a, ?float $b, ?bool $menorEsMejor): ?string
    {
        if ($menorEsMejor === null || $a === null || $b === null || $a == $b) {
            return null;
        }

        return ($menorEsMejor ? $a < $b : $a > $b) ? 'a' : 'b';
    }

    /**
     * @param array<string, array<string,string>> $disponibles
     * @param array{clave:string, desde:string, hasta:string} $spec
     * @return array<string,mixed>|null
     */
    private function lado(int $clienteId, array $disponibles, array $spec): ?array
    {
        $opcion = $disponibles[$spec['clave']] ?? null;
        if ($opcion === null) {
            return null;
        }
        $periodo = PeriodoReporte::resolver('', $spec['desde'], $spec['hasta'], []);
        [$plataforma, $id] = explode(':', $spec['clave']);
        $id = (int) $id;

        if ($plataforma === 'meta') {
            $t = $this->dashboard->totalesCampania($clienteId, $id, $periodo->desde, $periodo->hasta);
            $campania = $this->entidades->buscarCampania($id);
            $label = ObjetivoCampania::nombreResultadosEfectivo(
                $this->entidades->optimizationGoalPredominante($id),
                $campania['objetivo'] ?? null
            );
            $metricas = [
                'gasto' => (float) $t['gasto'],
                'impresiones' => (float) $t['impresiones'],
                'alcance' => (float) $t['alcance'],
                'clicks' => (float) $t['clicks'],
                'ctr' => self::nullableFloat($t['ctr']),
                'cpc' => self::nullableFloat($t['cpc']),
                'cpm' => self::nullableFloat($t['cpm']),
                'resultados' => (float) $t['resultados'],
                'costo_por_resultado' => self::nullableFloat($t['costo_por_resultado']),
            ];
            $serie = $this->dashboard->evolucionDiariaCampania($clienteId, $id, $periodo->desde, $periodo->hasta);
        } else {
            $t = $this->googleAds->totalesCampania($clienteId, $id, $periodo->desde, $periodo->hasta);
            $label = 'Conversiones';
            $metricas = [
                'gasto' => $t['gasto'],
                'impresiones' => (float) $t['impresiones'],
                'alcance' => null,
                'clicks' => (float) $t['clicks'],
                'ctr' => $t['ctr'],
                'cpc' => $t['cpc'],
                'cpm' => $t['cpm'],
                'resultados' => $t['conversiones'],
                'costo_por_resultado' => $t['costo_por_conversion'],
            ];
            $serie = array_map(
                static fn ($f) => ['fecha' => $f['fecha'], 'gasto' => $f['gasto'], 'resultados' => $f['conversiones']],
                $this->googleAds->evolucionCampania($clienteId, $id, $periodo->desde, $periodo->hasta)
            );
        }

        return $opcion + [
            'desde' => $periodo->desde,
            'hasta' => $periodo->hasta,
            'dias' => (int) ((strtotime($periodo->hasta) - strtotime($periodo->desde)) / 86400) + 1,
            'label_resultado' => $label,
            'metricas' => $metricas,
            'evolucion' => self::serieCompleta($serie, $periodo->desde, $periodo->hasta),
        ];
    }

    /**
     * Serie con todos los días del rango (los sin datos en 0), numerados desde
     * el día 1 para poder superponer dos períodos de distinto calendario.
     *
     * @param list<array<string,mixed>> $serie
     * @return list<array{dia:int, fecha:string, gasto:float, resultados:float}>
     */
    private static function serieCompleta(array $serie, string $desde, string $hasta): array
    {
        $porFecha = [];
        foreach ($serie as $f) {
            $porFecha[(string) $f['fecha']] = $f;
        }
        $completa = [];
        $dia = 1;
        for ($t = strtotime($desde); $t <= strtotime($hasta); $t = strtotime('+1 day', $t)) {
            $fecha = date('Y-m-d', $t);
            $completa[] = [
                'dia' => $dia++,
                'fecha' => $fecha,
                'gasto' => (float) ($porFecha[$fecha]['gasto'] ?? 0),
                'resultados' => (float) ($porFecha[$fecha]['resultados'] ?? 0),
            ];
        }

        return $completa;
    }

    private static function nullableFloat(mixed $v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}
