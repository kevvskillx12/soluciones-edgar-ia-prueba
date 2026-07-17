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

Route::get('/admin/direct-login', function () {
    if (auth()->check()) {
        return redirect('/admin/a-i-chat');
    }

    $error = session('direct_login_error');
    $csrf = csrf_field();

    return response(
        '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Acceso directo - Soluciones Edgar</title>'
        . '<style>'
        . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#111827;color:#f9fafb;font-family:system-ui,-apple-system,Segoe UI,sans-serif}'
        . 'form{width:min(92vw,420px);background:#18181b;border:1px solid #27272a;border-radius:24px;padding:28px;box-shadow:0 24px 80px #0008}'
        . 'h1{margin:0 0 8px;font-size:26px}p{margin:0 0 22px;color:#a1a1aa}'
        . 'label{display:block;margin:14px 0 7px;font-weight:700}'
        . 'input{width:100%;box-sizing:border-box;border:1px solid #3f3f46;border-radius:14px;background:#09090b;color:#fff;padding:14px 16px;font-size:16px}'
        . 'button{width:100%;margin-top:22px;border:0;border-radius:14px;background:#38bdf8;color:#082f49;padding:14px 16px;font-size:17px;font-weight:800}'
        . '.error{background:#7f1d1d;color:#fecaca;border:1px solid #ef4444;border-radius:14px;padding:12px 14px;margin-bottom:16px}'
        . '.hint{margin-top:16px;font-size:13px;color:#a1a1aa}</style></head><body>'
        . '<form method="post" action="/admin/direct-login" autocomplete="on">'
        . $csrf
        . '<h1>Soluciones Edgar</h1><p>Acceso alternativo para la demo.</p>'
        . ($error ? '<div class="error">' . e($error) . '</div>' : '')
        . '<label for="email">Correo electrónico</label>'
        . '<input id="email" name="email" type="email" value="solucionesedgar@gmail.com" required autofocus>'
        . '<label for="password">Contraseña</label>'
        . '<input id="password" name="password" type="password" required>'
        . '<button type="submit">Entrar</button>'
        . '<div class="hint">Usa: solucionesedgar@gmail.com / Password123!</div>'
        . '</form></body></html>'
    );
})->middleware('guest')->name('admin.direct-login');

Route::post('/admin/direct-login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (!\Illuminate\Support\Facades\Auth::attempt($credentials, true)) {
        return redirect('/admin/direct-login')
            ->with('direct_login_error', 'Correo o contraseña incorrectos.');
    }

    $request->session()->regenerate();

    return redirect('/admin/a-i-chat');
})->middleware('guest');

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

