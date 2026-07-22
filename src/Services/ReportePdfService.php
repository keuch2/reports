<?php

declare(strict_types=1);

namespace MisterCo\Reports\Services;

use Mpdf\Mpdf;
use MisterCo\Reports\Core\Database;
use MisterCo\Reports\Core\View;

final class ReportePdfService
{
    public function __construct(
        private readonly View $view,
        private readonly DashboardService $dashboard,
        private readonly Database $db,
        private readonly string $storagePath,
    ) {
    }

    /**
     * Genera un PDF agregado sobre TODAS las campañas asignadas al cliente.
     *
     * @param list<string> $secciones Secciones a incluir (de PlantillaPdfRepository::SECCIONES_DISPONIBLES).
     *                                 Si vacío, usa el set por defecto.
     * @return array{ruta:string, nombre:string, tamanio:int}
     */
    public function generar(
        int $clienteId,
        string $desde,
        string $hasta,
        int $generadoPorUsuarioId,
        array $secciones = [],
        ?string $comentarios = null,
        bool $marcaDeAgua = false,
    ): array {
        $cliente = $this->buscarCliente($clienteId);

        if ($secciones === []) {
            $secciones = ['resumen_ejecutivo', 'resultados_por_tipo', 'tabla_campanias', 'evolucion_diaria', 'costos'];
        }

        // Mismas fuentes de datos que el dashboard (DashboardController::index)
        // para garantizar números idénticos: mismo rango → mismas consultas.
        $totales = $this->dashboard->totalesGlobales($clienteId, $desde, $hasta);
        $campanias = $this->dashboard->porCampania($clienteId, $desde, $hasta);
        $evolucion = $this->dashboard->evolucionDiaria($clienteId, $desde, $hasta);
        $resultadosPorTipo = $this->dashboard->resultadosPorTipoGlobal($clienteId, $desde, $hasta);

        // La moneda del PDF es la de la primera campaña asignada (asumimos consistencia).
        $monedaInfo = $this->db->selectOne(
            'SELECT cp.moneda
               FROM permisos_cliente_campania pccam
               JOIN campanias c ON c.id = pccam.campania_id
               JOIN cuentas_publicitarias cp ON cp.id = c.cuenta_publicitaria_id
              WHERE pccam.cliente_id = :c
              LIMIT 1',
            ['c' => $clienteId]
        );
        $moneda = (string) ($monedaInfo['moneda'] ?? '');

        $html = $this->view->render('pdf/reporte', [
            'cliente' => $cliente,
            'cuenta' => ['nombre' => 'Campañas asignadas', 'moneda' => $moneda],
            'desde' => $desde,
            'hasta' => $hasta,
            'totales' => $totales,
            'campanias' => $campanias,
            'evolucion' => $evolucion,
            'resultados_por_tipo' => $resultadosPorTipo,
            'secciones' => $secciones,
            'comentarios' => $comentarios,
            'generado_en' => date('Y-m-d H:i'),
        ], layout: null);

        $nombre = sprintf(
            'reporte_%d_%s_%s_a_%s.pdf',
            $clienteId,
            preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $cliente['nombre_comercial'])),
            $desde,
            $hasta
        );

        return $this->renderizarPdf(
            $html, $nombre, (string) $cliente['nombre_comercial'], $desde, $hasta,
            $clienteId, $generadoPorUsuarioId, $comentarios, $marcaDeAgua
        );
    }

    /**
     * Genera un PDF de UNA campaña, reflejando exactamente la pantalla de
     * detalle de campaña (CampaniaController::detalle): mismos totales, mismos
     * resultados por tipo, misma tabla de grupos de anuncios y misma evolución.
     *
     * @return array{ruta:string, nombre:string, tamanio:int}
     */
    public function generarCampania(
        int $clienteId,
        int $campaniaId,
        string $desde,
        string $hasta,
        int $generadoPorUsuarioId,
        ?string $comentarios = null,
        bool $marcaDeAgua = false,
    ): array {
        $cliente = $this->buscarCliente($clienteId);

        $campania = $this->db->selectOne(
            "SELECT c.id, c.nombre, c.objetivo, c.estado,
                    cp.nombre AS cuenta_nombre, cp.moneda,
                    -- optimization_goal predominante con la MISMA prioridad que el
                    -- CASE de resultados de DashboardService::totalesCampania, para
                    -- que el label del PDF cuente lo mismo que el número (ver
                    -- EntidadesMetaRepository::optimizationGoalPredominante).
                    (SELECT cs.optimization_goal
                       FROM conjuntos_anuncios cs
                  LEFT JOIN anuncios a ON a.conjunto_anuncios_id = cs.id
                  LEFT JOIN metricas_snapshots ms ON ms.entidad_id = a.id AND ms.nivel = 'ad'
                      WHERE cs.campania_id = c.id AND cs.optimization_goal IS NOT NULL
                   GROUP BY cs.optimization_goal
                   ORDER BY CASE
                                WHEN cs.optimization_goal IN ('CONVERSATIONS','REPLIES') THEN 1
                                WHEN cs.optimization_goal IN ('LEAD_GENERATION','QUALITY_LEAD','LEAD') THEN 2
                                WHEN cs.optimization_goal IN ('POST_ENGAGEMENT','PAGE_LIKES','EVENT_RESPONSES') THEN 3
                                WHEN cs.optimization_goal IN ('REACH','IMPRESSIONS','AD_RECALL_LIFT') THEN 9
                                ELSE 5
                            END ASC,
                            COALESCE(SUM(ms.gasto), 0) DESC,
                            COUNT(DISTINCT cs.id) DESC
                      LIMIT 1) AS optimization_goal_predominante
               FROM campanias c
               JOIN cuentas_publicitarias cp ON cp.id = c.cuenta_publicitaria_id
              WHERE c.id = :id",
            ['id' => $campaniaId]
        );
        if ($campania === null) {
            throw new \RuntimeException("Campaña {$campaniaId} no encontrada.");
        }

        // Mismas fuentes que CampaniaController::detalle.
        $totales = $this->dashboard->totalesCampania($clienteId, $campaniaId, $desde, $hasta);
        $adsets = $this->dashboard->adsetsDeCampaniaConMetricas($clienteId, $campaniaId, $desde, $hasta);
        $resultadosPorTipo = $this->dashboard->resultadosPorTipoCampania($clienteId, $campaniaId, $desde, $hasta);
        $evolucion = $this->dashboard->evolucionDiariaCampania($clienteId, $campaniaId, $desde, $hasta);

        $html = $this->view->render('pdf/reporte_campania', [
            'cliente' => $cliente,
            'campania' => $campania,
            'desde' => $desde,
            'hasta' => $hasta,
            'totales' => $totales,
            'adsets' => $adsets,
            'resultados_por_tipo' => $resultadosPorTipo,
            'evolucion' => $evolucion,
            'comentarios' => $comentarios,
            'generado_en' => date('Y-m-d H:i'),
        ], layout: null);

        $nombre = sprintf(
            'reporte_campania_%d_%s_%s_a_%s.pdf',
            $campaniaId,
            preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $campania['nombre'])),
            $desde,
            $hasta
        );

        return $this->renderizarPdf(
            $html, $nombre, (string) $cliente['nombre_comercial'], $desde, $hasta,
            $clienteId, $generadoPorUsuarioId, $comentarios, $marcaDeAgua
        );
    }

    /**
     * Renderiza el HTML a PDF, lo guarda en disco y registra en el histórico.
     * Compartido por el reporte global y el de campaña.
     *
     * @return array{ruta:string, nombre:string, tamanio:int}
     */
    private function renderizarPdf(
        string $html,
        string $nombre,
        string $nombreComercial,
        string $desde,
        string $hasta,
        int $clienteId,
        int $generadoPorUsuarioId,
        ?string $comentarios,
        bool $marcaDeAgua,
    ): array {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 18,
            'tempDir' => $this->storagePath . '/../cache',
        ]);

        $mpdf->SetTitle('Reporte ' . $nombreComercial . ' · ' . $desde . ' al ' . $hasta);
        $mpdf->SetAuthor('Mister Co.');
        $mpdf->SetCreator('Mister Co. Reports');
        $mpdf->SetHTMLFooter('<div style="text-align:center;color:#999;font-size:9pt">Mister Co. · mister.com.py · Página {PAGENO}/{nbpg}</div>');

        if ($marcaDeAgua) {
            $mpdf->SetWatermarkText('CONFIDENCIAL — ' . mb_strtoupper($nombreComercial));
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = 'DejaVuSans';
            $mpdf->watermarkTextAlpha = 0.08;
        }

        $mpdf->WriteHTML($html);

        $ruta = $this->storagePath . '/' . $nombre;
        $mpdf->Output($ruta, \Mpdf\Output\Destination::FILE);

        $tamanio = (int) filesize($ruta);

        $this->db->execute(
            'INSERT INTO reportes_pdf_historico
                (cliente_id, generado_por_usuario_id, rango_inicio, rango_fin, archivo_ruta, archivo_tamanio, comentarios)
             VALUES (:c, :u, :ri, :rf, :a, :s, :com)',
            [
                'c' => $clienteId, 'u' => $generadoPorUsuarioId, 'ri' => $desde, 'rf' => $hasta,
                'a' => $ruta, 's' => $tamanio,
                'com' => $comentarios !== null && $comentarios !== '' ? json_encode(['texto' => $comentarios]) : null,
            ]
        );

        return ['ruta' => $ruta, 'nombre' => $nombre, 'tamanio' => $tamanio];
    }

    /** @return array<string,mixed> */
    private function buscarCliente(int $id): array
    {
        $row = $this->db->selectOne('SELECT id, nombre_comercial, correo_contacto FROM clientes WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            throw new \RuntimeException("Cliente {$id} no encontrado.");
        }

        return $row;
    }
}
