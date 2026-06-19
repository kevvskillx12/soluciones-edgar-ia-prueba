<?php

namespace App\Services\AI;

/**
 * Contrato para el puente hacia el script Python/RAG.
 *
 * En producción usa PythonRagBridgeService.
 * En tests se sustituye por FakeRagBridgeService.
 */
interface RagBridgeService
{
    /**
     * Ejecuta la consulta y llama $onChunk por cada token recibido.
     *
     * @param  string    $query           Pregunta con contexto
     * @param  callable  $onChunk         function(array $data): void
     *                                   $data puede tener: token|respuesta|status|error
     * @return bool  true si el proceso terminó con éxito
     */
    public function stream(string $query, callable $onChunk): bool;
}
