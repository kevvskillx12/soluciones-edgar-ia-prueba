<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('is_admin', true)->first();
$services = App\Models\Service::all();

if (! $admin || $services->isEmpty()) {
    fwrite(STDERR, "No hay admin o servicios disponibles\n");
    exit(1);
}

$statuses = ['pending', 'processing', 'completed', 'rejected'];
$channels = ['chat interno', 'WhatsApp', 'ventanilla digital', 'atención telefónica'];
$notes = [
    'Solicitud recibida y registrada para seguimiento.',
    'Documentación pendiente de revisión por el área operativa.',
    'Cliente solicita actualización del estado del trámite.',
    'Expediente capturado desde el centro de atención.',
    'Información lista para validación interna.',
];

for ($i = 1; $i <= 1000; $i++) {
    $service = $services->random();
    $user = App\Models\User::query()->where('id', '!=', $admin->id)->inRandomOrder()->first() ?? $admin;

    App\Models\Order::create([
        'user_id' => $user->id,
        'service_id' => $service->id,
        'input_data' => [
            'folio_interno' => sprintf('SE-ORD-2026-%06d', $i),
            'canal' => $channels[array_rand($channels)],
        ],
        'status' => $statuses[array_rand($statuses)],
        'admin_notes' => $notes[array_rand($notes)],
        'price_at_purchase' => $service->price ?? 100,
        'service_cost_snapshot' => $service->cost ?? 50,
        'service_price_snapshot' => $service->price ?? 100,
    ]);
}

echo "Creadas 1000 órdenes\n";
