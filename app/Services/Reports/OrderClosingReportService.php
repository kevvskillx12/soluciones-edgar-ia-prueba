<?php

namespace App\Services\Reports;

use App\Services\AI\OllamaReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class OrderClosingReportService
{
    protected OllamaReportService $aiService;

    public function __construct(OllamaReportService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Genera un reporte en texto plano de los pedidos seleccionados.
     * Guarda el archivo en storage/app/public/reports y devuelve información.
     *
     * @param Collection $orders Colección de App\Models\Order
     * @return array
     */
    public function generate(Collection $orders): array
    {
        $timestamp = now()->format('Ymd-His');
        $filename = "reporte-cierre-pedidos-{$timestamp}.txt";
        $path = "reports/{$filename}";

        $stats = [
            'total' => $orders->count(),
            'completed' => 0,
            'pending' => 0,
            'processing' => 0,
            'rejected' => 0,
            'api_processed' => 0,
            'api_failed' => 0,
            'api_manual_review' => 0,
        ];

        $providers = [];
        $details = [];

        foreach ($orders as $order) {
            // Conteo general
            switch ($order->status) {
                case 'completed': $stats['completed']++; break;
                case 'pending': $stats['pending']++; break;
                case 'processing': $stats['processing']++; break;
                case 'rejected': $stats['rejected']++; break;
            }

            // Conteo API
            if ($order->processed_by_api) {
                $stats['api_processed']++;
            }
            if ($order->api_status === 'failed') {
                $stats['api_failed']++;
            }
            if ($order->api_status === 'manual_review') {
                $stats['api_manual_review']++;
            }

            if ($order->external_provider) {
                $providers[$order->external_provider] = true;
            }

            $serviceName = $order->service ? $order->service->name : 'Servicio Desconocido';
            $userName = $order->user ? $order->user->name : 'Usuario Desconocido';
            $apiReport = $order->api_report ? trim(str_replace("\n", "\n  ", $order->api_report)) : 'N/A';
            $apiError = $order->api_error_message ? trim($order->api_error_message) : 'N/A';
            $processedByApiText = $order->processed_by_api ? 'Sí' : 'No';

            $details[] = "Pedido #: {$order->id}
Servicio: {$serviceName}
Usuario: {$userName}
Estado: {$order->status}
Procesado por API: {$processedByApiText}
Estado API: " . ($order->api_status ?? 'N/A') . "
Proveedor: " . ($order->external_provider ?? 'N/A') . "
Orden externa: " . ($order->external_order_id ?? 'N/A') . "
Reporte API:
  {$apiReport}
Error: {$apiError}";
        }

        $providersList = empty($providers) ? 'Ninguno' : implode(', ', array_keys($providers));
        $generatedBy = auth()->check() ? auth()->user()->name : 'Sistema';

        $reportData = "REPORTE DE CIERRE DE PEDIDOS\n\n";
        $reportData .= "Fecha: " . now()->toDateTimeString() . "\n";
        $reportData .= "Generado por: {$generatedBy}\n";
        $reportData .= "Total de pedidos: {$stats['total']}\n";
        $reportData .= "Completados: {$stats['completed']}\n";
        $reportData .= "Pendientes: {$stats['pending']}\n";
        $reportData .= "En proceso: {$stats['processing']}\n";
        $reportData .= "Rechazados: {$stats['rejected']}\n";
        $reportData .= "Procesados por API externa: {$stats['api_processed']}\n";
        $reportData .= "Fallidos por API: {$stats['api_failed']}\n";
        $reportData .= "Revisión manual: {$stats['api_manual_review']}\n";
        $reportData .= "Proveedores utilizados: {$providersList}\n\n";

        $reportData .= "DETALLE DE PEDIDOS:\n\n";
        $reportData .= implode("\n\n--------------------------------------------------\n\n", $details) . "\n\n";

        // Obtener resumen final usando Ollama (o fallback)
        $aiSummary = $this->aiService->generateAdministrativeSummary($reportData);

        $content = $reportData;
        $content .= "RESUMEN FINAL GENERADO CON OLLAMA:\n";
        $content .= "{$aiSummary}\n\n";
        $content .= "NOTA:\n";
        $content .= "Este reporte fue generado con datos del sistema. El resumen final fue redactado con apoyo de IA local mediante Ollama. Si Ollama no está disponible, el sistema genera un resumen básico automáticamente.\n";

        // Guardar archivo
        Storage::disk('public')->put($path, $content);

        return [
            'success' => true,
            'title' => 'Reporte de cierre de pedidos',
            'summary' => $aiSummary,
            'details' => $content,
            'file_path' => $path,
            'generated_at' => now()->toDateTimeString(),
            'absolute_path' => Storage::disk('public')->path($path),
        ];
    }
}
