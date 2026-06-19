# Auditoría Técnica - Rúbricas de Evaluación

Este documento detalla los resultados del análisis y la auditoría técnica del repositorio frente a las rúbricas oficiales de las semanas 4 y 5.

---

## 1. Análisis del Stack Tecnológico Detectado

- **Framework Principal:** Laravel 12.0 (PHP 8.3)
- **Base de Datos:** SQLite / MySQL / PostgreSQL (soporte mediante Eloquent, migraciones y `database.sqlite` en scripts).
- **Frontend / Panel Admin:** Filament v3.2 (PHP Livewire v3, Livewire Volt v1.7, TailwindCSS v3).
- **Motor / Backend de IA:** RAG en Python (`rag/rag_bridge.py`) interactuando con **Ollama** (`llama3.2:1b`) en `http://localhost:11434`.
- **Base de Datos Vectorial:** ChromaDB (`rag/chroma_db`).
- **Almacenamiento de Archivos:** Compatible con AWS S3 para PDFs y resultados.

---

## 2. Análisis Crítico: Semana 4 (Memoria y Persistencia)

### A. Mecanismo de Identificación de Sesión (`conversation_id` único)
- **Implementación Actual:** Se cuenta con el controlador de API `/ia-test` (dentro de `routes/web.php`) que recibe `conversation_id`. Si no se provee o se especifica un nuevo flujo, se genera un ID único mediante `conv_` + `uniqid()`. Se almacena en `localStorage` en el cliente y se persiste en la tabla `ai_conversations`.
- **Evaluación:** **CUMPLE**. El flujo de generación e identificación de sesión está estructurado e implementado.

### B. Almacenamiento del Historial (Chat History Store No Volátil)
- **Implementación Actual:** Existen las tablas `ai_conversations` y `ai_messages` creadas mediante migraciones. Los mensajes se guardan a través del `ConversationMemoryService` con campos para `role`, `content`, `tool_name`, `tool_status`, `token_estimate` y `metadata`.
- **Evaluación:** **CUMPLE**. El almacenamiento es persistente y no volátil en la base de datos de la aplicación.

### C. Lógica de Flujo de Prompting (Recuperación Ordenada del Historial)
- **Implementación Actual:** `ConversationMemoryService::getPromptBuffer` recupera los últimos N mensajes ordenados cronológicamente (`orderBy('created_at', 'desc')` limitado a N y luego invertido con `reverse()`).
- **Evaluación:** **CUMPLE**. Recupera el historial en el orden correcto y lo envía al modelo a través de `rag_bridge.py`.

### D. Gestión de la Ventana de Contexto (Sliding Window o Resumen)
- **Implementación Actual:** Hay un método `shouldSummarize` que comprueba si los tokens estimados superan los 1000. Si es así, ejecuta `updateSummary`, guardando una versión condensada en la columna `summary` de la conversación. No obstante, la generación del resumen es simulada / estática (`generateSummary` solo extrae el último mensaje del usuario) y no reduce dinámicamente el buffer de mensajes reales enviados en `getPromptBuffer` (los cuales siguen acumulándose hasta el límite parametrizado en la consulta).
- **Evaluación:** **PARCIAL**. La estructura existe, pero la reducción real y el resumen dinámico inteligente no están completamente optimizados para limitar los tokens que se envían a Ollama.

### E. Manejo de Errores en Function Calling (State Poisoning / Envenenamiento de Memoria)
- **Implementación Actual:** El servicio expone `addToolMessage` y permite registrar mensajes con el rol `tool`. Sin embargo, en el controlador `/ia-test` no se implementa una lógica de invocación real de herramientas por parte del LLM (Ollama no ejecuta Function Calling nativo en esta versión del puente). Las respuestas con herramientas son simuladas en PHP (`extractOrderContext` y `generateSimpleResponse` basados en regex heurísticos). Los fallos se loguean pero no están integrados en un ciclo de auto-corrección conversacional o de reintento del LLM.
- **Evaluación:** **PARCIAL**. Existe la estructura de guardado de herramientas, pero no hay un flujo real de Function Calling desde el LLM que pueda causar state poisoning ni mecanismos de auto-recuperación activa.

---

## 3. Análisis Crítico: Semana 5 (UX, Seguridad y Observabilidad)

### A. Interfaz Moderna y Responsiva
- **Implementación Actual:** Se tiene una vista en Filament (`a-i-chat.blade.php`) inspirada en la interfaz de ChatGPT (diseño oscuro, barra de chat centrada, chips de sugerencias). Es responsiva, pero el diseño web de Filament restringe la visualización a una página dentro del panel administrativo. Hay otra vista en `/ia-chat` (`ia-chat.blade.php`) que es sumamente básica (HTML puro con un textarea y un botón).
- **Evaluación:** **PARCIAL**. Falta unificar en un chat general de la aplicación con un diseño premium completo y animaciones refinadas que no dependan únicamente del panel de Filament si el usuario final es un cliente común.

### B. Streaming de Respuestas (SSE o WebSockets)
- **Implementación Actual:** El controlador `/ia-test` retorna respuestas síncronas en formato JSON completo al finalizar la ejecución del script. No hay implementación de Server-Sent Events (SSE) ni de WebSockets. El cliente espera a que termine la llamada HTTP y pinta el texto de golpe tras remover la animación de carga.
- **Evaluación:** **NO CUMPLE**. Se requiere refactorizar el endpoint de chat y el puente de Python para transmitir la respuesta token por token mediante SSE (`text/event-stream`).

### C. Estados del Agente (Pensando, Buscando, Ejecutando)
- **Implementación Actual:** El frontend de Filament muestra tres puntos suspensivos animados (`typing-dots`) que indican que el asistente está respondiendo ("Pensando"). No existen indicadores detallados cuando se busca en la base de datos vectorial (ChromaDB) o cuando se crea/ejecuta una orden.
- **Evaluación:** **PARCIAL**. Solo se indica el estado general de carga, no los micro-estados específicos (buscando en RAG o ejecutando orden).

### D. Entrada de Voz Local
- **Implementación Actual:** No hay ningún elemento o script para capturar voz en el frontend ni backend.
- **Evaluación:** **NO CUMPLE**. Se requiere integrar la captura de voz (usando la Web Speech API en el frontend como opción cliente o Whisper local) y añadir el control correspondiente en la barra de chat.

### E. Seguridad: Capa de Validación (Guardrails sin invocar al LLM)
- **Implementación Actual:** No existe un middleware o capa de validación para interceptar Prompt Injection, jailbreaks o fugas de instrucciones antes de llamar a Python/Ollama.
- **Evaluación:** **NO CUMPLE**. Se debe desarrollar un mecanismo de filtrado heurístico (heurísticas de palabras clave y patrones sospechosos) que actúe en el backend y retorne un mensaje de bloqueo predeterminado inmediatamente.

### F. Base de Datos de Observabilidad
- **Implementación Actual:** No existen tablas ni modelos de observabilidad con los campos requeridos (`ttft_ms`, `total_latency_ms`, `tokens_per_second`, `was_blocked`, `tools_executed`).
- **Evaluación:** **NO CUMPLE**. Se requiere crear la migración, el modelo `AiObservabilityLog` y la lógica de registro de métricas.

### G. Registro de Prompts Bloqueados e Historial de Herramientas
- **Implementación Actual:** No hay registro estructurado de bloqueos de seguridad ni de la ejecución JSON de herramientas (parámetros y estados).
- **Evaluación:** **NO CUMPLE**. Depende de la implementación de la capa de seguridad y observabilidad.
