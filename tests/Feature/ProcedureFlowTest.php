<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\AI\RagBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRagBridgeService;
use Tests\Support\ParsesIaTestSse;
use Tests\TestCase;

class ProcedureFlowTest extends TestCase
{
    use ParsesIaTestSse;
    use RefreshDatabase;

    private FakeRagBridgeService $fakeRagBridge;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRagBridge = new FakeRagBridgeService;
        $this->app->instance(RagBridgeService::class, $this->fakeRagBridge);
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'balance' => 0,
        ]);
        $this->actingAs($this->admin);

        Service::create([
            'code' => 'CURP-01',
            'name' => 'CURP Actualizada',
            'description' => 'Ordenar solo con CURP',
            'price' => 8,
            'cost' => 5,
            'service_type' => 'SERVICIOS',
            'processing_time' => '5 Minutos',
            'active_schedule' => '24/7',
            'is_active' => true,
            'form_schema' => [
                [
                    'name' => 'curp',
                    'label' => 'CURP',
                    'type' => 'text',
                    'required' => true,
                    'regex' => '/^[A-Z]{4}\d{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]{2}$/',
                ],
            ],
        ]);

        Service::create([
            'code' => 'EXP-INT',
            'name' => 'Expediente Integral',
            'description' => 'Servicio de prueba con campos dinámicos',
            'price' => 20,
            'cost' => 10,
            'service_type' => 'SERVICIOS',
            'processing_time' => '5 Minutos',
            'active_schedule' => '24/7',
            'is_active' => true,
            'form_schema' => [
                ['name' => 'nombre_completo', 'label' => 'Nombre Completo', 'type' => 'text', 'required' => true],
                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
                ['name' => 'telefono', 'label' => 'Teléfono', 'type' => 'text', 'required' => true],
                ['name' => 'correo', 'label' => 'Correo', 'type' => 'email', 'required' => true],
            ],
        ]);

        Service::create([
            'code' => 'DAT-SEL',
            'name' => 'Datos Demográficos',
            'description' => 'Servicio de prueba con fecha y opción',
            'price' => 20,
            'cost' => 10,
            'service_type' => 'SERVICIOS',
            'processing_time' => '5 Minutos',
            'active_schedule' => '24/7',
            'is_active' => true,
            'form_schema' => [
                ['name' => 'fecha_nacimiento', 'label' => 'Fecha de Nacimiento', 'type' => 'date', 'required' => true],
                [
                    'name' => 'estado',
                    'label' => 'Estado',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Yucatán', 'Quintana Roo'],
                ],
            ],
        ]);

        Service::create([
            'code' => 'ACT-NAC',
            'name' => 'Acta de Nacimiento',
            'description' => 'Ordenar solo con CURP',
            'price' => 70,
            'cost' => 49,
            'service_type' => 'ACTAS',
            'processing_time' => '1-30 Minutos',
            'active_schedule' => '8:00 AM a 8:00 PM',
            'is_active' => true,
            'form_schema' => [
                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
            ],
        ]);

        Service::create([
            'code' => 'CSF-02',
            'name' => 'CSF con RFC y IDCIF',
            'description' => 'Constancia de situación fiscal',
            'price' => 55,
            'cost' => 38.5,
            'service_type' => 'SAT',
            'processing_time' => '5 Minutos',
            'active_schedule' => '24/7',
            'is_active' => true,
            'form_schema' => [
                [
                    'name' => 'rfc',
                    'label' => 'RFC',
                    'type' => 'text',
                    'required' => true,
                    'regex' => '/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
                ],
                ['name' => 'idcif', 'label' => 'IDCIF', 'type' => 'text', 'required' => true],
            ],
        ]);
    }

    public function test_generic_request_asks_for_service_without_ollama(): void
    {
        $parsed = $this->send('Necesito ayuda con un trámite');

        $this->assertStringContainsString('¿Qué trámite necesitas?', $parsed['respuesta']);
        $this->assertStringContainsString('CURP Actualizada', $parsed['respuesta']);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public function test_person_is_persisted_then_curp_uses_configured_field_order(): void
    {
        $first = $this->send('Necesito un trámite para Kevin Montero');
        $conversationId = $first['conversation_id'];

        $this->assertStringContainsString('Kevin Montero', $first['respuesta']);
        $this->assertStringContainsString('¿Qué trámite', $first['respuesta']);

        $second = $this->send('quiero de una CURP', $conversationId);

        $this->assertStringContainsString('trámite CURP para Kevin Montero', $second['respuesta']);
        $this->assertStringContainsString('dato: CURP', $second['respuesta']);
        $this->assertStringNotContainsString('fecha de nacimiento', mb_strtolower($second['respuesta']));
        $this->assertStringNotContainsString('sexo', mb_strtolower($second['respuesta']));
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);

        $state = $this->state($conversationId);
        $this->assertSame('Kevin Montero', $state['subject_name']);
        $this->assertSame('CURP', $state['service_name']);
        $this->assertSame('curp', $state['current_field']);
        $this->assertSame(['curp'], $state['missing_fields']);
    }

    public function test_captures_curp_and_confirms_ready_state(): void
    {
        $conversationId = $this->startCurpFlow();
        $parsed = $this->send('ABCD010203HYNXXX09', $conversationId);

        $this->assertStringContainsString('datos necesarios', $parsed['respuesta']);
        $this->assertStringContainsString('CURP ABCD010203HYNXXX09', $parsed['respuesta']);

        $state = $this->state($conversationId);
        $this->assertSame('ready_to_confirm', $state['status']);
        $this->assertSame([], $state['missing_fields']);
        $this->assertSame('ABCD010203HYNXXX09', $state['collected_fields']['curp']);
    }

    public function test_answers_persistent_procedure_state_questions_without_ollama(): void
    {
        $conversationId = $this->startCurpFlow();

        $this->assertSame(
            'Estabas realizando el trámite CURP para Kevin Montero.',
            $this->send('¿Cuál era el último trámite que estaba haciendo?', $conversationId)['respuesta']
        );
        $this->assertSame(
            'Falta capturar: CURP.',
            $this->send('¿Qué datos faltan?', $conversationId)['respuesta']
        );
        $this->assertSame(
            'Te pedí el dato CURP.',
            $this->send('¿Qué dato me pediste?', $conversationId)['respuesta']
        );
        $this->assertSame(
            'El trámite era para Kevin Montero.',
            $this->send('¿Para quién era el trámite?', $conversationId)['respuesta']
        );
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public function test_cancel_marks_flow_cancelled(): void
    {
        $conversationId = $this->startCurpFlow();
        $parsed = $this->send('Cancela este trámite', $conversationId);

        $this->assertStringContainsString('quedó cancelado', $parsed['respuesta']);
        $this->assertSame('cancelled', $this->state($conversationId)['status']);
        $this->assertNull(AiConversation::where('conversation_id', $conversationId)->first()->metadata['pending_order'] ?? null);
    }

    public function test_change_service_preserves_person_and_clears_previous_service(): void
    {
        $conversationId = $this->startCurpFlow();
        $parsed = $this->send('Quiero cambiar de trámite', $conversationId);

        $this->assertStringContainsString('Conservaré a Kevin Montero', $parsed['respuesta']);
        $state = $this->state($conversationId);
        $this->assertSame('Kevin Montero', $state['subject_name']);
        $this->assertNull($state['service_id']);
        $this->assertSame([], $state['collected_fields']);
        $this->assertSame('awaiting_service', $state['status']);
    }

    public function test_procedure_state_does_not_mix_conversations(): void
    {
        $conversationA = $this->startCurpFlow();
        $conversationB = $this->send('Necesito ayuda con un trámite')['conversation_id'];

        $responseB = $this->send('¿Para quién era el trámite?', $conversationB);

        $this->assertNotEquals($conversationA, $conversationB);
        $this->assertSame('No hay un trámite registrado en esta conversación.', $responseB['respuesta']);
        $this->assertNull($this->state($conversationB)['subject_name'] ?? null);
    }

    public function test_confirmation_with_continua_creates_a_real_order(): void
    {
        $this->assertOrderCreatedByConfirmation('continua');
    }

    public function test_confirmation_with_si_hazlo_creates_a_real_order(): void
    {
        $this->assertOrderCreatedByConfirmation('si hazlo');
    }

    public function test_confirmation_with_termina_la_solicitud_creates_a_real_order(): void
    {
        $this->assertOrderCreatedByConfirmation('Termina la solicitud');
    }

    public function test_created_order_can_be_queried_and_is_not_duplicated(): void
    {
        $conversationId = $this->startActaFlowReadyToConfirm();
        $created = $this->send('si, continúa', $conversationId);
        $orderId = $this->state($conversationId)['order_id'];

        $this->assertStringContainsString("solicitud #{$orderId}", $created['respuesta']);
        $this->assertDatabaseCount('orders', 1);

        $status = $this->send('¿ya la hiciste?', $conversationId);
        $this->assertStringContainsString("solicitud #{$orderId}", $status['respuesta']);

        $duplicate = $this->send('continúa', $conversationId);
        $this->assertStringContainsString("solicitud #{$orderId}", $duplicate['respuesta']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public function test_cancel_ready_flow_does_not_create_order(): void
    {
        $conversationId = $this->startActaFlowReadyToConfirm();
        $parsed = $this->send('mejor no', $conversationId);

        $this->assertStringContainsString('Cancelé el trámite', $parsed['respuesta']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('cancelled', $this->state($conversationId)['status']);
    }

    public function test_separate_conversations_do_not_share_order_ids(): void
    {
        $conversationA = $this->startActaFlowReadyToConfirm('Kevin Montero');
        $this->send('procede', $conversationA);
        $orderA = $this->state($conversationA)['order_id'];

        $conversationB = $this->startActaFlowReadyToConfirm('Ana López');
        $this->send('hazlo', $conversationB);
        $orderB = $this->state($conversationB)['order_id'];

        $this->assertNotEquals($conversationA, $conversationB);
        $this->assertNotEquals($orderA, $orderB);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_confirmation_without_authenticated_user_reports_requirement(): void
    {
        auth()->logout();
        $conversationId = $this->startActaFlowReadyToConfirm();
        $parsed = $this->send('continua', $conversationId);

        $this->assertStringContainsString('necesito que inicies sesión', $parsed['respuesta']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('ready_to_confirm', $this->state($conversationId)['status']);
        $this->assertDatabaseHas('ai_observability_logs', [
            'session_id' => $conversationId,
            'was_blocked' => false,
        ]);
        $this->assertSame(
            'ERROR',
            \App\Models\AiObservabilityLog::latest('id')->first()->tools_executed['status']
        );
    }

    public function test_confirmation_with_insufficient_balance_does_not_create_order(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'balance' => 0,
        ]);
        $this->actingAs($user);

        $conversationId = $this->startActaFlowReadyToConfirm();
        $parsed = $this->send('procede', $conversationId);

        $this->assertStringContainsString('necesitas saldo suficiente', $parsed['respuesta']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame('ready_to_confirm', $this->state($conversationId)['status']);
        $this->assertSame(
            'ERROR',
            \App\Models\AiObservabilityLog::latest('id')->first()->tools_executed['status']
        );
    }

    public function test_greeting_without_active_procedure_does_not_invent_state(): void
    {
        $parsed = $this->send('hola');

        $this->assertSame(
            'Hola. Estoy listo para ayudarte con trámites. ¿Qué trámite necesitas realizar?',
            $parsed['respuesta']
        );
        $this->assertStringNotContainsString('Acta de Nacimiento', $parsed['respuesta']);
        $this->assertStringNotContainsString('Tramitar Una', $parsed['respuesta']);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public function test_greeting_with_active_procedure_does_not_advance_current_field(): void
    {
        $conversationId = $this->startActaFlow();
        $before = $this->state($conversationId);
        $parsed = $this->send('hola', $conversationId);

        $this->assertStringContainsString('Tienes un trámite en curso de Acta de Nacimiento', $parsed['respuesta']);
        $this->assertStringContainsString('continuarlo, cambiarlo o iniciar uno nuevo', $parsed['respuesta']);
        $this->assertSame($before, $this->state($conversationId));
    }

    public function test_corrupt_subject_state_is_reset(): void
    {
        $conversationId = $this->startActaFlow();
        $conversation = AiConversation::where('conversation_id', $conversationId)->firstOrFail();
        $metadata = $conversation->metadata;
        $metadata['procedure_flow']['subject_name'] = 'Tramitar Una';
        $conversation->update(['metadata' => $metadata]);

        $parsed = $this->send('hola', $conversationId);

        $this->assertSame(
            'Detecté un flujo anterior incompleto. ¿Qué trámite necesitas realizar?',
            $parsed['respuesta']
        );
        $this->assertNull($this->state($conversationId)['subject_name']);
        $this->assertNull($this->state($conversationId)['service_id']);
    }

    public function test_curp_request_asks_for_person_without_inventing_one(): void
    {
        $parsed = $this->send('necesito hacer un tramite de curp');
        $state = $this->state($parsed['conversation_id']);

        $this->assertSame('Seleccionaste el trámite CURP. ¿Para quién es el trámite?', $parsed['respuesta']);
        $this->assertNull($state['subject_name']);
    }

    public function test_explicit_person_is_saved_and_curp_is_requested(): void
    {
        $first = $this->send('necesito hacer un tramite de curp');
        $parsed = $this->send('para Kevin Montero', $first['conversation_id']);

        $this->assertStringContainsString('trámite CURP para Kevin Montero', $parsed['respuesta']);
        $this->assertStringContainsString('dato: CURP', $parsed['respuesta']);
        $this->assertSame('Kevin Montero', $this->state($first['conversation_id'])['subject_name']);
    }

    public function test_tramitar_una_curp_never_becomes_subject_name(): void
    {
        $parsed = $this->send('quiero tramitar una curp');

        $this->assertNull($this->state($parsed['conversation_id'])['subject_name']);
        $this->assertStringNotContainsString('Tramitar Una', $parsed['respuesta']);
    }

    public function test_new_conversation_does_not_inherit_procedure_flow(): void
    {
        $first = $this->send('necesito hacer un tramite de curp');
        $second = $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'hola',
            'conversation_id' => $first['conversation_id'],
            'new_conversation' => true,
        ]));

        $this->assertNotSame($first['conversation_id'], $second['conversation_id']);
        $this->assertSame(
            'Hola. Estoy listo para ayudarte con trámites. ¿Qué trámite necesitas realizar?',
            $second['respuesta']
        );
        $this->assertNull(
            AiConversation::where('conversation_id', $second['conversation_id'])
                ->firstOrFail()
                ->metadata['procedure_flow'] ?? null
        );
    }

    /**
     * @dataProvider naturalCurpMessages
     */
    public function test_curp_accepts_natural_capture_formats(string $message): void
    {
        $conversationId = $this->startCurpFlow();
        $parsed = $this->send($message, $conversationId);

        $this->assertStringContainsString('datos necesarios', $parsed['respuesta']);
        $this->assertSame('EXPL050202HYNKCSA0', $this->state($conversationId)['collected_fields']['curp']);
    }

    public static function naturalCurpMessages(): array
    {
        return [
            ['EXPL050202HYNKCSA0'],
            ['El curp es EXPL050202HYNKCSA0'],
            ['mi curp es EXPL050202HYNKCSA0'],
            ['CURP: EXPL050202HYNKCSA0'],
            ['te paso la curp EXPL050202HYNKCSA0'],
            ['es EXPL050202HYNKCSA0'],
            ['la tengo como EXPL050202HYNKCSA0'],
        ];
    }

    public function test_invalid_curp_is_not_saved(): void
    {
        $conversationId = $this->startCurpFlow();
        $parsed = $this->send('mi curp es dato-invalido', $conversationId);

        $this->assertStringContainsString('No pude identificar un valor válido', $parsed['respuesta']);
        $this->assertArrayNotHasKey('curp', $this->state($conversationId)['collected_fields']);
    }

    public function test_multiple_dynamic_fields_are_captured_from_one_message(): void
    {
        $first = $this->send('Necesito Expediente Integral para Kevin Montero');
        $parsed = $this->send(
            'Soy Kevin Montero, mi CURP es EXPL050202HYNKCSA0, mi teléfono es 9991234567 y correo kevin@test.com',
            $first['conversation_id']
        );
        $state = $this->state($first['conversation_id']);

        $this->assertSame('ready_to_confirm', $state['status']);
        $this->assertSame('Kevin Montero', $state['collected_fields']['nombre_completo']);
        $this->assertSame('EXPL050202HYNKCSA0', $state['collected_fields']['curp']);
        $this->assertSame('9991234567', $state['collected_fields']['telefono']);
        $this->assertSame('kevin@test.com', $state['collected_fields']['correo']);
        $this->assertStringContainsString('datos necesarios', $parsed['respuesta']);
    }

    public function test_date_and_select_options_are_normalized_from_natural_text(): void
    {
        $first = $this->send('Necesito Datos Demográficos para Kevin Montero');
        $parsed = $this->send(
            'Nací el 02/02/2005 y el estado es yucatan',
            $first['conversation_id']
        );
        $state = $this->state($first['conversation_id']);

        $this->assertSame('2005-02-02', $state['collected_fields']['fecha_nacimiento']);
        $this->assertSame('Yucatán', $state['collected_fields']['estado']);
        $this->assertSame('ready_to_confirm', $state['status']);
        $this->assertStringContainsString('datos necesarios', $parsed['respuesta']);
    }

    public function test_iso_date_is_accepted_and_unknown_option_is_rejected(): void
    {
        $first = $this->send('Necesito Datos Demográficos para Kevin Montero');
        $this->send('fecha de nacimiento: 2005-02-02', $first['conversation_id']);
        $parsed = $this->send('estado: Campeche', $first['conversation_id']);
        $state = $this->state($first['conversation_id']);

        $this->assertSame('2005-02-02', $state['collected_fields']['fecha_nacimiento']);
        $this->assertArrayNotHasKey('estado', $state['collected_fields']);
        $this->assertStringContainsString('No pude identificar un valor válido', $parsed['respuesta']);
    }

    public function test_correction_replaces_previously_captured_value(): void
    {
        $conversationId = $this->startCurpFlow();
        $this->send('ABCD010203HYNXXX09', $conversationId);
        $parsed = $this->send(
            'me equivoqué, la CURP correcta es EXPL050202HYNKCSA0',
            $conversationId
        );

        $this->assertSame('EXPL050202HYNKCSA0', $this->state($conversationId)['collected_fields']['curp']);
        $this->assertStringContainsString('CURP EXPL050202HYNKCSA0', $parsed['respuesta']);
    }

    public function test_combined_service_person_and_curp_reaches_confirmation_in_one_message(): void
    {
        $parsed = $this->send(
            'Necesito acta de nacimiento para Kevin Montero, CURP EXPL050202HYNKCSA0'
        );
        $state = $this->state($parsed['conversation_id']);

        $this->assertSame('Kevin Montero', $state['subject_name']);
        $this->assertSame('EXPL050202HYNKCSA0', $state['collected_fields']['curp']);
        $this->assertSame('ready_to_confirm', $state['status']);
    }

    public function test_completed_flow_returns_data_summary_and_never_duplicates_order(): void
    {
        $conversationId = $this->startActaFlowReadyToConfirm();
        $this->send('si hazlo', $conversationId);
        $orderId = $this->state($conversationId)['order_id'];

        $summary = $this->send('datos del último trámite', $conversationId);
        $this->assertStringContainsString('CURP: ABCD010203HYNXXX09', $summary['respuesta']);

        $this->send('continúa', $conversationId);
        $this->send('si hazlo', $conversationId);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame($orderId, $this->state($conversationId)['order_id']);
        $this->assertSame('completed', $this->state($conversationId)['status']);
    }

    /**
     * @dataProvider smalltalkPrompts
     */
    public function test_smalltalk_is_handled_without_rejection_or_llm(string $prompt): void
    {
        $parsed = $this->send($prompt);

        $this->assertStringContainsString('Estoy listo para ayudarte con trámites', $parsed['respuesta']);
        $this->assertStringNotContainsString('Lo siento', $parsed['respuesta']);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public static function smalltalkPrompts(): array
    {
        return [['hola'], ['Hola cómo estás'], ['buenos días'], ['qué tal']];
    }

    /**
     * @dataProvider capabilityPrompts
     */
    public function test_capability_questions_use_catalog_without_ollama(string $prompt): void
    {
        $parsed = $this->send($prompt);

        $this->assertStringContainsString('Puedo ayudarte a consultar trámites', $parsed['respuesta']);
        $this->assertStringContainsString('CURP Actualizada', $parsed['respuesta']);
        $this->assertStringNotContainsString('depósito', mb_strtolower($parsed['respuesta']));
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public static function capabilityPrompts(): array
    {
        return [
            ['en qué me puedes ayudar?'],
            ['¿Qué puedes hacer?'],
            ['quiero hacer algo, ¿cómo me puedes ayudar?'],
            ['¿Qué trámites manejas?'],
        ];
    }

    public function test_common_command_aliases_are_handled_without_ollama(): void
    {
        $first = $this->send('CURP para Kevin Montero');
        $conversationId = $first['conversation_id'];

        $this->assertStringContainsString('Kevin Montero', $first['respuesta']);
        $this->assertStringContainsString('CURP', $first['respuesta']);

        $missing = $this->send('que me falta', $conversationId);
        $this->assertStringContainsString('Falta capturar: CURP.', $missing['respuesta']);

        $current = $this->send('que dato sigue', $conversationId);
        $this->assertStringContainsString('Te pedí el dato CURP.', $current['respuesta']);

        $subject = $this->send('para quien es', $conversationId);
        $this->assertStringContainsString('Kevin Montero', $subject['respuesta']);

        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public function test_additional_service_aliases_enter_structured_flow(): void
    {
        Service::create([
            'code' => 'NSS-02',
            'name' => 'Localizar NSS con CURP',
            'description' => 'Localizar NSS con CURP',
            'price' => 10,
            'cost' => 5,
            'service_type' => 'SERVICIOS',
            'processing_time' => '5 Minutos',
            'active_schedule' => '24/7',
            'is_active' => true,
            'form_schema' => [
                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
            ],
        ]);

        $parsed = $this->send('necesito NSS para Kevin Montero');
        $state = $this->state($parsed['conversation_id']);

        $this->assertSame('NSS-02', $state['service_code']);
        $this->assertStringContainsString('Kevin Montero', $parsed['respuesta']);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public function test_capability_question_does_not_get_stuck_in_completed_flow(): void
    {
        $conversationId = $this->startActaFlowReadyToConfirm();
        $this->send('si hazlo', $conversationId);

        $parsed = $this->send('¿en qué me puedes ayudar?', $conversationId);

        $this->assertStringContainsString('Puedo ayudarte a consultar trámites', $parsed['respuesta']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('completed', $this->state($conversationId)['status']);
    }

    /**
     * @dataProvider legitimateFiscalPrompts
     */
    public function test_legitimate_fiscal_prompts_enter_structured_flow(string $prompt): void
    {
        $parsed = $this->send($prompt);
        $state = $this->state($parsed['conversation_id']);

        $this->assertSame('CSF-02', $state['service_code']);
        $this->assertStringContainsString('¿Para quién es el trámite?', $parsed['respuesta']);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    public static function legitimateFiscalPrompts(): array
    {
        return [
            ['para rfc'],
            ['consulta de rfc'],
            ['quiero consultar RFC'],
            ['descarga de constancia'],
            ['constancia fiscal'],
            ['constancia de situación fiscal'],
            ['quiero descargar mi constancia fiscal'],
        ];
    }

    private function startCurpFlow(): string
    {
        $first = $this->send('Necesito un trámite para Kevin Montero');
        $this->send('quiero de una CURP', $first['conversation_id']);

        return $first['conversation_id'];
    }

    private function startActaFlowReadyToConfirm(string $subject = 'Kevin Montero'): string
    {
        $first = $this->send("Quiero un trámite para {$subject}");
        $this->send('acta de nacimiento', $first['conversation_id']);
        $this->send('ABCD010203HYNXXX09', $first['conversation_id']);

        return $first['conversation_id'];
    }

    private function startActaFlow(string $subject = 'Kevin Montero'): string
    {
        $first = $this->send("Quiero un trámite para {$subject}");
        $this->send('acta de nacimiento', $first['conversation_id']);

        return $first['conversation_id'];
    }

    private function assertOrderCreatedByConfirmation(string $confirmation): void
    {
        $conversationId = $this->startActaFlowReadyToConfirm();
        $parsed = $this->send($confirmation, $conversationId);
        $state = $this->state($conversationId);

        $this->assertStringContainsString('Listo. Creé la solicitud #', $parsed['respuesta']);
        $this->assertStringContainsString('Quedó en estado pendiente', $parsed['respuesta']);
        $this->assertSame('completed', $state['status']);
        $this->assertNotNull($state['order_id']);
        $this->assertDatabaseHas('orders', [
            'id' => $state['order_id'],
            'user_id' => $this->admin->id,
            'service_id' => $state['service_id'],
            'status' => 'pending',
        ]);

        $order = Order::findOrFail($state['order_id']);
        $this->assertSame('Kevin Montero', $order->input_data['subject_name']);
        $this->assertSame('ABCD010203HYNXXX09', $order->input_data['curp']);
        $this->assertSame(0.0, (float) $order->price_at_purchase);
        $this->assertSame(0, $this->fakeRagBridge->invocationCount);
    }

    private function send(string $prompt, ?string $conversationId = null): array
    {
        $payload = ['pregunta' => $prompt];
        if ($conversationId) {
            $payload['conversation_id'] = $conversationId;
        }

        return $this->parseIaTestSse($this->postJson('/ia-test', $payload));
    }

    private function state(string $conversationId): array
    {
        return AiConversation::where('conversation_id', $conversationId)
            ->firstOrFail()
            ->metadata['procedure_flow'];
    }
}
