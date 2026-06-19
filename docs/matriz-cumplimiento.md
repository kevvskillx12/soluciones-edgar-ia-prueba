# Matriz de cumplimiento técnico

Fecha de cierre provisional: 2026-06-19.

La matriz diferencia entre evidencia automatizada registrada, inspección de código y validación manual pendiente. La existencia de una implementación no sustituye una prueba visual o de operación real.

| Requisito | Estado de implementación | Evidencia disponible | Validación pendiente |
| --- | --- | --- | --- |
| Identificador único de conversación | Implementado | Pruebas específicas registradas y `ConversationMemoryService` | Ninguna adicional para el código |
| Reutilización y aislamiento de conversaciones | Implementado | Pruebas específicas registradas | Recuperación tras reiniciar la aplicación |
| Persistencia de mensajes y roles | Implementado | Migraciones, modelos y pruebas específicas registradas | Recuperación tras reinicio real |
| Ventana de contexto | Implementado | `ConversationMemoryService` y pruebas específicas registradas | Ninguna adicional para el código |
| Manejo seguro de errores de herramientas | Implementado | Pruebas específicas registradas | Desconexión durante una respuesta |
| Guardrails | Implementado | `GuardrailService` y pruebas específicas registradas | Revisión manual de casos adicionales |
| Observabilidad | Implementado | Migración, modelo y pruebas específicas registradas | Registros reales generados con Ollama |
| Streaming SSE | Implementado | Pruebas específicas registradas e inspección del flujo | Streaming visible en navegador |
| Estados visuales del agente | Implementado | Código de interfaz | Confirmación visual en navegador |
| Entrada por micrófono | Implementado, no verificado manualmente | Código Web Speech API | Prueba real de micrófono y permisos |
| Apariencia responsive | No verificada manualmente | Código de vistas | Prueba en tamaños móvil, tableta y escritorio |
| Build frontend | Pendiente | `npm.cmd run build` llega a Vite, pero falta `vendor/filament/support/tailwind.config.preset.js` | Restaurar dependencias PHP y repetir build |

## Resultado de pruebas registrado para el cierre

- 19 pruebas específicas aprobadas.
- 149 aserciones específicas aprobadas.
- 53 de 57 pruebas globales aprobadas.
- Cuatro fallos globales preexistentes:
  - tres casos de `PasswordResetTest`;
  - un caso de `ExampleTest` por esperar HTTP 200 y recibir redirección HTTP 302.

Estos valores se registran como resultado aprobado del cierre. El 2026-06-19 no fue posible reejecutarlos en este equipo porque `php`, `composer` y `vendor/bin/phpunit` no están disponibles localmente.

## Modelo local de IA

El modelo configurado en `rag/rag_bridge.py` es `llama3.2:1b`, mediante `MODELO = "llama3.2:1b"`.
