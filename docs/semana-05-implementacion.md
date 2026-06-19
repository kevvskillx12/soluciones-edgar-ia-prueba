# Semana 05: guardrails, observabilidad, streaming y voz

## Alcance implementado

- Guardrails locales antes de invocar el modelo.
- Registro en `ai_observability_logs`.
- Métricas de TTFT, latencia total y tokens por segundo.
- Registro JSON de herramientas ejecutadas.
- Streaming mediante SSE.
- Estados visuales del agente.
- Integración de Web Speech API para entrada por micrófono.

## Evidencia automatizada registrada

- 19 pruebas específicas aprobadas.
- 149 aserciones específicas aprobadas.
- 53 de 57 pruebas globales aprobadas.
- Cuatro fallos globales preexistentes:
  - tres en `PasswordResetTest`;
  - uno en `ExampleTest`, por la redirección HTTP 302 de `/`.

Los comandos específicos que deben conservarse como evidencia reproducible están en `docs/comandos-demostracion.md`.

## Estado del build frontend

El build queda pendiente, no aprobado.

`npm.cmd install` terminó correctamente. Después, `npm.cmd run build` falló porque Tailwind intenta cargar:

```text
./vendor/filament/support/tailwind.config.preset.js
```

El directorio `vendor` no está instalado en este entorno. No se modificó `tailwind.config.js` ni se instaló PHP/Composer automáticamente.

## Elementos que no se consideran verificados automáticamente

- Entrada por micrófono.
- Apariencia responsive.
- Streaming visible en navegador.
- Registros reales con Ollama.
- Desconexión durante una respuesta.

La presencia del código y las pruebas con dobles controlados no sustituyen esas comprobaciones manuales.

## Modelo Ollama comprobado

`rag/rag_bridge.py` define:

```python
MODELO = "llama3.2:1b"
```

Por tanto, la documentación del cierre mantiene `llama3.2:1b`.
