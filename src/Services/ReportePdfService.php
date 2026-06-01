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
            $secciones = ['resumen_ejecutivo', 'tabla_campanias', 'evolucion_diaria'];
        }

        $totales = $this->dashboard->totalesGlobales($clienteId, $desde, $hasta);
        $campanias = $this->dashboard->porCampania($clienteId, $desde, $hasta);
        $evolucion = $this->dashboard->evolucionDiaria($clienteId, $desde, $hasta);

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
            'secciones' => $secciones,
            'comentarios' => $comentarios,
            'generado_en' => date('Y-m-d H:i'),
        ], layout: null);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 18,
            'tempDir' => $this->storagePath . '/../cache',
        ]);

        $mpdf->SetTitle('Reporte ' . $cliente['nombre_comercial'] . ' · ' . $desde . ' al ' . $hasta);
        $mpdf->SetAuthor('Mister Co.');
        $mpdf->SetCreator('Mister Co. Reports');
        $mpdf->SetHTMLFooter('<div style="text-align:center;color:#999;font-size:9pt">Mister Co. · mister.com.py · Página {PAGENO}/{nbpg}</div>');

        if ($marcaDeAgua) {
            $mpdf->SetWatermarkText('CONFIDENCIAL — ' . mb_strtoupper((string) $cliente['nombre_comercial']));
            $mpdf->showWatermarkText = true;
            $mpdf->watermark_font = 'DejaVuSans';
            $mpdf->watermarkTextAlpha = 0.08;
        }

        $mpdf->WriteHTML($html);

        $nombre = sprintf(
            'reporte_%d_%s_%s_a_%s.pdf',
            $clienteId,
            preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) $cliente['nombre_comercial'])),
            $desde,
            $hasta
        );
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
