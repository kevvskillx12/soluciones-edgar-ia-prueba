<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\Service;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRagBridge = new FakeRagBridgeService;
        $this->app->instance(RagBridgeService::class, $this->fakeRagBridge);

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

    private function startCurpFlow(): string
    {
        $first = $this->send('Necesito un trámite para Kevin Montero');
        $this->send('quiero de una CURP', $first['conversation_id']);

        return $first['conversation_id'];
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
