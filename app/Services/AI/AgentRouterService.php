<?php

namespace App\Services\AI;

use App\Models\AiConversation;

class AgentRouterService
{
    /**
     * @param  array<int, array{role?: string, content?: string}>  $conversationHistory
     * @return array{agent: string, intent: string, confidence: float, reason: string, handoff_context: array<string, mixed>}
     */
    public function route(AiConversation $conversation, string $prompt, array $conversationHistory = []): array
    {
        $normalized = $this->normalize($prompt);
        $state = ($conversation->metadata ?? [])['procedure_flow'] ?? null;
        $activeStatus = $state['status'] ?? null;

        if ($this->hasActiveProcedure($activeStatus) && $this->matches($normalized, [
            'si',
            'hazlo',
            'continua',
            'continuar',
            'termina la solicitud',
            'ya la hiciste',
            'ya lo hiciste',
            'que datos faltan',
            'que dato me pediste',
            'para quien era',
            'ultimo tramite',
            'cancela',
            'cancelar',
            'cambiar de tramite',
            'otro tramite',
        ])) {
            return $this->decision(
                'transactional_agent',
                'procedure_state_followup',
                0.96,
                'La conversacion tiene un flujo de tramite activo y el mensaje consulta o modifica ese estado.',
                $state,
                $conversationHistory
            );
        }

        if ($this->matches($normalized, [
            'tramite',
            'tramitar',
            'solicitud',
            'orden',
            'pedido',
            'curp',
            'acta',
            'rfc',
            'nss',
            'constancia fiscal',
            'constancia de situacion fiscal',
            'asignala',
            'asignalo',
            'asigna',
            'crear otro',
            'cancelar este',
            'cancela este',
            'que datos faltan',
            'que dato me pediste',
            'para quien era',
            'cual fue el ultimo',
            'ultimo tramite',
            'en que me puedes ayudar',
            'que puedes hacer',
            'que tramite puedes',
            'hola',
            'buenos dias',
            'buenas tardes',
            'buenas noches',
        ])) {
            return $this->decision(
                'transactional_agent',
                'procedure_or_business_command',
                0.9,
                'El mensaje pide iniciar, consultar, cancelar o continuar un tramite.',
                $state,
                $conversationHistory
            );
        }

        return $this->decision(
            'rag_agent',
            'knowledge_query',
            0.82,
            'El mensaje no requiere modificar base de datos; se delega al especialista RAG.',
            $state,
            $conversationHistory
        );
    }

    private function hasActiveProcedure(?string $status): bool
    {
        return in_array($status, [
            'awaiting_service',
            'awaiting_subject',
            'collecting',
            'ready_to_confirm',
            'completed',
            'cancelled',
        ], true);
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @param  array<int, array{role?: string, content?: string}>  $conversationHistory
     * @return array{agent: string, intent: string, confidence: float, reason: string, handoff_context: array<string, mixed>}
     */
    private function decision(
        string $agent,
        string $intent,
        float $confidence,
        string $reason,
        ?array $state,
        array $conversationHistory
    ): array {
        return [
            'agent' => $agent,
            'intent' => $intent,
            'confidence' => $confidence,
            'reason' => $reason,
            'handoff_context' => [
                'procedure_status' => $state['status'] ?? null,
                'service_name' => $state['service_name'] ?? null,
                'subject_name' => $state['subject_name'] ?? null,
                'history_messages' => count($conversationHistory),
            ],
        ];
    }

    private function matches(string $normalized, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($normalized, $this->normalize($phrase))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
