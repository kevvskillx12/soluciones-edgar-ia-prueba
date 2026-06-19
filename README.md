# Soluciones Edgar IA

Aplicación Laravel con chat de IA local, memoria persistente, RAG con ChromaDB, Ollama, streaming SSE, guardrails y observabilidad.

## Requisitos

- PHP 8.3 o superior y Composer.
- Node.js y npm.
- Python 3.
- Ollama.
- Extensiones PHP declaradas en `composer.json`.

El modelo configurado en `rag/rag_bridge.py` es `llama3.2:1b`.

## Instalación

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
if (-not (Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File }
php artisan migrate --seed
npm.cmd install
```

Preparar el entorno RAG:

```powershell
ollama pull llama3.2:1b
python -m venv rag/venv
.\rag\venv\Scripts\python.exe -m pip install chromadb requests
.\rag\venv\Scripts\python.exe .\rag\ingestar.py
```

## Inicio local

Mantener Ollama activo:

```powershell
ollama serve
```

En terminales separadas:

```powershell
php artisan serve
```

```powershell
php artisan queue:listen --tries=1 --timeout=0
```

```powershell
npm.cmd run dev
```

## Verificación

```powershell
npm.cmd run build
php vendor/bin/phpunit tests/Feature/Week5VerificationTest.php --testdox
php vendor/bin/phpunit tests/Feature/Week4RequirementsTest.php tests/Feature/ConversationMemoryTest.php --testdox
php vendor/bin/phpunit tests/Feature/AiChatFlowTest.php --testdox
php vendor/bin/phpunit --testdox
```

Resultado de cierre registrado:

- 19 pruebas específicas aprobadas.
- 149 aserciones específicas aprobadas.
- 53 de 57 pruebas globales aprobadas.
- Cuatro fallos globales preexistentes.

En la verificación local del 2026-06-19, Node `v24.14.0`, npm `11.9.0` y `npm.cmd install` funcionaron. El build frontend quedó pendiente porque faltaba `vendor/filament/support/tailwind.config.preset.js`. PHPUnit tampoco pudo reejecutarse porque PHP, Composer y `vendor` no estaban disponibles.

## Documentación de cierre

- `docs/reporte-final-cumplimiento.md`
- `docs/matriz-cumplimiento.md`
- `docs/comandos-demostracion.md`
- `docs/consultas-observabilidad.sql`
- `docs/evidencias-pendientes.md`

Las pruebas manuales de micrófono, responsive, streaming visible, recuperación tras reinicio, registros reales con Ollama y desconexión durante respuesta permanecen pendientes. El PDF todavía no se genera.
