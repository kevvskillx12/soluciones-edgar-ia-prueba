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

        $conversationHistory = $memoryService->getPromptBuffer($conversation, 20);
        $conversationContext = '';
        if (!empty($conversationHistory)) {
            $conversationContext = collect($conversationHistory)
                ->map(fn ($item) => strtoupper($item['role']) . ': ' . $item['content'])
                ->implode("\n\n");
        }

        $preguntaConContexto = trim(($conversationContext ? $conversationContext . "\n\n" : '') . 'PREGUNTA ACTUAL: ' . $pregunta);

        $detectNewFlow = function (string $text) {
            $textLower = strtolower($text);
            $triggers = [
                'quiero sacar un curp',
                'quiero sacar una curp',
                'quiero sacar un acta',
                'quiero sacar un acta para otra persona',
                'quiero otro trámite',
                'quiero otro tramite',
                'ahora quiero',
                'necesito sacar',
                'quiero otro',
                'quiero sacar',
            ];

            foreach ($triggers as $t) {
                if (str_contains($textLower, $t)) {
                    return true;
                }
            }

            if (preg_match('/\bquiero\s+(sacar|otro|necesito)\b/i', $text)) {
                return true;
            }

            return false;
        };

        // Si detectamos un nuevo flujo en el mensaje actual, no debemos reutilizar el historial
        $isNewFlow = $detectNewFlow($pregunta);

        if ($isNewFlow) {
            try {
                $prevPending = $memoryService->getPendingOrderState($conversation);
                if ($prevPending) {
                    $meta = $conversation->metadata ?? [];
                    $closed = $meta['closed_orders'] ?? [];
                    $prevPending['closed_at'] = now()->toDateTimeString();
                    $prevPending['closed_reason'] = 'new_flow_started';
                    $closed[] = $prevPending;
                    $meta['closed_orders'] = $closed;
                    unset($meta['pending_order']);
                    $conversation->update(['metadata' => $meta]);
                }
            } catch (\Throwable $e) {
            }

            // Creamos una nueva conversación limpia para no arrastrar contexto
            $conversation = $memoryService->startOrResume(null, $user, 'admin_chat');
            $conversationHistory = [];
            $conversationContext = '';
            $preguntaConContexto = 'PREGUNTA ACTUAL: ' . $pregunta;
        }

        // Guardar el mensaje del usuario en la conversación correcta (la nueva si se inició)
        $memoryService->addUserMessage($conversation, $pregunta);

        if (app()->environment('testing')) {
            $respuesta = "Respuesta de prueba con memoria persistente.";
            try {
                $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant (simulated): ' . $e->getMessage(), ['conversation_id' => $conversation->conversation_id]);
            }
            
            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $findPythonExecutable = function () {
            $candidates = [
                env('PYTHON_PATH'),
                base_path('rag/venv/Scripts/python.exe'),
                base_path('rag/venv/bin/python'),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate && file_exists($candidate)) {
                    return $candidate;
                }
            }

            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                $wherePython = trim(@shell_exec('where python 2>NUL'));
                if ($wherePython) {
                    foreach (preg_split('/\r?\n/', $wherePython) as $path) {
                        if ($path && file_exists($path)) {
                            return $path;
                        }
                    }
                }

                $wherePython3 = trim(@shell_exec('where python3 2>NUL'));
                if ($wherePython3) {
                    foreach (preg_split('/\r?\n/', $wherePython3) as $path) {
                        if ($path && file_exists($path)) {
                            return $path;
                        }
                    }
                }
            } else {
                $whichPython = trim(@shell_exec('command -v python3 2>/dev/null') ?: @shell_exec('command -v python 2>/dev/null'));
                if ($whichPython && file_exists($whichPython)) {
                    return $whichPython;
                }
            }

            return null;
        };

        $findServiceByName = function (?string $serviceName) {
            if (empty($serviceName)) {
                return null;
            }

            $searchValue = trim($serviceName);
            $service = \App\Models\Service::where('code', strtoupper($searchValue))
                ->orWhere('name', 'like', '%' . $searchValue . '%')
                ->first();

            if ($service) {
                return $service;
            }

            $normalized = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'],
                ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'],
                $searchValue
            );

            return \App\Models\Service::where('name', 'like', '%' . $normalized . '%')->first();
        };

        $extractOrderContext = function ($pregunta, array $conversationHistory) {
            $preguntaLower = strtolower($pregunta);
            // Solo juntar mensajes de usuario (no assistant) para evitar extraer texto del asistente
            $userMessages = array_filter($conversationHistory, fn($m) => ($m['role'] ?? '') === 'user');
            $historyUser = strtolower(implode(' ', array_column($userMessages, 'content')));
            $combinedText = trim($historyUser . ' ' . $preguntaLower);

            $serviceMatch = [];
            preg_match('/\b(acta de nacimiento|acta de defunción|acta de divorcio|acta de matrimonio|constancia de nacimiento|constancia de acta|csf clon con curp|csf con rfc y idcif|idcif|nss|afore|curp actualizada)\b/i', $combinedText, $serviceMatch);
            $service = $serviceMatch[1] ?? null;

            if (empty($service)) {
                $codeMatch = [];
                preg_match('/\b(ACT-NAC|ACT-DEF|ACT-DIV|ACT-MAT|CSF-01|CSF-02|IDCIF-01|NSS-01|NSS-02|NSS-03|AFO-01|CURP-01)\b/i', $combinedText, $codeMatch);
                $service = $codeMatch[1] ?? $service;
            }

            // Extraer nombre priorizando el mensaje actual (usuario). No usar texto del asistente.
            $name = null;
            $namePatterns = [
                '/\b(?:para|para el cliente|para la clienta|para el|para la|para)\s+([^\.,;\n\r\?\!]{2,100})/i',
                '/\bnombre(?: completo)?(?: es)?\s+([^\.,;\n\r\?\!]{2,100})/i',
            ];

            // Probar primero en el mensaje actual (pregunta)
            foreach ($namePatterns as $pattern) {
                if (preg_match($pattern, $pregunta, $nameMatch)) {
                    $cand = trim($nameMatch[1]);
                    // retirar posibles fragmentos de trámite que puedan quedar al inicio
                    $cand = preg_replace('/^(el|la|los|las)\s+/i', '', $cand);
                    // recortar por palabras clave que no forman parte de un nombre
                    $cand = preg_split('/\b(acta|nacimiento|curp|trámite|tramite|solicitud|entendido|cliente|correo|usuario|su|es|el|la|los|las|mi|del|para)\b/i', $cand)[0];
                    $cand = trim($cand);
                    if (strlen($cand) > 0) {
                        $name = $cand;
                        break;
                    }
                }
            }

            // Si no encontrado en el mensaje actual, probar en mensajes previos del usuario
            // Buscar en cada mensaje individualmente para evitar que se concatenen palabras de distintos mensajes
            $userMessages = array_filter($conversationHistory, fn($m) => ($m['role'] ?? '') === 'user');
            $userMessagesReversed = array_reverse(array_values($userMessages));
            if (empty($name) && !empty($userMessagesReversed)) {
                foreach ($userMessagesReversed as $msg) {
                    foreach ($namePatterns as $pattern) {
                        if (preg_match($pattern, $msg['content'], $nameMatch)) {
                            $cand = trim($nameMatch[1]);
                            $cand = preg_replace('/^(el|la|los|las)\s+/i', '', $cand);
                            $cand = preg_split('/\b(acta|nacimiento|curp|trámite|tramite|solicitud|entendido|cliente|correo|usuario|su|es|el|la|los|las|mi|del|para)\b/i', $cand)[0];
                            $cand = trim($cand);
                            if (strlen($cand) > 0) {
                                $name = $cand;
                                break 2;
                            }
                        }
                    }
                }
            }

            // Normalizar capitalización simple y limpiar espacios extras
            if (!empty($name)) {
                $name = preg_replace('/\s+/', ' ', trim($name));
                $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            }

            $curpMatch = [];
            preg_match('/\b([A-Z]{4}\d{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]{2})\b/i', $combinedText, $curpMatch);
            $curp = isset($curpMatch[1]) ? strtoupper($curpMatch[1]) : null;

            $emailMatch = [];
            preg_match('/\b([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[A-Za-z]{2,})\b/i', $combinedText, $emailMatch);
            $email = $emailMatch[1] ?? null;

            $actionCreate = (bool) preg_match('/\b(crear( la)? solicitud|registrar( la)? solicitud|hacer( la)? solicitud|ordenar( el| la)? trámite|quiero (crear|hacer|registrar)( la)? solicitud|continuar con la solicitud|quiero crearla|sí,? crear|si,? crear|sí quiero crear|si quiero crear)\b/i', $preguntaLower);
            // Intención explícita más estricta para crear orden (se requiere frase de confirmación directa)
            $explicitCreate = (bool) preg_match('/\b(deseo crear( la)? solicitud|deseo crear la solicitud|crear solicitud|registrar solicitud|confirmo crear|confirmar crear|confirmar crear la solicitud|quiero crear la solicitud|deseo crear|quiero crearla|sí,? crear|si,? crear)\b/i', $preguntaLower);
            $actionInfo = (bool) preg_match('/\b(consultar información|información del trámite|revisar costo|cuánto cuesta|precio|costo)\b/i', $preguntaLower);
            $actionCancel = (bool) preg_match('/\b(cancelar|terminar|ya no|no quiero continuar)\b/i', $preguntaLower);

            return compact('service', 'name', 'curp', 'email', 'actionCreate', 'explicitCreate', 'actionInfo', 'actionCancel');
        };

        $createOrderIfReady = function (?string $serviceText, ?string $name, ?string $curp, ?string $email, $conversation, $memoryService, $user) use ($findServiceByName) {
            if (empty($serviceText) || empty($name) || empty($curp) || empty($email)) {
                return null;
            }

            $service = $findServiceByName($serviceText);
            if (! $service) {
                return [
                    'success' => false,
                    'message' => "Encontré el trámite \"{$serviceText}\", pero no pude ubicarlo en el catálogo. Por favor indica el nombre exacto del servicio."
                ];
            }

            if (! $user->is_admin && $user->balance < $service->price) {
                return [
                    'success' => false,
                    'message' => "No tienes saldo suficiente para crear esta solicitud. El costo es \${$service->price} y tu saldo disponible es \${$user->balance}."
                ];
            }

            try {
                $order = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $service, $name, $curp, $email) {
                    return \App\Models\Order::create([
                        'user_id' => $user->id,
                        'service_id' => $service->id,
                        'input_data' => ['curp' => $curp, 'cliente' => $name, 'cliente_email' => $email],
                        'status' => 'pending',
                        'price_at_purchase' => $service->price,
                        'admin_notes' => "Solicitud creada por chat. Cliente: {$name}. Email: {$email}. CURP: {$curp}."
                    ]);
                });

                // Marcar pending order como limpio y marcar la conversación como completada
                try {
                    $memoryService->clearPendingOrderState($conversation);

                    $meta = $conversation->metadata ?? [];
                    $meta['status'] = 'completed';
                    $meta['order_id'] = $order->id;
                    $meta['completed_at'] = now()->toDateTimeString();
                    $conversation->update(['metadata' => $meta]);
                } catch (\Throwable $e) {
                    // no bloquear la creación por errores de metadata
                }

                return [
                    'success' => true,
                    'message' => "Solicitud registrada correctamente. Pedido #{$order->id} creado con servicio {$service->name} y estado pendiente.",
                    'order_id' => $order->id,
                    'conversation_completed' => true,
                    'reset_conversation' => true,
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Error creando orden desde AI Chat: ' . $e->getMessage(), [
                    'exception' => $e,
                    'conversation_id' => $conversation->conversation_id,
                ]);

                return [
                    'success' => false,
                    'message' => 'No pude crear la solicitud por un error interno. Revisa logs.'
                ];
            }
        };

        $generateSimpleResponse = function ($pregunta, array $conversationHistory = [], $conversation = null, $memoryService = null, $user = null) use ($extractOrderContext, $findServiceByName, $createOrderIfReady) {
            $orderContext = $extractOrderContext($pregunta, $conversationHistory);
            $service = $orderContext['service'];
            $name = $orderContext['name'];
            $curp = $orderContext['curp'];
            $email = $orderContext['email'];
            $actionCreate = $orderContext['actionCreate'];
            $actionInfo = $orderContext['actionInfo'];
            $actionCancel = $orderContext['actionCancel'];

            $hasService = !empty($service);
            $hasName = !empty($name);
            $hasCurp = !empty($curp);
            $allUserText = strtolower(implode(' ', array_column(
                array_filter($conversationHistory, fn($m) => ($m['role'] ?? '') === 'user'),
                'content'
            )));
            $hasUserClient = !empty($email) || preg_match('/usuario\s+cliente/i', $allUserText . ' ' . strtolower($pregunta));

            $serviceModel = $findServiceByName($service);
            $pendingOrderData = array_filter([
                'service_name' => $service,
                'service_id' => $serviceModel?->id,
                'client_name' => $name,
                'client_email' => $email,
                'input_data' => array_filter(['curp' => $curp, 'cliente' => $name, 'cliente_email' => $email]),
            ], fn ($value) => $value !== null && $value !== []);

            if (! empty($pendingOrderData) && $conversation && $memoryService) {
                $memoryService->savePendingOrderState($conversation, $pendingOrderData);
            }

            if ($actionCancel) {
                $memoryService->clearPendingOrderState($conversation);
                return 'Entendido. He cancelado la solicitud en proceso. Si quieres comenzar de nuevo, dime qué trámite necesitas.';
            }

            if ($actionCreate) {
                if (! $hasService) {
                    return 'Para crear la solicitud necesito saber qué trámite deseas solicitar. Ejemplo: acta de nacimiento.';
                }

                if (! $hasName) {
                    return "Para el trámite {$service}, necesito el nombre completo del cliente.";
                }

                if (! $hasCurp) {
                    return "Para el trámite {$service} de {$name}, necesito la CURP.";
                }

                if (! $hasUserClient) {
                    return 'Necesito el correo electrónico del usuario cliente para registrar la solicitud.';
                }

                // Requerir confirmación explícita para crear la orden
                $explicitCreate = $orderContext['explicitCreate'] ?? false;
                if (! $explicitCreate) {
                    return sprintf(
                        'Perfecto. Tengo: trámite de %s, nombre %s, CURP %s y usuario cliente %s. ¿Deseas crear la solicitud ahora?',
                        $service,
                        $name,
                        $curp,
                        $email ?: 'el usuario cliente'
                    );
                }

                $orderResult = $createOrderIfReady($service, $name, $curp, $email, $conversation, $memoryService, $user);
                if ($orderResult['success']) {
                    return $orderResult['message'];
                }

                return $orderResult['message'];
            }

            if ($actionInfo && $serviceModel) {
                return "El costo actual del servicio {$serviceModel->name} es \${$serviceModel->price}. Si deseas, puedo crear la solicitud cuando tengas todos los datos.";
            }

            if ($hasService && $hasName && $hasCurp && $hasUserClient) {
                return sprintf(
                    'Perfecto. Tengo: trámite de %s, nombre %s, CURP %s y usuario cliente %s. ¿Deseas crear la solicitud ahora?',
                    $service,
                    $name,
                    $curp,
                    $email ?: 'el usuario cliente'
                );
            }

            if ($hasService && $hasName && $hasCurp && ! $hasUserClient) {
                return sprintf(
                    'Ya tengo el trámite, el nombre y la CURP. Ahora necesito saber para qué usuario cliente se va a registrar la solicitud. Por favor proporciona el correo electrónico del usuario cliente.'
                );
            }

            if ($hasService && $hasName && ! $hasCurp) {
                return sprintf(
                    'Entendido. Para el %s de %s, necesito la CURP.',
                    $service ?: 'trámite',
                    $name
                );
            }

            if ($hasService && ! $hasName) {
                return sprintf(
                    'Entendido. Para el %s, necesito el nombre completo del cliente.',
                    $service ?: 'trámite'
                );
            }

            if (preg_match('/hola|buenos días|buenas tardes|buenas noches/i', $pregunta)) {
                return '¡Hola! ¿En qué puedo ayudarte hoy?';
            }

            if (preg_match('/cómo estás|cómo va/i', $pregunta)) {
                return 'Estoy funcionando bien, gracias por preguntar. ¿En qué puedo ayudarte hoy?';
            }

            if (preg_match('/gracias/i', $pregunta)) {
                return 'De nada! Estoy aquí para ayudarte. ¿Necesitas algo más?';
            }

            if (preg_match('/nombre/i', $pregunta)) {
                return 'Soy el asistente de Soluciones Edgar, aquí para ayudarte con información sobre nuestros servicios.';
            }

            if (!empty($conversationHistory)) {
                if ($hasService && $hasName && ! $hasCurp) {
                    return sprintf('Entendido. Para el %s de %s, necesito la CURP.', $service ?: 'trámite', $name);
                }
                if ($hasService && $hasName && $hasCurp && ! $hasUserClient) {
                    return 'Ya tengo el trámite, el nombre y la CURP. Ahora necesito saber para qué usuario cliente se va a registrar la solicitud.';
                }
                return 'Tengo el historial de la conversación. Por favor confirma si deseas continuar con el mismo trámite o agrega más datos para avanzar.';
            }

            return 'Entiendo tu pregunta. ¿Podrías proporcionar más detalles para que pueda ayudarte mejor?';
        };

        $pythonPath = $findPythonExecutable();
        $scriptPath = base_path('rag/rag_bridge.py');

        if (!$pythonPath || !file_exists($scriptPath)) {
            $respuesta = $generateSimpleResponse($pregunta, $conversationHistory, $conversation, $memoryService, $user);
                try {
                    $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant (simulated): ' . $e->getMessage(), ['conversation_id' => $conversation->conversation_id]);
                }

            $isCompleted = isset($conversation->metadata['status']) && $conversation->metadata['status'] === 'completed';
            $orderId = $conversation->metadata['order_id'] ?? null;

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => array_merge(['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId, 'new_flow' => ($isNewFlow ?? false), 'new_conversation_id' => ($isNewFlow ? $conversation->conversation_id : null)], ['conversation_completed' => $isCompleted, 'reset_conversation' => $isCompleted, 'order_id' => $orderId])
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        putenv('PYTHONIOENCODING=utf-8');
        $pythonBin = escapeshellarg($pythonPath);
        $scriptPathEsc = escapeshellarg($scriptPath);
        $preguntaEsc = escapeshellarg($preguntaConContexto);
        $command = $pythonBin . ' ' . $scriptPathEsc . ' ' . $preguntaEsc . ' 2>&1';

        $output = shell_exec($command);

        if ($output === null) {
            $respuesta = $generateSimpleResponse($pregunta, $conversationHistory, $conversation, $memoryService, $user);
            try {
                $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant (simulated): ' . $e->getMessage(), ['conversation_id' => $conversation->conversation_id]);
            }

            $isCompleted = isset($conversation->metadata['status']) && $conversation->metadata['status'] === 'completed';
            $orderId = $conversation->metadata['order_id'] ?? null;

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => array_merge(['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId, 'new_flow' => ($isNewFlow ?? false), 'new_conversation_id' => ($isNewFlow ? $conversation->conversation_id : null)], ['conversation_completed' => $isCompleted, 'reset_conversation' => $isCompleted, 'order_id' => $orderId])
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $respuesta = $generateSimpleResponse($pregunta, $conversationHistory, $conversation, $memoryService, $user);
            try {
                $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant (simulated): ' . $e->getMessage(), ['conversation_id' => $conversation->conversation_id]);
            }

            $isCompleted = isset($conversation->metadata['status']) && $conversation->metadata['status'] === 'completed';
            $orderId = $conversation->metadata['order_id'] ?? null;

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => array_merge(['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId, 'new_flow' => ($isNewFlow ?? false), 'new_conversation_id' => ($isNewFlow ? $conversation->conversation_id : null)], ['conversation_completed' => $isCompleted, 'reset_conversation' => $isCompleted, 'order_id' => $orderId])
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        if ($data && isset($data['respuesta'])) {
            $respuesta = mb_convert_encoding($data['respuesta'], 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            try {
                $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'rag']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant (rag): ' . $e->getMessage(), ['conversation_id' => $conversation->conversation_id]);
            }
            $isCompleted = isset($conversation->metadata['status']) && $conversation->metadata['status'] === 'completed';
            $orderId = $conversation->metadata['order_id'] ?? null;

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->conversation_id,
                'respuesta' => $respuesta,
                'metadata' => array_merge(['tool' => 'rag', 'conversation_resumed' => (bool) $conversationId, 'new_flow' => ($isNewFlow ?? false), 'new_conversation_id' => ($isNewFlow ? $conversation->conversation_id : null)], ['conversation_completed' => $isCompleted, 'reset_conversation' => $isCompleted, 'order_id' => $orderId])
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $respuesta = $generateSimpleResponse($pregunta, $conversationHistory, $conversation, $memoryService, $user);
        try {
            $memoryService->addAssistantMessage($conversation, $respuesta, ['tool' => 'simulated']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant (simulated): ' . $e->getMessage(), ['conversation_id' => $conversation->conversation_id]);
        }

        $isCompleted = isset($conversation->metadata['status']) && $conversation->metadata['status'] === 'completed';
        $orderId = $conversation->metadata['order_id'] ?? null;

        return response()->json([
            'success' => true,
            'conversation_id' => $conversation->conversation_id,
            'respuesta' => $respuesta,
            'metadata' => array_merge(['tool' => 'simulated', 'conversation_resumed' => (bool) $conversationId, 'new_flow' => ($isNewFlow ?? false), 'new_conversation_id' => ($isNewFlow ? $conversation->conversation_id : null)], ['conversation_completed' => $isCompleted, 'reset_conversation' => $isCompleted, 'order_id' => $orderId])
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