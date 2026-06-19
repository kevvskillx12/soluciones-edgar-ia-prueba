# Evidencias pendientes

## Checklist de pruebas manuales

- [ ] Entrada por micrófono: conceder permisos, dictar texto, confirmar transcripción editable y enviar el mensaje.
- [ ] Apariencia responsive: revisar móvil, tableta y escritorio sin desbordes, controles ocultos ni texto ilegible.
- [ ] Streaming visible: confirmar que la respuesta aparece incrementalmente y que se muestran los estados del agente.
- [ ] Recuperación después de reiniciar: continuar una conversación persistida con el mismo `conversation_id`.
- [ ] Registros reales con Ollama: ejecutar una consulta contra `llama3.2:1b` y revisar métricas y herramientas en la base de datos.
- [ ] Desconexión durante una respuesta: interrumpir red o proceso y comprobar que una respuesta incompleta no queda registrada como éxito.
- [ ] Build frontend: restaurar dependencias PHP, ejecutar `npm.cmd run build` y conservar la salida aprobada.

## Evidencia que debe recopilarse

- Fecha, sistema operativo y navegador de cada prueba manual.
- Pasos ejecutados y resultado esperado/obtenido.
- Capturas reales únicamente cuando se realice la prueba.
- Identificador de conversación usado en recuperación y desconexión.
- Filas reales de `ai_observability_logs` para una consulta con Ollama.
- Salida completa del build frontend aprobado.
- Salida completa de las tres tandas específicas de PHPUnit y de la suite global, cuando el entorno PHP esté disponible.

## Restricciones del cierre

No se inventan capturas, benchmarks, integrantes ni reflexiones personales. Ningún punto de esta lista está marcado como aprobado sin evidencia real.
