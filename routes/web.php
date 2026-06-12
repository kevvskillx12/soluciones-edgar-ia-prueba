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

        $pregunta = request('pregunta') ?? request('message');
        
        if (empty($pregunta)) {
            return response()->json([
                'success' => false,
                'error' => 'Se requiere pregunta o message'
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $conversationId = request('conversation_id');
        $newConversation = request('new_conversation', false);
        $user = auth()->user();

        $memoryService = app(\App\Services\AI\ConversationMemoryService::class);

        if ($newConversation || !$conversationId) {
            $conversation = $memoryService->startOrResume(null, $user, 'admin_chat');
        } else {
            $conversation = $memoryService->startOrResume($conversationId, $user, 'admin_chat');
        }

        $memoryService->addUserMessage($conversation, $pregunta);

        if (app()->environment('testing')) {
            $respuesta = "Respuesta de prueba con memoria persistente.";
            $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);
            
            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        function generateSimpleResponse($pregunta) {
            $preguntaLower = strtolower($pregunta);
            
            if (preg_match('/hola|buenos días|buenas tardes|buenas noches/', $preguntaLower)) {
                return '¡Hola! ¿En qué puedo ayudarte hoy?';
            }
            
            if (preg_match('/cómo estás|cómo va/', $preguntaLower)) {
                return 'Estoy funcionando bien, gracias por preguntar. ¿En qué puedo ayudarte hoy?';
            }
            
            if (preg_match('/gracias/', $preguntaLower)) {
                return 'De nada! Estoy aquí para ayudarte. ¿Necesitas algo más?';
            }
            
            if (preg_match('/nombre/', $preguntaLower)) {
                return 'Soy el asistente de Soluciones Edgar, aquí para ayudarte con información sobre nuestros servicios.';
            }
            
            return 'Entiendo tu pregunta. ¿Podrías proporcionar más detalles para que pueda ayudarte mejor?';
        }

        $pythonPath = base_path('rag/venv/bin/python');
        $scriptPath = base_path('rag/rag_bridge.py');

        if (!file_exists($pythonPath) || !file_exists($scriptPath)) {
            $respuesta = generateSimpleResponse($pregunta);
            $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => ['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $preguntaEsc = escapeshellarg($pregunta);
        $command = 'PYTHONIOENCODING=utf-8 "' . $pythonPath . '" "' . $scriptPath . '" ' . $preguntaEsc . ' 2>&1';

        $output = shell_exec($command);

        if ($output === null) {
            $respuesta = generateSimpleResponse($pregunta);
            $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => ['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $respuesta = generateSimpleResponse($pregunta);
            $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => ['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if ($data && isset($data['respuesta'])) {
            $respuesta = mb_convert_encoding($data['respuesta'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'rag']);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => ['tool' => 'rag', 'conversation_resumed' => (bool) $conversationId]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $respuesta = generateSimpleResponse($pregunta);
        $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->conversation_id,
            'respuesta' => $respuesta,
            'metadata' => ['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId]
        ], 200, [], JSON_UNESCAPED_UNICODE);

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Error en /ia-test: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'conversation_id' => isset($conversation) ? $conversation->conversation_id : null,
            'respuesta' => 'Ocurrió un error al procesar su solicitud. Por favor intente de nuevo más tarde.',
            'metadata' => ['error' => 'php_exception']
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
});