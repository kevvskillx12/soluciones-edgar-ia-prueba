<?php

namespace Tests\Feature;

use App\Models\AiObservabilityLog;
use App\Models\Service;
use App\Services\AI\AgentRouterService;
use App\Services\AI\RagBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRagBridgeService;
use Tests\Support\ParsesIaTestSse;
use Tests\TestCase;

class Week7MultiAgentTest extends TestCase
{
    use ParsesIaTestSse;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $fake = new FakeRagBridgeService;
        $fake->tokens = ['Respuesta RAG determinista.'];
        $this->app->instance(RagBridgeService::class, $fake);
    }

    public function test_router_classifies_transactional_and_rag_intents(): void
    {
        $router = app(AgentRouterService::class);
        $conversation = \App\Models\AiConversation::create([
            'conversation_id' => 'week7-router-test',
            'channel' => 'test',
            'metadata' => [],
        ]);

        $transactional = $router->route($conversation, 'Necesito tramitar una CURP para Kevin Montero');
        $rag = $router->route($conversation, 'Que tecnologias usa el sistema?');

        $this->assertSame('transactional_agent', $transactional['agent']);
        $this->assertSame('rag_agent', $rag['agent']);
    }

    public function test_ia_test_records_transactional_agent_in_observability(): void
    {
        Service::create([
            'code' => 'CURP-01',
            'name' => 'CURP Actualizada',
            'description' => 'CURP',
            'price' => 8,
            'cost' => 5,
            'service_type' => 'SERVICIOS',
            'processing_time' => '5 Minutos',
            'active_schedule' => '24/7',
            'is_active' => true,
            'form_schema' => [
                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
            ],
        ]);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Necesito tramitar una CURP para Kevin Montero',
        ]);

        $this->parseIaTestSse($response);

        $log = AiObservabilityLog::latest('id')->first();
        $this->assertSame('transactional_agent', $log->tools_executed['agent'] ?? null);
        $this->assertSame('transactional_agent', $log->tools_executed['router']['agent'] ?? null);
        $this->assertSame('procedure_flow', $log->tools_executed['name'] ?? null);
    }

    public function test_ia_test_records_rag_agent_for_knowledge_query(): void
    {
        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Que tecnologias usa el sistema?',
        ]);

        $this->parseIaTestSse($response);

        $log = AiObservabilityLog::latest('id')->first();
        $this->assertSame('rag_agent', $log->tools_executed['agent'] ?? null);
        $this->assertSame('rag_agent', $log->tools_executed['router']['agent'] ?? null);
        $this->assertSame('rag_search', $log->tools_executed['name'] ?? null);
    }
}
