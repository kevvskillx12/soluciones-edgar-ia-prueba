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

        if ($this->isCapabilityQuestion($normalized)) {
            return $this->handled($this->capabilityResponse(), $state);
        }

        $preparedResponse = $this->answerPreparedQuestion($normalized, $state);
        if ($preparedResponse !== null) {
            return $this->handled($preparedResponse, $state);
        }

        $creationStatusResponse = $this->answerCreationStatusQuestion($normalized, $state);
        if ($creationStatusResponse !== null) {
            return $this->handled($creationStatusResponse, $state);
        }

        $assignmentResult = $this->applyUserAssignmentFromPrompt($conversation, $state, $prompt, $user);
        if ($assignmentResult['error'] !== null) {
            return $this->handled($assignmentResult['error'], $assignmentResult['state']);
        }
        $state = $assignmentResult['state'];
        $procedurePrompt = $this->stripAssignmentDirective($prompt);

        if (($state['status'] ?? null) === 'completed' && $this->isConfirmation($normalized)) {
            return $this->handled($this->completedResponse($state), $state);
        }

        if (($state['status'] ?? null) === 'ready_to_confirm') {
            if ($this->isCorrection($normalized)) {
                $correction = $this->fieldExtractor->extract(
                    $procedurePrompt,
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
            'deten el tramite',
            'detener tramite',
            'mejor cancelalo',
            'salir del tramite',
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
            'quiero crear otro tramite',
            'puedo crear otro',
            'puedo hacer otro',
            'crear otro tramite',
            'hacer otro tramite',
            'tramitar otro',
            'otra solicitud',
            'crear otra solicitud',
            'hacer otra solicitud',
            'nuevo tramite',
            'iniciar otro tramite',
            'empezar otro tramite',
            'vamos con otro tramite',
        ])) {
            $shouldStartClean = ($state['status'] ?? null) === 'completed'
                || $this->matches($normalized, [
                    'quiero crear otro tramite',
                    'puedo crear otro',
                    'puedo hacer otro',
                    'crear otro tramite',
                    'hacer otro tramite',
                    'tramitar otro',
                    'otra solicitud',
                    'crear otra solicitud',
                    'hacer otra solicitud',
                    'nuevo tramite',
                    'iniciar otro tramite',
                    'empezar otro tramite',
                ]);
            $subjectName = $shouldStartClean ? null : ($state['subject_name'] ?? $this->extractSubjectName($prompt));
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
        $service = $this->detectService($procedurePrompt, $services);
        if (!empty($state['service_id'])
            && in_array($state['status'] ?? null, ['awaiting_subject', 'collecting', 'ready_to_confirm'], true)) {
            $service = null;
        }
        $subjectName = $this->extractSubjectName($procedurePrompt);
        if (!$subjectName
            && !empty($state['service_id'])
            && ($state['status'] ?? null) === 'awaiting_subject'
            && !$service) {
            $subjectName = $this->extractDirectSubjectName($prompt);
        }

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

            if (($state['service_id'] ?? null) && !$service && $this->looksLikeIncompleteSubjectResponse($prompt)) {
                return $this->handled(
                    "Necesito el nombre completo de la persona para el trámite {$state['service_name']}. "
                    . 'Por ejemplo: "Kevin Montero".',
                    $state
                );
            }

            return $this->handled(
                "Seleccionaste el trámite {$state['service_name']}. ¿Para quién es el trámite?",
                $state
            );
        }

        if ($state['current_field']) {
            $current = $this->currentFieldDefinition($state) ?? [];
            $capture = $this->fieldExtractor->extract(
                $procedurePrompt,
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
            'que tramite hice',
            'que estaba haciendo',
            'ultimo tramite',
            'mi ultimo tramite',
        ]);
        $isMissing = $this->matches($normalized, [
            'que datos faltan',
            'cuales datos faltan',
            'que falta',
            'que dato falta',
            'que me falta',
            'que falta capturar',
        ]);
        $isCurrent = $this->matches($normalized, [
            'que dato me pediste',
            'cual dato me pediste',
            'que dato solicitaste',
            'que dato sigue',
            'que me pediste',
            'que necesitas ahora',
        ]);
        $isSubject = $this->matches($normalized, [
            'para quien era el tramite',
            'como se llama la persona',
            'quien es la persona',
            'a nombre de quien',
            'como me llamo y que tramite necesito',
            'para quien es',
            'para quien era',
            'quien era el cliente',
            'a quien le estoy haciendo el tramite',
        ]);
        $isDataSummary = $this->matches($normalized, [
            'datos del ultimo tramite',
            'cuales fueron los datos',
            'que datos capture',
            'resumen del tramite',
            'muestrame los datos',
            'datos capturados',
        ]);

        if (!$isLastProcedure && !$isMissing && !$isCurrent && !$isSubject && !$isDataSummary) {
            return null;
        }

        if (!$state || empty($state['service_name'])) {
            return 'No hay un trámite registrado en esta conversación.';
        }

        $subject = $state['subject_name'] ?? null;
        if ($isLastProcedure && !$isDataSummary) {
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
            'ya quedo',
            'ya quedo la solicitud',
            'ya la creaste',
            'numero de solicitud',
            'id de orden',
            'folio de la solicitud',
            'dame el folio',
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

    private function applyUserAssignmentFromPrompt(
        AiConversation $conversation,
        ?array $state,
        string $prompt,
        ?User $currentUser
    ): array {
        $email = $this->extractAssignmentEmail($prompt);
        if ($email === null) {
            return ['state' => $state, 'error' => null];
        }

        $state ??= $this->emptyState();

        if (!$currentUser) {
            return [
                'state' => $state,
                'error' => 'Necesito que inicies sesión para asignar la solicitud a un usuario.',
            ];
        }

        if (!$currentUser->is_admin && strcasecmp($currentUser->email, $email) !== 0) {
            return [
                'state' => $state,
                'error' => 'Solo un administrador puede asignar solicitudes a otro usuario.',
            ];
        }

        $targetUser = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email, 'UTF-8')])
            ->first();

        if (!$targetUser) {
            return [
                'state' => $state,
                'error' => "No encontré un usuario registrado con el correo {$email}. "
                    . 'Créalo primero en Usuarios o indícame otro correo.',
            ];
        }

        $state['assigned_user_id'] = $targetUser->id;
        $state['assigned_user_email'] = $targetUser->email;
        $state['assigned_user_name'] = $targetUser->name;

        $this->persistState($conversation, $state);

        return ['state' => $state, 'error' => null];
    }

    private function extractAssignmentEmail(string $prompt): ?string
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $prompt, $match) !== 1) {
            return null;
        }

        $normalized = $this->normalize($prompt);
        if (!$this->matches($normalized, [
            'asigna',
            'asignala',
            'asignalo',
            'ponla',
            'ponlo',
            'ponga',
            'usuario',
            'cliente',
            'cuenta',
            'correo del usuario',
            'correo del cliente',
            'para el correo',
        ])) {
            return null;
        }

        return mb_strtolower($match[0], 'UTF-8');
    }

    private function stripAssignmentDirective(string $prompt): string
    {
        $email = $this->extractAssignmentEmail($prompt);
        if ($email === null) {
            return $prompt;
        }

        $escapedEmail = preg_quote($email, '/');
        $clean = preg_replace(
            '/\s*\b(?:asign(?:a|ala|alo|ar)|pon(?:la|lo)?|ponga|para\s+(?:el\s+)?(?:usuario|cliente|correo|cuenta)|al\s+(?:usuario|cliente)|usuario|cliente)\s+(?:a\s+)?' . $escapedEmail . '\b/iu',
            '',
            $prompt
        );

        return trim((string) $clean, " \t\n\r\0\x0B.,;:¿?¡!");
    }

    private function answerPreparedQuestion(string $normalized, ?array $state): ?string
    {
        if ($this->matches($normalized, [
            'quien eres',
            'que eres',
            'eres humano',
            'eres una persona',
            'eres un bot',
            'eres inteligencia artificial',
            'como te llamas',
        ])) {
            return 'Soy el asistente de Soluciones Edgar. Puedo orientarte sobre trámites, capturar datos, '
                . 'crear solicitudes y recordar el estado de esta conversación.';
        }

        if ($this->matches($normalized, [
            'como funciona',
            'como uso el chat',
            'como empiezo',
            'que hago primero',
            'que sigue',
            'pasos para usar',
            'explicame el proceso',
        ]) && !$this->matches($normalized, ['saldo', 'balance', 'usuario'])) {
            if ($state && !empty($state['service_name'])) {
                return "Estamos con {$state['service_name']}. " . $this->nextStepHint($state);
            }

            return 'Funciona así: dime el trámite y la persona, te pediré solo los datos necesarios, '
                . 'confirmaré el resumen y, cuando escribas "sí, hazlo", crearé la solicitud.';
        }

        if ($this->matches($normalized, [
            'catalogo completo',
            'catalogo de servicios',
            'lista de servicios',
            'todos los servicios',
            'todos los tramites',
            'que servicios manejan',
            'servicios disponibles',
        ])) {
            return $this->catalogResponse();
        }

        if ($this->matches($normalized, [
            'dame ejemplos',
            'ejemplos de uso',
            'frases de ejemplo',
            'que puedo preguntar',
            'como le pido un tramite',
            'repertorio',
            'menu',
        ])) {
            return $this->examplesResponse();
        }

        if ($this->matches($normalized, [
            'que necesito',
            'requisitos',
            'que datos piden',
            'que datos necesito',
            'que documentos necesito',
            'documentos necesarios',
            'datos necesarios',
        ])) {
            return $this->requirementsResponse($normalized, $state);
        }

        if ($this->matches($normalized, [
            'puedes hacer',
            'pueden hacer',
            'haces',
            'manejas',
            'tienes el tramite',
            'tienen el tramite',
            'trabajan',
        ])) {
            $availability = $this->serviceAvailabilityResponse($normalized);
            if ($availability !== null) {
                return $availability;
            }
        }

        if ($this->matches($normalized, [
            'formato de curp',
            'como va la curp',
            'curp valida',
            'validar curp',
            'formato de rfc',
            'como va el rfc',
            'rfc valido',
            'validar rfc',
        ])) {
            return $this->formatHelpResponse($normalized);
        }

        if ($this->matches($normalized, [
            'cuanto cuesta',
            'cual es el costo',
            'precio',
            'tarifa',
            'cuanto vale',
            'cuanto cobran',
        ])) {
            return $this->priceResponse($normalized, $state);
        }

        if ($this->matches($normalized, [
            'cuanto tarda',
            'tiempo de entrega',
            'cuando queda',
            'en cuanto tiempo',
            'horario',
            'a que hora',
            'estan abiertos',
            'atienden',
        ])) {
            return $this->scheduleResponse($normalized, $state);
        }

        if ($this->matches($normalized, [
            'es seguro',
            'mis datos',
            'privacidad',
            'datos personales',
            'puedo mandar documentos',
            'documentos completos',
            'informacion sensible',
        ])) {
            return 'Para seguridad, comparte solo los datos que el trámite solicite. '
                . 'No envíes contraseñas, códigos bancarios ni información que no sea necesaria para la solicitud.';
        }

        if ($this->matches($normalized, [
            'quiero pagar',
            'como pago',
            'deposito',
            'transferencia',
            'tarjeta',
            'cuenta bancaria',
            'transaccion financiera',
        ])) {
            return 'Desde este chat no proceso depósitos, transferencias ni datos bancarios. '
                . 'Aquí puedo ayudarte a preparar y crear la solicitud del trámite disponible.';
        }

        if ($this->matches($normalized, [
            'quiero hablar con una persona',
            'asesor humano',
            'soporte',
            'contacto',
            'whatsapp',
            'administrador',
        ])) {
            return 'Puedo ayudarte desde el chat con trámites y solicitudes. '
                . 'Si necesitas atención humana, revisa los datos de contacto oficiales de Soluciones Edgar o consulta al administrador.';
        }

        if ($this->matches($normalized, [
            'guia admin',
            'guia del admin',
            'que puede hacer el admin',
            'como modifica el admin',
            'como elimino',
            'puedes eliminar',
            'puedes modificar',
            'editar solicitud',
            'modificar solicitud',
            'eliminar solicitud',
            'rechazar solicitud',
            'completar solicitud',
            'panel de tramites',
        ])) {
            return 'Guia rapida para admin: desde el chat puedo crear solicitudes nuevas, capturar datos, asignarlas a un usuario por correo, '
                . 'consultar el ultimo tramite, decir que datos faltan, cancelar un flujo antes de crear la orden o cambiar de tramite. '
                . 'Para modificar, completar, rechazar o eliminar una solicitud ya creada, usa el panel de Tramites/Ordenes de Filament; '
                . 'por seguridad la IA no borra ni edita registros finales directamente. '
                . 'Frases utiles: "Acta de nacimiento para Kevin Montero asignala a cliente@email.com", "que datos faltan?", '
                . '"si, hazlo", "cancela este tramite" o "quiero cambiar de tramite".';
        }

        if ($this->matches($normalized, [
            'no puedo entrar',
            'no me deja entrar',
            'no llega el correo',
            'activar cuenta',
            'verificar cuenta',
            'olvide mi contrasena',
            'recuperar contrasena',
            'credenciales no coinciden',
            'mi cuenta',
        ])) {
            return 'Si tienes problema con tu cuenta, revisa que el correo esté escrito completo, usa la opción de recuperar contraseña '
                . 'si aplica y confirma con el administrador que tu cuenta esté activa. Por seguridad, no compartas contraseñas en el chat.';
        }

        if ($this->matches($normalized, [
            'no funciona',
            'sale error',
            'me marca error',
            'se trabo',
            'se quedo cargando',
            'no responde',
            'fallo',
            'problema con el chat',
        ])) {
            return 'Si algo falla, intenta actualizar la página y enviar el mensaje otra vez. '
                . 'Si ya había un trámite en curso, puedo retomarlo con esta conversación usando el folio o el último estado guardado.';
        }

        if ($this->matches($normalized, [
            'si',
            'si hazlo',
            'hazlo',
            'continua',
            'continuar',
            'termina',
            'termina la solicitud',
            'creala',
            'crealo',
        ]) && (!$state || empty($state['service_name']))) {
            return 'Todavia no tengo una solicitud lista para crear. Primero dime el tramite y la persona. '
                . 'Ejemplos: "CURP para Kevin Montero" o "Acta de nacimiento para Luis Ek".';
        }

        if ($this->matches($normalized, [
            'folio',
            'id de solicitud',
            'numero de solicitud',
            'ya la hiciste',
            'ya lo hiciste',
            'se creo',
            'esta creada',
        ]) && (!$state || empty($state['order_id']))) {
            return 'Todavia no hay una solicitud creada en esta conversacion. '
                . 'Cuando tenga tramite, persona y datos requeridos, escribe "si, hazlo" para crearla.';
        }

        if ($this->matches($normalized, [
            'crear usuario',
            'registrar usuario',
            'nuevo usuario',
            'dar de alta usuario',
            'asignar usuario',
            'correo de usuario',
            'usuario no existe',
        ])) {
            return 'Puedo asignar una solicitud a un usuario existente si me das su correo. '
                . 'Ejemplo: "Acta de nacimiento para Kevin Montero asignala a cliente@email.com". '
                . 'Si el usuario no existe, primero crealo desde el panel de Usuarios.';
        }

        if ($this->matches($normalized, [
            'subir archivo',
            'subir documento',
            'adjuntar archivo',
            'adjuntar documento',
            'pdf',
            'comprobante',
            'resultado del tramite',
        ])) {
            return 'El chat puede guiar la captura y crear la solicitud. '
                . 'Para subir resultados, comprobantes o documentos finales usa el panel correspondiente de Filament, '
                . 'por ejemplo Tramites/Ordenes o Solicitudes de saldo.';
        }

        if ($this->matches($normalized, [
            'microfono',
            'mic',
            'voz',
            'no escucha',
            'no aparece el microfono',
            'no puedo dictar',
        ])) {
            return 'El microfono depende del navegador. En Chrome/Edge debes permitir permisos de microfono y usar HTTPS o localhost. '
                . 'Si no funciona, escribe el mensaje manualmente; el flujo del tramite no se pierde.';
        }

        if ($this->matches($normalized, [
            'celular',
            'movil',
            'pantalla chica',
            'responsive',
            'se ve mal',
            'no cabe',
        ]) && !$this->hasActiveProcedureState($state)) {
            return 'En movil puedes usar el mismo chat. Si algo no cabe, gira el telefono o actualiza la pagina. '
                . 'El input, microfono, nuevo chat y envio deben seguir disponibles.';
        }

        if ($this->matches($normalized, [
            'cuantas personas',
            'muchos usuarios',
            'varios usuarios',
            '10 personas',
            'diez personas',
            'concurrencia',
            'al mismo tiempo',
        ])) {
            return 'El sistema separa conversaciones por conversation_id, asi que varias personas pueden usar el chat sin mezclar memoria. '
                . 'El rendimiento real depende de Docker, SQLite, Ollama y la capacidad de la computadora.';
        }

        if ($state && !empty($state['service_name']) && $this->matches($normalized, [
            'no entiendo',
            'me confundi',
            'estoy confundido',
            'que sigue',
            'que hago ahora',
            'explicalo mejor',
        ])) {
            return "No te preocupes. Estamos con {$state['service_name']}. " . $this->nextStepHint($state);
        }

        if ($this->matches($normalized, [
            'cuentame un chiste',
            'dime un chiste',
            'hazme reir',
        ])) {
            return 'Puedo intentarlo: ¿por qué el trámite fue al gimnasio? Porque quería estar en forma. '
                . 'Ya en serio, puedo ayudarte con trámites y solicitudes. Opciones: "CURP para Kevin Montero", '
                . '"que datos faltan?" o "guia admin".';
        }

        if ($this->matches($normalized, [
            'no se',
            'no se que poner',
            'que pongo',
            'que escribo',
            'no entiendo',
            'estoy perdido',
            'ayudame',
            'hazlo tu',
            'lo que sea',
            'cualquier cosa',
            'prueba',
            'test',
            'asdf',
            'jaja',
            'xd',
            'ok',
            'va',
            'sale',
            'gracias',
            'eres real',
            'me escuchas',
            'estas ahi',
            'que onda',
            'quien te hizo',
            'cuentame un chiste',
            'te amo',
            'eres tonto',
            'no sirves',
            'quiero comida',
            'quiero jugar',
            'dame dinero',
            'haz mi tarea',
            'busca en google',
            'abre whatsapp',
            'llama a alguien',
        ]) && !$this->hasActiveProcedureState($state)) {
            return 'Estoy aqui para ayudarte con tramites de Soluciones Edgar. '
                . 'Si no sabes que escribir, usa una de estas opciones: '
                . '1) "CURP para Kevin Montero". '
                . '2) "Acta de nacimiento para Kevin Montero". '
                . '3) "Que servicios manejas?". '
                . '4) "Que datos faltan?". '
                . '5) "guia admin". '
                . 'Si tu pregunta no es de tramites, puedo orientarte de forma general, pero no hago acciones fuera del sistema.';
        }

        if ($this->matches($normalized, [
            'corregir dato',
            'cambiar dato',
            'me equivoque',
            'dato incorrecto',
            'editar dato',
            'corrige el dato',
        ])) {
            if ($state && !empty($state['service_name'])) {
                return null;
            }

            return 'Puedes corregir datos durante un trámite activo. Primero dime qué trámite necesitas realizar.';
        }

        if ($this->matches($normalized, [
            'nuevo chat',
            'nueva conversacion',
            'empezar de cero',
            'borrar conversacion',
            'reiniciar chat',
        ])) {
            return 'Puedes usar el botón "Nuevo chat" para empezar una conversación limpia. '
                . 'Si solo quieres cambiar de trámite aquí, escribe "quiero cambiar de trámite".';
        }

        if ($this->matches($normalized, [
            'donde veo mis solicitudes',
            'donde veo mi pedido',
            'donde reviso la orden',
            'donde esta mi solicitud',
            'historial de solicitudes',
            'mis ordenes',
            'mis pedidos',
        ])) {
            return 'Puedes revisar tus solicitudes en el panel de la aplicación. '
                . 'Si esta conversación ya creó una solicitud, también puedo decirte el ID o folio aquí mismo.';
        }

        if ($this->matches($normalized, [
            'gracias',
            'muchas gracias',
            'ok gracias',
            'perfecto gracias',
        ])) {
            return $state && !empty($state['service_name'])
                ? "Con gusto. Seguimos con el trámite {$state['service_name']} cuando quieras."
                : 'Con gusto. Cuando quieras, dime qué trámite necesitas realizar.';
        }

        if ($this->matches($normalized, [
            'adios',
            'hasta luego',
            'nos vemos',
            'bye',
            'salir',
        ])) {
            return 'De acuerdo. Cuando necesites otro trámite, aquí estaré para ayudarte.';
        }

        if ($this->matches($normalized, [
            'no entiendo',
            'me confundi',
            'estoy confundido',
            'explicalo mejor',
            'que significa',
        ])) {
            return $state && !empty($state['service_name'])
                ? "No te preocupes. Estamos con {$state['service_name']}. "
                    . $this->nextStepHint($state)
                : 'No te preocupes. Solo dime el trámite que necesitas, por ejemplo: CURP, acta de nacimiento, RFC, NSS o constancia fiscal.';
        }

        if ($this->isClearlyOutOfScopeQuestion($normalized)) {
            return 'Puedo ayudarte principalmente con trámites y solicitudes de Soluciones Edgar. '
                . 'Si quieres, dime qué trámite necesitas o escribe "qué trámites manejas".';
        }

        return null;
    }

    private function priceResponse(string $normalized, ?array $state): string
    {
        $service = $this->serviceFromStateOrPrompt($state, $normalized);

        if ($service) {
            return "El trámite {$service->name} tiene un costo de \${$service->price}. "
                . 'Si quieres iniciarlo, dime para quién es.';
        }

        return 'El costo depende del trámite. Dime cuál necesitas, por ejemplo CURP, acta de nacimiento, RFC o NSS, y te ayudo a identificarlo.';
    }

    private function scheduleResponse(string $normalized, ?array $state): string
    {
        $service = $this->serviceFromStateOrPrompt($state, $normalized);

        if ($service) {
            return "Para {$service->name}, el horario configurado es {$service->active_schedule} "
                . "y el tiempo estimado es {$service->processing_time}.";
        }

        return 'El horario y tiempo dependen del trámite. Dime cuál necesitas y te indico la información disponible.';
    }

    private function catalogResponse(): string
    {
        $services = $this->availableServices();

        if ($services->isEmpty()) {
            return 'Por ahora no hay servicios activos cargados en el catálogo.';
        }

        $options = $services
            ->take(20)
            ->pluck('name')
            ->implode(', ');

        $suffix = $services->count() > 20
            ? ' Hay más servicios en el catálogo; dime el nombre o tipo de trámite que buscas.'
            : '';

        return "Servicios disponibles: {$options}.{$suffix} Para iniciar uno, escribe algo como: \"Acta de nacimiento para Kevin Montero\".";
    }

    private function examplesResponse(): string
    {
        return 'Puedes escribir frases como: "CURP para Kevin Montero", "acta de nacimiento para Luis Ek", '
            . '"puedes tramitarme mi RFC", "qué datos faltan", "qué dato me pediste", '
            . '"cuánto cuesta el acta de nacimiento", "si, hazlo", "ya la hiciste", '
            . '"cancela este trámite" o "quiero cambiar de trámite".';
    }

    private function requirementsResponse(string $normalized, ?array $state): string
    {
        $service = $this->serviceFromStateOrPrompt($state, $normalized);

        if (!$service) {
            return 'Dime de qué trámite quieres conocer requisitos, por ejemplo: "qué necesito para acta de nacimiento" o "requisitos para RFC".';
        }

        $fields = collect($service->form_schema ?? [])
            ->filter(fn ($field) => (bool) ($field['required'] ?? false))
            ->map(fn ($field) => (string) ($field['label'] ?? $field['name']))
            ->filter()
            ->values()
            ->all();

        if (empty($fields)) {
            return "El trámite {$service->name} no tiene datos obligatorios configurados en este momento.";
        }

        if ($state && !empty($state['service_id']) && (int) $state['service_id'] === (int) $service->id) {
            $missing = $this->missingLabels($state);

            return $missing
                ? "Para {$service->name} falta capturar: " . implode(', ', $missing) . '.'
                : "Para {$service->name} ya tengo capturados los datos requeridos.";
        }

        return "Para {$service->name} necesito: " . implode(', ', $fields) . '.';
    }

    private function serviceAvailabilityResponse(string $normalized): ?string
    {
        $service = $this->detectService($normalized, $this->availableServices());

        if (!$service) {
            return null;
        }

        return "Sí, puedo ayudarte con {$service->name}. "
            . "Costo configurado: \${$service->price}. Tiempo estimado: {$service->processing_time}. "
            . 'Para iniciarlo, dime para quién es el trámite.';
    }

    private function formatHelpResponse(string $normalized): string
    {
        if (str_contains($normalized, 'rfc')) {
            return 'El RFC normalmente usa 12 o 13 caracteres: letras iniciales, fecha en formato AAMMDD y homoclave. '
                . 'Puedo revisar el formato básico cuando lo captures, pero la validez oficial depende del SAT.';
        }

        return 'La CURP normalmente tiene 18 caracteres con letras, fecha, sexo, entidad y homoclave. '
            . 'Puedo revisar el formato básico cuando la captures, pero la validez oficial depende de la fuente oficial.';
    }

    private function serviceFromStateOrPrompt(?array $state, string $normalized): ?Service
    {
        if (!empty($state['service_id'])) {
            return Service::find($state['service_id']);
        }

        return $this->detectService($normalized, $this->availableServices());
    }

    private function nextStepHint(array $state): string
    {
        if (($state['status'] ?? null) === 'awaiting_subject') {
            return 'Necesito saber para quién es el trámite.';
        }

        if (($state['status'] ?? null) === 'collecting') {
            $current = $this->currentFieldDefinition($state);

            return $current
                ? "El dato pendiente es {$current['label']}."
                : 'Todavía faltan datos por capturar.';
        }

        if (($state['status'] ?? null) === 'ready_to_confirm') {
            return 'Ya tengo los datos; falta que confirmes si deseas crear la solicitud.';
        }

        if (($state['status'] ?? null) === 'completed' && !empty($state['order_id'])) {
            return "La solicitud ya fue creada con ID #{$state['order_id']}.";
        }

        return 'Dime cómo quieres continuar.';
    }

    private function isClearlyOutOfScopeQuestion(string $normalized): bool
    {
        if (!str_contains($normalized, '?') && !preg_match('/\b(quiero|puedes|dime|hazme|explica|ayudame|cuentame)\b/u', $normalized)) {
            return false;
        }

        return $this->matches($normalized, [
            'clima',
            'chiste',
            'poema',
            'cancion',
            'receta',
            'futbol',
            'pelicula',
            'musica',
            'tarea',
            'matematicas',
            'programar',
            'codigo',
            'amor',
            'consejo personal',
            'noticias',
            'politica',
            'medicina',
            'salud',
            'inversion',
            'prestamo',
            'criptomonedas',
        ]);
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

        $orderUser = $this->orderUserForState($state, $user);
        if (!$orderUser) {
            $state['last_error'] = 'assigned_user_not_found';
            $this->persistState($conversation, $state);

            return $this->handled(
                'Ya tengo los datos, pero no encontré el usuario al que se debe asignar la solicitud. '
                . 'Verifica el correo o créalo primero.',
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

        if (!$user->is_admin && (float) $orderUser->balance < (float) $service->price) {
            $state['last_error'] = 'insufficient_balance';
            $this->persistState($conversation, $state);

            return $this->handled(
                "Ya tengo los datos, pero necesitas saldo suficiente antes de crear la orden. "
                . "Costo: \${$service->price}. Saldo disponible: \${$orderUser->balance}.",
                $state,
                'ERROR'
            );
        }

        try {
            $order = DB::transaction(function () use ($orderUser, $user, $service, $state) {
                return Order::create([
                    'user_id' => $orderUser->id,
                    'service_id' => $service->id,
                    'input_data' => array_merge(
                        $state['collected_fields'] ?? [],
                        ['subject_name' => $state['subject_name']]
                    ),
                    'status' => 'pending',
                    'price_at_purchase' => $service->price,
                    'admin_notes' => $this->orderAdminNotes($state, $user, $orderUser),
                ]);
            });

            $state['status'] = 'completed';
            $state['order_id'] = $order->id;
            $state['order_status'] = $order->status;
            $state['order_user_id'] = $orderUser->id;
            $state['order_user_email'] = $orderUser->email;
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

    private function orderUserForState(array $state, User $currentUser): ?User
    {
        if (!empty($state['assigned_user_id'])) {
            return User::find($state['assigned_user_id']);
        }

        return $currentUser;
    }

    private function orderAdminNotes(array $state, User $actor, User $orderUser): string
    {
        $notes = "Solicitud creada desde AI Chat para {$state['subject_name']}.";

        if ($actor->id !== $orderUser->id) {
            $notes .= " Asignada al usuario {$orderUser->email} por {$actor->email}.";
        }

        return $notes;
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
            'acta nacimiento' => 'ACT-NAC',
            'acta de defuncion' => 'ACT-DEF',
            'acta defuncion' => 'ACT-DEF',
            'acta de divorcio' => 'ACT-DIV',
            'acta divorcio' => 'ACT-DIV',
            'acta de matrimonio' => 'ACT-MAT',
            'acta matrimonio' => 'ACT-MAT',
            'antecedentes no penales' => 'ANP-01',
            'constancia de situacion fiscal' => 'CSF-02',
            'constancia fiscal' => 'CSF-02',
            'descarga de constancia' => 'CSF-02',
            'consulta de rfc' => 'CSF-02',
            'consultar rfc' => 'CSF-02',
            'tramitarme mi rfc' => 'CSF-02',
            'tramitar mi rfc' => 'CSF-02',
            'tramite de rfc' => 'CSF-02',
            'sacar rfc' => 'CSF-02',
            'para rfc' => 'CSF-02',
            'rfc' => 'CSF-02',
            'curp' => 'CURP-01',
            'nss' => 'NSS-02',
            'numero de seguro social' => 'NSS-02',
            'seguro social' => 'NSS-02',
            'semanas cotizadas' => 'NSS-03',
            'vigencia de derechos' => 'NSS-01',
            'afore' => 'AFO-01',
            'idcif' => 'IDCIF-01',
            'recibo cfe' => 'CFE-01',
            'cfe' => 'CFE-01',
            'repuve' => 'HOJ-REP',
            'hoja repuve' => 'HOJ-REP',
            'tenencia cdmx' => 'FP-TCDMX',
            'tenencia ciudad de mexico' => 'FP-TCDMX',
            'tenencia edomex' => 'FP-TEDOMX',
            'tenencia estado de mexico' => 'FP-TEDOMX',
            'cuenta infonavit' => 'EDO-INF',
            'estado de cuenta infonavit' => 'EDO-INF',
            'recuperar clave infonavit' => 'REC-INF',
            'reporte historico infonavit' => 'REPHIS-INF',
            'resumen credito infonavit' => 'RESCRED-INF',
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

        $assignment = !empty($state['assigned_user_email'])
            ? " La solicitud quedará asignada a {$state['assigned_user_email']}."
            : '';

        return "Gracias. Tengo los datos necesarios para el trámite {$state['service_name']} de "
            . "{$state['subject_name']}: {$details}.{$assignment} ¿Deseas continuar con la solicitud?";
    }

    private function completedResponse(array $state, bool $justCreated = false): string
    {
        $prefix = $justCreated ? 'Listo. Creé' : 'Sí. Ya fue creada';
        $orderId = $state['order_id'];
        $service = $state['service_name'];
        $subject = $state['subject_name'];
        $assignment = !empty($state['order_user_email'] ?? $state['assigned_user_email'] ?? null)
            ? ' Asignada a ' . ($state['order_user_email'] ?? $state['assigned_user_email']) . '.'
            : '';

        return "{$prefix} la solicitud #{$orderId} para el trámite {$service} de {$subject}. "
            . "Quedó en estado pendiente.{$assignment}";
    }

    private function serviceQuestion(Collection $services): string
    {
        if ($services->isEmpty()) {
            return '¿Qué trámite necesitas? El catálogo de servicios todavía no tiene opciones cargadas.';
        }

        $options = $services->take(8)->pluck('name')->implode(', ');

        return "¿Qué trámite necesitas? Puedo ayudarte con: {$options}.";
    }

    private function capabilityResponse(): string
    {
        $services = $this->availableServices();
        $options = $services->take(8)->pluck('name')->implode(', ');

        $response = 'Puedo ayudarte a consultar trámites, capturar sus datos, '
            . 'crear solicitudes y revisar el estado o folio de una solicitud anterior.';

        if ($options !== '') {
            $response .= " Algunos servicios disponibles son: {$options}.";
        }

        return $response
            . ' Puedes decir, por ejemplo: "CURP para Kevin Montero", '
            . '"¿Qué datos faltan?", "si, hazlo", "¿cuál fue el último trámite?", '
            . '"cancela este trámite" o "quiero cambiar de trámite". '
            . 'Dime qué trámite necesitas realizar.';
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

    private function extractDirectSubjectName(string $prompt): ?string
    {
        $candidate = trim($prompt, " \t\n\r\0\x0B.,;:Â¿?Â¡!");
        $candidate = preg_replace('/^(?:es|soy|se llama|nombre|cliente|persona|interesado)\s+/iu', '', $candidate);
        $candidate = trim((string) $candidate, " \t\n\r\0\x0B.,;:Â¿?Â¡!");

        if ($candidate === '' || !$this->isValidSubjectName($candidate)) {
            return null;
        }

        return mb_convert_case($candidate, MB_CASE_TITLE, 'UTF-8');
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
            'senor',
            'senora',
            'sr',
            'sra',
            'una',
            'un',
        ];

        $words = preg_split('/\s+/u', $this->normalize($candidate), -1, PREG_SPLIT_NO_EMPTY);

        return count($words) >= 2
            && collect($words)->every(fn (string $word) => !in_array($word, $forbidden, true));
    }

    private function looksLikeIncompleteSubjectResponse(string $prompt): bool
    {
        $candidate = trim($prompt, " \t\n\r\0\x0B.,;:Â¿?Â¡!");
        $normalized = $this->normalize($candidate);

        return preg_match('/\b(senor|senora|sr|sra)\b/u', $normalized) === 1
            || (preg_match('/^[A-Za-zÃÃ‰ÃÃ“ÃšÃœÃ‘Ã¡Ã©Ã­Ã³ÃºÃ¼Ã±]+(?:\s+[A-Za-zÃÃ‰ÃÃ“ÃšÃœÃ‘Ã¡Ã©Ã­Ã³ÃºÃ¼Ã±]+){0,3}$/u', $candidate) === 1
                && !$this->isValidSubjectName($candidate));
    }

    private function isGenericGreeting(string $normalized): bool
    {
        return preg_match(
            '/^(hola(?:\s+como estas)?|buenos dias|buenas tardes|buenas noches|que tal|ayuda)[.!?]*$/u',
            $normalized
        ) === 1;
    }

    private function isCapabilityQuestion(string $normalized): bool
    {
        return $this->matches($normalized, [
            'en que me puedes ayudar',
            'como me puedes ayudar',
            'que puedes hacer',
            'que sabes hacer',
            'que tramite puedes hacer para mi',
            'que tramites puedes hacer para mi',
            'que tramite me puedes hacer',
            'que tramites me puedes hacer',
            'que tramites manejas',
            'que servicios tienes',
            'con que me ayudas',
            'comandos disponibles',
            'comandos del chat',
            'que puedo escribir',
            'que le puedo decir',
            'ayuda del chat',
            'menu de ayuda',
            'opciones del asistente',
        ]);
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
            'quiero hacer un tramite',
            'necesito hacer un tramite',
            'tramitar',
            'hacer tramite',
            'sacar tramite',
            'quiero de una',
            'quiero tramitar',
            'quiero sacar',
            'tramite para',
        ]);
    }

    private function hasActiveProcedureState(?array $state): bool
    {
        return $state !== null && in_array($state['status'] ?? null, [
            'awaiting_service',
            'awaiting_subject',
            'collecting',
            'ready_to_confirm',
        ], true);
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
            'assigned_user_id' => null,
            'assigned_user_email' => null,
            'assigned_user_name' => null,
            'order_user_id' => null,
            'order_user_email' => null,
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
                'assigned_user_id' => $state['assigned_user_id'] ?? null,
                'assigned_user_email' => $state['assigned_user_email'] ?? null,
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
            $needle = $this->normalize($phrase);

            if ($needle === '') {
                continue;
            }

            if (mb_strlen($needle, 'UTF-8') <= 3) {
                if (preg_match('/(?<![\pL\pN])' . preg_quote($needle, '/') . '(?![\pL\pN])/u', $normalized) === 1) {
                    return true;
                }

                continue;
            }

            if (str_contains($normalized, $needle)) {
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
