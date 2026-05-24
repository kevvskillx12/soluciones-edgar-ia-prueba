<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaReportService
{
    /**
     * Genera un resumen administrativo a partir de los datos del reporte
     * usando una instancia local de Ollama.
     *
     * @param string $reportData El texto plano con los conteos y detalles.
     * @return string El resumen generado o un fallback en caso de error.
     */
    public function generateAdministrativeSummary(string $reportData): string
    {
        $prompt = "Eres un asistente administrativo. Genera un resumen de cierre basado ESTRICTAMENTE en los datos a continuación. Cumple estas reglas obligatorias:\n"
            . "1. No inventes pedidos, errores ni revisiones manuales.\n"
            . "2. Si 'Fallidos por API' es 0, indica claramente que no hubo fallos.\n"
            . "3. Si 'Revisión manual' es 0, indica claramente que no hay pedidos en revisión manual.\n"
            . "4. Si un dato no aparece, no lo menciones.\n"
            . "5. No des recomendaciones que contradigan los números.\n"
            . "6. Limita tu respuesta a máximo 2 párrafos cortos o una lista puntual.\n"
            . "Responde en español y con lenguaje profesional.\n\n"
            . "DATOS DEL REPORTE:\n" . $reportData;

        try {
            Log::info('[OllamaReportService] Solicitando resumen a Ollama (llama3.2:1b)...');

            $response = Http::timeout(120)->post('http://localhost:11434/api/generate', [
                'model' => 'llama3.2:1b',
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful() && isset($response->json()['response'])) {
                Log::info('[OllamaReportService] Resumen generado exitosamente por Ollama.');
                return trim($response->json()['response']);
            }

            Log::warning('[OllamaReportService] Respuesta fallida o inesperada de Ollama. Se usará fallback PHP. Respuesta: ' . $response->body());
        } catch (\Throwable $e) {
            Log::warning('[OllamaReportService] Error conectando a Ollama: ' . $e->getMessage() . '. Se usará fallback PHP.');
        }

        return $this->getFallbackSummary();
    }

    /**
     * Resumen básico si Ollama no está disponible o falla.
     */
    protected function getFallbackSummary(): string
    {
        return "Reporte generado satisfactoriamente con la herramienta interna.\n"
            . "(Nota: El servicio de IA local con Ollama no estuvo disponible o excedió el tiempo de espera. Este es un resumen generado automáticamente por el sistema.)";
    }
}
