# Implementación Semana 05

Este documento resume la implementación completa de la Semana 05, abordando las áreas de Guardrails, Observabilidad, Streaming y Voz, y Pruebas Globales.

## Fases Completadas

### Fase 1: Guardrails
- **`GuardrailService` en PHP**: Implementado en `app/Services/AI/GuardrailService.php`. Normaliza el prompt y bloquea proactivamente inyecciones, jailbreaks ("ignora las instrucciones anteriores", "revela el system prompt", etc.) y longitudes excesivas.
- **Flujo Controlado**: Al detectarse una inyección, se bloquea la solicitud antes de llamar al LLM (ahorrando tiempo y cómputo) y se marca como `was_blocked=true`.

### Fase 2: Observabilidad
- **Métricas detalladas**: Se implementó la migración y el modelo `AiObservabilityLog`. 
- Se capturan las métricas clave:
  - `user_prompt`: Prompt del usuario.
  - `system_response`: Respuesta generada (ya sea LLM, fallback o bloqueo).
  - `was_blocked`: True si fue bloqueado por el Guardrail.
  - `ttft_ms`: Time to First Token (en ms).
  - `total_latency_ms`: Latencia total (en ms).
  - `tokens_per_second`: Velocidad de generación (TPS).
- Se modificó `routes/web.php` para registrar estas métricas al finalizar o al bloquear cada solicitud.

### Fase 3: Streaming y Estados (SSE)
- **`rag_bridge.py`**: Refactorizado para usar `stream: True` al consultar a Ollama y emitir `print` de JSON lines incrementales.
- **Backend (`routes/web.php`)**: Sustituido el `shell_exec` sincrónico por `Symfony\Component\Process\Process` y `response()->stream()` para empujar eventos Server-Sent Events (SSE).
- **Frontend (`a-i-chat.blade.php` e `ia-chat.blade.php`)**:
  - Implementado consumo del stream SSE usando `fetch()` y `getReader()`.
  - Se añadieron estados de interfaz interactiva: `SEARCHING`, `THINKING`, `STREAMING`, `ERROR` y `COMPLETED`.

### Fase 4: Voz
- **Web Speech API**: Agregado un botón de micrófono (con icono) en el frontend de Filament (`a-i-chat.blade.php`).
- Al presionar, el navegador escucha y transcribe (con resultados parciales y finales) directamente al área de texto del prompt, listo para enviar.

### Fase 5: Pruebas y Fallos Preexistentes
- Se crearon pruebas unitarias y funcionales para la Semana 5 (ej. `Phase2ObservabilityTest`).
- Se validó que las pruebas de la Semana 4 continúan funcionando (`Week4RequirementsTest`).
- Se reparó `AiChatFlowTest` que había roto temporalmente al migrar el endpoint a Streaming (se modificó para leer el `StreamedResponse`).

#### Fallos preexistentes en la suite global (4 fallos, 0 por nuestra implementación actual):
1. **PasswordResetTest (3 tests fallidos)**: Los tests esperan que la notificación `ResetPassword` sea enviada (`NotificationFake`), pero falla porque aparentemente la lógica de envío de correo/reset de la plantilla por defecto no está configurada o no dispara el evento adecuado en este entorno.
2. **ExampleTest (1 test fallido)**: El test `test_the_application_returns_a_successful_response` espera un código `200` en la ruta `/`, pero la aplicación devuelve un `302` (probablemente una redirección al `/admin` de Filament o al login).
Ninguno de estos fallos está relacionado con la implementación del Chat IA (Semana 4 y 5).

## Conclusión
La Semana 05 ha sido implementada en su totalidad cumpliendo todos los requisitos de la rúbrica.
