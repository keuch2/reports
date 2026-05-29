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
     * Genera un PDF y retorna {ruta, nombre}.
     *
     * @return array{ruta:string, nombre:string, tamanio:int}
     */
    /**
     * @param list<string> $secciones Secciones a incluir (de PlantillaPdfRepository::SECCIONES_DISPONIBLES).
     *                                 Si vacío, se usa el set por defecto.
     */
    public function generar(
        int $clienteId,
        int $cuentaId,
        string $desde,
        string $hasta,
        int $generadoPorUsuarioId,
        array $secciones = [],
        ?string $comentarios = null,
        bool $marcaDeAgua = false,
    ): array {
        $cuenta = $this->buscarCuenta($cuentaId);
        $cliente = $this->buscarCliente($clienteId);

        if ($secciones === []) {
            $secciones = ['resumen_ejecutivo', 'tabla_campanias', 'evolucion_diaria'];
        }

        $totales = $this->dashboard->totalesPorCuenta($clienteId, $cuentaId, $desde, $hasta);
        $campanias = $this->dashboard->porCampania($clienteId, $cuentaId, $desde, $hasta);
        $evolucion = $this->dashboard->evolucionDiaria($clienteId, $cuentaId, $desde, $hasta);

        $html = $this->view->render('pdf/reporte', [
            'cliente' => $cliente,
            'cuenta' => $cuenta,
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

    /** @return array<string,mixed> */
    private function buscarCuenta(int $id): array
    {
        $row = $this->db->selectOne('SELECT id, nombre, meta_account_id, moneda FROM cuentas_publicitarias WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            throw new \RuntimeException("Cuenta {$id} no encontrada.");
        }

        return $row;
    }
}
