<?php

namespace App\Services\AI;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Reports\OrderClosingReportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminChatCommandService
{
    protected array $guidedServiceExamples = [
        'Acta de Nacimiento',
        'CURP actualizada',
        'Constancia de Situacion Fiscal',
        'Localizar NSS',
        'Semanas Cotizadas',
        'Recibo CFE',
        'Tenencia CDMX / EDOMEX',
        'Hoja REPUVE',
    ];

    /**
     * Analiza el mensaje para ver si es un comando administrativo
     * y, de ser así, lo ejecuta y devuelve la respuesta.
     * Si no es un comando, devuelve null.
     */
    public function handle(string $message, ?User $user = null): ?array
    {
        if (!$user || !$user->is_admin) {
            return null;
        }

        $messageLower = Str::lower(Str::ascii($message));

        // 0. Comando: Crear trámite / pedido (ALTA PRIORIDAD - antes de otros comandos)
        $createKeywords = [
            'crea un trámite', 'crea un tramite', 'crea una orden',
            'genera un pedido', 'genera un trámite', 'genera un tramite',
            'registra un trámite', 'registra un tramite',
            'haz un pedido', 'crear trámite', 'crear tramite',
            'crear pedido', 'crea pedido', 'nuevo trámite', 'nuevo tramite',
            'nuevo pedido', 'crear orden',
        ];
        if (Str::contains($messageLower, $createKeywords)) {
            return $this->handleServiceRequestIntent($message, $messageLower, $user);
        }

        // 1. Comando: Generar Reporte de Cierre (Existente)
        $reportKeywords = [
            'reporte de los últimos', 'reporte de los ultimos',
            'generar reporte de trámites', 'generar reporte de tramites',
            'generar reporte de pedidos', 'reporte de cierre',
            'reporte de servicios procesados', 'últimos pedidos',
            'ultimos pedidos', 'ultimo tramite', 'ultimo pedido',
            'del ultimo tramite', 'del ultimo pedido',
            'de el ultimo tramite', 'de el ultimo pedido'
        ];
        if (Str::contains($messageLower, $reportKeywords)) {
            return $this->generateReport($messageLower);
        }

        // 2. Comando: Pedidos Fallidos / Errores
        $failedKeywords = [
            'resume los pedidos fallidos', 'dime los pedidos fallidos',
            'errores de api', 'resumen de errores', 'pedidos fallidos'
        ];
        if (Str::contains($messageLower, $failedKeywords)) {
            return $this->analyzeFailedOrders($messageLower);
        }

        // 3. Comando: Revisión Manual
        $manualReviewKeywords = [
            'trámites en revisión manual', 'tramites en revision manual',
            'pedidos en revisión manual', 'pedidos en revision manual',
            'qué quedó pendiente de revisión', 'que quedo pendiente de revision',
            'revisión manual', 'revision manual'
        ];
        if (Str::contains($messageLower, $manualReviewKeywords)) {
            return $this->analyzeManualReviewOrders($messageLower);
        }

        // 4. Comando: Completados Hoy
        $completedTodayKeywords = [
            'resumen de trámites completados hoy', 'resumen de tramites completados hoy',
            'trámites completados hoy', 'tramites completados hoy',
            'cierre de hoy', 'completados hoy'
        ];
        if (Str::contains($messageLower, $completedTodayKeywords)) {
            return $this->analyzeCompletedToday($messageLower);
        }

        // 5. Comando: Pedidos Pendientes
        $pendingKeywords = [
            'pedidos pendientes', 'trámites pendientes', 'tramites pendientes',
            'dame los pedidos pendientes', 'dame los trámites pendientes',
            'dame los tramites pendientes', 'órdenes pendientes', 'ordenes pendientes',
            'cúantos pedidos hay pendientes', 'cuantos pedidos hay pendientes',
        ];
        if (Str::contains($messageLower, $pendingKeywords)) {
            return $this->listPendingOrders($messageLower);
        }

        // 6. Comando: Consultar Servicio
        $queryServiceKeywords = [
            'consultar servicio', 'consulta servicio', 'buscar servicio',
            'dime el servicio', 'información del servicio', 'informacion del servicio',
            'qué servicios hay', 'que servicios hay', 'lista de servicios',
            'servicios disponibles',
        ];
        if (Str::contains($messageLower, $queryServiceKeywords)) {
            return $this->queryService($message, $messageLower);
        }

        // 7. Comando: Consultar Usuario
        $queryUserKeywords = [
            'consultar usuario', 'consulta usuario', 'buscar usuario',
            'dime el usuario', 'información del usuario', 'informacion del usuario',
            'datos del usuario', 'buscar cliente', 'consultar cliente',
        ];
        if (Str::contains($messageLower, $queryUserKeywords)) {
            return $this->queryUser($message, $messageLower);
        }

        if ($this->looksLikeServiceRequestIntent($messageLower)) {
            return $this->handleServiceRequestIntent($message, $messageLower, $user);
        }

        return null;
    }

