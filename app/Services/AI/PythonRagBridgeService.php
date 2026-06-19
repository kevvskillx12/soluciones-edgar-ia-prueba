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
    private string $pythonPath;
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

        $command = sprintf(
            'PYTHONIOENCODING=utf-8 %s %s %s 2>&1',
            escapeshellarg($this->pythonPath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($query)
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);
        $process->start();

        $process->wait(function ($type, $buffer) use ($onChunk) {
            foreach (explode("\n", trim($buffer)) as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $data = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $onChunk($data);
                }
            }
        });

        return $process->isSuccessful();
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
            foreach (['where python', 'where python3'] as $cmd) {
                $out = trim(@shell_exec($cmd . ' 2>NUL'));
                foreach (preg_split('/\r?\n/', $out) as $path) {
                    if ($path && file_exists($path)) {
                        return $path;
                    }
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
