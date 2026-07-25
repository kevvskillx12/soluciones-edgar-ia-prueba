<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::pluck('id')->all();
$services = App\Models\Service::pluck('id')->all();
$statuses = ['pending', 'processing', 'completed', 'rejected'];
$channels = ['chat interno', 'WhatsApp', 'ventanilla digital', 'atención telefónica'];
$notes = [
    'Solicitud recibida y registrada para seguimiento.',
    'Documentación pendiente de revisión por el área operativa.',
    'Cliente solicita actualización del estado del trámite.',
    'Expediente capturado desde el centro de atención.',
    'Información lista para validación interna.',
];

$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$stmt = $pdo->prepare('INSERT INTO orders (user_id, service_id, input_data, status, admin_notes, price_at_purchase, service_cost_snapshot, service_price_snapshot, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$now = date('Y-m-d H:i:s');

for ($i = 1; $i <= 1000; $i++) {
    $userId = $users[array_rand($users)];
    $serviceId = $services[array_rand($services)];
    $input = json_encode([
        'folio_interno' => sprintf('SE-ORD-2026-%06d', $i),
        'canal' => $channels[array_rand($channels)],
    ], JSON_UNESCAPED_UNICODE);
    $status = $statuses[array_rand($statuses)];
    $price = 100;
    $cost = 50;

    $stmt->execute([$userId, $serviceId, $input, $status, $notes[array_rand($notes)], $price, $cost, $price, $now, $now]);
}

echo "Creadas 1000 órdenes\n";
