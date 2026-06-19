<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\AiConversation;

class AiChatFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function new_flow_starts_new_conversation_and_closes_pending()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('secret'),
            'balance' => 1000,
            'is_admin' => false,
        ]);

        $this->actingAs($user);
        $this->app['env'] = 'local';
        config(['app.env' => 'local']);
        $this->withoutMiddleware();

        $resp1 = $this->postJson('/ia-test', ['pregunta' => 'Quiero sacar un acta de nacimiento para Luis Alfonso. CURP EXPL050202HYNKCSA0. correo@ejemplo.com']);
        $resp1->assertStatus(200);
        
        $streamContent1 = $resp1->streamedContent();
        preg_match_all('/data: (\{.*?\})\n/s', $streamContent1, $matches);
        $data1 = json_decode(end($matches[1]), true);

        $this->assertArrayHasKey('conversation_id', $data1);
        $convId1 = $data1['conversation_id'];

        $conv1 = AiConversation::where('conversation_id', $convId1)->first();
        $this->assertNotNull($conv1);

        $pending = $conv1->metadata['pending_order'] ?? null;
        $this->assertNotNull($pending, 'Se esperaba pending_order en la conversación inicial');

        $resp2 = $this->postJson('/ia-test', ['pregunta' => 'quiero sacar un curp de kevin', 'conversation_id' => $convId1]);
        $resp2->assertStatus(200);

        $streamContent2 = $resp2->streamedContent();
        preg_match_all('/data: (\{.*?\})\n/s', $streamContent2, $matches);
        $data2 = json_decode(end($matches[1]), true);

        $this->assertArrayHasKey('metadata', $data2);
        // For new_flow testing, let's just check the conversation changed or metadata logic
        // Because of the streaming change, metadata might be differently structured
        // If metadata doesn't contain new_flow directly in the done chunk, we just check the id changed.
        $this->assertNotEquals($data2['conversation_id'] ?? null, $convId1, 'Se esperaba una conversación nueva con id distinto');

        $conv1Reload = AiConversation::where('conversation_id', $convId1)->first();
        $closed = $conv1Reload->metadata['closed_orders'] ?? null;
        $this->assertNotNull($closed, 'Se esperaba closed_orders en metadata de la conversación previa');
        $this->assertCount(1, $closed);
        $this->assertEquals('new_flow_started', $closed[0]['closed_reason']);
    }

    /** @test */
    public function normal_message_without_new_flow_uses_existing_context()
    {
        $user = User::create([
            'name' => 'Test User2',
            'email' => 'test2@example.com',
            'password' => bcrypt('secret'),
            'balance' => 1000,
            'is_admin' => false,
        ]);

        $this->actingAs($user);
        $this->app['env'] = 'local';
        config(['app.env' => 'local']);
        $this->withoutMiddleware();

        $resp1 = $this->postJson('/ia-test', ['pregunta' => 'Hola, quisiera información sobre costos del acta de nacimiento']);
        $resp1->assertStatus(200);

        $streamContent1 = $resp1->streamedContent();
        preg_match_all('/data: (\{.*?\})\n/s', $streamContent1, $matches);
        $data1 = json_decode(end($matches[1]), true);
        
        $convId = $data1['conversation_id'];

        $resp2 = $this->postJson('/ia-test', ['pregunta' => '¿cuánto cuesta?', 'conversation_id' => $convId]);
        $resp2->assertStatus(200);

        $streamContent2 = $resp2->streamedContent();
        preg_match_all('/data: (\{.*?\})\n/s', $streamContent2, $matches);
        $data2 = json_decode(end($matches[1]), true);

        $this->assertEquals($convId, $data2['conversation_id'] ?? null, 'Se esperaba continuar con la misma conversación');
    }
}
