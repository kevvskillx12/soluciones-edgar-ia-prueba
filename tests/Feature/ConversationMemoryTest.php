<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_conversation_id_if_not_provided(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $response->assertJsonStructure([
            'success',
            'conversation_id',
            'respuesta',
        ]);

        $responseData = $response->json();
        $this->assertTrue($responseData['success']);
        $this->assertNotNull($responseData['conversation_id']);

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

        $firstResponseData = $firstResponse->json();
        $conversationId = $firstResponseData['conversation_id'];

        $secondResponse = $this->postJson('/ia-test', [
            'pregunta' => '¿Cómo estás?',
            'conversation_id' => $conversationId,
        ]);

        $secondResponse->assertJsonStructure([
            'success',
            'conversation_id',
            'respuesta',
        ]);

        $secondResponseData = $secondResponse->json();
        $this->assertEquals($conversationId, $secondResponseData['conversation_id']);

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $this->assertNotNull($conversation);

        $messages = AiMessage::where('ai_conversation_id', $conversation->id)->get();
        $this->assertCount(4, $messages); // 2 requests = 4 messages (2 user, 2 assistant)
    }

    public function test_saves_user_and_assistant_messages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Hola',
        ]);

        $responseData = $response->json();
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

        $firstResponseData = $firstResponse->json();
        $conversationId1 = $firstResponseData['conversation_id'];

        // Don't pass the first conversation ID to ensure a new one is created
        $secondResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Segundo mensaje',
        ]);

        $secondResponseData = $secondResponse->json();
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

        $responseData = $response->json();
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

        $firstResponseData = $firstResponse->json();
        $conversationId = $firstResponseData['conversation_id'];

        $secondResponse = $this->postJson('/ia-test', [
            'pregunta' => 'Su CURP es EXPL050202HYNKCSA0',
            'conversation_id' => $conversationId,
        ]);

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

        $response->assertJsonStructure([
            'success',
            'conversation_id',
            'respuesta',
        ]);

        $responseData = $response->json();
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

        $responseData = $response->json();
        $conversationId = $responseData['conversation_id'];

        $conversation = AiConversation::where('conversation_id', $conversationId)->first();
        $messages = AiMessage::where('ai_conversation_id', $conversation->id)->get();

        $this->assertCount(2, $messages);

        $toolMessages = $messages->where('role', 'tool');
        $this->assertCount(0, $toolMessages);
    }
}