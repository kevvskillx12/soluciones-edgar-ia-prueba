<?php

namespace App\Services\Automation;

use App\Models\Order;
use App\Services\Dhru\DhruApiService;
use Illuminate\Support\Facades\Log;

class ExternalOrderAutomationService
{
    protected DhruApiService $dhruApi;

    public function __construct(DhruApiService $dhruApi)
    {
        $this->dhruApi = $dhruApi;
    }

    /**
     * Procesar un pedido según el tipo de automatización de su servicio.
     *
     * Flujo:
     *  - manual         → marca manual_review, no automatiza.
     *  - semi_automatic  → marca manual_review con nota, requiere revisión humana.
     *  - automatic       → envía al proveedor externo (real o simulado).
     *
     * @return array  Resultado con claves: success, status, message
     */
    public function process(Order $order): array
    {
        try {
            // Cargar la relación service si no está cargada
            $service = $order->service;

            if (! $service) {
                $this->markFailed($order, 'El pedido no tiene un servicio asociado.');

                return [
                    'success' => false,
                    'status'  => 'failed',
                    'message' => 'El pedido no tiene un servicio asociado.',
                ];
            }

            $automationType = $service->automation_type ?? 'manual';

            Log::info("[AutomationService] Procesando Order #{$order->id} | automation_type={$automationType}");

            return match ($automationType) {
                'manual'         => $this->handleManual($order),
                'semi_automatic' => $this->handleSemiAutomatic($order),
                'automatic'      => $this->handleAutomatic($order),
                default          => $this->handleManual($order),
            };
        } catch (\Throwable $e) {
            Log::error("[AutomationService] Error procesando Order #{$order->id}: " . $e->getMessage());

            $this->markFailed($order, $e->getMessage());

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Flujo manual: solo marca el pedido para revisión humana.
     */
    protected function handleManual(Order $order): array
    {
        $order->update([
            'api_status'      => 'manual_review',
            'api_report'      => 'Este pedido requiere procesamiento manual. El servicio no tiene automatización configurada.',
            'api_processed_at' => now(),
        ]);

        Log::info("[AutomationService] Order #{$order->id} marcado como manual_review.");

        return [
            'success' => true,
            'status'  => 'manual_review',
            'message' => 'Pedido marcado para revisión manual.',
        ];
    }

    /**
     * Flujo semi-automático: marca para revisión pero indica que podría automatizarse.
     */
    protected function handleSemiAutomatic(Order $order): array
    {
        $order->update([
            'api_status'       => 'manual_review',
            'api_report'       => 'Este pedido es semi-automático. Requiere verificación humana antes de enviar al proveedor externo. Un administrador debe revisar y, si corresponde, procesar manualmente la orden por API.',
            'external_provider' => $order->service->external_provider,
            'api_processed_at'  => now(),
        ]);

        Log::info("[AutomationService] Order #{$order->id} marcado como semi_automatic → manual_review.");

        return [
            'success' => true,
            'status'  => 'manual_review',
            'message' => 'Pedido semi-automático marcado para revisión. Un administrador debe completar el envío.',
        ];
    }

    /**
     * Flujo automático: envía al proveedor externo, consulta estado y resultado.
     */
    protected function handleAutomatic(Order $order): array
    {
        $service = $order->service;

        // Validar que el servicio tenga proveedor externo configurado
        if (empty($service->external_provider)) {
            $this->markFailed($order, 'El servicio no tiene un external_provider configurado.');

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => 'El servicio no tiene un external_provider configurado.',
            ];
        }

        // Validar external_service_id para Dhru
        if ($service->external_provider === 'dhru' && empty($service->external_service_id)) {
            $this->markFailed($order, 'El servicio usa proveedor dhru pero no tiene external_service_id configurado.');

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => 'Falta external_service_id para el proveedor dhru.',
            ];
        }

        // Paso 1: Enviar la orden al proveedor
        $placeResult = $this->dhruApi->placeOrder($order);

        if (! ($placeResult['success'] ?? false)) {
            $this->markFailed($order, $placeResult['message'] ?? 'Error desconocido al enviar orden al proveedor.');

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => $placeResult['message'] ?? 'Error al enviar orden.',
            ];
        }

        $externalOrderId = $placeResult['external_order_id'] ?? null;

        // Guardar datos iniciales del envío
        $order->update([
            'external_provider'  => $service->external_provider,
            'external_order_id'  => $externalOrderId,
            'api_status'         => $placeResult['status'] ?? 'sent',
            'api_report'         => $placeResult['message'] ?? '',
            'api_processed_at'   => now(),
            'status'             => 'processing',
        ]);

        Log::info("[AutomationService] Order #{$order->id} enviada al proveedor. external_order_id={$externalOrderId}");

        // Paso 2: Consultar estado de la orden
        if ($externalOrderId) {
            $statusResult = $this->dhruApi->checkOrderStatus($externalOrderId);

            if (($statusResult['success'] ?? false) && ($statusResult['status'] ?? '') === 'completed') {

                // Paso 3: Obtener resultado
                $resultData = $this->dhruApi->getOrderResult($externalOrderId);

                $report = "--- Reporte de automatización ---\n"
                    . "Proveedor: {$service->external_provider}\n"
                    . "Orden externa: {$externalOrderId}\n"
                    . "Estado proveedor: completed\n"
                    . "Fecha: " . now()->toDateTimeString() . "\n"
                    . "Simulado: " . (($statusResult['simulated'] ?? false) ? 'Sí' : 'No') . "\n"
                    . "\n--- Resultado ---\n"
                    . ($resultData['result'] ?? 'Sin resultado disponible.');

                $updateData = [
                    'processed_by_api'  => true,
                    'api_status'        => 'completed',
                    'api_report'        => $report,
                    'api_processed_at'  => now(),
                    'status'            => 'completed',
                ];

                // Si se generó un archivo de resultado, guardarlo
                if (isset($resultData['result_file'])) {
                    $updateData['result_file_path'] = $resultData['result_file'];
                }

                $order->update($updateData);

                Log::info("[AutomationService] Order #{$order->id} completada exitosamente vía API externa.");

                return [
                    'success' => true,
                    'status'  => 'completed',
                    'message' => 'Pedido procesado y completado exitosamente por el proveedor externo.',
                ];
            }

            // Si el status no es completed, dejar en processing
            $order->update([
                'api_status' => $statusResult['status'] ?? 'processing',
                'api_report' => $order->api_report . "\nEstado consultado: " . ($statusResult['status'] ?? 'desconocido'),
            ]);

            return [
                'success' => true,
                'status'  => $statusResult['status'] ?? 'processing',
                'message' => 'Orden enviada. Estado actual: ' . ($statusResult['status'] ?? 'en espera'),
            ];
        }

        return [
            'success' => true,
            'status'  => 'sent',
            'message' => 'Orden enviada al proveedor, pendiente de seguimiento.',
        ];
    }

    /**
     * Marcar un pedido como fallido con mensaje de error.
     */
    protected function markFailed(Order $order, string $errorMessage): void
    {
        $order->update([
            'api_status'        => 'failed',
            'api_error_message' => $errorMessage,
            'api_processed_at'  => now(),
        ]);

        Log::warning("[AutomationService] Order #{$order->id} marcada como failed: {$errorMessage}");
    }
}
