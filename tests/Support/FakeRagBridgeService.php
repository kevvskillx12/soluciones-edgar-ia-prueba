<?php

namespace Tests\Support;

use App\Services\AI\RagBridgeService;

/**
 * Fake determinista para pruebas. No invoca Python, Ollama ni servicios externos.
 */
class FakeRagBridgeService implements RagBridgeService
{
    public const MODE_SUCCESS = 'success';

    public const MODE_ERROR = 'error';

    public const MODE_EMPTY = 'empty';

    public int $invocationCount = 0;

    public string $mode = self::MODE_SUCCESS;

    /** @var list<string> */
    public array $tokens = ['Token ', 'determinista.'];

    public function stream(string $query, callable $onChunk): bool
    {
        $this->invocationCount++;

        if ($this->mode === self::MODE_EMPTY) {
            return true;
        }

        if ($this->mode === self::MODE_ERROR) {
            $onChunk(['error' => 'simulated bridge error']);

            return false;
        }

        foreach ($this->tokens as $token) {
            $onChunk(['token' => $token]);
        }

        return true;
    }
}
