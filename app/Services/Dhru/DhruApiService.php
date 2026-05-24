<?php

namespace App\Services\Dhru;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DhruApiService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('dhru');
    }

    /**
     * Verificar si la API de Dhru está habilitada.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * Obtener lista de servicios disponibles en el proveedor.
     * En modo simulación devuelve un catálogo de ejemplo.
     */
    public function getServices(): array
    {
        if (! $this->isEnabled()) {
            Log::info('[DhruApiService] Modo simulación: getServices()');

            return [
                'success' => true,
                'simulated' => true,
                'services' => [
                    ['id' => 'SIM-SVC-001', 'name' => 'Servicio simulado 1', 'price' => '10.00'],
                    ['id' => 'SIM-SVC-002', 'name' => 'Servicio simulado 2', 'price' => '25.00'],
                    ['id' => 'SIM-SVC-003', 'name' => 'Servicio simulado 3', 'price' => '50.00'],
                ],
            ];
        }

        // --- Modo real (futuro) ---
        try {
            $response = Http::timeout($this->config['timeout'])
                ->post($this->config['api_url'], [
                    'username'       => $this->config['username'],
                    'apiaccesskey'   => $this->config['access_key'],
                    'action'         => 'getservices',
                    'requestformat'  => $this->config['request_format'],
                ]);

            $data = $response->json();

            return [
                'success'  => true,
                'simulated' => false,
                'services' => $data['LIST'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('[DhruApiService] Error en getServices: ' . $e->getMessage());

            return [
                'success' => false,
                'simulated' => false,
                'message' => $e->getMessage(),
                'services' => [],
            ];
        }
    }

    /**
     * Enviar una orden al proveedor externo.
     * En modo simulación genera un external_order_id ficticio.
     */
    public function placeOrder(Order $order): array
    {
        if (! $this->isEnabled()) {
            Log::info("[DhruApiService] Modo simulación: placeOrder(Order #{$order->id})");

            $externalOrderId = 'SIM-' . $order->id . '-' . time();

            return [
                'success'           => true,
                'simulated'         => true,
                'external_order_id' => $externalOrderId,
                'status'            => 'sent',
                'message'           => 'Orden simulada enviada correctamente al proveedor externo.',
            ];
        }

        // --- Modo real (futuro) ---
        try {
            $service = $order->service;

            $response = Http::timeout($this->config['timeout'])
                ->post($this->config['api_url'], [
                    'username'       => $this->config['username'],
                    'apiaccesskey'   => $this->config['access_key'],
                    'action'         => 'placeorder',
                    'requestformat'  => $this->config['request_format'],
                    'service'        => $service->external_service_id ?? '',
                    'imei'           => $order->input_data['imei'] ?? '',
                ]);

            $data = $response->json();

            if (isset($data['ID'])) {
                return [
                    'success'           => true,
                    'simulated'         => false,
                    'external_order_id' => $data['ID'],
                    'status'            => 'sent',
                    'message'           => 'Orden enviada al proveedor real.',
                ];
            }

            return [
                'success'  => false,
                'simulated' => false,
                'status'   => 'error',
                'message'  => $data['ERROR'] ?? 'Respuesta inesperada del proveedor.',
            ];
        } catch (\Throwable $e) {
            Log::error("[DhruApiService] Error en placeOrder(Order #{$order->id}): " . $e->getMessage());

            return [
                'success' => false,
                'simulated' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Consultar el estado de una orden en el proveedor externo.
     * En modo simulación siempre devuelve 'completed'.
     */
    public function checkOrderStatus(string $externalOrderId): array
    {
        if (! $this->isEnabled()) {
            Log::info("[DhruApiService] Modo simulación: checkOrderStatus('{$externalOrderId}')");

            return [
                'success'   => true,
                'simulated' => true,
                'status'    => 'completed',
                'message'   => 'Estado simulado: la orden fue completada exitosamente.',
            ];
        }

        // --- Modo real (futuro) ---
        try {
            $response = Http::timeout($this->config['timeout'])
                ->post($this->config['api_url'], [
                    'username'       => $this->config['username'],
                    'apiaccesskey'   => $this->config['access_key'],
                    'action'         => 'getorderstatus',
                    'requestformat'  => $this->config['request_format'],
                    'orderid'        => $externalOrderId,
                ]);

            $data = $response->json();

            return [
                'success'   => true,
                'simulated' => false,
                'status'    => strtolower($data['Status'] ?? 'unknown'),
                'message'   => $data['StatusMessage'] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error("[DhruApiService] Error en checkOrderStatus('{$externalOrderId}'): " . $e->getMessage());

            return [
                'success' => false,
                'simulated' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtener el resultado de una orden completada.
     * En modo simulación genera un archivo de texto de prueba en storage.
     */
    public function getOrderResult(string $externalOrderId): array
    {
        if (! $this->isEnabled()) {
            Log::info("[DhruApiService] Modo simulación: getOrderResult('{$externalOrderId}')");

            // Generar archivo simulado de resultado
            $filename = "results/resultado-simulado-{$externalOrderId}.txt";
            $content  = "=== RESULTADO SIMULADO ===\n"
                . "Orden externa: {$externalOrderId}\n"
                . "Fecha: " . now()->toDateTimeString() . "\n"
                . "Estado: Completado\n"
                . "Proveedor: " . ($this->config['provider_name'] ?? 'dhru') . " (simulación)\n"
                . "\n"
                . "Este es un resultado de prueba generado en modo simulación.\n"
                . "Cuando la API esté habilitada, aquí aparecerá el resultado real del proveedor.\n";

            Storage::disk('public')->put($filename, $content);

            return [
                'success'     => true,
                'simulated'   => true,
                'result'      => $content,
                'result_file' => $filename,
                'message'     => 'Resultado simulado generado correctamente.',
            ];
        }

        // --- Modo real (futuro) ---
        try {
            $response = Http::timeout($this->config['timeout'])
                ->post($this->config['api_url'], [
                    'username'       => $this->config['username'],
                    'apiaccesskey'   => $this->config['access_key'],
                    'action'         => 'getorderresult',
                    'requestformat'  => $this->config['request_format'],
                    'orderid'        => $externalOrderId,
                ]);

            $data = $response->json();

            return [
                'success'   => true,
                'simulated' => false,
                'result'    => $data['Code'] ?? $data['Result'] ?? json_encode($data),
                'message'   => 'Resultado obtenido del proveedor.',
            ];
        } catch (\Throwable $e) {
            Log::error("[DhruApiService] Error en getOrderResult('{$externalOrderId}'): " . $e->getMessage());

            return [
                'success' => false,
                'simulated' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
