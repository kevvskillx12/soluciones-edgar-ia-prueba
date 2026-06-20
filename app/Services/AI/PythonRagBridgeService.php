<?php

namespace App\Services\AI;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

/**
 * Implementación real: lanza el script Python rag_bridge.py como proceso hijo
 * y entrega cada chunk via callback conforme llegan del stdout.
 */
class PythonRagBridgeService implements RagBridgeService
{
    private ?string $pythonPath;
    private string $scriptPath;

    public function __construct()
    {
        $this->pythonPath = $this->resolvePythonPath();
        $this->scriptPath = base_path('rag/rag_bridge.py');
    }

    public function stream(string $query, callable $onChunk): bool
    {
        if (!$this->pythonPath || !file_exists($this->scriptPath)) {
            Log::warning('RagBridge: Python o script no disponible', [
                'python' => $this->pythonPath,
                'script' => $this->scriptPath,
            ]);
            return false;
        }

        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        $environment['PYTHONIOENCODING'] = 'utf-8';

        $process = new Process(
            [$this->pythonPath, $this->scriptPath, $query],
            base_path(),
            $environment
        );
        $process->setTimeout(300);
        $process->start();

        $stdoutBuffer = '';
        $receivedContent = false;

        $process->wait(function ($type, $buffer) use ($onChunk, &$stdoutBuffer, &$receivedContent) {
            if ($type === Process::ERR) {
                Log::warning('RagBridge: salida de error de Python', [
                    'stderr' => trim($buffer),
                ]);

                return;
            }

            $stdoutBuffer .= $buffer;

            while (($newlinePosition = strpos($stdoutBuffer, "\n")) !== false) {
                $line = trim(substr($stdoutBuffer, 0, $newlinePosition));
                $stdoutBuffer = substr($stdoutBuffer, $newlinePosition + 1);

                if ($line === '') {
                    continue;
                }

                $data = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('RagBridge: línea JSON inválida', ['line' => $line]);
                    continue;
                }

                $receivedContent = $receivedContent
                    || isset($data['token'])
                    || isset($data['respuesta']);
                $onChunk($data);
            }
        });

        $remainingLine = trim($stdoutBuffer);
        if ($remainingLine !== '') {
            $data = json_decode($remainingLine, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $receivedContent = $receivedContent
                    || isset($data['token'])
                    || isset($data['respuesta']);
                $onChunk($data);
            }
        }

        if (!$process->isSuccessful() || !$receivedContent) {
            Log::warning('RagBridge: proceso sin respuesta útil', [
                'exit_code' => $process->getExitCode(),
                'stderr' => trim($process->getErrorOutput()),
                'python' => $this->pythonPath,
                'script' => $this->scriptPath,
            ]);
        }

        return $process->isSuccessful() && $receivedContent;
    }

    private function resolvePythonPath(): ?string
    {
        $candidates = [
            env('PYTHON_PATH'),
            base_path('rag/venv/Scripts/python.exe'),
            base_path('rag/venv/bin/python'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && file_exists($candidate)) {
                return $candidate;
            }
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            foreach (['where.exe python', 'where.exe python3', 'where.exe py'] as $cmd) {
                $out = trim(@shell_exec($cmd . ' 2>NUL'));
                foreach (preg_split('/\r?\n/', $out) as $path) {
                    if ($path && file_exists($path)) {
                        return $path;
                    }
                }
            }

            $localAppData = getenv('LOCALAPPDATA');
            if ($localAppData) {
                $installedPython = glob($localAppData . '/Programs/Python/Python*/python.exe') ?: [];
                if ($installedPython !== []) {
                    rsort($installedPython);
                    return $installedPython[0];
                }
            }
        } else {
            $out = trim(@shell_exec('command -v python3 2>/dev/null') ?: @shell_exec('command -v python 2>/dev/null'));
            if ($out && file_exists($out)) {
                return $out;
            }
        }

        return null;
    }
}
