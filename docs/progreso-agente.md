# Progreso del Agente y Registro de Auditoría

Este documento sirve como bitácora y registro de avance del agente y el equipo de desarrollo para cumplir las metas propuestas.

---

## 1. Hitos de Auditoría Inicial

- **Fecha de Auditoría:** 2026-06-19
- **Estado del Repositorio:** Funciona con base de datos SQLite y scripts en Python para RAG (interfaz con ChromaDB y Ollama `llama3.2:1b`).
- **Análisis de Requisitos:**
  - **Semana 4:** Alto cumplimiento base (80%), falta refinamiento del sliding window real de tokens y control estructurado de state poisoning en la invocación de herramientas locales.
  - **Semana 5:** Nulo cumplimiento (0%), no hay streaming SSE, no hay observabilidad en BD, no hay guardrails de seguridad locales ni entrada de voz en el chat.

---

## 2. Registro de Progreso

| ID Tarea | Descripción | Estado | Fecha de Inicio | Fecha de Fin | Notas / Evidencias |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **AUD-01** | Auditoría de código vs PDF de rúbricas | **COMPLETADO** | 2026-06-19 | 2026-06-19 | Se crearon las matrices de cumplimiento y el plan de implementación. |
| **1.1** | Migración y Modelo de Observabilidad | **COMPLETADO** | 2026-06-19 | 2026-06-20 | Creada tabla `ai_observability_logs`. |
| **1.2** | Capa de Seguridad (Guardrails) | **COMPLETADO** | 2026-06-20 | 2026-06-20 | Implementada validación heurística. |
| **2.1** | Stream en `rag_bridge.py` | **COMPLETADO** | 2026-06-21 | 2026-06-21 | Adaptado a stream = True. |
| **2.2** | Endpoint SSE en Laravel `/ia-test` | **COMPLETADO** | 2026-06-21 | 2026-06-21 | Implementado StreamedResponse. |
| **3.1** | Sliding Window y State Poisoning | **COMPLETADO** | 2026-06-19 | 2026-06-19 | Implementado recorte por tokens real y manejo seguro de tool errors en historial. |
| **4.1** | UI Chat Stream y Estados | **COMPLETADO** | 2026-06-22 | 2026-06-22 | Consumo del stream con JS. |
| **4.2** | Entrada de voz en el chat | **COMPLETADO** | 2026-06-22 | 2026-06-22 | Implementada Web Speech API. |

---

### Fase 3: Streaming y Estados
- [x] Refactorizar `rag_bridge.py` para emitir JSON Lines con el stream de tokens.
- [x] Adaptar el backend de Laravel (`routes/web.php`) para usar `StreamedResponse` y enviar SSE (Server-Sent Events) hacia el cliente.
- [x] Añadir estados de interfaz en el frontend (`SEARCHING`, `THINKING`, `STREAMING`, `ERROR`, `COMPLETED`).
- [x] Consumir correctamente el endpoint SSE mediante el `fetch` nativo de JavaScript y `TextDecoder`.

### Fase 4: Voz
- [x] Integrar botón de micrófono interactivo en el chat de Filament.
- [x] Utilizar Web Speech API para capturar, transcribir y colocar el audio convertido a texto dentro de la caja de texto.

### Fase 5: Pruebas y Revisión Final
- [x] Ejecutar la suite global de pruebas y reportar los fallos preexistentes no relacionados a nuestro código.
- [x] Corregir tests afectados por la migración a StreamedResponse (ej: `AiChatFlowTest`).
- [x] Asegurar que las regresiones de la Semana 4 no existan.

---

**Estado Final:** 
La Semana 04 y Semana 05 han sido implementadas exitosamente. Todo el stack funciona como se esperaba, validado bajo pruebas y documentado exhaustivamente.
