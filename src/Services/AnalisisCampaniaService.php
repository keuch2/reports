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
     * @param list<array{tipo:string, cantidad:int, gasto:float, costo:?float}> $resultadosPorTipo
     *        Desglose por tipo de objetivo. Si trae 2+ entradas, el párrafo
     *        las enumera en lugar de usar solo el "resultado principal".
     */
    public function generar(
        array $totales,
        array $campania,
        ?array $previos,
        string $moneda,
        string $desde,
        string $hasta,
        array $resultadosPorTipo = [],
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

        // Si hay desglose por tipo con 2+ entradas, enumeramos todos los tipos
        // (caso típico: cliente con campañas de leads + WhatsApp + interacciones).
        if (count($resultadosPorTipo) >= 2) {
            $piezas = [];
            foreach ($resultadosPorTipo as $r) {
                $label = $this->labelTipoPlural((string) $r['tipo']);
                $piezas[] = $this->formatoNumero((int) $r['cantidad']) . ' ' . $label;
            }
            $fragmentos[count($fragmentos) - 1] .= ', alcanzando ' . $this->enumerar($piezas);
        } elseif ($resultados > 0) {
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

        // CTR: solo lo destacamos cuando es positivo. Como agencia evitamos
        // resaltar números bajos; si el CTR no es bueno simplemente lo omitimos.
        if ($ctr !== null && $impresiones > 100) {
            $elogio = $this->elogioCtr($ctr);
            if ($elogio !== null) {
                $fragmentos[] = sprintf(
                    'El CTR alcanzó %s%%, un nivel %s para este tipo de campaña',
                    number_format($ctr, 2, ',', '.'),
                    $elogio,
                );
                $fragmentos[count($fragmentos) - 1] .= '.';
            }
        }

        // Tendencia vs período anterior: solo mencionamos mejoras. Si el período
        // empeoró, lo omitimos (no resaltamos cosas negativas al cliente).
        if ($previos !== null && (float) ($previos['gasto'] ?? 0) > 0 && $resultados > 0) {
            $costoPrevio = isset($previos['costo_por_resultado']) ? (float) $previos['costo_por_resultado'] : null;
            $resultadosPrevios = (int) ($previos['resultados'] ?? 0);
            if ($costoPrevio !== null && $costoPrevio > 0 && $costoResultado !== null) {
                $varCosto = ($costoResultado - $costoPrevio) / $costoPrevio * 100;
                if ($varCosto <= -5) {
                    $fragmentos[] = sprintf(
                        'El costo por %s mejoró un %s%% respecto al período anterior, optimizando la inversión',
                        $this->singularizar($labelResultado),
                        number_format(abs($varCosto), 1, ',', '.'),
                    );
                    $fragmentos[count($fragmentos) - 1] .= '.';
                }
            }
            if ($resultadosPrevios > 0) {
                $varRes = ($resultados - $resultadosPrevios) / $resultadosPrevios * 100;
                if ($varRes >= 10) {
                    $fragmentos[] = sprintf(
                        'La cantidad de %s creció un %s%% respecto al período anterior',
                        $this->pluralizar(2, $labelResultado),
                        number_format($varRes, 1, ',', '.'),
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

    /**
     * Elogio del CTR solo cuando da espacio para resaltarlo positivamente.
     * Si está por debajo del umbral, devuelve null y se omite la frase.
     */
    private function elogioCtr(float $ctr): ?string
    {
        return match (true) {
            $ctr >= 2.5 => 'muy destacado',
            $ctr >= 1.2 => 'sólido',
            $ctr >= 0.7 => 'positivo',
            default => null,
        };
    }

    private function formatoMoneda(string $moneda, float $valor): string
    {
        // PYG no usa decimales; redondeamos hacia arriba al guaraní entero.
        $numero = $moneda === 'PYG'
            ? number_format(ceil($valor), 0, ',', '.')
            : number_format($valor, 2, ',', '.');
        return trim($moneda . ' ' . $numero);
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

    private function labelTipoPlural(string $tipo): string
    {
        return match ($tipo) {
            'conversaciones' => 'conversaciones de WhatsApp',
            'leads' => 'clientes potenciales',
            'interacciones' => 'interacciones',
            'visitas' => 'visitas al destino',
            default => $tipo,
        };
    }

    /**
     * Une elementos como "a, b y c" (estilo español). Con un solo elemento
     * devuelve ese elemento; con vacío devuelve string vacío.
     *
     * @param list<string> $items
     */
    private function enumerar(array $items): string
    {
        $n = count($items);
        if ($n === 0) return '';
        if ($n === 1) return $items[0];
        $ultimo = array_pop($items);
        return implode(', ', $items) . ' y ' . $ultimo;
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
