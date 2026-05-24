<?php

namespace App\Services\AI;

use App\Models\Order;
use App\Models\User;
use App\Services\Reports\OrderClosingReportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminChatCommandService
{
    /**
     * Analiza el mensaje para ver si es un comando administrativo
     * y, de ser así, lo ejecuta y devuelve la respuesta.
     * Si no es un comando, devuelve null.
     */
    public function handle(string $message, ?User $user = null): ?array
    {
        if (!$user || !$user->is_admin) {
            return null;
        }

        $messageLower = Str::lower($message);

        // 1. Comando: Generar Reporte de Cierre (Existente)
        $reportKeywords = [
            'reporte de los últimos', 'reporte de los ultimos',
            'generar reporte de trámites', 'generar reporte de tramites',
            'generar reporte de pedidos', 'reporte de cierre',
            'reporte de servicios procesados', 'últimos pedidos',
            'ultimos pedidos'
        ];
        if (Str::contains($messageLower, $reportKeywords)) {
            return $this->generateReport($messageLower);
        }

        // 2. Comando: Pedidos Fallidos / Errores
        $failedKeywords = [
            'resume los pedidos fallidos', 'dime los pedidos fallidos',
            'errores de api', 'resumen de errores', 'pedidos fallidos'
        ];
        if (Str::contains($messageLower, $failedKeywords)) {
            return $this->analyzeFailedOrders($messageLower);
        }

        // 3. Comando: Revisión Manual
        $manualReviewKeywords = [
            'trámites en revisión manual', 'tramites en revision manual',
            'pedidos en revisión manual', 'pedidos en revision manual',
            'qué quedó pendiente de revisión', 'que quedo pendiente de revision',
            'revisión manual', 'revision manual'
        ];
        if (Str::contains($messageLower, $manualReviewKeywords)) {
            return $this->analyzeManualReviewOrders($messageLower);
        }

        // 4. Comando: Completados Hoy
        $completedTodayKeywords = [
            'resumen de trámites completados hoy', 'resumen de tramites completados hoy',
            'trámites completados hoy', 'tramites completados hoy',
            'cierre de hoy', 'completados hoy'
        ];
        if (Str::contains($messageLower, $completedTodayKeywords)) {
            return $this->analyzeCompletedToday($messageLower);
        }

        return null;
    }

    /**
     * Extrae el número de límite solicitado, por defecto 10, máximo 50.
     */
    protected function extractLimit(string $message, int $default = 10, int $max = 50): int
    {
        preg_match('/\b(\d+)\b/', $message, $matches);
        if (!empty($matches[1])) {
            $parsed = (int) $matches[1];
            if ($parsed > 0) {
                return min($parsed, $max);
            }
        }
        return $default;
    }

    protected function generateReport(string $message): array
    {
        $limit = $this->extractLimit($message);

        $orders = Order::with(['user', 'service'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => 'No hay pedidos recientes en el sistema para generar un reporte.'];
        }

        $reportService = app(OrderClosingReportService::class);
        $result = $reportService->generate($orders);
        $publicUrl = Storage::disk('public')->url($result['file_path']);

        $respuestaTexto = "✅ **Reporte generado correctamente.**\n";
        $respuestaTexto .= "Se incluyeron los últimos {$orders->count()} trámites.\n";
        $respuestaTexto .= "Descargar archivo: [{$result['file_path']}]({$publicUrl})\n\n";
        $respuestaTexto .= "**Resumen Final:**\n" . $result['summary'];

        return [
            'handled' => true,
            'respuesta' => $respuestaTexto,
            'file_path' => $result['file_path'],
            'url' => $publicUrl
        ];
    }

    protected function analyzeFailedOrders(string $message): array
    {
        $limit = $this->extractLimit($message);

        $orders = Order::with(['user', 'service'])
            ->where(function ($q) {
                $q->where('api_status', 'failed')
                  ->orWhereNotNull('api_error_message');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => '✅ No se encontraron pedidos fallidos recientes. Todo parece funcionar correctamente.'];
        }

        $reportData = "ANALISIS DE PEDIDOS FALLIDOS:\n";
        $reportData .= "Total de fallidos encontrados: {$orders->count()}\n\n";

        $respuestaTexto = "⚠️ **Se encontraron {$orders->count()} pedidos con errores.**\n\n";

        foreach ($orders as $order) {
            $serviceName = $order->service ? $order->service->name : 'N/A';
            $userName = $order->user ? $order->user->name : 'N/A';
            $error = $order->api_error_message ?? 'Error no especificado';
            
            $line = "- **Pedido #{$order->id}** | Servicio: {$serviceName} | Usuario: {$userName} | Prov: {$order->external_provider}\n  **Error:** {$error}\n";
            $respuestaTexto .= $line;
            $reportData .= $line;
        }

        $aiService = app(OllamaReportService::class);
        // Prompt adaptado para análisis de fallos
        $customPrompt = "Eres un analista técnico. Con base en los siguientes pedidos fallidos, genera un resumen administrativo breve (máx 3 líneas) indicando cuáles son los errores más comunes y qué acción recomiendas. No inventes datos.\n\n" . $reportData;
        
        $aiSummary = $this->askOllamaDirectly($aiService, $customPrompt);

        if ($aiSummary) {
            $respuestaTexto .= "\n**Análisis de IA:**\n" . $aiSummary;
        }

        return ['handled' => true, 'respuesta' => $respuestaTexto];
    }

    protected function analyzeManualReviewOrders(string $message): array
    {
        $limit = $this->extractLimit($message);

        $orders = Order::with(['user', 'service'])
            ->where(function ($q) {
                $q->where('api_status', 'manual_review')
                  ->orWhere('status', 'pending');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => '✅ No hay trámites pendientes de revisión manual en este momento.'];
        }

        $respuestaTexto = "🔍 **Se encontraron {$orders->count()} pedidos en revisión manual / pendientes.**\n\n";

        foreach ($orders as $order) {
            $serviceName = $order->service ? $order->service->name : 'N/A';
            $userName = $order->user ? $order->user->name : 'N/A';
            $motivo = $order->api_report ? Str::limit($order->api_report, 50) : 'Pendiente de acción';

            $respuestaTexto .= "- **Pedido #{$order->id}** - {$serviceName}\n";
            $respuestaTexto .= "  Usuario: {$userName} | Estado: {$order->status} / {$order->api_status}\n";
            $respuestaTexto .= "  Motivo/Nota: {$motivo}\n\n";
        }

        $respuestaTexto .= "**Recomendación:** Revisa los datos capturados en estos pedidos antes de procesarlos o rechazarlos.";

        return ['handled' => true, 'respuesta' => $respuestaTexto];
    }

    protected function analyzeCompletedToday(string $message): array
    {
        $orders = Order::with(['service'])
            ->where('status', 'completed')
            ->whereDate('updated_at', today())
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => 'ℹ️ Aún no hay pedidos completados en el día de hoy.'];
        }

        $apiProcessed = $orders->where('processed_by_api', true)->count();
        $providers = $orders->pluck('external_provider')->filter()->unique()->implode(', ');
        if (empty($providers)) $providers = 'Ninguno';

        $reportData = "CIERRE DEL DÍA (COMPLETADOS HOY):\n";
        $reportData .= "Total completados: {$orders->count()}\n";
        $reportData .= "Procesados por API: {$apiProcessed}\n";
        $reportData .= "Proveedores usados: {$providers}\n";

        $respuestaTexto = "📊 **Resumen de trámites completados hoy:**\n";
        $respuestaTexto .= "- **Total:** {$orders->count()}\n";
        $respuestaTexto .= "- **Por API externa:** {$apiProcessed}\n";
        $respuestaTexto .= "- **Proveedores:** {$providers}\n\n";

        $aiService = app(OllamaReportService::class);
        $customPrompt = "Eres un asistente administrativo. Genera un mensaje breve y motivador resumiendo el cierre del día con estos datos. Máximo 2 líneas.\n\n" . $reportData;
        
        $aiSummary = $this->askOllamaDirectly($aiService, $customPrompt);

        if ($aiSummary) {
            $respuestaTexto .= "**Nota del Asistente:** " . $aiSummary;
        }

        return ['handled' => true, 'respuesta' => $respuestaTexto];
    }

    /**
     * Helper para hacer una consulta cruda a Ollama y obtener solo el string.
     */
    protected function askOllamaDirectly(OllamaReportService $aiService, string $prompt): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)->post('http://localhost:11434/api/generate', [
                'model' => 'llama3.2:1b',
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful() && isset($response->json()['response'])) {
                return trim($response->json()['response']);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AdminChatCommandService] Ollama error: ' . $e->getMessage());
        }
        return null;
    }
}
