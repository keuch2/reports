<?php
/** @var \MisterCo\Reports\Core\View $view */
/** @var list<array{tipo:string, cantidad:int, gasto:float, costo:?float}> $resultados_por_tipo */
/** @var string $mon */
/** @var \Closure $fmtMoneda */
/** @var \Closure $fmtNum */

if (empty($resultados_por_tipo)) {
    return;
}

$labelTipo = [
    'conversaciones' => ['plural' => 'Conversaciones WhatsApp', 'singular' => 'conversación'],
    'leads' => ['plural' => 'Clientes potenciales', 'singular' => 'cliente potencial'],
    'interacciones' => ['plural' => 'Interacciones', 'singular' => 'interacción'],
    'visitas' => ['plural' => 'Visitas a destino', 'singular' => 'visita'],
];
?>
<div class="kpi-grid kpi-grid--resultados" style="margin-top:0.75rem">
    <?php foreach ($resultados_por_tipo as $r):
        $info = $labelTipo[$r['tipo']] ?? ['plural' => ucfirst($r['tipo']), 'singular' => $r['tipo']];
    ?>
        <div class="kpi kpi--resultado">
            <span class="kpi__label"><?= $view->e($info['plural']) ?></span>
            <span class="kpi__value"><?= $fmtNum($r['cantidad']) ?></span>
            <?php if ($r['costo'] !== null): ?>
                <span class="kpi__sub muted">
                    <?= $view->e($mon) ?> <?= $fmtMoneda($r['costo']) ?> por <?= $view->e($info['singular']) ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
