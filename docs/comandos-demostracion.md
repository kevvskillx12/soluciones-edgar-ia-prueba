# Comandos de demostración y reproducción

Ejecutar desde la raíz del repositorio.

## Preparación inicial

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
if (-not (Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File }
php artisan migrate --seed
npm.cmd install
```

En sistemas donde `npm` no esté bloqueado por PowerShell, `npm install` es equivalente.

## RAG y Ollama

El modelo configurado es exactamente `llama3.2:1b`.

```powershell
ollama serve
ollama pull llama3.2:1b
python -m venv rag/venv
.\rag\venv\Scripts\python.exe -m pip install chromadb requests
.\rag\venv\Scripts\python.exe .\rag\ingestar.py
```

`ollama serve` debe permanecer activo. La ingesta solo debe repetirse si cambia la base de conocimiento o no existe la colección.

## Iniciar el proyecto

Usar terminales separadas:

```powershell
php artisan serve
```

```powershell
php artisan queue:listen --tries=1 --timeout=0
```

```powershell
npm.cmd run dev
```

Con Ollama activo y la colección RAG preparada, abrir la URL mostrada por `php artisan serve`.

## Build frontend

```powershell
npm.cmd run build
```

En el entorno auditado el build queda pendiente hasta ejecutar `composer install`, porque Tailwind requiere `vendor/filament/support/tailwind.config.preset.js`.

## Pruebas específicas

Los comandos solicitados son:

```powershell
vendor/bin/phpunit tests/Feature/Week5VerificationTest.php --testdox
vendor/bin/phpunit tests/Feature/Week4RequirementsTest.php tests/Feature/ConversationMemoryTest.php --testdox
vendor/bin/phpunit tests/Feature/AiChatFlowTest.php --testdox
```

En PowerShell/Windows, si el wrapper Unix no se ejecuta directamente:

```powershell
php vendor/bin/phpunit tests/Feature/Week5VerificationTest.php --testdox
php vendor/bin/phpunit tests/Feature/Week4RequirementsTest.php tests/Feature/ConversationMemoryTest.php --testdox
php vendor/bin/phpunit tests/Feature/AiChatFlowTest.php --testdox
```

Resultado de cierre registrado: 19 pruebas específicas y 149 aserciones específicas aprobadas.

## Suite global

```powershell
php vendor/bin/phpunit --testdox
```

Resultado de cierre registrado: 53 de 57 pruebas aprobadas y cuatro fallos globales preexistentes.
