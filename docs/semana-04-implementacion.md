# Semana 04: persistencia y memoria

## Alcance implementado

La solución contiene identificación de conversación, persistencia relacional de mensajes, reutilización de contexto, aislamiento entre conversaciones, recorte de contexto y manejo de mensajes de herramienta con error.

Los componentes principales son:

- `app/Services/AI/ConversationMemoryService.php`;
- modelos `AiConversation` y `AiMessage`;
- migraciones de conversaciones y mensajes;
- endpoint `/ia-test`;
- pruebas `Week4RequirementsTest.php` y `ConversationMemoryTest.php`.

## Evidencia automatizada registrada

El cierre registra, junto con las demás pruebas específicas:

- 19 pruebas específicas aprobadas;
- 149 aserciones específicas aprobadas.

La suite global registra 53 de 57 pruebas aprobadas, con cuatro fallos preexistentes no atribuidos a la implementación de IA.

## Límite de la verificación local

El 2026-06-19 no se pudo reejecutar PHPUnit en este equipo:

```text
vendor/bin/phpunit: comando no reconocido
vendor/autoload.php: no existe
php: no disponible en PATH
composer: no disponible en PATH
```

No se instalaron PHP ni Composer automáticamente y no se modificaron pruebas.

## Validación manual pendiente

La persistencia está implementada, pero no se marca como verificada la recuperación después de reiniciar la aplicación. Para cerrarla se debe:

1. Crear una conversación y conservar su `conversation_id`.
2. Confirmar los mensajes en `ai_conversations` y `ai_messages`.
3. Detener Laravel y cualquier proceso auxiliar.
4. Iniciar de nuevo el proyecto.
5. Continuar con el mismo `conversation_id`.
6. Confirmar que el contexto anterior se recupera y que no se mezclan conversaciones.

## Modelo utilizado

El puente RAG mantiene `llama3.2:1b`, configurado en `rag/rag_bridge.py`.
