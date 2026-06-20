<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProcedureFlowService
{
    /**
     * @return array{handled: bool, response: ?string, state: ?array}
     */
    public function handle(AiConversation $conversation, string $prompt): array
    {
        $state = $this->getState($conversation);
        $normalized = $this->normalize($prompt);

        if ($this->matches($normalized, [
            'cancela este tramite',
            'cancelar este tramite',
            'cancela el tramite',
            'cancelar el tramite',
            'ya no quiero continuar',
        ])) {
            if (!$state) {
                return $this->handled('No hay un trámite activo para cancelar.', null);
            }

            $state['status'] = 'cancelled';
            $state['current_field'] = null;
            $this->persistState($conversation, $state, false);

            return $this->handled('Entendido. El trámite actual quedó cancelado.', $state);
        }

        if ($this->matches($normalized, [
            'quiero cambiar de tramite',
            'cambiar de tramite',
            'cambia el tramite',
            'quiero otro tramite',
        ])) {
            $subjectName = $state['subject_name'] ?? $this->extractSubjectName($prompt);
            $state = $this->emptyState($subjectName);
            $this->persistState($conversation, $state, false);

            return $this->handled(
                $subjectName
                    ? "Conservaré a {$subjectName} como persona interesada. ¿Qué trámite deseas realizar ahora?"
                    : 'De acuerdo. ¿Qué trámite deseas realizar ahora?',
                $state
            );
        }

        $stateResponse = $this->answerStateQuestion($normalized, $state);
        if ($stateResponse !== null) {
            return $this->handled($stateResponse, $state);
        }

        $services = $this->availableServices();
        $service = $this->detectService($prompt, $services);
        $subjectName = $this->extractSubjectName($prompt);

        if (!$state && $services->isEmpty() && $this->mentionsKnownService($normalized)) {
            return $this->notHandled();
        }

        if (!$state && !$this->looksLikeProcedureRequest($normalized) && !$service) {
            return $this->notHandled();
        }

        $state ??= $this->emptyState();

        if ($subjectName) {
            $state['subject_name'] = $subjectName;
        }

        if ($service && (int) ($state['service_id'] ?? 0) !== $service->id) {
            $state = $this->applyService($state, $service);
        }

        if (($state['status'] ?? null) === 'cancelled' && !$service) {
            return $this->notHandled();
        }

        if (!$state['service_id']) {
            $state['status'] = 'awaiting_service';
            $this->persistState($conversation, $state, false);

            if ($state['subject_name']) {
                return $this->handled(
                    "Perfecto, tengo registrado a {$state['subject_name']}. {$this->serviceQuestion($services)}",
                    $state
                );
            }

            return $this->handled($this->serviceQuestion($services), $state);
        }

        if (!$state['subject_name']) {
            $state['status'] = 'awaiting_subject';
            $this->persistState($conversation, $state);

            return $this->handled(
                "Seleccionaste el trámite {$state['service_name']}. ¿Para quién es el trámite?",
                $state
            );
        }

        if ($state['current_field'] && !$service && !$subjectName) {
            $capture = $this->captureCurrentField($state, $prompt);
            if ($capture['attempted']) {
                if (!$capture['valid']) {
                    return $this->handled(
                        "El valor no tiene el formato esperado para {$capture['label']}. Por favor, vuelve a capturarlo.",
                        $state
                    );
                }

                $state = $capture['state'];
            }
        }

        $state = $this->refreshProgress($state);
        $this->persistState($conversation, $state);

        if ($state['status'] === 'ready_to_confirm') {
            return $this->handled($this->readyResponse($state), $state);
        }

        $current = $this->currentFieldDefinition($state);
        $prefix = $service
            ? "Perfecto, iniciaré el trámite {$state['service_name']} para {$state['subject_name']}."
            : 'Gracias.';

        return $this->handled(
            "{$prefix} Para este trámite necesito el dato: {$current['label']}. ¿Cuál es {$current['label']}?",
            $state
        );
    }

    public function getState(AiConversation $conversation): ?array
    {
        return ($conversation->metadata ?? [])['procedure_flow'] ?? null;
    }

    private function answerStateQuestion(string $normalized, ?array $state): ?string
    {
        $isLastProcedure = $this->matches($normalized, [
            'cual era el ultimo tramite',
            'ultimo tramite que estaba haciendo',
            'que tramite estaba haciendo',
        ]);
        $isMissing = $this->matches($normalized, ['que datos faltan', 'cuales datos faltan', 'que falta']);
        $isCurrent = $this->matches($normalized, ['que dato me pediste', 'cual dato me pediste', 'que dato solicitaste']);
        $isSubject = $this->matches($normalized, [
            'para quien era el tramite',
            'como se llama la persona',
            'quien es la persona',
            'a nombre de quien',
            'como me llamo y que tramite necesito',
        ]);

        if (!$isLastProcedure && !$isMissing && !$isCurrent && !$isSubject) {
            return null;
        }

        if (!$state || empty($state['service_name'])) {
            return 'No hay un trámite registrado en esta conversación.';
        }

        $subject = $state['subject_name'] ?? null;
        if ($isLastProcedure) {
            return $subject
                ? "Estabas realizando el trámite {$state['service_name']} para {$subject}."
                : "Estabas realizando el trámite {$state['service_name']}.";
        }

        if ($isMissing) {
            $labels = $this->missingLabels($state);
            return $labels
                ? 'Falta capturar: ' . implode(', ', $labels) . '.'
                : 'No faltan datos configurados para este trámite.';
        }

        if ($isCurrent) {
            $current = $this->currentFieldDefinition($state);
            return $current
                ? "Te pedí el dato {$current['label']}."
                : 'No hay un dato pendiente por capturar.';
        }

        if (!$subject) {
            return 'Aún no tengo registrada la persona para este trámite.';
        }

        if (str_contains($normalized, 'que tramite')) {
            return "La persona indicada es {$subject} y el trámite es {$state['service_name']}.";
        }

        return "El trámite era para {$subject}.";
    }

    private function availableServices(): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function detectService(string $prompt, Collection $services): ?Service
    {
        $normalized = $this->normalize($prompt);

        $preferredCodes = [
            'acta de nacimiento' => 'ACT-NAC',
            'curp' => 'CURP-01',
        ];

        foreach ($preferredCodes as $phrase => $code) {
            if (preg_match('/\b' . preg_quote($phrase, '/') . '\b/u', $normalized) === 1) {
                $service = $services->firstWhere('code', $code);
                if ($service) {
                    return $service;
                }
            }
        }

        return $services
            ->sortByDesc(fn (Service $service) => mb_strlen($service->name))
            ->first(function (Service $service) use ($normalized) {
                $name = $this->normalize($service->name);
                $code = $this->normalize($service->code ?? '');

                return ($name !== '' && str_contains($normalized, $name))
                    || ($code !== '' && preg_match('/\b' . preg_quote($code, '/') . '\b/u', $normalized) === 1);
            });
    }

    private function applyService(array $state, Service $service): array
    {
        $fields = collect($service->form_schema ?? [])
            ->filter(fn ($field) => (bool) ($field['required'] ?? false))
            ->values()
            ->map(fn ($field) => [
                'name' => (string) $field['name'],
                'label' => (string) ($field['label'] ?? $field['name']),
                'type' => (string) ($field['type'] ?? 'text'),
                'required' => true,
                'regex' => $field['regex'] ?? null,
            ])
            ->all();

        $state['service_id'] = $service->id;
        $state['service_code'] = $service->code;
        $state['catalog_service_name'] = $service->name;
        $state['service_name'] = $service->code === 'CURP-01' ? 'CURP' : $service->name;
        $state['required_fields'] = $fields;
        $state['collected_fields'] = [];
        $state['missing_fields'] = array_column($fields, 'name');
        $state['current_field'] = $fields[0]['name'] ?? null;
        $state['status'] = $fields ? 'collecting' : 'ready_to_confirm';

        return $state;
    }

    private function captureCurrentField(array $state, string $prompt): array
    {
        $field = $this->currentFieldDefinition($state);
        if (!$field) {
            return ['attempted' => false, 'valid' => false, 'label' => '', 'state' => $state];
        }

        $value = trim($prompt);
        if ($field['name'] === 'curp' && preg_match('/\b([A-Z]{4}\d{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]{2})\b/i', $prompt, $match)) {
            $value = strtoupper($match[1]);
        } elseif (preg_match('/:\s*(.+)$/u', $prompt, $match)) {
            $value = trim($match[1]);
        }

        if ($value === '' || $this->looksLikeProcedureRequest($this->normalize($value))) {
            return ['attempted' => false, 'valid' => false, 'label' => $field['label'], 'state' => $state];
        }

        $valid = true;
        if (!empty($field['regex'])) {
            $valid = @preg_match($field['regex'], $value) === 1;
        }

        if ($valid) {
            $state['collected_fields'][$field['name']] = $value;
        }

        return [
            'attempted' => true,
            'valid' => $valid,
            'label' => $field['label'],
            'state' => $state,
        ];
    }

    private function refreshProgress(array $state): array
    {
        $collected = $state['collected_fields'] ?? [];
        $missing = [];

        foreach ($state['required_fields'] ?? [] as $field) {
            if (!array_key_exists($field['name'], $collected) || $collected[$field['name']] === '') {
                $missing[] = $field['name'];
            }
        }

        $state['missing_fields'] = $missing;
        $state['current_field'] = $missing[0] ?? null;
        $state['status'] = $missing ? 'collecting' : 'ready_to_confirm';

        return $state;
    }

    private function currentFieldDefinition(array $state): ?array
    {
        $current = $state['current_field'] ?? null;
        if (!$current) {
            return null;
        }

        foreach ($state['required_fields'] ?? [] as $field) {
            if (($field['name'] ?? null) === $current) {
                return $field;
            }
        }

        return null;
    }

    private function missingLabels(array $state): array
    {
        $missing = $state['missing_fields'] ?? [];

        return collect($state['required_fields'] ?? [])
            ->whereIn('name', $missing)
            ->pluck('label')
            ->values()
            ->all();
    }

    private function readyResponse(array $state): string
    {
        $details = collect($state['required_fields'] ?? [])
            ->map(function ($field) use ($state) {
                $value = $state['collected_fields'][$field['name']] ?? '';
                return "{$field['label']} {$value}";
            })
            ->implode(', ');

        return "Gracias. Tengo los datos necesarios para el trámite {$state['service_name']} de "
            . "{$state['subject_name']}: {$details}. ¿Deseas continuar con la solicitud?";
    }

    private function serviceQuestion(Collection $services): string
    {
        if ($services->isEmpty()) {
            return '¿Qué trámite necesitas? El catálogo de servicios todavía no tiene opciones cargadas.';
        }

        $options = $services->take(8)->pluck('name')->implode(', ');

        return "¿Qué trámite necesitas? Puedo ayudarte con: {$options}.";
    }

    private function extractSubjectName(string $prompt): ?string
    {
        $patterns = [
            '/\b(?:trámite|tramite)\s+(?:de\s+\S+\s+)?para\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){0,4})/u',
            '/\b(?:curp|acta de nacimiento|rfc|nss)\s+(?:para|de)\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){0,4})/iu',
            '/\b(?:mi nombre es|me llamo)\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){0,4})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $match) === 1) {
                $candidate = trim($match[1], " \t\n\r\0\x0B.,;:¿?¡!");
                if ($candidate !== '' && !in_array($this->normalize($candidate), ['una curp', 'un curp'], true)) {
                    return mb_convert_case($candidate, MB_CASE_TITLE, 'UTF-8');
                }
            }
        }

        return null;
    }

    private function looksLikeProcedureRequest(string $normalized): bool
    {
        return $this->matches($normalized, [
            'necesito ayuda con un tramite',
            'necesito un tramite',
            'quiero un tramite',
            'quiero de una',
            'quiero tramitar',
            'quiero sacar',
            'tramite para',
        ]);
    }

    private function mentionsKnownService(string $normalized): bool
    {
        return $this->matches($normalized, [
            'curp',
            'acta de nacimiento',
            'acta de defuncion',
            'acta de divorcio',
            'acta de matrimonio',
            'rfc',
            'nss',
            'constancia fiscal',
        ]);
    }

    private function emptyState(?string $subjectName = null): array
    {
        return [
            'service_id' => null,
            'service_code' => null,
            'service_name' => null,
            'catalog_service_name' => null,
            'subject_name' => $subjectName,
            'required_fields' => [],
            'collected_fields' => [],
            'missing_fields' => [],
            'current_field' => null,
            'status' => 'awaiting_service',
        ];
    }

    private function persistState(AiConversation $conversation, array $state, bool $mirrorPendingOrder = true): void
    {
        $metadata = $conversation->metadata ?? [];
        $metadata['procedure_flow'] = $state;

        if ($mirrorPendingOrder && !empty($state['service_id'])) {
            $metadata['pending_order'] = [
                'service_id' => $state['service_id'],
                'service_name' => $state['service_name'],
                'client_name' => $state['subject_name'],
                'input_data' => $state['collected_fields'],
                'missing_fields' => $state['missing_fields'],
                'current_field' => $state['current_field'],
                'status' => $state['status'],
            ];
        } else {
            unset($metadata['pending_order']);
        }

        $conversation->update(['metadata' => $metadata]);
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($text), 'UTF-8')));
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

    private function handled(string $response, ?array $state): array
    {
        return ['handled' => true, 'response' => $response, 'state' => $state];
    }

    private function notHandled(): array
    {
        return ['handled' => false, 'response' => null, 'state' => null];
    }
}
