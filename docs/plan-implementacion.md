# Plan de Implementación de Mejoras (Semanas 4 y 5)

Este plan de desarrollo detalla las tareas requeridas para subsanar los puntos pendientes y lograr el cumplimiento del 100% en las evaluaciones de las semanas 4 y 5.

---

## Tareas y Planificación

### Fase 1: Base de Datos, Modelos y Seguridad (Prioridad Alta)
- **Tarea 1.1: Migración y Modelo de Observabilidad**
  - Crear la migración `create_ai_observability_logs_table` con los campos: `id`, `session_id`, `timestamp`, `user_prompt`, `system_response`, `ttft_ms`, `total_latency_ms`, `tokens_per_second`, `was_blocked`, `tools_executed` (JSON).
  - Crear el modelo `App\Models\AiObservabilityLog`.
- **Tarea 1.2: Implementación de la Capa de Seguridad (Guardrails)**
  - Crear una clase validadora `App\Services\AI\GuardrailService` con reglas heurísticas en PHP para interceptar Prompt Injection y jailbreaks (ej: "ignora", "system prompt", "jailbreak", "asume el rol", etc.).
  - Integrar esta validación en el controlador `/ia-test` antes de invocar a Python/Ollama. Si es bloqueada, registrar en la tabla de observabilidad con `was_blocked = true` y retornar una respuesta genérica estándar de inmediato.

### Fase 2: Streaming de Respuestas (SSE) y Optimización del RAG Bridge (Prioridad Alta)
- **Tarea 2.1: Modificación de `rag_bridge.py` para soporte de Stream**
  - Configurar la llamada a Ollama con `"stream": true`.
  - Imprimir los fragmentos/tokens en `sys.stdout` en tiempo real (utilizando delimitadores claros para el backend, p. ej. formato SSE o salidas crudas controladas).
- **Tarea 2.2: Refactorizar `/ia-test` para Server-Sent Events (SSE)**
  - Modificar la respuesta del endpoint en Laravel para retornar una `Symfony\Component\HttpFoundation\StreamedResponse` bajo la cabecera `text/event-stream`.
  - Leer la salida incremental del script de Python y transmitirla al frontend token por token.
  - Medir con precisión los tiempos:
    - **TTFT (Time To First Token):** Tiempo transcurrido desde el inicio de la petición hasta el primer token retornado por Ollama.
    - **Latencia Total:** Tiempo desde el inicio hasta la finalización de la respuesta.
    - **Tokens por Segundo (TPS):** Calcular matemáticamente `cantidad de tokens / tiempo de generación activa`.
  - Registrar todos estos datos y la lista de herramientas en la base de datos de observabilidad al finalizar el streaming.

### Fase 3: Ventana de Contexto y Gestión del Historial (Prioridad Media)
- **Tarea 3.1: Algoritmo de Ventana Deslizante (Sliding Window)**
  - Ajustar `ConversationMemoryService::getPromptBuffer` para realizar un recorte real por límite de tokens estimado o número de mensajes máximos estrictos, evitando que el buffer exceda el límite del modelo `llama3.2:1b`.

### Fase 4: Experiencia de Usuario, Animaciones e Integración de Voz (Prioridad Media)
- **Tarea 4.1: Interfaz Web y Estados del Agente**
  - Actualizar `a-i-chat.blade.php` para consumir la respuesta de forma incremental mediante la API de Streams del navegador (`EventSource` o `fetch` con lector de streams).
  - Añadir estados visuales explícitos en el frontend:
    - `"Pensando..."` durante la inferencia inicial.
    - `"Buscando..."` cuando se realiza la consulta RAG.
    - `"Ejecutando..."` cuando se confirma/procesa una orden de trámite.
- **Tarea 4.2: Entrada de Voz Local (Web Speech API)**
  - Añadir un botón de micrófono en la caja de texto del chat.
  - Implementar la Web Speech API para capturar y transcribir la voz del usuario directamente en el navegador, rellenando el prompt de entrada para su envío.
  - Añadir la justificación de inviabilidad técnica de Whisper en el servidor local debido a limitación de recursos de hardware en el documento técnico correspondiente.

---

## Dependencias
1. Las tareas de observabilidad y guardrails (Fase 1) son prerrequisitos para el logging correcto del stream.
2. La refactorización del puente de Python (Tarea 2.1) es prerrequisito para habilitar SSE en Laravel (Tarea 2.2).
3. El streaming (Fase 2) es prerrequisito para actualizar la interfaz gráfica del chat (Fase 4).
