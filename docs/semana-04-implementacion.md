# Implementación Técnica - Semana 4: Persistencia y Memoria

Este documento detalla cómo se resolvieron y completaron todos los requisitos técnicos para la persistencia stateful del agente de IA local.

## 1. Identificación de Sesión (`conversation_id`)
El sistema está diseñado para que cada cliente genere o reciba un `conversation_id` único, persistido localmente en el `localStorage` del navegador y enviado en cada solicitud POST a `/ia-test`.
- Si la solicitud no contiene ID, o si el usuario indica una nueva intención de trámite explícita, se genera un ID único mediante la función de Laravel (`conv_` + `uniqid()`).
- Esto aísla los contextos entre distintos usuarios y distintas tareas del mismo usuario.

## 2. Almacenamiento No Volátil (BD Relacional)
Se implementó un esquema de base de datos relacional para guardar el historial sin pérdida de datos en reinicios del servidor.
- **Tabla `ai_conversations`**: Guarda el identificador único, usuario, canal y resumen.
- **Tabla `ai_messages`**: Guarda la secuencia de mensajes con clave foránea a la conversación. Almacena el rol (`user`, `assistant`, `system`, `tool`), el contenido textual y metadata adicional en formato JSON.

## 3. Ventana de Contexto (Sliding Window Basado en Tokens)
Para evitar los desbordes del modelo local `llama3.2:1b` (que tiene un límite de 8192 tokens de VRAM estándar en configuraciones pequeñas):
- Se implementó un algoritmo dinámico en el método `getPromptBuffer()` del servicio `ConversationMemoryService`.
- Este algoritmo lee los mensajes ordenados descendentemente por ID, calcula una aproximación de los tokens (`strlen / 4`) y acumula los mensajes hasta un máximo configurable (4000 tokens en la inferencia estándar).
- Asegura que si el límite se excede, los mensajes más antiguos queden descartados, preservando siempre el system prompt (mediante resúmenes) y el mensaje actual.

## 4. Manejo de Errores en Function Calling (Evitar State Poisoning)
Una de las vulnerabilidades más comunes en agentes con herramientas locales es el bucle infinito cuando una herramienta devuelve una excepción o estado de error, y el LLM, sin contexto, vuelve a intentar llamar a la misma herramienta con idénticos parámetros.
- Se ha interceptado esta condición en `getPromptBuffer()`.
- Si el buffer detecta un mensaje con el rol `tool` y su estado interno (`tool_status`) es `error`, el sistema anexa automáticamente un log del sistema (`[SYSTEM LOG]`) indicando claramente que la herramienta falló de manera interna y prohibiendo reintentar los mismos parámetros.

## 5. Batería de Pruebas Unitarias y de Integración (PHPUnit)
Se escribieron y validaron las siguientes pruebas:
- Creación de nueva conversación si no se envía ID.
- Reutilización correcta si se envía ID (sin mezclar mensajes de otras conversaciones).
- Recorte de contexto basado en tokens (garantizando el límite del Sliding Window).
- Control de error en herramientas verificando que no se envía la excepción desnuda, sino con prevención de state poisoning.