    /**
     * Extrae el número de límite solicitado, por defecto 10, máximo 50.
     */
    protected function extractLimit(string $message, int $default = 10, int $max = 50): int
    {
        preg_match('/\b(\d+)\b/', $message, $matches);
        if (!empty($matches[1])) {
            $parsed = (int) $matches[1];
            if ($parsed > 0) {
                return min($parsed, $max);
            }
        }
        
        if (Str::contains($message, ['ultimo', 'último'])) {
            return 1;
        }

        return $default;
    }

    protected function generateReport(string $message): array
    {
        $limit = $this->extractLimit($message);

        $orders = Order::with(['user', 'service'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => 'No hay pedidos recientes en el sistema para generar un reporte.'];
        }

        try {
            $reportService = app(OrderClosingReportService::class);
            $result = $reportService->generate($orders);
        } catch (\Throwable $e) {
            Log::error('[AdminChatCommandService] Error al generar reporte: ' . $e->getMessage());
            return ['handled' => true, 'respuesta' => '❌ Error al generar el reporte: ' . $e->getMessage()];
        }

        $publicUrl = Storage::disk('public')->url($result['file_path']);

        // Verificar que el archivo realmente existe
        if (!Storage::disk('public')->exists($result['file_path'])) {
            Log::error('[AdminChatCommandService] El archivo de reporte no existe después de la generación', [
                'file_path' => $result['file_path'],
                'absolute_path' => $result['absolute_path'] ?? 'N/A',
            ]);
            return ['handled' => true, 'respuesta' => '❌ Error interno: el archivo de reporte no se encontró después de generarlo. Contacta al administrador.'];
        }

        $respuestaTexto = "✅ **Reporte generado correctamente.**\n";
        if ($orders->count() === 1) {
            $respuestaTexto .= "Se incluyó el último trámite.\n";
        } else {
            $respuestaTexto .= "Se incluyeron los últimos {$orders->count()} trámites.\n";
        }
        $respuestaTexto .= "\n<a href=\"{$publicUrl}\" class=\"gpt-download-btn\" target=\"_blank\">📥 Descargar Reporte</a>\n\n";
        $respuestaTexto .= "**Resumen Final:**\n" . $result['summary'];

        return [
            'handled' => true,
            'respuesta' => $respuestaTexto,
            'file_path' => $result['file_path'],
            'url' => $publicUrl
        ];
    }

    protected function analyzeFailedOrders(string $message): array
    {
        $limit = $this->extractLimit($message);

        $orders = Order::with(['user', 'service'])
            ->where(function ($q) {
                $q->where('api_status', 'failed')
                  ->orWhereNotNull('api_error_message');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => '✅ No se encontraron pedidos fallidos recientes. Todo parece funcionar correctamente.'];
        }

        $reportData = "ANALISIS DE PEDIDOS FALLIDOS:\n";
        $reportData .= "Total de fallidos encontrados: {$orders->count()}\n\n";

        $respuestaTexto = "⚠️ **Se encontraron {$orders->count()} pedidos con errores.**\n\n";

        foreach ($orders as $order) {
            $serviceName = $order->service ? $order->service->name : 'N/A';
            $userName = $order->user ? $order->user->name : 'N/A';
            $error = $order->api_error_message ?? 'Error no especificado';
            
            $line = "- **Pedido #{$order->id}** | Servicio: {$serviceName} | Usuario: {$userName} | Prov: {$order->external_provider}\n  **Error:** {$error}\n";
            $respuestaTexto .= $line;
            $reportData .= $line;
        }

        $aiService = app(OllamaReportService::class);
        // Prompt adaptado para análisis de fallos
        $customPrompt = "Eres un analista técnico. Con base en los siguientes pedidos fallidos, genera un resumen administrativo breve (máx 3 líneas) indicando cuáles son los errores más comunes y qué acción recomiendas. No inventes datos.\n\n" . $reportData;
        
        $aiSummary = $this->askOllamaDirectly($aiService, $customPrompt);

        if ($aiSummary) {
            $respuestaTexto .= "\n**Análisis de IA:**\n" . $aiSummary;
        }

        return ['handled' => true, 'respuesta' => $respuestaTexto];
    }

