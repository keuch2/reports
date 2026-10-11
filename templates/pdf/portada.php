<?php
/**
 * Estilos y portada compartidos por los PDF de Google y el comparativo
 * (mismo look que templates/pdf/reporte.php).
 *
 * @var \MisterCo\Reports\Core\View $view
 * @var array<string,mixed> $cliente
 * @var string $subtitulo
 * @var string $detalle
 * @var string $desde
 * @var string $hasta
 * @var string $generado_en
 */
?>
<style>
    body { font-family: sans-serif; color: #1a1d24; font-size: 10pt; }
    h1 { font-size: 20pt; margin: 0 0 4pt; color: #1f3a8a; }
    h2 { font-size: 13pt; margin: 18pt 0 6pt; color: #1f3a8a; border-bottom: 1pt solid #e5e7eb; padding-bottom: 4pt; }
    .portada { padding-top: 80pt; text-align: center; }
    .portada h1 { font-size: 28pt; }
    .portada .sub { font-size: 14pt; color: #6b7280; margin-top: 8pt; }
    .portada .rango { margin-top: 24pt; font-size: 12pt; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
    th, td { padding: 5pt 6pt; border-bottom: 0.5pt solid #e5e7eb; text-align: left; font-size: 9pt; }
    th { background: #f5f6f8; font-weight: 600; }
    td.num, th.num { text-align: right; }
    .kpi-row { width: 100%; margin-top: 8pt; }
    .kpi-row td { width: 33%; padding: 8pt; border: 0.5pt solid #e5e7eb; background: #f9fafb; vertical-align: top; }
    .kpi-label { font-size: 8pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-value { font-size: 16pt; font-weight: 700; margin-top: 2pt; }
    .kpi-sub { font-size: 8pt; color: #6b7280; margin-top: 2pt; }
    .costos td, .costos th { font-size: 10pt; }
    .costos .total td, .costos .total th { font-weight: 700; border-top: 1pt solid #1f3a8a; }
</style>

<div class="portada">
    <h1>Mister Co.</h1>
    <p class="sub"><?= $view->e($subtitulo) ?></p>
    <h2 style="border:none;margin-top:24pt;color:#1a1d24"><?= $view->e((string) $cliente['nombre_comercial']) ?></h2>
    <p class="muted"><?= $view->e($detalle) ?></p>
    <p class="rango"><strong>Del <?= $view->e($desde) ?> al <?= $view->e($hasta) ?></strong></p>
    <p class="muted" style="margin-top:60pt">Generado el <?= $view->e($generado_en) ?></p>
</div>

<pagebreak/>