Route::middleware('auth')->group(function () {
    Route::get('/ia-conversations', function () {
        $memory = app(\App\Services\AI\ConversationMemoryService::class);

        return response()->json([
            'conversations' => $memory->recentConversations(auth()->user())->map(function ($conversation) {
                $state = ($conversation->metadata ?? [])['procedure_flow'] ?? [];
                $lastMessage = $conversation->messages->first();

                return [
                    'conversation_id' => $conversation->conversation_id,
                    'title' => $conversation->title ?: 'Nueva conversación',
                    'updated_at' => $conversation->updated_at?->toIso8601String(),
                    'last_message' => $lastMessage ? \Illuminate\Support\Str::limit($lastMessage->content, 80) : null,
                    'status' => $state['status'] ?? null,
                    'order_id' => $state['order_id'] ?? null,
                ];
            })->values(),
        ]);
    });

    Route::get('/ia-conversations/{conversationId}', function (string $conversationId) {
        $memory = app(\App\Services\AI\ConversationMemoryService::class);
        $conversation = $memory->conversationForUser($conversationId, auth()->user());

        return response()->json([
            'conversation_id' => $conversation->conversation_id,
            'title' => $conversation->title ?: 'Nueva conversación',
            'metadata' => $conversation->metadata,
            'messages' => $conversation->messages
                ->whereIn('role', ['user', 'assistant'])
                ->map(fn ($message) => [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    });
});

Route::post('/ia-test', function () {
    try {
        set_time_limit(300);

        $startTime = microtime(true);
        $pregunta  = request('pregunta') ?? request('message');

        if (empty($pregunta)) {
            return response()->json([
                'success' => false,
                'error'   => 'Se requiere pregunta o message',
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $conversationId  = request('conversation_id');
        $newConversation = request('new_conversation', false);
        $user            = auth()->user();

        $memoryService = app(\App\Services\AI\ConversationMemoryService::class);

        if ($newConversation || !$conversationId) {
            $conversation = $memoryService->startOrResume(null, $user, 'admin_chat');
        } else {
            $conversation = $memoryService->startOrResume($conversationId, $user, 'admin_chat');
        }

        // ── Observabilidad ────────────────────────────────────────────────────
        $observability = \App\Models\AiObservabilityLog::create([
            'session_id' => $conversation->conversation_id,
            'timestamp'  => now(),
            'user_prompt' => $pregunta,
        ]);

        // ── Guardrails ────────────────────────────────────────────────────────
        $guardrail = app(\App\Services\AI\GuardrailService::class);
        if ($guardrail->isBlocked($pregunta)) {
            $observability->update([
                'was_blocked'      => true,
                'system_response'  => $guardrail->getGenericBlockMessage(),
                'total_latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
            return response()->json([
                'success'         => false,
                'conversation_id' => $conversation->conversation_id,
                'respuesta'       => $guardrail->getGenericBlockMessage(),
                'metadata'        => [
                    'was_blocked' => true,
                    'error'       => 'blocked_by_guardrails',
                ],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // ── Historial y contexto ──────────────────────────────────────────────
        $conversationHistory = $memoryService->getPromptBuffer($conversation, 4000);
        $conversationContext = '';
        if (!empty($conversationHistory)) {
            $conversationContext = collect($conversationHistory)
                ->map(fn ($item) => strtoupper($item['role']) . ': ' . $item['content'])
                ->implode("\n\n");
        }

        $preguntaConContexto = trim(
            ($conversationContext ? $conversationContext . "\n\n" : '') . 'PREGUNTA ACTUAL: ' . $pregunta
        );

        // ── Detección de nuevo flujo ──────────────────────────────────────────
        $detectNewFlow = function (string $text): bool {
            $textLower = strtolower($text);
            $triggers  = [
                'quiero sacar un curp', 'quiero sacar una curp', 'quiero sacar un acta',
                'quiero sacar un acta para otra persona', 'quiero otro trámite',
                'quiero otro tramite', 'ahora quiero', 'necesito sacar',
                'quiero otro', 'quiero sacar',
            ];
            foreach ($triggers as $t) {
                if (str_contains($textLower, $t)) {
                    return true;
                }
            }
            return (bool) preg_match('/\bquiero\s+(sacar|otro|necesito)\b/i', $text);
        };

        $isNewFlow = $detectNewFlow($pregunta);

        if ($isNewFlow) {
            try {
                $prevPending = $memoryService->getPendingOrderState($conversation);
                if ($prevPending) {
                    $meta                    = $conversation->metadata ?? [];
                    $closed                  = $meta['closed_orders'] ?? [];
                    $prevPending['closed_at']     = now()->toDateTimeString();
                    $prevPending['closed_reason'] = 'new_flow_started';
                    $closed[]                = $prevPending;
                    $meta['closed_orders']   = $closed;
                    unset($meta['pending_order']);
                    $conversation->update(['metadata' => $meta]);
                }
            } catch (\Throwable) {
            }
            $conversation        = $memoryService->startOrResume(null, $user, 'admin_chat');
            $conversationHistory = [];
            $conversationContext = '';
            $preguntaConContexto = 'PREGUNTA ACTUAL: ' . $pregunta;
        }

        $memoryService->addUserMessage($conversation, $pregunta);
        $routerDecision = app(\App\Services\AI\AgentRouterService::class)
            ->route($conversation, $pregunta, $conversationHistory);
        $procedureFlowResult = app(\App\Services\AI\ProcedureFlowService::class)
            ->handle($conversation, $pregunta, $user);
        $procedureFlowResponse = $procedureFlowResult['handled']
            ? $procedureFlowResult['response']
            : null;
        $procedureFlowToolStatus = $procedureFlowResult['tool_status'] ?? 'SUCCESS';
        $procedureFlowOrderId = $procedureFlowResult['order_id'] ?? null;
        $memoryService->refreshTitle($conversation);

        // ── Helpers de extracción de contexto de pedidos ──────────────────────
        $findServiceByName = function (?string $serviceName) {
            if (empty($serviceName)) {
                return null;
            }
            $searchValue = trim($serviceName);
            $service     = \App\Models\Service::where('code', strtoupper($searchValue))
                ->orWhere('name', 'like', '%' . $searchValue . '%')
                ->first();
            if ($service) {
                return $service;
            }
            $normalized = str_replace(
                ['á','é','í','ó','ú','Á','É','Í','Ó','Ú'],
                ['a','e','i','o','u','A','E','I','O','U'],
                $searchValue
            );
            return \App\Models\Service::where('name', 'like', '%' . $normalized . '%')->first();
        };

        $extractOrderContext = function ($pregunta, array $conversationHistory) {
            $preguntaLower = strtolower($pregunta);
            $userMessages  = array_filter($conversationHistory, fn($m) => ($m['role'] ?? '') === 'user');
            $historyUser   = strtolower(implode(' ', array_column($userMessages, 'content')));
            $combinedText  = trim($historyUser . ' ' . $preguntaLower);

            $serviceMatch = [];
            preg_match('/\b(acta de nacimiento|acta de defunción|acta de divorcio|acta de matrimonio|constancia de nacimiento|constancia de acta|csf clon con curp|csf con rfc y idcif|idcif|nss|afore|curp actualizada)\b/i', $combinedText, $serviceMatch);
            $service = $serviceMatch[1] ?? null;
            if (empty($service)) {
                $codeMatch = [];
                preg_match('/\b(ACT-NAC|ACT-DEF|ACT-DIV|ACT-MAT|CSF-01|CSF-02|IDCIF-01|NSS-01|NSS-02|NSS-03|AFO-01|CURP-01)\b/i', $combinedText, $codeMatch);
                $service = $codeMatch[1] ?? $service;
            }

            $name         = null;
            $namePatterns = [
                '/\b(?:para|para el cliente|para la clienta|para el|para la|para)\s+([^\.,;\n\r\?\!]{2,100})/i',
                '/\bnombre(?: completo)?(?: es)?\s+([^\.,;\n\r\?\!]{2,100})/i',
            ];
            foreach ($namePatterns as $pattern) {
                if (preg_match($pattern, $pregunta, $nameMatch)) {
                    $cand = preg_replace('/^(el|la|los|las)\s+/i', '', trim($nameMatch[1]));
                    $cand = preg_split('/\b(acta|nacimiento|curp|trámite|tramite|solicitud|entendido|cliente|correo|usuario|su|es|el|la|los|las|mi|del|para)\b/i', $cand)[0];
                    $cand = trim($cand);
                    if (strlen($cand) > 0) { $name = $cand; break; }
                }
            }
            if (empty($name)) {
                $userMessagesReversed = array_reverse(array_values($userMessages));
                foreach ($userMessagesReversed as $msg) {
                    foreach ($namePatterns as $pattern) {
                        if (preg_match($pattern, $msg['content'], $nameMatch)) {
                            $cand = preg_replace('/^(el|la|los|las)\s+/i', '', trim($nameMatch[1]));
                            $cand = preg_split('/\b(acta|nacimiento|curp|trámite|tramite|solicitud|entendido|cliente|correo|usuario|su|es|el|la|los|las|mi|del|para)\b/i', $cand)[0];
                            $cand = trim($cand);
                            if (strlen($cand) > 0) { $name = $cand; break 2; }
                        }
                    }
                }
            }
            if (!empty($name)) {
                $name = mb_convert_case(preg_replace('/\s+/', ' ', trim($name)), MB_CASE_TITLE, 'UTF-8');
            }

            $curpMatch  = [];
            preg_match('/\b([A-Z]{4}\d{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9]{2})\b/i', $combinedText, $curpMatch);
            $curp = isset($curpMatch[1]) ? strtoupper($curpMatch[1]) : null;

            $emailMatch = [];
            preg_match('/\b([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[A-Za-z]{2,})\b/i', $combinedText, $emailMatch);
            $email = $emailMatch[1] ?? null;

            $actionCreate  = (bool) preg_match('/\b(crear( la)? solicitud|registrar( la)? solicitud|hacer( la)? solicitud|ordenar( el| la)? trámite|quiero (crear|hacer|registrar)( la)? solicitud|continuar con la solicitud|quiero crearla|sí,? crear|si,? crear|sí quiero crear|si quiero crear)\b/i', $preguntaLower);
            $explicitCreate = (bool) preg_match('/\b(deseo crear( la)? solicitud|deseo crear la solicitud|crear solicitud|registrar solicitud|confirmo crear|confirmar crear|confirmar crear la solicitud|quiero crear la solicitud|deseo crear|quiero crearla|sí,? crear|si,? crear)\b/i', $preguntaLower);
            $actionInfo    = (bool) preg_match('/\b(consultar información|información del trámite|revisar costo|cuánto cuesta|precio|costo)\b/i', $preguntaLower);
            $actionCancel  = (bool) preg_match('/\b(cancelar|terminar|ya no|no quiero continuar)\b/i', $preguntaLower);

            return compact('service', 'name', 'curp', 'email', 'actionCreate', 'explicitCreate', 'actionInfo', 'actionCancel');
        };

        $createOrderIfReady = function (?string $serviceText, ?string $name, ?string $curp, ?string $email, $conversation, $memoryService, $user) use ($findServiceByName) {
            if (empty($serviceText) || empty($name) || empty($curp) || empty($email)) {
                return null;
            }
            $service = $findServiceByName($serviceText);
            if (!$service) {
                return ['success' => false, 'message' => "Encontré el trámite \"{$serviceText}\", pero no pude ubicarlo en el catálogo."];
            }
            if (!$user->is_admin && $user->balance < $service->price) {
                return ['success' => false, 'message' => "No tienes saldo suficiente. El costo es \${$service->price} y tu saldo es \${$user->balance}."];
            }
            try {
                $order = \Illuminate\Support\Facades\DB::transaction(function () use ($user, $service, $name, $curp, $email) {
                    return \App\Models\Order::create([
                        'user_id'           => $user->id,
                        'service_id'        => $service->id,
                        'input_data'        => ['curp' => $curp, 'cliente' => $name, 'cliente_email' => $email],
                        'status'            => 'pending',
                        'price_at_purchase' => $service->price,
                        'admin_notes'       => "Solicitud creada por chat. Cliente: {$name}. Email: {$email}. CURP: {$curp}.",
                    ]);
                });
                try {
                    $memoryService->clearPendingOrderState($conversation);
                    $meta             = $conversation->metadata ?? [];
                    $meta['status']   = 'completed';
                    $meta['order_id'] = $order->id;
                    $meta['completed_at'] = now()->toDateTimeString();
                    $conversation->update(['metadata' => $meta]);
                } catch (\Throwable) {
                }
                return [
                    'success'              => true,
                    'message'              => "Solicitud registrada. Pedido #{$order->id} creado con servicio {$service->name}.",
                    'order_id'             => $order->id,
                    'conversation_completed' => true,
                    'reset_conversation'   => true,
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Error creando orden desde AI Chat: ' . $e->getMessage(), [
                    'exception'       => $e,
                    'conversation_id' => $conversation->conversation_id,
                ]);
                return ['success' => false, 'message' => 'No pude crear la solicitud por un error interno.'];
            }
        };

        $generateSimpleResponse = function ($pregunta, array $conversationHistory = [], $conversation = null, $memoryService = null, $user = null) use ($extractOrderContext, $findServiceByName, $createOrderIfReady) {
            if (preg_match('/\b(c[oó]mo me llamo|qu[eé] tr[aá]mite necesito|qu[eé] informaci[oó]n tienes sobre m[ií]|qu[eé] te dije|recuerdas)\b/iu', $pregunta)) {
                return null;
            }

            $orderContext  = $extractOrderContext($pregunta, $conversationHistory);
            $service       = $orderContext['service'];
            $name          = $orderContext['name'];
            $curp          = $orderContext['curp'];
            $email         = $orderContext['email'];
            $actionCreate  = $orderContext['actionCreate'];
            $actionInfo    = $orderContext['actionInfo'];
            $actionCancel  = $orderContext['actionCancel'];
            $explicitCreate = $orderContext['explicitCreate'] ?? false;

            $hasService    = !empty($service);
            $hasName       = !empty($name);
            $hasCurp       = !empty($curp);
            $allUserText   = strtolower(implode(' ', array_column(
                array_filter($conversationHistory, fn($m) => ($m['role'] ?? '') === 'user'),
                'content'
            )));
            $hasUserClient = !empty($email) || preg_match('/usuario\s+cliente/i', $allUserText . ' ' . strtolower($pregunta));

            $serviceModel    = $findServiceByName($service);
            $pendingOrderData = array_filter([
                'service_name'  => $service,
                'service_id'    => $serviceModel?->id,
                'client_name'   => $name,
                'client_email'  => $email,
                'input_data'    => array_filter(['curp' => $curp, 'cliente' => $name, 'cliente_email' => $email]),
            ], fn ($v) => $v !== null && $v !== []);

            if (!empty($pendingOrderData) && $conversation && $memoryService) {
                $memoryService->savePendingOrderState($conversation, $pendingOrderData);
            }

            if ($actionCancel) {
                $memoryService->clearPendingOrderState($conversation);
                return 'Entendido. He cancelado la solicitud en proceso.';
            }
            if ($actionCreate) {
                if (!$hasService) return 'Para crear la solicitud necesito saber qué trámite deseas solicitar.';
                if (!$hasName)    return "Para el trámite {$service}, necesito el nombre completo del cliente.";
                if (!$hasCurp)    return "Para el trámite {$service} de {$name}, necesito la CURP.";
                if (!$hasUserClient) return 'Necesito el correo electrónico del usuario cliente.';
                if (!$explicitCreate) {
                    return sprintf('Perfecto. Tengo: trámite de %s, nombre %s, CURP %s y usuario %s. ¿Deseas crear la solicitud ahora?', $service, $name, $curp, $email ?: 'el usuario cliente');
                }
                $orderResult = $createOrderIfReady($service, $name, $curp, $email, $conversation, $memoryService, $user);
                return $orderResult['message'];
            }
            if ($actionInfo && $serviceModel) {
                return "El costo del servicio {$serviceModel->name} es \${$serviceModel->price}.";
            }
            if ($hasService && $hasName && $hasCurp && $hasUserClient) {
                return sprintf('Perfecto. Tengo: trámite de %s, nombre %s, CURP %s y usuario %s. ¿Deseas crear la solicitud ahora?', $service, $name, $curp, $email ?: 'el usuario cliente');
            }
            if ($hasService && $hasName && $hasCurp && !$hasUserClient) {
                return 'Ya tengo el trámite, nombre y CURP. Necesito el correo del usuario cliente.';
            }
            if ($hasService && $hasName && !$hasCurp) {
                return sprintf('Entendido. Para el %s de %s, necesito la CURP.', $service ?: 'trámite', $name);
            }
            if ($hasService && !$hasName) {
                return sprintf('Entendido. Para el %s, necesito el nombre completo del cliente.', $service ?: 'trámite');
            }
            return null;
        };

        // ── SSE único — usa RagBridgeService inyectable ───────────────────────
        $ragBridge = app(\App\Services\AI\RagBridgeService::class);

        return response()->stream(
            function () use (
                $ragBridge, $memoryService, $conversation, $user,
                $pregunta, $preguntaConContexto, $observability, $startTime,
                $generateSimpleResponse, $conversationHistory, $procedureFlowResponse,
                $procedureFlowToolStatus, $procedureFlowOrderId
                , $routerDecision
            ) {
                $fullResponse          = '';
                $ttftRecorded          = false;
                $tokenCount            = 0;
                $ttftMs                = null;
                $activeGenerationStart = null;
                $flushStream = static function (): void {
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();
                };

                echo "data: " . json_encode([
                    'type'            => 'conversation',
                    'conversation_id' => $conversation->conversation_id,
                ]) . "\n\n";
                $flushStream();

                echo "data: " . json_encode([
                    'type' => 'status',
                    'status' => 'ROUTING',
                    'agent' => $routerDecision['agent'],
                    'intent' => $routerDecision['intent'],
                ]) . "\n\n";
                $flushStream();

                $businessResponse = $procedureFlowResponse ?? $generateSimpleResponse(
                    $pregunta,
                    $conversationHistory,
                    $conversation,
                    $memoryService,
                    $user
                );
                $businessSource = $procedureFlowResponse !== null
                    ? 'procedure_flow'
                    : 'business_fallback';

                $onChunk = function (array $data) use (
                    &$fullResponse, &$ttftRecorded, &$tokenCount,
                    &$ttftMs, &$activeGenerationStart, $startTime, $flushStream
                ) {
                    if (isset($data['status'])) {
                        echo "data: " . json_encode(['type' => 'status', 'status' => $data['status']]) . "\n\n";
                        $flushStream();
                    } elseif (isset($data['token'])) {
                        if (!$ttftRecorded) {
                            $ttftMs                = round((microtime(true) - $startTime) * 1000, 2);
                            $activeGenerationStart = microtime(true);
                            $ttftRecorded          = true;
                        }
                        $tokenCount++;
                        $fullResponse .= $data['token'];
                        echo "data: " . json_encode(['type' => 'token', 'token' => $data['token']]) . "\n\n";
                        $flushStream();
                    } elseif (isset($data['respuesta'])) {
                        if (!$ttftRecorded) {
                            $ttftMs       = round((microtime(true) - $startTime) * 1000, 2);
                            $ttftRecorded = true;
                        }
                        $tokenCount   += (int) ceil(strlen($data['respuesta']) / 4);
                        $fullResponse .= $data['respuesta'];
                        echo "data: " . json_encode(['type' => 'token', 'token' => $data['respuesta']]) . "\n\n";
                        $flushStream();
                    } elseif (isset($data['error'])) {
                        echo "data: " . json_encode(['type' => 'error', 'error' => $data['error']]) . "\n\n";
                        $flushStream();
                    }
                };

                if ($businessResponse) {
                    $ttftMs = round((microtime(true) - $startTime) * 1000, 2);
                    $ttftRecorded = true;
                    $fullResponse = $businessResponse;
                    $tokenCount = max(1, (int) ceil(strlen($fullResponse) / 4));
                    echo "data: " . json_encode(['type' => 'token', 'token' => $fullResponse]) . "\n\n";
                    $flushStream();
                    $success = false;
                } else {
                    $success = $ragBridge->stream($preguntaConContexto, $onChunk);
                }

                // Solo conserva respuestas deterministas de negocio. Nunca simula
                // una respuesta general cuando el bridge Python/Ollama falla.
                if (empty(trim($fullResponse))) {
                    if (!$ttftRecorded) {
                        $ttftMs       = round((microtime(true) - $startTime) * 1000, 2);
                        $ttftRecorded = true;
                    }

                    $fullResponse = 'No fue posible conectar con el servicio local de IA. Verifica Python y Ollama e intenta de nuevo.';
                    $tokenCount   = max(1, (int) ceil(strlen($fullResponse) / 4));

                    echo "data: " . json_encode(['type' => 'error', 'error' => $fullResponse]) . "\n\n";
                    $flushStream();
                }

                // Guardar mensaje asistente
                try {
                    $memoryService->addAssistantMessage($conversation, $fullResponse, [
                        'tool' => $success ? 'rag_stream' : ($businessResponse ? $businessSource : 'rag_bridge_error'),
                    ]);
                    $memoryService->refreshTitle($conversation);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('No se pudo guardar mensaje assistant: ' . $e->getMessage());
                }

                // Métricas de observabilidad
                $totalLatencyMs = round((microtime(true) - $startTime) * 1000, 2);
                $generationTime = $activeGenerationStart ? (microtime(true) - $activeGenerationStart) : ($totalLatencyMs / 1000);
                $tps            = $generationTime > 0 ? round($tokenCount / $generationTime, 2) : 0;

                $toolDetails = [
                    'name'        => $success ? 'rag_search' : ($businessResponse ? $businessSource : 'rag_bridge'),
                    'parameters'  => ['query' => $pregunta],
                    'status'      => $businessSource === 'procedure_flow'
                        ? $procedureFlowToolStatus
                        : ($success || $businessResponse ? 'SUCCESS' : 'ERROR'),
                    'duration_ms' => $totalLatencyMs,
                    'router'      => $routerDecision,
                    'agent'       => $businessSource === 'procedure_flow'
                        ? 'transactional_agent'
                        : ($success ? 'rag_agent' : ($businessResponse ? $businessSource : 'rag_agent')),
                ];
                if ($procedureFlowOrderId) {
                    $toolDetails['order_id'] = $procedureFlowOrderId;
                }

                $observability->update([
                    'system_response'    => $fullResponse,
                    'ttft_ms'            => $ttftMs,
                    'total_latency_ms'   => $totalLatencyMs,
                    'tokens_per_second'  => $tps,
                    'tools_executed'     => $toolDetails,
                    'was_blocked' => false,
                ]);

                // Evento final con conversation_id
                $isCompleted = isset($conversation->metadata['status'])
                    && $conversation->metadata['status'] === 'completed';
                echo "data: " . json_encode([
                    'type'            => 'done',
                    'conversation_id' => $conversation->conversation_id,
                    'metadata'        => ['conversation_completed' => $isCompleted],
                ]) . "\n\n";
                $flushStream();
            },
            200,
            [
                'Cache-Control'    => 'no-cache',
                'Content-Type'     => 'text/event-stream',
                'Connection'       => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]
        );

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Error en /ia-test: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
        ]);
        return response()->json([
            'success'         => false,
            'conversation_id' => isset($conversation) ? $conversation->conversation_id : null,
            'respuesta'       => 'Ocurrió un error al procesar su solicitud. Por favor intente de nuevo más tarde.',
            'metadata'        => ['error' => 'php_exception'],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
});
