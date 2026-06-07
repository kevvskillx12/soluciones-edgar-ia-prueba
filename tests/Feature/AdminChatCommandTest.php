<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\AI\AdminChatCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminChatCommandTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected Service $serviceActa;
    protected Service $serviceCURP;
    protected Service $serviceNSS;
    protected Service $serviceCSF;
    protected Service $serviceTenenciaCdmx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'is_admin' => true,
            'balance' => 0,
        ]);

        $this->client = User::factory()->create([
            'name' => 'Luis Alfonso Ek Pech',
            'email' => 'luis@test.com',
            'is_admin' => false,
            'balance' => 100,
        ]);

        $this->serviceActa = Service::create([
            'name' => 'Acta de Nacimiento',
            'code' => 'ACT-NAC-TEST',
            'price' => 70.00,
            'cost' => 49.00,
            'is_active' => true,
            'automation_type' => 'manual',
            'service_type' => 'ACTAS',
            'form_schema' => [
                ['name' => 'curp', 'label' => 'CURP', 'type' => 'text', 'required' => true],
            ],
        ]);

        $this->serviceCURP = Service::create([
            'name' => 'CURP Actualizada',
            'code' => 'CURP-TEST',
            'price' => 8.00,
            'cost' => 5.00,
            'is_active' => true,
            'automation_type' => 'manual',
            'service_type' => 'SERVICIOS',
        ]);

        $this->serviceNSS = Service::create([
            'name' => 'Localizar NSS con CURP',
            'code' => 'NSS-LOC-TEST',
            'price' => 20.00,
            'cost' => 14.00,
            'is_active' => true,
            'automation_type' => 'manual',
            'service_type' => 'SINDOS IMSS',
        ]);

        $this->serviceCSF = Service::create([
            'name' => 'Constancia de Situación Fiscal',
            'code' => 'CSF-TEST',
            'price' => 55.00,
            'cost' => 35.00,
            'is_active' => true,
            'automation_type' => 'manual',
            'service_type' => 'SAT',
            'form_schema' => [
                ['name' => 'rfc', 'label' => 'RFC', 'type' => 'text', 'required' => true],
            ],
        ]);

        $this->serviceTenenciaCdmx = Service::create([
            'name' => 'FORMATO PAGO DE TENENCIA CD MX',
            'code' => 'TEN-CDMX-TEST',
            'price' => 30.00,
            'cost' => 20.00,
            'is_active' => true,
            'automation_type' => 'manual',
            'service_type' => 'VEHICULOS',
            'form_schema' => [
                ['name' => 'plate', 'label' => 'Placa', 'type' => 'text', 'required' => true],
                ['name' => 'year', 'label' => 'Año', 'type' => 'text', 'required' => true],
            ],
        ]);
    }

    protected function getCommandService(): AdminChatCommandService
    {
        return app(AdminChatCommandService::class);
    }

    // ============ REPORTE DE CIERRE ============

    public function test_reporte_cierre_generates_file_when_orders_exist()
    {
        Order::create([
            'user_id' => $this->client->id,
            'service_id' => $this->serviceActa->id,
            'status' => 'completed',
            'input_data' => ['curp' => 'EXPL050202HYNKCSA0'],
            'price_at_purchase' => 0,
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('reporte de cierre', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Reporte generado', $result['respuesta']);
        $this->assertStringContainsString('storage/reports/', $result['url']);
        $this->assertTrue(Storage::disk('public')->exists($result['file_path']));
    }

    public function test_reporte_cierre_returns_message_when_no_orders()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('reporte de cierre', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('No hay pedidos recientes', $result['respuesta']);
    }

    public function test_reporte_ultimo_tramite_generates_file()
    {
        Order::insert([
            [
                'user_id' => $this->client->id,
                'service_id' => $this->serviceActa->id,
                'status' => 'completed',
                'input_data' => json_encode(['curp' => 'EXPL...']),
                'price_at_purchase' => 0,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
            [
                'user_id' => $this->client->id,
                'service_id' => $this->serviceCURP->id,
                'status' => 'pending',
                'input_data' => json_encode(['curp' => 'LOAA...']),
                'price_at_purchase' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('genera un reporte del último trámite', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('último trámite', $result['respuesta']);
        $this->assertTrue(Storage::disk('public')->exists($result['file_path']));
    }

    // ============ CREAR TRÁMITE / PEDIDO ============

    public function test_crear_tramite_creates_order_with_valid_data()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle(
            'Crea un trámite de Acta de Nacimiento para Luis Alfonso con CURP EXPL050202HYNKCSA0 para usuario luis@test.com',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Pedido creado correctamente', $result['respuesta']);
        $this->assertStringContainsString('Acta de Nacimiento', $result['respuesta']);
        $this->assertArrayHasKey('order_id', $result);

        $order = Order::find($result['order_id']);
        $this->assertNotNull($order);
        $this->assertEquals($this->serviceActa->id, $order->service_id);
        $this->assertEquals($this->client->id, $order->user_id);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('manual_review', $order->api_status);
        $this->assertEquals('EXPL050202HYNKCSA0', $order->input_data['curp']);
        $this->assertEquals('Luis Alfonso', $order->input_data['solicitante']);
    }

    public function test_crear_tramite_without_user_asks_for_user()
    {
        User::factory()->create([
            'name' => 'Ana López',
            'email' => 'ana@test.com',
            'is_admin' => false,
            'balance' => 50,
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle(
            'Crea un trámite de CURP para Ana López con CURP LOAA010203MYNPRA09',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('falta indicar el usuario cliente', $result['respuesta']);
        $this->assertStringContainsString('ana@test.com', $result['respuesta']);
    }

    public function test_crear_tramite_service_not_found_suggests_similar()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle(
            'Crea un trámite de ServicioInexistente123 para Luis Alfonso',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('No encontre un servicio', $result['respuesta']);
        $this->assertStringContainsString('Acta de Nacimiento', $result['respuesta']);
    }

    public function test_crear_tramite_without_service_name_asks_for_it()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle(
            'Crea un trámite',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('puedo ayudarte a solicitar un servicio', Str::lower($result['respuesta']));
    }

    public function test_crear_tramite_no_user_available_asks_for_user()
    {
        User::factory()->create([
            'name' => 'Carlos Pérez',
            'email' => 'carlos@test.com',
            'is_admin' => false,
            'balance' => 50,
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle(
            'Crea un trámite de Acta de Nacimiento para Carlos Pérez',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Usuario cliente', $result['respuesta']);
        $this->assertStringContainsString('carlos@test.com', $result['respuesta']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_generic_natural_service_request_returns_guided_help()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('quiero solicitar un servicio bien perrón', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertSame(AdminChatCommandService::class, $this->getCommandService()::class);
        $this->assertStringContainsString('puedo ayudarte a solicitar un servicio', Str::lower($result['respuesta']));
        $this->assertStringContainsString('Acta de Nacimiento', $result['respuesta']);
        $this->assertStringContainsString('CURP actualizada', $result['respuesta']);
        $this->assertStringNotContainsString('No hay información específica', $result['respuesta']);
    }

    public function test_natural_acta_request_asks_only_for_curp_and_user()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('Quiero sacar un acta de nacimiento para Luis Alfonso', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertStringContainsString('Acta de Nacimiento para Luis Alfonso', $result['respuesta']);
        $this->assertStringContainsString('Luis Alfonso', $result['respuesta']);
        $this->assertStringContainsString('CURP del solicitante', $result['respuesta']);
        $this->assertStringContainsString('Usuario cliente', $result['respuesta']);
        $this->assertStringNotContainsString('No hay información específica', $result['respuesta']);
    }

    public function test_natural_curp_request_asks_for_missing_data()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('quiero sacar mi curp', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('CURP actualizada', $result['respuesta']);
        $this->assertStringContainsString('Nombre del solicitante', $result['respuesta']);
        $this->assertStringContainsString('Usuario cliente', $result['respuesta']);
    }

    public function test_constancia_fiscal_without_user_asks_for_user_only()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle(
            'Crea un trámite de Constancia de Situación Fiscal para Juan Pérez con RFC PEJJ010203AB1',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('falta indicar el usuario cliente', $result['respuesta']);
        $this->assertStringContainsString('luis@test.com', $result['respuesta']);
    }

    public function test_natural_constancia_fiscal_request_asks_for_rfc_and_user()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('quiero una constancia fiscal para Juan Pérez', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Constancia de Situacion Fiscal para Juan Pérez', $result['respuesta']);
        $this->assertStringContainsString('RFC del solicitante', $result['respuesta']);
        $this->assertStringContainsString('Usuario cliente', $result['respuesta']);
    }

    public function test_tenencia_without_region_asks_for_clarification()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('quiero pagar tenencia', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertSame('¿La tenencia es de CDMX o EDOMEX?', $result['respuesta']);
    }

    public function test_acta_without_type_asks_for_clarification()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('quiero un acta', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Que tipo de acta', Str::ascii($result['respuesta']));
        $this->assertStringContainsString('Acta de Matrimonio', $result['respuesta']);
    }

    public function test_constancia_fiscal_with_complete_data_creates_real_order()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle(
            'Crea un trámite de Constancia de Situación Fiscal para Juan Pérez con RFC PEJJ010203AB1 para usuario luis@test.com',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Pedido creado correctamente', $result['respuesta']);
        $this->assertStringContainsString('Constancia de Situación Fiscal', $result['respuesta']);

        $order = Order::find($result['order_id']);
        $this->assertNotNull($order);
        $this->assertEquals($this->serviceCSF->id, $order->service_id);
        $this->assertEquals('Juan Pérez', $order->input_data['solicitante']);
        $this->assertEquals('PEJJ010203AB1', $order->input_data['rfc']);
    }

    public function test_informational_message_returns_null_and_can_fall_back_to_rag()
    {
        $this->actingAs($this->admin);

        $result = $this->getCommandService()->handle('¿Cómo funciona el saldo del usuario?', $this->admin);

        $this->assertNull($result);
    }

    // ============ PEDIDOS FALLIDOS ============

    public function test_pedidos_fallidos_returns_message_when_none()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('resume los pedidos fallidos', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('No se encontraron pedidos fallidos', $result['respuesta']);
    }

    public function test_pedidos_fallidos_lists_failed_orders()
    {
        Order::create([
            'user_id' => $this->client->id,
            'service_id' => $this->serviceActa->id,
            'status' => 'pending',
            'api_status' => 'failed',
            'api_error_message' => 'Error de conexión con proveedor',
            'input_data' => ['curp' => 'EXPL...'],
            'price_at_purchase' => 0,
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('pedidos fallidos', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Error de conexión', $result['respuesta']);
    }

    // ============ PEDIDOS PENDIENTES ============

    public function test_pedidos_pendientes_returns_message_when_none()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('pedidos pendientes', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('No hay pedidos pendientes', $result['respuesta']);
    }

    public function test_pedidos_pendientes_lists_pending_orders()
    {
        Order::create([
            'user_id' => $this->client->id,
            'service_id' => $this->serviceCURP->id,
            'status' => 'pending',
            'input_data' => ['curp' => 'LOAA...'],
            'price_at_purchase' => 0,
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('dame los pedidos pendientes', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('CURP Actualizada', $result['respuesta']);
    }

    // ============ REVISIÓN MANUAL ============

    public function test_revision_manual_returns_orders()
    {
        Order::create([
            'user_id' => $this->client->id,
            'service_id' => $this->serviceActa->id,
            'status' => 'pending',
            'api_status' => 'manual_review',
            'input_data' => ['curp' => 'EXPL...'],
            'price_at_purchase' => 0,
        ]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('revisión manual', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('revisión manual', $result['respuesta']);
    }

    // ============ COMPLETADOS HOY ============

    public function test_completados_hoy_returns_message_when_none()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('completados hoy', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Aún no hay pedidos completados', $result['respuesta']);
    }

    public function test_completados_hoy_lists_completed_orders()
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'service_id' => $this->serviceActa->id,
            'status' => 'completed',
            'input_data' => ['curp' => 'EXPL...'],
            'price_at_purchase' => 0,
        ]);
        \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $order->id)
            ->update(['updated_at' => now()]);

        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('cierre de hoy', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('completados hoy', $result['respuesta']);
    }

    // ============ CONSULTAR SERVICIO ============

    public function test_consultar_servicio_lists_all_when_no_name()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle('qué servicios hay', $this->admin);

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Servicios disponibles', $result['respuesta']);
        $this->assertStringContainsString('Acta de Nacimiento', $result['respuesta']);
        $this->assertStringContainsString('CURP Actualizada', $result['respuesta']);
    }

    // ============ CONSULTAR USUARIO ============

    public function test_consultar_usuario_by_email()
    {
        $this->actingAs($this->admin);
        $result = $this->getCommandService()->handle(
            'consultar usuario luis@test.com',
            $this->admin
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['handled']);
        $this->assertStringContainsString('Luis Alfonso', $result['respuesta']);
        $this->assertStringContainsString('luis@test.com', $result['respuesta']);
    }

    // ============ NO ES ADMIN ============

    public function test_non_admin_returns_null()
    {
        $result = $this->getCommandService()->handle('reporte de cierre', $this->client);

        $this->assertNull($result);
    }

    // ============ HTTP ENDPOINT ============

    public function test_http_endpoint_returns_admin_command_response_for_admin_user()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/ia-test', [
            'pregunta' => 'Crea un trámite de Acta de Nacimiento para Luis Alfonso con CURP EXPL050202HYNKCSA0 para usuario luis@test.com',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['respuesta']);
        $response->assertSeeText('Pedido creado correctamente');
    }
}
