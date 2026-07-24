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
for ($i = 1; $i <= 1000; $i++) {
    $service = $services->random();
    $user = App\Models\User::query()->where('id', '!=', $admin->id)->inRandomOrder()->first() ?? $admin;
    App\Models\Order::create([
        'user_id' => $user->id,
        'service_id' => $service->id,
        'input_data' => ['demo' => "registro-$i"],
        'status' => $statuses[array_rand($statuses)],
        'admin_notes' => 'Registro generado para demo '.$i,
        'price_at_purchase' => $service->price ?? 100,
        'service_cost_snapshot' => $service->cost ?? 50,
        'service_price_snapshot' => $service->price ?? 100,
    ]);
}
echo "Creadas 1000 órdenes\n";
