<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->is_admin ? redirect('/admin') : redirect('/app');
    }

    return redirect('/app/login');
});

Route::get('/login', function () {
    return redirect('/app/login');
})->name('login');

Route::get('/support/whatsapp', \App\Http\Controllers\SupportRedirectController::class)
    ->middleware('auth')
    ->name('support.whatsapp');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('/app/orders/{order}/download', function (\App\Models\Order $order) {
    if ($order->user_id !== auth()->id() && !auth()->user()->is_admin) {
        abort(403);
    }

    if (!$order->result_file_path) {
        abort(404);
    }

    return \Illuminate\Support\Facades\Storage::disk('s3')->download(
        $order->result_file_path,
        'Resultado_' . $order->id . '.pdf'
    );
})->middleware(['auth'])->name('orders.download');

require __DIR__ . '/auth.php';

Route::view('/ia-chat', 'ia-chat');

Route::post('/ia-test', function () {

    try {
        set_time_limit(300);

        $pregunta = request('pregunta', 'Hola');

        $pythonPath = base_path('rag/venv/bin/python');
        $scriptPath = base_path('rag/rag_bridge.py');

        if (!file_exists($pythonPath)) {
            return response()->json([
                'respuesta' => 'ERROR: No existe python.exe en esta ruta: ' . $pythonPath
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if (!file_exists($scriptPath)) {
            return response()->json([
                'respuesta' => 'ERROR: No existe rag_bridge.py en esta ruta: ' . $scriptPath
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $preguntaEsc = escapeshellarg($pregunta);

        $command = 'PYTHONIOENCODING=utf-8 "' . $pythonPath . '" "' . $scriptPath . '" ' . $preguntaEsc . ' 2>&1';

        $output = shell_exec($command);

        if ($output === null) {
            return response()->json([
                'respuesta' => 'ERROR: shell_exec devolvió null. Puede estar deshabilitado en PHP o no pudo ejecutar el comando.',
                'comando' => $command
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Forzar salida a UTF-8 para evitar error de caracteres mal codificados
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'respuesta' => 'ERROR: La salida del RAG no es JSON válido. Salida recibida: ' . $output,
                'comando' => $command
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if ($data && isset($data['respuesta'])) {
            $respuesta = mb_convert_encoding($data['respuesta'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

            return response()->json([
                'respuesta' => $respuesta
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'respuesta' => 'ERROR: El RAG respondió JSON, pero no contiene la clave respuesta. Salida: ' . $output,
            'comando' => $command
        ], 200, [], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        return response()->json([
            'respuesta' => 'ERROR PHP en /ia-test: ' . $e->getMessage()
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
});