<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AiConversation;
use App\Models\AiMessage;

class Week4RequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_window_trimming_by_tokens()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = AiConversation::create([
            'conversation_id' => 'conv_test_tokens',
            'user_id' => $user->id,
            'channel' => 'admin_chat',
        ]);

        $memoryService = app(\App\Services\AI\ConversationMemoryService::class);

        // Add multiple large messages
        for ($i = 0; $i < 5; $i++) {
            // Un mensaje muy largo, unos 500 caracteres ~ 125 tokens aprox
            $content = str_repeat('palabra ', 60); 
            $memoryService->addUserMessage($conversation, $content . ' ' . $i);
        }

        // Limit buffer to roughly 200 tokens. This should only include the very last messages.
        $buffer = $memoryService->getPromptBuffer($conversation, 200);

        // We expect fewer than 5 messages because of the token limit
        $this->assertLessThan(5, count($buffer));
        // The last message added must be there
        $this->assertStringContainsString(' 4', end($buffer)['content']);
    }

    public function test_tool_error_prevents_infinite_loop()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $conversation = AiConversation::create([
            'conversation_id' => 'conv_test_tool',
            'user_id' => $user->id,
            'channel' => 'admin_chat',
        ]);

        $memoryService = app(\App\Services\AI\ConversationMemoryService::class);

        // Agregamos un mensaje de herramienta que falló
        $memoryService->addToolMessage($conversation, 'create_order', 'Error interno al crear orden.', 'error');

        $buffer = $memoryService->getPromptBuffer($conversation, 4000);

        // Debería agregar la indicación de error de sistema para prevenir state poisoning
        $lastMessage = end($buffer);
        $this->assertEquals('tool', $lastMessage['role']);
        $this->assertStringContainsString('[SYSTEM LOG]: La herramienta falló.', $lastMessage['content']);
        $this->assertStringContainsString('Error interno al crear orden', $lastMessage['content']);
    }
}
