# Reporte final de cumplimiento

Fecha: 2026-06-19.

## Dictamen

La implementación de las semanas 04 y 05 queda aprobada provisionalmente. El cierre documental no modifica código funcional de IA ni pruebas.

## Resultados registrados

- 19 pruebas específicas aprobadas.
- 149 aserciones específicas aprobadas.
- 53 de 57 pruebas globales aprobadas.
- Cuatro fallos globales preexistentes:
  - tres pruebas de `PasswordResetTest`;
  - una prueba de `ExampleTest` que espera HTTP 200 en `/`, mientras la aplicación redirige con HTTP 302.
- Build frontend: pendiente.
- Pruebas manuales: pendientes.

## Verificación local realizada

- Node.js: `v24.14.0`.
- npm: `11.9.0`, ejecutado como `npm.cmd` porque PowerShell bloquea `npm.ps1`.
- `npm.cmd install`: aprobado.
- `npm.cmd run build`: pendiente por ausencia de `vendor/filament/support/tailwind.config.preset.js`.
- PHPUnit: no reejecutable en este equipo porque faltan PHP, Composer y `vendor`.

Los conteos de pruebas se conservan como resultados aprobados registrados; no se presentan como una nueva ejecución local del 2026-06-19.

## Modelo real

El puente `rag/rag_bridge.py` usa `llama3.2:1b`. Ollama se consulta en `http://localhost:11434/api/generate`.

## Cumplimiento técnico

La inspección y la evidencia automatizada registrada cubren persistencia conversacional, aislamiento, ventana de contexto, prevención de contaminación por errores de herramientas, guardrails, observabilidad y contrato de streaming.

Permanecen fuera de la aprobación automática:

- entrada por micrófono;
- apariencia responsive;
- streaming visible en navegador;
- recuperación después de reiniciar;
- registros reales con Ollama;
- desconexión durante una respuesta;
- build frontend.

## Datos faltantes para preparar el PDF

- Evidencias reales del checklist manual.
- Salida aprobada del build frontend.
- Salidas completas reejecutadas de PHPUnit en un entorno PHP funcional.
- Capturas reales seleccionadas para el informe.
- Portada requerida por la institución: nombre oficial del proyecto, asignatura, docente, grupo, periodo y fecha de entrega.
- Nombres de integrantes, solo si el usuario los proporciona.
- Rúbrica o formato final exigido para ordenar secciones y anexos.
- Reflexiones personales, solo si cada autor las proporciona.

No se genera PDF en esta etapa.
