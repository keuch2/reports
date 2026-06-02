<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var array<string,mixed> $totales */
/** @var string $mon  Código de moneda (PYG, USD, etc.) */

$gasto = (float) ($totales['gasto'] ?? 0);
if ($gasto <= 0) {
    return;
}

$COMISION_PCT = 0.20;
$IVA_PCT = 0.10;

$comision = $gasto * $COMISION_PCT;
$subtotal = $gasto + $comision;
$iva = $subtotal * $IVA_PCT;
$total = $subtotal + $iva;

// PYG no usa decimales; redondeamos hacia arriba al guaraní entero.
$fmt = fn (float $v) => $mon === 'PYG'
    ? number_format(ceil($v), 0, ',', '.')
    : number_format($v, 2, ',', '.');
?>
<article class="card" style="margin-top:1.5rem">
    <h2>Costos del período</h2>
    <table class="table table--costos">
        <tbody>
            <tr>
                <th>Costo neto</th>
                <td class="num"><?= $view->e($mon) ?> <?= $fmt($gasto) ?></td>
            </tr>
            <tr>
                <th>Comisión agencia (20%)</th>
                <td class="num"><?= $view->e($mon) ?> <?= $fmt($comision) ?></td>
            </tr>
            <tr class="table__subtotal">
                <th>Subtotal sin IVA</th>
                <td class="num"><?= $view->e($mon) ?> <?= $fmt($subtotal) ?></td>
            </tr>
            <tr>
                <th>IVA (10%)</th>
                <td class="num"><?= $view->e($mon) ?> <?= $fmt($iva) ?></td>
            </tr>
            <tr class="table__total">
                <th>Total con IVA</th>
                <td class="num"><strong><?= $view->e($mon) ?> <?= $fmt($total) ?></strong></td>
            </tr>
        </tbody>
    </table>
</article>
