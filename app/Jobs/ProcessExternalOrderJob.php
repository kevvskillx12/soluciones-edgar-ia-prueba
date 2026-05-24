<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Automation\ExternalOrderAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExternalOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos antes de marcar como fallido.
     */
    public int $tries = 3;

    /**
     * Timeout en segundos para este Job.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExternalOrderAutomationService $automationService): void
    {
        Log::info("[ProcessExternalOrderJob] Iniciando procesamiento de Order #{$this->order->id}");

        try {
            $result = $automationService->process($this->order);

            Log::info("[ProcessExternalOrderJob] Order #{$this->order->id} procesada. Resultado: " . json_encode($result));
        } catch (\Throwable $e) {
            Log::error("[ProcessExternalOrderJob] Error en Order #{$this->order->id}: " . $e->getMessage());

            $this->order->update([
                'api_status'        => 'failed',
                'api_error_message' => 'Error en Job: ' . $e->getMessage(),
                'api_processed_at'  => now(),
            ]);

            // Re-lanzar para que Laravel registre el fallo y reintente si corresponde
            throw $e;
        }
    }

    /**
     * Manejar el fallo definitivo del Job (después de agotar reintentos).
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("[ProcessExternalOrderJob] FALLO DEFINITIVO en Order #{$this->order->id}: " . ($exception?->getMessage() ?? 'Error desconocido'));

        $this->order->update([
            'api_status'        => 'failed',
            'api_error_message' => 'Job falló definitivamente: ' . ($exception?->getMessage() ?? 'Error desconocido'),
            'api_processed_at'  => now(),
        ]);
    }
}
