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
        // Forzar entorno no-testing para que el endpoint ejecute la lógica completa
        $this->app['env'] = 'local';
        config(['app.env' => 'local']);
        $this->withoutMiddleware();

        // Primer mensaje que crea un trámite y genera pending_order en la conversación
        $resp1 = $this->postJson('/ia-test', ['pregunta' => 'Quiero sacar un acta de nacimiento para Luis Alfonso. CURP EXPL050202HYNKCSA0. correo@ejemplo.com']);
        $resp1->assertStatus(200)->assertJson(['success' => true]);

        $data1 = $resp1->json();
        $this->assertArrayHasKey('conversation_id', $data1);
        $convId1 = $data1['conversation_id'];

        $conv1 = AiConversation::where('conversation_id', $convId1)->first();
        $this->assertNotNull($conv1);

        $pending = $conv1->metadata['pending_order'] ?? null;
        $this->assertNotNull($pending, 'Se esperaba pending_order en la conversación inicial');

        // Ahora enviar mensaje que inicia un nuevo flujo
        $resp2 = $this->postJson('/ia-test', ['pregunta' => 'quiero sacar un curp de kevin', 'conversation_id' => $convId1]);
        $resp2->assertStatus(200)->assertJson(['success' => true]);

        $data2 = $resp2->json();
        $this->assertArrayHasKey('metadata', $data2);
        $this->assertArrayHasKey('new_flow', $data2['metadata']);
        $this->assertTrue($data2['metadata']['new_flow'], 'Se esperaba new_flow = true en la segunda respuesta');
        $this->assertNotEquals($data2['conversation_id'], $convId1, 'Se esperaba una conversación nueva con id distinto');

        // Recargar la conversación anterior y comprobar closed_orders
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
        // Forzar entorno no-testing para que el endpoint ejecute la lógica completa
        $this->app['env'] = 'local';
        config(['app.env' => 'local']);
        $this->withoutMiddleware();

        $resp1 = $this->postJson('/ia-test', ['pregunta' => 'Hola, quisiera información sobre costos del acta de nacimiento']);
        $resp1->assertStatus(200)->assertJson(['success' => true]);

        $data1 = $resp1->json();
        $convId = $data1['conversation_id'];

        // Mensaje de seguimiento que no debe iniciar nuevo flujo
        $resp2 = $this->postJson('/ia-test', ['pregunta' => '¿cuánto cuesta?', 'conversation_id' => $convId]);
        $resp2->assertStatus(200)->assertJson(['success' => true]);

        $data2 = $resp2->json();
        $this->assertArrayHasKey('metadata', $data2);
        $this->assertFalse($data2['metadata']['new_flow'] ?? false, 'Se esperaba new_flow = false para mensaje de seguimiento');
        $this->assertEquals($convId, $data2['conversation_id'], 'Se esperaba continuar con la misma conversación');
    }
}
