# Soluciones Edgar

Plataforma web para gestionar trámites y servicios digitales. Sistema con RAG (búsqueda inteligente en base de conocimiento) e IA local para asistencia administrativa.

## ¿Qué problema resuelve?

Soluciones Edgar permite que los usuarios soliciten trámites como actas, servicios SAT, IMSS, Infonavit, vehiculares y más, sin necesidad de acudir físicamente. El administrador gestiona los pedidos, sube resultados y el sistema puede automatizar algunos procesos por API externa. La IA local ayuda a responder preguntas, generar reportes y resumir información del sistema.

## Tecnologías usadas

- **Laravel 11** — Backend PHP
- **Filament v3** — Panel administrativo
- **Livewire Volt** — Autenticación
- **Python** — Sistema RAG
- **ChromaDB** — Base de datos vectorial
- **Ollama** — IA local (modelo llama3.2)
- **SQLite / MySQL** — Base de datos
- **S3 / Local** — Almacenamiento de archivos

## Cómo funciona el RAG

1. El documento `conocimiento_soluciones_edgar.txt` se divide en fragmentos (chunks).
2. Cada fragmento se convierte a vector (embedding) y se guarda en ChromaDB.
3. Cuando el usuario hace una pregunta, se convierte a vector y se buscan fragmentos similares.
4. Los fragmentos se envían como contexto a Ollama, que genera una respuesta.
5. La respuesta se muestra al usuario en el chat.

## Cómo ejecutar Laravel

```bash
cp .env.example .env
# Configurar .env con la base de datos
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Cómo ejecutar la ingesta (solo una vez o cuando cambie el documento)

```bash
cd rag
source venv/bin/activate
python ingestar.py
```

## Cómo probar el RAG desde terminal

```bash
cd rag
source venv/bin/activate
python rag_query.py
# Escribe preguntas como "¿Qué servicios ofrece Soluciones Edgar?"
```

O directamente:

```bash
cd rag
source venv/bin/activate
python rag_bridge.py "¿Qué servicios ofrece Soluciones Edgar?"
```

## Cómo probar el chat desde el sistema

- Abrir en el navegador: `http://localhost:8000/ia-chat`
- O desde el panel Filament: menú "AI Chat" (solo admin)

## Evidencias para el video del entregable

1. Ejecutar `python ingestar.py` — mostrar la ingesta exitosa.
2. Ejecutar `python verificar_chroma.py` — mostrar los fragmentos guardados.
3. Ejecutar `python rag_bridge.py "¿Qué servicios ofrece Soluciones Edgar?"` — mostrar respuesta.
4. Mostrar el chat en `/ia-chat` funcionando con una pregunta.
5. Ejecutar `python metricas.py` — mostrar la tabla de métricas.
6. Mostrar el archivo `rag/preguntas_prueba.txt` con las preguntas recomendadas.
7. Mostrar que `.env` no está en el repositorio (`.gitignore` lo excluye).
