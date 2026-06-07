# Soluciones Edgar

Plataforma web para gestionar trámites y servicios digitales. Sistema con RAG (búsqueda inteligente en base de conocimiento) e IA local para asistencia administrativa.

## ¿Qué problema resuelve?

Soluciones Edgar permite que los usuarios soliciten trámites como actas, servicios SAT, IMSS, Infonavit, vehiculares y más, sin necesidad de acudir físicamente. El administrador gestiona los pedidos, sube resultados y el sistema puede automatizar algunos procesos por API externa. La IA local ayuda a responder preguntas, generar reportes y resumir información del sistema.

## Tecnologías usadas

- **Laravel 12** — Backend PHP
- **Filament v3** — Panel administrativo
- **Livewire Volt** — Autenticación
- **Python** — Sistema RAG
- **ChromaDB** — Base de datos vectorial
- **Ollama** — IA local (modelo llama3.2)
- **PostgreSQL / SQLite** — Base de datos
- **S3 (Cloudflare R2) / Local** — Almacenamiento de archivos

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

## Comandos administrativos soportados por la IA

Cuando el administrador escribe en el chat, el sistema detecta automáticamente si es un comando administrativo (acción sobre datos reales) o una pregunta RAG (consulta sobre la base de conocimiento).

### Comandos disponibles

| Comando | Ejemplo | Acción |
|---------|---------|--------|
| Reporte de cierre | `reporte de cierre` | Genera archivo .txt descargable con estadística de pedidos |
| Reporte del último trámite | `genera un reporte del último trámite` | Reporte con solo el pedido más reciente |
| Pedidos fallidos | `pedidos fallidos` | Lista pedidos con error de API |
| Revisión manual | `revisión manual` | Lista pedidos pendientes de revisión |
| Completados hoy | `completados hoy` | Resumen de pedidos completados el día de hoy |
| Pedidos pendientes | `pedidos pendientes` | Lista todos los pedidos en estado pendiente |
| Crear trámite/pedido | `Crea un trámite de Acta de Nacimiento para Luis Alfonso con CURP EXPL050202HYNKCSA0` | Crea un pedido REAL en la base de datos |
| Consultar servicios | `qué servicios hay` o `consultar servicio [nombre]` | Lista servicios o muestra detalle de uno |
| Consultar usuario | `consultar usuario [email]` | Muestra datos del usuario |

### Requisitos para reportes descargables

Los reportes .txt se generan en `storage/app/public/reports/` y se sirven mediante la URL pública `/storage/reports/...`. Para que funcionen correctamente:

```bash
# Crear el enlace simbólico (obligatorio para servir archivos)
php artisan storage:link
```

Si el enlace no abre:
1. Verifica que el symlink existe: `ls -la public/storage`
2. Re-ejecuta: `php artisan storage:link`
3. Verifica que el archivo existe: `ls -la storage/app/public/reports/`

## Cómo ejecutar las pruebas

```bash
# Ejecutar todas las pruebas
php artisan test

# Ejecutar solo pruebas del chat administrativo
php artisan test --filter=AdminChatCommandTest
```

## Archivos de prueba manual

- `rag/preguntas_prueba_admin.txt` — Escenarios de prueba para comandos administrativos
- `rag/preguntas_prueba.txt` — Preguntas generales para el RAG

## Evidencias para el video del entregable

1. Ejecutar `python ingestar.py` — mostrar la ingesta exitosa.
2. Ejecutar `python verificar_chroma.py` — mostrar los fragmentos guardados.
3. Ejecutar `python rag_bridge.py "¿Qué servicios ofrece Soluciones Edgar?"` — mostrar respuesta.
4. Mostrar el chat en `/ia-chat` funcionando con una pregunta.
5. Ejecutar `python metricas.py` — mostrar la tabla de métricas.
6. Mostrar el archivo `rag/preguntas_prueba.txt` con las preguntas recomendadas.
7. Mostrar que `.env` no está en el repositorio (`.gitignore` lo excluye).
8. **Prueba de reporte descargable**: ejecutar `reporte de cierre` en el chat y abrir el enlace.
9. **Prueba de creación de trámite**: ejecutar `Crea un trámite de Acta de Nacimiento para Luis Alfonso con CURP EXPL050202HYNKCSA0` y verificar que el pedido aparece en `/admin/orders`.
10. **Prueba de servicio inexistente**: ejecutar `Crea un trámite de ServicioInexistente123` y verificar que sugiere servicios similares.
11. **Prueba de datos faltantes**: ejecutar `Crea un trámite` y verificar que pide completar datos.
12. **Ejecutar pruebas unitarias**: `php artisan test --filter=AdminChatCommandTest` — deben pasar 19 pruebas.
13. **Validar comandos de PowerShell** (Windows):
    ```powershell
    # Verificar symlink
    ls public/storage
    
    # Verificar reportes generados
    ls storage/app/public/reports/
    
    # Verificar pedidos del chat
    php artisan tinker --execute="\App\Models\Order::where('input_data->creado_por_chat', true)->get();"
    
    # Limpiar caché después de cambios
    php artisan optimize:clear
    ```
