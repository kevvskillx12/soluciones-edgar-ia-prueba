<?php

namespace App\Services\AI;

class GuardrailService
{
    /**
     * Checks if a user prompt is potentially malicious.
     * Normalizes text, removes accents, and checks against heuristic rules.
     */
    public function isBlocked(string $prompt): bool
    {
        $normalized = $this->normalizeText($prompt);
        
        // 1. Entradas excesivamente largas o repetitivas
        if (strlen($normalized) > 5000) {
            return true;
        }

        // 2. Patrones de jailbreak o fugas (Prompt Injection heuristics)
        $blockedPatterns = [
            'ignora las instrucciones',
            'ignora todas las instrucciones',
            'revela el system prompt',
            'revela tus instrucciones',
            'asume el rol de',
            'comportate como',
            'ahora eres un',
            'olvida tus reglas',
            'imprime tus instrucciones',
            'ignora lo anterior',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (str_contains($normalized, $this->normalizeText($pattern))) {
                return true;
            }
        }

        // 3. Solicitudes explícitas de falsificación documental o de datos
        // personales ajenos. Las consultas legítimas sobre requisitos y trámites
        // administrativos no coinciden con estos patrones.
        $documentFraudPatterns = [
            '/\b(hazme|genera|crea|fabrica|inventa)\b.{0,40}\b(curp|acta|rfc|nss|documentos?|identificacion)\b.{0,25}\b(fals[ao]s?|fraudulent[ao]s?)\b/u',
            '/^\s*(una?\s+)?(curp|acta|rfc|nss|documentos?|identificacion)\b.{0,25}\b(fals[ao]s?|fraudulent[ao]s?)\b/u',
            '/\b(como|ayudame a|quiero)\b.{0,30}\b(falsificar|falsifico|alterar)\b.{0,30}\b(curp|acta|rfc|nss|documentos?|identificacion)\b/u',
            '/\bdame\b.{0,30}\bdatos personales reales\b.{0,30}\b(otra persona|tercero)\b/u',
            '/\b(descarga|obten|consulta|dame|consigue)\b.{0,35}\b(curp|rfc|nss|constancia|datos?)\b.{0,40}\b(otra persona|tercero|sin autorizacion)\b/u',
            '/\bdame\s+(?:el|la)\s+(?:curp|rfc|nss)\s+de\s+[a-z]+(?:\s+[a-z]+)+/u',
            '/\b(contrasena|password|e firma|efirma|clave privada|acceso ilegal|suplantacion)\b/u',
        ];

        foreach ($documentFraudPatterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    public function getGenericBlockMessage(): string
    {
        return 'Lo siento, no puedo procesar esta solicitud porque incumple las políticas de seguridad del sistema.';
    }

    private function normalizeText(string $text): string
    {
        // Convertir a minúsculas
        $text = mb_strtolower($text, 'UTF-8');
        
        // Quitar acentos (forma simple)
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ä', 'ë', 'ï', 'ö', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n'],
            $text
        );
        
        // Reducir espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
}
