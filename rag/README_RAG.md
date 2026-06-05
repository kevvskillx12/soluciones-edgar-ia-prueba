# Documentación del Sistema RAG — Soluciones Edgar

## Archivo de conocimiento usado

- `conocimiento_soluciones_edgar.txt` (versión 4.0)
- Contiene información del sistema: servicios, pedidos, saldo, depósitos, usuarios, administradores, tecnologías, API externa, reportes y justificación de IA.

## Proceso de limpieza

1. Se eliminan líneas de separación (`---`).
2. Se saltan líneas vacías consecutivas.
3. Se eliminan comentarios del archivo (líneas que empiezan con `#` que no sean encabezados de sección).
4. Se conservan solo líneas con contenido útil.

## Estrategia de chunking

- Tamaño de chunk: **1200 caracteres**.
- Se utiliza división por secciones (`## ` encabezados) para mantener coherencia temática.
- Cada sección se fragmenta en bloques de hasta 1200 caracteres.
- Se evita cortar palabras: si el corte cae dentro de una palabra, se retrocede al último espacio (si está después del 70% del chunk).
- Los chunks menores a 50 caracteres se descartan.

## Overlap usado

- **200 caracteres** de solapamiento entre fragmentos consecutivos.
- Esto permite que el contexto no se pierda entre chunks y mejora la recuperación.

## Base vectorial usada

- **ChromaDB** (persistente, almacenada en `rag/chroma_db/`).
- Colección: `soluciones_edgar`.
- Métrica de distancia: **coseno** (`hnsw:space: cosine`).
- Embeddings generados automáticamente por ChromaDB con `all-MiniLM-L6-v2`.

## Modelo de embeddings

- `all-MiniLM-L6-v2` (usado internamente por ChromaDB para convertir texto a vectores).

## Modelo de Ollama

- Por defecto: `llama3.2:1b` (funciona en CPU).
- Recomendado (si hay recursos): `llama3.2:3b`.
- Configurable mediante variable de entorno `OLLAMA_MODEL`.

## Flujo: pregunta → respuesta

1. El usuario escribe una pregunta en el chat (`/ia-test` o `/ia-chat`).
2. Laravel recibe la pregunta y la envía a `rag_bridge.py` vía `shell_exec`.
3. `rag_bridge.py` recibe la pregunta y busca en ChromaDB los fragmentos más similares (top 12).
4. ChromaDB convierte la pregunta a embedding y calcula similitud del coseno.
5. Los fragmentos recuperados se envían como contexto a Ollama junto con la pregunta.
6. Ollama genera una respuesta basada únicamente en el contexto proporcionado.
7. La respuesta se devuelve como JSON a Laravel, que la muestra al usuario.

## Limitaciones actuales

- El modelo `llama3.2:1b` es pequeño y puede dar respuestas imprecisas en preguntas complejas.
- Las métricas son heurísticas (no se usa RAGAS).
- La ingesta debe re-ejecutarse manualmente si cambia el documento de conocimiento.
- No hay control de versiones de los embeddings.
- No hay feedback loop para mejorar recuperación.

## Mejoras futuras

- Actualizar a `llama3.2:3b` o `llama3.2:8b` para mejorar calidad de respuestas.
- Implementar RAGAS para métricas formales.
- Automatizar la re-ingesta cuando el documento cambie.
- Agregar reranking de fragmentos recuperados.
- Implementar cache de consultas frecuentes.
- Agregar evaluación humana de respuestas.
