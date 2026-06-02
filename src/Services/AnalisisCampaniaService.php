<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use MisterCo\Reports\Domain\ObjetivoCampania;

/**
 * Genera un párrafo en español describiendo el rendimiento de una campaña
 * a partir de sus métricas. Sin LLM: reglas determinísticas para que el
 * texto sea siempre consistente con los números mostrados.
 */
final class AnalisisCampaniaService
{
    /**
     * @param array<string,mixed> $totales   métricas del rango actual
     * @param array<string,mixed> $campania  fila con `objetivo`, `optimization_goal` agregado, etc.
     * @param array<string,mixed>|null $previos métricas del período anterior comparable (mismo nro de días), opcional
     */
    public function generar(
        array $totales,
        array $campania,
        ?array $previos,
        string $moneda,
        string $desde,
        string $hasta,
    ): string {
        $gasto = (float) ($totales['gasto'] ?? 0);
        $impresiones = (int) ($totales['impresiones'] ?? 0);
        $resultados = (int) ($totales['resultados'] ?? 0);
        $costoResultado = isset($totales['costo_por_resultado']) ? (float) $totales['costo_por_resultado'] : null;
        $ctr = isset($totales['ctr']) ? (float) $totales['ctr'] : null;

        $objetivo = (string) ($campania['objetivo'] ?? '');
        $optGoal = (string) ($campania['optimization_goal_predominante'] ?? '');
        $labelOrigen = $optGoal !== '' ? $optGoal : $objetivo;
        $labelResultado = mb_strtolower(ObjetivoCampania::nombreCortoResultados($labelOrigen));

        if ($gasto <= 0 && $impresiones <= 0) {
            return sprintf(
                'Entre el %s y el %s no se registra inversión ni impresiones en esta campaña. Es probable que haya estado pausada o sin presupuesto disponible durante el período.',
                $this->fechaHumana($desde),
                $this->fechaHumana($hasta),
            );
        }

        $fragmentos = [];

        $fragmentos[] = sprintf(
            'Entre el %s y el %s la campaña invirtió %s y generó %s impresiones',
            $this->fechaHumana($desde),
            $this->fechaHumana($hasta),
            $this->formatoMoneda($moneda, $gasto),
            $this->formatoNumero($impresiones),
        );

        if ($resultados > 0) {
            $fragmentos[count($fragmentos) - 1] .= sprintf(
                ', alcanzando %s %s',
                $this->formatoNumero($resultados),
                $this->pluralizar($resultados, $labelResultado),
            );
            if ($costoResultado !== null && $costoResultado > 0) {
                $singular = $this->singularizar($labelResultado);
                $fragmentos[count($fragmentos) - 1] .= sprintf(
                    ' a un costo promedio de %s por %s',
                    $this->formatoMoneda($moneda, $costoResultado),
                    $singular,
                );
            }
        } else {
            $fragmentos[count($fragmentos) - 1] .= ', sin resultados registrados todavía para el objetivo de la campaña';
        }

        $fragmentos[count($fragmentos) - 1] .= '.';

        // CTR
        if ($ctr !== null && $impresiones > 100) {
            $juicio = $this->juicioCtr($ctr);
            $fragmentos[] = sprintf(
                'El CTR fue de %s%%, considerado %s para este tipo de campaña',
                number_format($ctr, 2, ',', '.'),
                $juicio,
            );
            $fragmentos[count($fragmentos) - 1] .= '.';
        }

        // Tendencia vs período anterior, solo si hay datos comparables.
        if ($previos !== null && (float) ($previos['gasto'] ?? 0) > 0 && $resultados > 0) {
            $costoPrevio = isset($previos['costo_por_resultado']) ? (float) $previos['costo_por_resultado'] : null;
            $resultadosPrevios = (int) ($previos['resultados'] ?? 0);
            if ($costoPrevio !== null && $costoPrevio > 0 && $costoResultado !== null) {
                $varCosto = ($costoResultado - $costoPrevio) / $costoPrevio * 100;
                if (abs($varCosto) >= 5) {
                    $direccion = $varCosto < 0 ? 'bajó' : 'subió';
                    $impacto = $varCosto < 0 ? 'mejorando la eficiencia' : 'encareciendo cada resultado';
                    $fragmentos[] = sprintf(
                        'Comparado con el período anterior, el costo por %s %s un %s%%, %s',
                        $this->singularizar($labelResultado),
                        $direccion,
                        number_format(abs($varCosto), 1, ',', '.'),
                        $impacto,
                    );
                    $fragmentos[count($fragmentos) - 1] .= '.';
                }
            } elseif ($resultadosPrevios > 0) {
                $varRes = ($resultados - $resultadosPrevios) / $resultadosPrevios * 100;
                if (abs($varRes) >= 10) {
                    $direccion = $varRes > 0 ? 'aumentó' : 'cayó';
                    $fragmentos[] = sprintf(
                        'La cantidad de %s %s un %s%% respecto al período anterior',
                        $this->pluralizar(2, $labelResultado),
                        $direccion,
                        number_format(abs($varRes), 1, ',', '.'),
                    );
                    $fragmentos[count($fragmentos) - 1] .= '.';
                }
            }
        }

        return implode(' ', $fragmentos);
    }

    /**
     * Calcula el rango previo equivalente (mismo número de días, terminando
     * el día anterior al inicio del rango actual). Devuelve [desde, hasta].
     *
     * @return array{0:string,1:string}
     */
    public function rangoAnterior(string $desde, string $hasta): array
    {
        $tsDesde = strtotime($desde);
        $tsHasta = strtotime($hasta);
        if ($tsDesde === false || $tsHasta === false || $tsHasta < $tsDesde) {
            return [$desde, $hasta];
        }
        $duracion = (int) (($tsHasta - $tsDesde) / 86400) + 1;
        $prevHasta = date('Y-m-d', strtotime("-1 day", $tsDesde));
        $prevDesde = date('Y-m-d', strtotime("-{$duracion} days", $tsDesde));

        return [$prevDesde, $prevHasta];
    }

    private function juicioCtr(float $ctr): string
    {
        return match (true) {
            $ctr >= 2.5 => 'muy bueno',
            $ctr >= 1.2 => 'bueno',
            $ctr >= 0.7 => 'aceptable',
            default => 'bajo',
        };
    }

    private function formatoMoneda(string $moneda, float $valor): string
    {
        return trim($moneda . ' ' . number_format($valor, 2, ',', '.'));
    }

    private function formatoNumero(int|float $valor): string
    {
        return number_format((float) $valor, 0, ',', '.');
    }

    private function fechaHumana(string $ymd): string
    {
        $ts = strtotime($ymd);
        if ($ts === false) {
            return $ymd;
        }
        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return (int) date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    }

    private function pluralizar(int $n, string $palabra): string
    {
        if ($n === 1) {
            return $this->singularizar($palabra);
        }
        return $palabra;
    }

    private function singularizar(string $palabra): string
    {
        $mapa = [
            'conversaciones iniciadas' => 'conversación iniciada',
            'conversaciones' => 'conversación',
            'clientes potenciales' => 'cliente potencial',
            'visitas a destino' => 'visita a destino',
            'clicks al enlace' => 'click al enlace',
            'reproducciones de video' => 'reproducción de video',
            'interacciones' => 'interacción',
            'compras' => 'compra',
            'conversiones' => 'conversión',
            'resultados' => 'resultado',
        ];
        return $mapa[mb_strtolower($palabra)] ?? $palabra;
    }
}
