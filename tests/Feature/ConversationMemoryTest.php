<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Models\Service;
use App\Services\AI\RagBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRagBridgeService;
use Tests\Support\ParsesIaTestSse;
use Tests\TestCase;

class ConversationMemoryTest extends TestCase
{
    use ParsesIaTestSse;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(RagBridgeService::class, new FakeRagBridgeService);

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
            'form_schema' => [[
                'name' => 'curp',
                'label' => 'CURP',
                'type' => 'text',
                'required' => true,
                'regex' => '/^[A-Z]{4}\d{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]{2}$/',
            ]],
        ]);
    }

    public function test_creates_conversation_id_if_not_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $responseData = $this->parseIaTestSse($response);

        $this->assertTrue($responseData['success']);
        $this->assertNotNull($responseData['conversation_id']);
        $this->assertNotEmpty($responseData['respuesta']);

        $conversation = AiConversation::where('conversation_id', $responseData['conversation_id'])->first();
        $this->assertNotNull($conversation);
        $this->assertEquals($user->id, $conversation->user_id);
    }

    public function test_reuses_conversation_id_if_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $firstResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $firstResponseData = $this->parseIaTestSse($firstResponse);
        $conversationId = $firstResponseData['conversation_id'];

        $secondResponse = $this->postJson('/ia-test', [
            'pregunta' => '¿Cómo estás?',
            'conversation_id' => $conversationId,
        ]);

        $secondResponseData = $this->parseIaTestSse($secondResponse);
        $this->assertEquals($conversationId, $secondResponseData['conversation_id']);

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $this->assertNotNull($conversation);

        $messages = AiMessage::where('ai_conversation_id', $conversation->id)->get();
        $this->assertCount(4, $messages);
    }

    public function test_saves_user_and_assistant_messages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $responseData = $this->parseIaTestSse($response);
        $conversationId = $responseData['conversation_id'];

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $messages = AiMessage::where('ai_conversation_id', $conversation->id)->get();

        $this->assertCount(2, $messages);

        $userMessage = $messages->where('role', 'user')->first();
        $this->assertNotNull($userMessage);
        $this->assertEquals('Hola', $userMessage->content);

        $assistantMessage = $messages->where('role', 'assistant')->first();
        $this->assertNotNull($assistantMessage);
        $this->assertNotEmpty($assistantMessage->content);
    }

    public function test_does_not_mix_two_different_conversations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $firstResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Primer mensaje',
        ]);

        $firstResponseData = $this->parseIaTestSse($firstResponse);
        $conversationId1 = $firstResponseData['conversation_id'];

        $secondResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Segundo mensaje',
        ]);

        $secondResponseData = $this->parseIaTestSse($secondResponse);
        $conversationId2 = $secondResponseData['conversation_id'];

        $this->assertNotEquals($conversationId1, $conversationId2);

        $conversation1 = AiConversation::where('conversation_id', $conversationId1)->first();
        $conversation2 = AiConversation::where('conversation_id', $conversationId2)->first();

        $this->assertNotNull($conversation1);
        $this->assertNotNull($conversation2);
        $this->assertNotEquals($conversation1->id, $conversation2->id);

        $messages1 = AiMessage::where('ai_conversation_id', $conversation1->id)->get();
        $this->assertCount(2, $messages1);

        $messages2 = AiMessage::where('ai_conversation_id', $conversation2->id)->get();
        $this->assertCount(2, $messages2);
    }

    public function test_get_prompt_buffer_limits_messages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $responseData = $this->parseIaTestSse($response);
        $conversationId = $responseData['conversation_id'];

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $memoryService = app(\App\Services\AI\ConversationMemoryService::class);

        $buffer = $memoryService->getPromptBuffer($conversation, 5);

        $this->assertIsArray($buffer);
        $this->assertLessThanOrEqual(6, count($buffer));
    }

    public function test_remembers_data_between_turns(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $firstResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Quiero sacar un acta de nacimiento para Luis Alfonso',
        ]);

        $firstResponseData = $this->parseIaTestSse($firstResponse);
        $conversationId = $firstResponseData['conversation_id'];

        $secondResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Su CURP es EXPL050202HYNKCSA0',
            'conversation_id' => $conversationId,
        ]);

        $this->parseIaTestSse($secondResponse);

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $messages = AiMessage::where('ai_conversation_id', $conversation->id)->get();

        $this->assertCount(4, $messages);

        $userMessages = $messages->where('role', 'user')->values();
        $this->assertEquals('Quiero sacar un acta de nacimiento para Luis Alfonso', $userMessages[0]->content);
        $this->assertEquals('Su CURP es EXPL050202HYNKCSA0', $userMessages[1]->content);
    }

    public function test_endpoint_response_includes_conversation_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $responseData = $this->parseIaTestSse($response);

        $this->assertTrue($responseData['success']);
        $this->assertNotNull($responseData['conversation_id']);
        $this->assertNotEmpty($responseData['respuesta']);
    }

    public function test_tool_error_does_not_contaminate_memory(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $responseData = $this->parseIaTestSse($response);
        $conversationId = $responseData['conversation_id'];

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $messages = AiMessage::where('ai_conversation_id', $conversation->id)->get();

        $this->assertCount(2, $messages);

        $toolMessages = $messages->where('role', 'tool');
        $this->assertCount(0, $toolMessages);
    }

    public function test_conversation_title_uses_first_message_then_procedure_state(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $generic = $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'Necesito orientación administrativa general',
        ]));
        $genericConversation = AiConversation::where('conversation_id', $generic['conversation_id'])->firstOrFail();
        $this->assertStringStartsWith('Necesito orientación administrativa', $genericConversation->title);

        $procedure = $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'Curp para Luis Ek',
        ]));
        $procedureConversation = AiConversation::where('conversation_id', $procedure['conversation_id'])->firstOrFail();
        $this->assertSame('CURP para Luis Ek', $procedureConversation->title);
    }

    public function test_completed_conversation_title_includes_order_id(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        $first = $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'Curp para Luis Ek',
        ]));
        $conversationId = $first['conversation_id'];
        $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'EXPL050202HYNKCSA0',
            'conversation_id' => $conversationId,
        ]));
        $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'si hazlo',
            'conversation_id' => $conversationId,
        ]));

        $conversation = AiConversation::where('conversation_id', $conversationId)->firstOrFail();
        $orderId = $conversation->metadata['procedure_flow']['order_id'];
        $this->assertSame("CURP para Luis Ek · Solicitud #{$orderId}", $conversation->title);

        $status = $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'Cuál fue el último trámite que hice',
            'conversation_id' => $conversationId,
        ]));
        $this->assertStringContainsString('CURP', $status['respuesta']);
        $this->assertStringContainsString('Luis Ek', $status['respuesta']);
        $this->assertStringContainsString("Solicitud #{$orderId}", $status['respuesta']);
    }

    public function test_history_lists_only_authenticated_users_conversations(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        AiConversation::create(['conversation_id' => 'conv_a', 'user_id' => $userA->id, 'channel' => 'admin_chat', 'title' => 'Chat A']);
        AiConversation::create(['conversation_id' => 'conv_b', 'user_id' => $userB->id, 'channel' => 'admin_chat', 'title' => 'Chat B']);

        $this->actingAs($userA)
            ->getJson('/ia-conversations')
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.conversation_id', 'conv_a');
    }

    public function test_can_load_own_conversation_with_messages_and_metadata(): void
    {
        $user = User::factory()->create();
        $conversation = AiConversation::create([
            'conversation_id' => 'conv_history',
            'user_id' => $user->id,
            'channel' => 'admin_chat',
            'title' => 'Consulta RFC',
            'metadata' => ['procedure_flow' => ['status' => 'collecting']],
        ]);
        $conversation->messages()->create(['role' => 'user', 'content' => 'consulta de rfc']);

        $this->actingAs($user)
            ->getJson('/ia-conversations/conv_history')
            ->assertOk()
            ->assertJsonPath('title', 'Consulta RFC')
            ->assertJsonPath('messages.0.content', 'consulta de rfc')
            ->assertJsonPath('metadata.procedure_flow.status', 'collecting');
    }

    public function test_user_cannot_load_another_users_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        AiConversation::create([
            'conversation_id' => 'conv_private',
            'user_id' => $owner->id,
            'channel' => 'admin_chat',
            'title' => 'Privado',
        ]);

        $this->actingAs($other)
            ->getJson('/ia-conversations/conv_private')
            ->assertNotFound();
    }

    public function test_loaded_incomplete_conversation_can_continue(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        $first = $this->parseIaTestSse($this->postJson('/ia-test', ['pregunta' => 'Curp para Luis Ek']));

        $loaded = $this->getJson('/ia-conversations/' . $first['conversation_id'])->assertOk()->json();
        $continued = $this->parseIaTestSse($this->postJson('/ia-test', [
            'pregunta' => 'EXPL050202HYNKCSA0',
            'conversation_id' => $loaded['conversation_id'],
        ]));

        $this->assertStringContainsString('datos necesarios', $continued['respuesta']);
    }
}