    protected function analyzeManualReviewOrders(string $message): array
    {
        $limit = $this->extractLimit($message);

        $orders = Order::with(['user', 'service'])
            ->where(function ($q) {
                $q->where('api_status', 'manual_review')
                  ->orWhere('status', 'pending');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => '✅ No hay trámites pendientes de revisión manual en este momento.'];
        }

        $respuestaTexto = "🔍 **Se encontraron {$orders->count()} pedidos en revisión manual / pendientes.**\n\n";

        foreach ($orders as $order) {
            $serviceName = $order->service ? $order->service->name : 'N/A';
            $userName = $order->user ? $order->user->name : 'N/A';
            $motivo = $order->api_report ? Str::limit($order->api_report, 50) : 'Pendiente de acción';

            $respuestaTexto .= "- **Pedido #{$order->id}** - {$serviceName}\n";
            $respuestaTexto .= "  Usuario: {$userName} | Estado: {$order->status} / {$order->api_status}\n";
            $respuestaTexto .= "  Motivo/Nota: {$motivo}\n\n";
        }

        $respuestaTexto .= "**Recomendación:** Revisa los datos capturados en estos pedidos antes de procesarlos o rechazarlos.";

        return ['handled' => true, 'respuesta' => $respuestaTexto];
    }

    protected function analyzeCompletedToday(string $message): array
    {
        $orders = Order::with(['service'])
            ->where('status', 'completed')
            ->whereDate('updated_at', today())
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => 'ℹ️ Aún no hay pedidos completados en el día de hoy.'];
        }

        $apiProcessed = $orders->where('processed_by_api', true)->count();
        $providers = $orders->pluck('external_provider')->filter()->unique()->implode(', ');
        if (empty($providers)) $providers = 'Ninguno';

        $reportData = "CIERRE DEL DÍA (COMPLETADOS HOY):\n";
        $reportData .= "Total completados: {$orders->count()}\n";
        $reportData .= "Procesados por API: {$apiProcessed}\n";
        $reportData .= "Proveedores usados: {$providers}\n";

        $respuestaTexto = "📊 **Resumen de trámites completados hoy:**\n";
        $respuestaTexto .= "- **Total:** {$orders->count()}\n";
        $respuestaTexto .= "- **Por API externa:** {$apiProcessed}\n";
        $respuestaTexto .= "- **Proveedores:** {$providers}\n\n";

        $aiService = app(OllamaReportService::class);
        $customPrompt = "Eres un asistente administrativo. Genera un mensaje breve y motivador resumiendo el cierre del día con estos datos. Máximo 2 líneas.\n\n" . $reportData;
        
        $aiSummary = $this->askOllamaDirectly($aiService, $customPrompt);

        if ($aiSummary) {
            $respuestaTexto .= "**Nota del Asistente:** " . $aiSummary;
        }

        return ['handled' => true, 'respuesta' => $respuestaTexto];
    }

    /**
     * Crea un pedido/trámite real desde el chat del administrador.
     */
    protected function createOrder(string $message, string $messageLower, User $admin): array
    {
        return $this->handleServiceRequestIntent($message, $messageLower, $admin);
    }

    protected function handleServiceRequestIntent(string $message, string $messageLower, User $admin): array
    {
        $serviceIntent = $this->resolveServiceIntent($message, $messageLower);
        $clientName = $this->extractClientName($message);
        $curp = $this->extractCURP($message);
        $rfc = $this->extractRFC($message);
        $nss = $this->extractNSS($message);
        $email = $this->extractEmail($message);
        $plate = $this->extractPlate($message);
        $year = $this->extractYear($message);

        if (($serviceIntent['status'] ?? null) === 'missing') {
            return [
                'handled' => true,
                'respuesta' => $this->buildGenericServiceGuide(),
            ];
        }

        if (($serviceIntent['status'] ?? null) === 'ambiguous') {
            return [
                'handled' => true,
                'respuesta' => $serviceIntent['message'],
            ];
        }

        $serviceLabel = $serviceIntent['label'];
        $service = $serviceIntent['service'] ?? null;

        if (!$service) {
            return [
                'handled' => true,
                'respuesta' => $this->buildServiceNotFoundResponse($serviceLabel),
            ];
        }

        $missingItems = [];

        if (!$clientName) {
            $missingItems[] = 'Nombre del solicitante.';
        }

        foreach ($this->getMissingRequiredFields($service, [
            'curp' => $curp,
            'rfc' => $rfc,
            'nss' => $nss,
            'plate' => $plate,
            'year' => $year,
        ]) as $fieldMessage) {
            $missingItems[] = $fieldMessage;
        }

        $targetUser = $this->resolveTargetUser($message, $email);

        if (!$targetUser) {
            $missingItems[] = 'Usuario cliente al que se asignara el pedido.';
        }

        if (!empty($missingItems)) {
            return [
                'handled' => true,
                'respuesta' => $this->buildMissingDataResponse(
                    $service,
                    $serviceLabel,
                    $clientName,
                    $curp,
                    $rfc,
                    $nss,
                    $plate,
                    $year,
                    $email,
                    $missingItems,
                    $targetUser === null && count($missingItems) === 1
                ),
            ];
        }

        $inputData = [
            'solicitante' => $clientName,
            'creado_por_chat' => true,
            'admin_id' => $admin->id,
        ];

        $clientName = $this->extractClientName($message);
        if ($curp) $inputData['curp'] = strtoupper($curp);
        if ($rfc) $inputData['rfc'] = strtoupper($rfc);
        if ($nss) $inputData['nss'] = $nss;
        if ($plate) $inputData['plate'] = strtoupper($plate);
        if ($year) $inputData['year'] = $year;

        try {
            $order = Order::create([
                'user_id' => $targetUser->id,
                'service_id' => $service->id,
                'input_data' => $inputData,
                'status' => 'pending',
                'api_status' => 'manual_review',
                'admin_notes' => "Pedido creado desde chat administrativo por {$admin->name}",
            ]);

            Log::info("[AdminChatCommandService] Pedido #{$order->id} creado desde chat por admin #{$admin->id}", [
                'service' => $service->name,
                'user' => $targetUser->email,
                'input_data' => $inputData,
            ]);

            return [
                'handled' => true,
                'respuesta' => "Pedido creado correctamente. ID: #{$order->id}. Servicio: {$serviceLabel}. Solicitante: {$clientName}. Estado: {$order->status}.",
                'order_id' => $order->id,
            ];

        } catch (\Throwable $e) {
            Log::error('[AdminChatCommandService] Error al crear pedido desde chat: ' . $e->getMessage(), [
                'service_id' => $service->id,
                'user_id' => $targetUser->id,
                'input_data' => $inputData,
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'handled' => true,
                'respuesta' => '❌ Error al crear el pedido: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista los pedidos pendientes.
     */
    protected function listPendingOrders(string $message): array
    {
        $limit = $this->extractLimit($message, 10, 50);

        $orders = Order::with(['user', 'service'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return ['handled' => true, 'respuesta' => '✅ No hay pedidos pendientes en este momento.'];
        }

        $respuesta = "📋 **Hay {$orders->count()} pedidos pendientes:**\n\n";
        foreach ($orders as $order) {
            $serviceName = $order->service ? $order->service->name : 'N/A';
            $userName = $order->user ? $order->user->name : 'N/A';
            $createdAt = $order->created_at->format('d/m/Y H:i');
            $respuesta .= "- **#{$order->id}** | {$serviceName} | {$userName} | {$createdAt}\n";
        }

        return ['handled' => true, 'respuesta' => $respuesta];
    }

    /**
     * Consulta información de un servicio.
     */
    protected function queryService(string $message, string $messageLower): array
    {
        $serviceName = $this->extractServiceName($message);

        if (!$serviceName) {
            // Listar todos los servicios
            $services = Service::where(fn($q) => $q->where('is_active', true)->orWhereNull('is_active'))
                ->orderBy('name')
                ->get();

            if ($services->isEmpty()) {
                return ['handled' => true, 'respuesta' => 'No hay servicios registrados en el sistema.'];
            }

            $respuesta = "📦 **Servicios disponibles ({$services->count()}):**\n\n";
            foreach ($services as $s) {
                $respuesta .= "- **{$s->name}** | \${$s->price} | " . ($s->automation_type ?? 'manual') . "\n";
            }
            return ['handled' => true, 'respuesta' => $respuesta];
        }

        $service = $this->findService($serviceName);
        if (!$service) {
            return ['handled' => true, 'respuesta' => "No encontré un servicio llamado \"{$serviceName}\"."];
        }

        $respuesta = "📦 **{$service->name}**\n"
            . "- **Precio:** \${$service->price}\n"
            . "- **Costo:** \${$service->cost}\n"
            . "- **Tipo:** {$service->service_type}\n"
            . "- **Automatización:** {$service->automation_type}\n"
            . "- **Proveedor:** " . ($service->external_provider ?? 'N/A') . "\n"
            . "- **Descripción:** " . ($service->description ?? 'Sin descripción') . "\n";

        return ['handled' => true, 'respuesta' => $respuesta];
    }

    /**
     * Consulta información de un usuario.
     */
    protected function queryUser(string $message, string $messageLower): array
    {
        // Intentar extraer email o nombre
        $email = $this->extractEmail($message);
        $name = $this->extractClientName($message);

        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        }
        if (!$user && $name) {
            $user = User::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%'])->first();
        }

        if (!$user) {
            // Extraer ID numérico
            preg_match('/\b(\d+)\b/', $message, $matches);
            if (!empty($matches[1])) {
                $user = User::find((int)$matches[1]);
            }
        }

        if (!$user) {
            return ['handled' => true, 'respuesta' => 'No encontré el usuario. Proporciona ID, correo o nombre.'];
        }

        $orderCount = Order::where('user_id', $user->id)->count();
        $completedOrders = Order::where('user_id', $user->id)->where('status', 'completed')->count();
        $pendingOrders = Order::where('user_id', $user->id)->where('status', 'pending')->count();

        $respuesta = "👤 **Usuario #{$user->id}**\n"
            . "- **Nombre:** {$user->name}\n"
            . "- **Email:** {$user->email}\n"
            . "- **Teléfono:** " . ($user->phone ?? 'N/A') . "\n"
            . "- **Saldo:** \${$user->balance}\n"
            . "- **Tipo:** " . ($user->is_admin ? 'Administrador' : ($user->account_type ?? 'Cliente')) . "\n"
            . "- **Pedidos totales:** {$orderCount} (completados: {$completedOrders}, pendientes: {$pendingOrders})\n";

        return ['handled' => true, 'respuesta' => $respuesta];
    }

    // ============ HELPERS DE EXTRACCIÓN ============

    /**
     * Extrae el nombre del servicio del mensaje.
     */
    protected function extractServiceName(string $message): ?string
    {
        // Patrones: "de Servicio" o "de: Servicio"
        $patterns = [
            '/de(?:l)?\s+(?:servicio|trámite|tramite|pedido|orden)\s+(?:de\s+)?(.+?)(?:$|para\s+|con\s+|al\s+)/iu',
            '/de\s+(.+?)(?:\s+para|\s+con|\s+al|$)/iu',
            '/servicio\s+(.+?)(?:$|para\s+|con\s+|al\s+)/iu',
            '/trámite\s+(?:de\s+)?(.+?)(?:$|para\s+|con\s+|al\s+)/iu',
            '/tramite\s+(?:de\s+)?(.+?)(?:$|para\s+|con\s+|al\s+)/iu',
            '/pedido\s+(?:de\s+)?(.+?)(?:$|para\s+|con\s+|al\s+)/iu',
            '/orden\s+(?:de\s+)?(.+?)(?:$|para\s+|con\s+|al\s+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $name = trim($matches[1]);
                if (!empty($name)) return $name;
            }
        }

        return null;
    }

    protected function looksLikeServiceRequestIntent(string $messageLower): bool
    {
        $intentKeywords = [
            'quiero', 'necesito', 'ocupo', 'sacar', 'solicitar', 'hacer', 'crear', 'tramite', 'trámite', 'pedido', 'servicio', 'pagar',
        ];

        if (!Str::contains($messageLower, $intentKeywords)) {
            return false;
        }

        $serviceHints = [
            'servicio', 'tramite', 'trámite', 'pedido', 'acta', 'curp', 'constancia', 'situacion fiscal', 'situación fiscal', 'csf', 'sat',
            'nss', 'seguro social', 'semanas cotizadas', 'tenencia', 'cfe', 'repuve', 'antecedentes',
        ];

        return Str::contains($messageLower, $serviceHints);
    }

    protected function resolveServiceIntent(string $message, string $messageLower): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($messageLower))));
        $serviceName = $this->extractServiceName($message);

        if ($serviceName) {
            $service = $this->findService($serviceName);

            return [
                'status' => 'resolved',
                'label' => $service?->name ?? $serviceName,
                'service' => $service,
            ];
        }

        $aliases = [
            [
                'needles' => ['acta de nacimiento', 'acta nacimiento'],
                'label' => 'Acta de Nacimiento',
                'service_names' => ['Acta de Nacimiento'],
            ],
            [
                'needles' => ['acta de matrimonio', 'acta matrimonio'],
                'label' => 'Acta de Matrimonio',
                'service_names' => ['Acta de Matrimonio'],
            ],
            [
                'needles' => ['acta de defuncion', 'acta defuncion'],
                'label' => 'Acta de Defuncion',
                'service_names' => ['Acta de Defunción', 'Acta de Defuncion'],
            ],
            [
                'needles' => ['acta de divorcio', 'acta divorcio'],
                'label' => 'Acta de Divorcio',
                'service_names' => ['Acta de Divorcio'],
            ],
            [
                'needles' => ['constancia de situacion fiscal', 'constancia fiscal', 'situacion fiscal', 'csf'],
                'label' => 'Constancia de Situacion Fiscal',
                'service_names' => ['Constancia de Situación Fiscal', 'Constancia de Situacion Fiscal', 'CSF Clon con CURP', 'CSF con RFC y IDCIF'],
            ],
            [
                'needles' => ['curp'],
                'label' => 'CURP actualizada',
                'service_names' => ['CURP Actualizada'],
            ],
            [
                'needles' => ['semanas cotizadas'],
                'label' => 'Semanas Cotizadas',
                'service_names' => ['Semanas Cotizadas por CURP'],
            ],
            [
                'needles' => ['nss', 'seguro social'],
                'label' => 'Localizar NSS',
                'service_names' => ['Localizar NSS con CURP', 'Localizar NSS'],
            ],
            [
                'needles' => ['recibo cfe', 'cfe'],
                'label' => 'Recibo CFE',
                'service_names' => ['Recibo CFE PDF', 'Recibo CFE'],
            ],
            [
                'needles' => ['hoja repuve', 'repuve'],
                'label' => 'Hoja REPUVE',
                'service_names' => ['HOJA REPUVE', 'Hoja REPUVE'],
            ],
            [
                'needles' => ['antecedentes'],
                'label' => 'Antecedentes no penales',
                'service_names' => ['Antecedentes no Penales'],
            ],
            [
                'needles' => ['tenencia cdmx'],
                'label' => 'Tenencia CDMX',
                'service_names' => ['FORMATO PAGO DE TENENCIA CD MX', 'Tenencia CDMX'],
            ],
            [
                'needles' => ['tenencia edomex'],
                'label' => 'Tenencia EDOMEX',
                'service_names' => ['FORMATO PAGO DE TENENCIA EDOMEX', 'Tenencia EDOMEX'],
            ],
        ];

        foreach ($aliases as $alias) {
            if (!Str::contains($normalized, $alias['needles'])) {
                continue;
            }

            return [
                'status' => 'resolved',
                'label' => $alias['label'],
                'service' => $this->findServiceByCandidates($alias['service_names']),
            ];
        }

        if (preg_match('/\bacta\b/u', $normalized)) {
            return [
                'status' => 'ambiguous',
                'message' => "¿Que tipo de acta necesitas?\n\n- Acta de Nacimiento\n- Acta de Matrimonio\n- Acta de Defuncion\n- Acta de Divorcio",
            ];
        }

        if (preg_match('/\btenencia\b/u', $normalized)) {
            return [
                'status' => 'ambiguous',
                'message' => '¿La tenencia es de CDMX o EDOMEX?',
            ];
        }

        if (preg_match('/\bsat\b/u', $normalized)) {
            return [
                'status' => 'ambiguous',
                'message' => "¿Necesitas una Constancia de Situacion Fiscal o localizar un IDCIF del SAT?",
            ];
        }

        return ['status' => 'missing'];
    }

    /**
     * Extrae el nombre del cliente del mensaje.
     */
    protected function extractClientName(string $message): ?string
    {
        $patterns = [
            '/para\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/u',
            '/para\s+el\s+usuario\s+([A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)+)/u',
            '/para\s+([A-ZÁÉÍÓÚÑ][\w\s]+?)(?:\s+con|\s+curp|\s+rfc|\s+nss|$)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    protected function extractPlate(string $message): ?string
    {
        if (preg_match('/\bplaca\s+([A-Z0-9-]{5,10})\b/iu', $message, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    protected function extractYear(string $message): ?string
    {
        if (preg_match('/\b(20\d{2}|19\d{2})\b/', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extrae CURP de 18 caracteres del mensaje.
     */
    protected function extractCURP(string $message): ?string
    {
        preg_match('/\b[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d\b/u', strtoupper($message), $matches);
        return $matches[0] ?? null;
    }

    /**
     * Extrae RFC del mensaje (12 o 13 caracteres).
     */
    protected function extractRFC(string $message): ?string
    {
        preg_match('/\b[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}\b/u', strtoupper($message), $matches);
        if (!empty($matches[0])) {
            $rfc = $matches[0];
            // No confundir con CURP (18 chars)
            if (strlen($rfc) >= 12 && strlen($rfc) <= 13) {
                return $rfc;
            }
        }
        return null;
    }

    /**
     * Extrae NSS (Número de Seguridad Social) de 11 dígitos.
     */
    protected function extractNSS(string $message): ?string
    {
        preg_match('/\b\d{11}\b/', $message, $matches);
        return $matches[0] ?? null;
    }

    /**
     * Extrae email del mensaje.
     */
    protected function extractEmail(string $message): ?string
    {
        preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message, $matches);
        return $matches[0] ?? null;
    }

    /**
     * Busca un servicio por nombre (coincidencia parcial).
     */
    protected function findService(string $name): ?Service
    {
        $name = trim($name);

        $activeScope = function ($query) {
            $query->where('is_active', true)->orWhereNull('is_active');
        };

        // 1. Coincidencia exacta (case insensitive)
        $service = Service::where($activeScope)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($service) return $service;

        // 2. Coincidencia parcial
        $service = Service::where($activeScope)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($name) . '%'])
            ->first();
        if ($service) return $service;

        // 3. Búsqueda relajada: eliminar palabras comunes
        $searchTerms = preg_replace('/\b(acta|de|la|del|el|los|las|con|para|por|y|al|un|una)\b/iu', '', $name);
        $searchTerms = trim($searchTerms);
        if (strlen($searchTerms) > 2) {
            $service = Service::where($activeScope)
                ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($searchTerms) . '%'])
                ->first();
            if ($service) return $service;
        }

        // 4. Mapeo de alias comunes
        $aliasMap = [
            'acta de nacimiento' => ['Acta de Nacimiento'],
            'curp' => ['CURP Actualizada'],
            'curp actualizada' => ['CURP Actualizada'],
            'nss' => ['Localizar NSS con CURP'],
            'localizar nss' => ['Localizar NSS con CURP'],
            'constancia fiscal' => ['CSF Clon con CURP', 'CSF con RFC y IDCIF'],
            'csf' => ['CSF Clon con CURP', 'CSF con RFC y IDCIF'],
            'constancia de situación fiscal' => ['CSF Clon con CURP', 'CSF con RFC y IDCIF'],
            'rfc' => ['CSF Clon con CURP', 'CSF con RFC y IDCIF'],
            'acta de matrimonio' => ['Acta de Matrimonio'],
            'acta de divorcio' => ['Acta de Divorcio'],
            'acta de defunción' => ['Acta de Defunción'],
            'semanas cotizadas' => ['Semanas Cotizadas por CURP'],
            'infonavit' => ['ESTADO DE CUENTA MENSUAL INFONAVIT', 'RECUPERAR CLAVE CUENTA INFONAVIT', 'REPORTE HISTORICO INFONAVIT', 'RESUMEN CREDITO INFONAVIT'],
            'afore' => ['Localizar AFORE (Saber el Banco o Institución)'],
            'repuve' => ['HOJA REPUVE'],
            'antecedentes' => ['Antecedentes no Penales'],
            'cfe' => ['Recibo CFE PDF'],
            'tenencia' => ['FORMATO PAGO DE TENENCIA CD MX', 'FORMATO PAGO DE TENENCIA EDOMEX'],
            'vigencia derechos' => ['Constancia Vigencia Derechos NSS PDF por CURP'],
            'idcif' => ['LOCALIZAR IDCIF [ORDENAR CON RFC] SOLO ARROJA IDCIF'],
        ];

        $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        if (isset($aliasMap[$key])) {
            foreach ($aliasMap[$key] as $aliasName) {
                $service = Service::where('name', $aliasName)->first();
                if ($service) return $service;
            }
        }

        return null;
    }

    protected function findServiceByCandidates(array $candidates): ?Service
    {
        foreach ($candidates as $candidate) {
            $service = $this->findService($candidate);
            if ($service) {
                return $service;
            }
        }

        return null;
    }

    protected function getMissingRequiredFields(Service $service, array $detectedData): array
    {
        $messages = [];

        foreach ($service->form_schema ?? [] as $field) {
            $fieldName = $field['name'] ?? null;

            if (!$fieldName || empty($field['required'])) {
                continue;
            }

            if ($fieldName === 'input_string') {
                if (empty($detectedData['curp'])) {
                    $messages[] = 'CURP del solicitante.';
                }
                if (empty($detectedData['nss'])) {
                    $messages[] = 'NSS del solicitante.';
                }
                continue;
            }

            $fieldMessages = [
                'curp' => 'CURP del solicitante.',
                'rfc' => 'RFC del solicitante.',
                'nss' => 'NSS del solicitante.',
                'plate' => 'Placa del vehiculo.',
                'year' => 'Ano a pagar.',
                'service_number' => 'Numero de servicio de CFE.',
                'idcif' => 'IDCIF del solicitante.',
            ];

            if (empty($detectedData[$fieldName])) {
                $messages[] = $fieldMessages[$fieldName] ?? (($field['label'] ?? $fieldName) . '.');
            }
        }

        return array_values(array_unique($messages));
    }

    protected function resolveTargetUser(string $message, ?string $email): ?User
    {
        if ($email) {
            return User::where('email', $email)->first();
        }

        if (preg_match('/usuario\s+#?(\d+)/iu', $message, $matches)) {
            return User::find((int) $matches[1]);
        }

        return null;
    }

    protected function buildGenericServiceGuide(): string
    {
        return "Claro, puedo ayudarte a solicitar un servicio.\n¿Que tramite necesitas realizar?\n\nAlgunos servicios disponibles son:\n\n- "
            . implode("\n- ", $this->guidedServiceExamples)
            . "\n\nPuedes escribir, por ejemplo:\nCrea un tramite de Acta de Nacimiento para Luis Alfonso con CURP EXPL050202HYNKCSA0 para usuario [correo@ejemplo.com](mailto:correo@ejemplo.com)";
    }

    protected function buildServiceNotFoundResponse(string $serviceName): string
    {
        $similar = Service::where(fn($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($serviceName) . '%'])
            ->limit(5)
            ->pluck('name')
            ->toArray();

        if (empty($similar)) {
            $similar = Service::where(fn($q) => $q->where('is_active', true)->orWhereNull('is_active'))
                ->limit(8)
                ->pluck('name')
                ->toArray();
        }

        $suggestions = empty($similar)
            ? 'No hay servicios registrados en el sistema.'
            : "Servicios similares disponibles:\n- " . implode("\n- ", $similar);

        return "No encontre un servicio llamado \"{$serviceName}\".\n{$suggestions}";
    }

    protected function buildMissingDataResponse(
        Service $service,
        string $serviceLabel,
        ?string $clientName,
        ?string $curp,
        ?string $rfc,
        ?string $nss,
        ?string $plate,
        ?string $year,
        ?string $email,
        array $missingItems,
        bool $onlyUserMissing
    ): string {
        $example = 'Crea un tramite de ' . $serviceLabel;

        if ($clientName) {
            $example .= ' para ' . $clientName;
        } else {
            $example .= ' para [nombre del solicitante]';
        }

        if ($curp) {
            $example .= ' con CURP ' . strtoupper($curp);
        } elseif ($this->serviceRequiresField($service, 'curp')) {
            $example .= ' con CURP EXPL050202HYNKCSA0';
        }

        if ($rfc) {
            $example .= ' con RFC ' . strtoupper($rfc);
        } elseif ($this->serviceRequiresField($service, 'rfc')) {
            $example .= ' con RFC PEJJ010203AB1';
        }

        if ($nss) {
            $example .= ' con NSS ' . $nss;
        } elseif ($this->serviceRequiresField($service, 'nss') || $this->serviceRequiresField($service, 'input_string')) {
            $example .= ' con NSS 12345678901';
        }

        if ($plate) {
            $example .= ' con placa ' . strtoupper($plate);
        } elseif ($this->serviceRequiresField($service, 'plate')) {
            $example .= ' con placa ABC1234';
        }

        if ($year) {
            $example .= ' del ano ' . $year;
        } elseif ($this->serviceRequiresField($service, 'year')) {
            $example .= ' del ano 2026';
        }

        $example .= ' para usuario ' . ($email ? '[' . $email . '](mailto:' . $email . ')' : '[correo@ejemplo.com](mailto:correo@ejemplo.com)');

        if ($onlyUserMissing) {
            return "Puedo crear el pedido, pero falta indicar el usuario cliente.\n\nEscribe por ejemplo:\n{$example}\n\nUsuarios disponibles:\n\n" . $this->buildAvailableUsersList();
        }

        $intro = $clientName
            ? "Claro, puedo ayudarte a crear un pedido de {$serviceLabel} para {$clientName}."
            : "Claro, puedo ayudarte a crear un pedido de {$serviceLabel}.";

        return $intro
            . "\n\nPara continuar necesito:\n\n- "
            . implode("\n- ", $missingItems)
            . "\n\nEjemplo:\n{$example}"
            . (!$email ? "\n\nUsuarios disponibles:\n\n" . $this->buildAvailableUsersList() : '');
    }

    protected function buildAvailableUsersList(): string
    {
        $users = User::where('is_admin', false)->orderBy('id')->limit(5)->get();

        if ($users->isEmpty()) {
            return '- No hay usuarios cliente registrados.';
        }

        return $users
            ->map(fn($u) => "- #{$u->id}: {$u->name} ([{$u->email}](mailto:{$u->email}))")
            ->implode("\n");
    }

    protected function serviceRequiresField(Service $service, string $fieldName): bool
    {
        foreach ($service->form_schema ?? [] as $field) {
            if (($field['name'] ?? null) === $fieldName && !empty($field['required'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper para hacer una consulta cruda a Ollama y obtener solo el string.
     */
    protected function askOllamaDirectly(OllamaReportService $aiService, string $prompt): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)->post('http://localhost:11434/api/generate', [
                'model' => 'llama3.2:1b',
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful() && isset($response->json()['response'])) {
                return trim($response->json()['response']);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AdminChatCommandService] Ollama error: ' . $e->getMessage());
        }
        return null;
    }
}
