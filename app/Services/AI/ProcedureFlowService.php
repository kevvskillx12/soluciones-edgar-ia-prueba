<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcedureFlowService
{
    public function __construct(private readonly ProcedureFieldExtractor $fieldExtractor)
    {
    }

    /**
     * @return array{handled: bool, response: ?string, state: ?array}
     */
    public function handle(AiConversation $conversation, string $prompt, ?User $user = null): array
    {
        $state = $this->getState($conversation);
        $normalized = $this->normalize($prompt);

        if ($state && $this->isCorruptState($state)) {
            $state = $this->emptyState();
            $this->persistState($conversation, $state, false);

            return $this->handled(
                'Detecté un flujo anterior incompleto. ¿Qué trámite necesitas realizar?',
                $state
            );
        }

        if ($this->isGenericGreeting($normalized)) {
            if ($state && !empty($state['service_name']) && in_array(
                $state['status'] ?? null,
                ['collecting', 'ready_to_confirm', 'awaiting_subject'],
                true
            )) {
                return $this->handled(
                    "Hola. Tienes un trámite en curso de {$state['service_name']}. "
                    . '¿Deseas continuarlo, cambiarlo o iniciar uno nuevo?',
                    $state
                );
            }

            return $this->handled(
                'Hola. Estoy listo para ayudarte con trámites. ¿Qué trámite necesitas realizar?',
                $state
            );
        }

        $creationStatusResponse = $this->answerCreationStatusQuestion($normalized, $state);
        if ($creationStatusResponse !== null) {
            return $this->handled($creationStatusResponse, $state);
        }

        if (($state['status'] ?? null) === 'completed' && $this->isConfirmation($normalized)) {
            return $this->handled($this->completedResponse($state), $state);
        }

        if (($state['status'] ?? null) === 'ready_to_confirm') {
            if ($this->isCorrection($normalized)) {
                $correction = $this->fieldExtractor->extract(
                    $prompt,
                    null,
                    [],
                    $state,
                    $state['required_fields'] ?? []
                );
                foreach ($correction['fields'] as $fieldName => $value) {
                    $state['collected_fields'][$fieldName] = $value;
                }
                if ($correction['found']) {
                    $state = $this->refreshProgress($state);
                    $this->persistState($conversation, $state);

                    return $this->handled($this->readyResponse($state), $state);
                }
            }

            if ($this->isCancellation($normalized)) {
                $state['status'] = 'cancelled';
                $state['current_field'] = null;
                $this->persistState($conversation, $state, false);

                return $this->handled('Entendido. Cancelé el trámite antes de crear la solicitud.', $state);
            }

            if ($this->isConfirmation($normalized)) {
                return $this->createOrder($conversation, $state, $user);
            }
        }

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

        if (($state['status'] ?? null) === 'completed') {
            return $this->handled($this->completedResponse($state), $state);
        }

        $services = $this->availableServices();
        $service = $this->detectService($prompt, $services);
        if (!empty($state['service_id'])
            && in_array($state['status'] ?? null, ['awaiting_subject', 'collecting', 'ready_to_confirm'], true)) {
            $service = null;
        }
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

        if ($state['current_field']) {
            $current = $this->currentFieldDefinition($state) ?? [];
            $capture = $this->fieldExtractor->extract(
                $prompt,
                $state['current_field'],
                $current,
                $state,
                $state['required_fields'] ?? []
            );

            foreach ($capture['fields'] as $fieldName => $value) {
                $state['collected_fields'][$fieldName] = $value;
            }

            if (!$capture['found'] && !$service && !$subjectName) {
                return $this->handled(
                    "No pude identificar un valor válido para {$current['label']}. "
                    . 'Por favor, indícalo nuevamente.',
                    $state
                );
            }
        }

        $state = $this->refreshProgress($state);
        $this->persistState($conversation, $state);

        if ($state['status'] === 'ready_to_confirm') {
            return $this->handled($this->readyResponse($state), $state);
        }

        $current = $this->currentFieldDefinition($state);
        $prefix = $service || $subjectName
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
            'cual fue el ultimo tramite que hice',
            'cual fue el ultimo tramite',
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
        $isDataSummary = $this->matches($normalized, [
            'datos del ultimo tramite',
            'cuales fueron los datos',
            'que datos capture',
        ]);

        if (!$isLastProcedure && !$isMissing && !$isCurrent && !$isSubject && !$isDataSummary) {
            return null;
        }

        if (!$state || empty($state['service_name'])) {
            return 'No hay un trámite registrado en esta conversación.';
        }

        $subject = $state['subject_name'] ?? null;
        if ($isLastProcedure) {
            $response = $subject
                ? "Estabas realizando el trámite {$state['service_name']} para {$subject}."
                : "Estabas realizando el trámite {$state['service_name']}.";
            if (!empty($state['order_id'])) {
                $response = rtrim($response, '.') . " · Solicitud #{$state['order_id']}.";
            }

            return $response;
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

        if ($isDataSummary) {
            $details = collect($state['required_fields'] ?? [])
                ->map(function ($field) use ($state) {
                    $value = $state['collected_fields'][$field['name']] ?? null;
                    return $value !== null ? "{$field['label']}: {$value}" : null;
                })
                ->filter()
                ->implode(', ');

            return $details !== ''
                ? "Datos del trámite {$state['service_name']}: {$details}."
                : "El trámite {$state['service_name']} todavía no tiene datos capturados.";
        }

        if (!$subject) {
            return 'Aún no tengo registrada la persona para este trámite.';
        }

        if (str_contains($normalized, 'que tramite')) {
            return "La persona indicada es {$subject} y el trámite es {$state['service_name']}.";
        }

        return "El trámite era para {$subject}.";
    }

    private function answerCreationStatusQuestion(string $normalized, ?array $state): ?string
    {
        if (!$this->matches($normalized, [
            'ya la hiciste',
            'creaste la solicitud',
            'creaste el pedido',
            'cual es el folio',
            'cual es la solicitud',
            'ya fue creada',
        ])) {
            return null;
        }

        if (!$state) {
            return 'No hay una solicitud registrada en esta conversación.';
        }

        if (($state['status'] ?? null) === 'completed' && !empty($state['order_id'])) {
            return $this->completedResponse($state);
        }

        if (($state['status'] ?? null) === 'ready_to_confirm') {
            return 'Ya tengo todos los datos, pero falta tu confirmación para crear la solicitud.';
        }

        if (($state['status'] ?? null) === 'collecting') {
            $labels = $this->missingLabels($state);
            return 'Todavía no se crea la solicitud. Falta capturar: ' . implode(', ', $labels) . '.';
        }

        if (($state['status'] ?? null) === 'cancelled') {
            return 'El trámite fue cancelado y no se creó ninguna solicitud.';
        }

        return 'La solicitud todavía no ha sido creada.';
    }

    private function createOrder(AiConversation $conversation, array $state, ?User $user): array
    {
        if (!empty($state['order_id'])) {
            $state['status'] = 'completed';
            $this->persistState($conversation, $state, false);

            return $this->handled($this->completedResponse($state), $state);
        }

        if (!$user) {
            $state['last_error'] = 'authentication_required';
            $this->persistState($conversation, $state);

            return $this->handled(
                'Ya tengo los datos, pero necesito que inicies sesión para crear la solicitud.',
                $state,
                'ERROR'
            );
        }

        $service = Service::find($state['service_id'] ?? null);
        if (!$service) {
            $state['last_error'] = 'service_not_found';
            $this->persistState($conversation, $state);

            return $this->handled(
                'Ya tengo los datos, pero el servicio ya no está disponible en el catálogo.',
                $state,
                'ERROR'
            );
        }

        if (!$user->is_admin && (float) $user->balance < (float) $service->price) {
            $state['last_error'] = 'insufficient_balance';
            $this->persistState($conversation, $state);

            return $this->handled(
                "Ya tengo los datos, pero necesitas saldo suficiente antes de crear la orden. "
                . "Costo: \${$service->price}. Saldo disponible: \${$user->balance}.",
                $state,
                'ERROR'
            );
        }

        try {
            $order = DB::transaction(function () use ($user, $service, $state) {
                return Order::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'input_data' => array_merge(
                        $state['collected_fields'] ?? [],
                        ['subject_name' => $state['subject_name']]
                    ),
                    'status' => 'pending',
                    'price_at_purchase' => $service->price,
                    'admin_notes' => "Solicitud creada desde AI Chat para {$state['subject_name']}.",
                ]);
            });

            $state['status'] = 'completed';
            $state['order_id'] = $order->id;
            $state['order_status'] = $order->status;
            $state['completed_at'] = now()->toDateTimeString();
            $state['last_error'] = null;
            $this->persistState($conversation, $state, false);

            return $this->handled($this->completedResponse($state, true), $state, 'SUCCESS', $order->id);
        } catch (\Throwable $exception) {
            report($exception);
            $state['last_error'] = 'order_creation_failed';
            $this->persistState($conversation, $state);

            return $this->handled(
                'Ya tengo los datos, pero no pude crear la solicitud de forma segura. '
                . 'Verifica el saldo y vuelve a intentarlo.',
                $state,
                'ERROR'
            );
        }
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
            'constancia de situacion fiscal' => 'CSF-02',
            'constancia fiscal' => 'CSF-02',
            'descarga de constancia' => 'CSF-02',
            'consulta de rfc' => 'CSF-02',
            'consultar rfc' => 'CSF-02',
            'para rfc' => 'CSF-02',
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
            ->map(fn ($field, $index) => array_merge($field, [
                'name' => (string) $field['name'],
                'label' => (string) ($field['label'] ?? $field['name']),
                'type' => (string) ($field['type'] ?? 'text'),
                'required' => true,
                'order' => $field['order'] ?? $index,
            ]))
            ->sortBy('order')
            ->values()
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

    private function completedResponse(array $state, bool $justCreated = false): string
    {
        $prefix = $justCreated ? 'Listo. Creé' : 'Sí. Ya fue creada';
        $orderId = $state['order_id'];
        $service = $state['service_name'];
        $subject = $state['subject_name'];

        return "{$prefix} la solicitud #{$orderId} para el trámite {$service} de {$subject}. "
            . 'Quedó en estado pendiente.';
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
            '/\b(?:a nombre de|del cliente|para la persona|para)\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){1,3})/iu',
            '/\b(?:mi nombre es|me llamo)\s+([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:\s+[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+){1,3})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $prompt, $match) === 1) {
                $candidate = trim($match[1], " \t\n\r\0\x0B.,;:¿?¡!");
                if ($candidate !== '' && $this->isValidSubjectName($candidate)) {
                    return mb_convert_case($candidate, MB_CASE_TITLE, 'UTF-8');
                }
            }
        }

        return null;
    }

    private function isValidSubjectName(string $candidate): bool
    {
        $forbidden = [
            'tramitar',
            'tramite',
            'curp',
            'acta',
            'nacimiento',
            'solicitud',
            'servicio',
            'una',
            'un',
        ];

        $words = preg_split('/\s+/u', $this->normalize($candidate), -1, PREG_SPLIT_NO_EMPTY);

        return count($words) >= 2
            && collect($words)->every(fn (string $word) => !in_array($word, $forbidden, true));
    }

    private function isGenericGreeting(string $normalized): bool
    {
        return preg_match(
            '/^(hola(?:\s+como estas)?|buenos dias|buenas tardes|buenas noches|que tal|ayuda)[.!?]*$/u',
            $normalized
        ) === 1;
    }

    private function isCorruptState(array $state): bool
    {
        $subject = $state['subject_name'] ?? $state['person_name'] ?? null;
        if ($subject && !$this->isValidSubjectName((string) $subject)) {
            return true;
        }

        $serviceId = $state['service_id'] ?? null;
        if (!$serviceId) {
            return !empty($state['service_name'])
                || !empty($state['current_field'])
                || !empty($state['required_fields']);
        }

        $service = Service::find($serviceId);
        if (!$service) {
            return true;
        }

        $serviceName = $state['service_name'] ?? null;
        $validNames = [$service->name];
        if ($service->code === 'CURP-01') {
            $validNames[] = 'CURP';
        }
        if (!$serviceName || !in_array($serviceName, $validNames, true)) {
            return true;
        }

        $requiredFields = collect($state['required_fields'] ?? []);
        if (in_array($state['status'] ?? null, ['collecting', 'ready_to_confirm'], true)
            && $requiredFields->isEmpty()) {
            return true;
        }

        $currentField = $state['current_field'] ?? null;

        return $currentField !== null
            && !$requiredFields->contains(fn ($field) => ($field['name'] ?? null) === $currentField);
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
            'order_id' => null,
            'order_status' => null,
            'completed_at' => null,
            'last_error' => null,
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

    private function isConfirmation(string $normalized): bool
    {
        if ($normalized === 'si') {
            return true;
        }

        return preg_match(
            '/\b(continua|deseo continuar|continuar solicitud|procede|termina la solicitud|hazlo|termina y procede)\b/u',
            $normalized
        ) === 1;
    }

    private function isCancellation(string $normalized): bool
    {
        return $normalized === 'no'
            || preg_match('/\b(cancelar|cancela|no continuar|mejor no)\b/u', $normalized) === 1;
    }

    private function isCorrection(string $normalized): bool
    {
        return preg_match('/\b(me equivoque|correct[oa]|corrige|cambia)\b/u', $normalized) === 1;
    }

    private function handled(
        string $response,
        ?array $state,
        string $toolStatus = 'SUCCESS',
        ?int $orderId = null
    ): array
    {
        return [
            'handled' => true,
            'response' => $response,
            'state' => $state,
            'tool_status' => $toolStatus,
            'order_id' => $orderId ?? ($state['order_id'] ?? null),
        ];
    }

    private function notHandled(): array
    {
        return [
            'handled' => false,
            'response' => null,
            'state' => null,
            'tool_status' => null,
            'order_id' => null,
        ];
    }
}
