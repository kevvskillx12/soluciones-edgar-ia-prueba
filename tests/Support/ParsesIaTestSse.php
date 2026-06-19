<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

trait ParsesIaTestSse
{
    /**
     * @return array{
     *     content: string,
     *     conversation_id: ?string,
     *     respuesta: string,
     *     success: bool,
     *     done: ?array,
     *     data_event_count: int,
     *     token_event_count: int
     * }
     */
    protected function parseIaTestSse(TestResponse $response): array
    {
        $response->assertOk();
        $response->assertStreamed();
        $this->assertStringContainsString(
            'text/event-stream',
            (string) $response->headers->get('Content-Type')
        );

        $content = $response->streamedContent();
        $tokens = [];
        $conversationId = null;
        $doneEvent = null;
        $dataEventCount = 0;
        $tokenEventCount = 0;

        foreach (explode("\n", $content) as $line) {
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }
            $dataEventCount++;
            $data = json_decode(substr($line, 6), true);
            if (! is_array($data)) {
                continue;
            }
            if (($data['type'] ?? null) === 'token') {
                $tokenEventCount++;
                $tokens[] = $data['token'] ?? '';
            }
            if (($data['type'] ?? null) === 'done') {
                $doneEvent = $data;
                $conversationId = $data['conversation_id'] ?? null;
            }
        }

        return [
            'content' => $content,
            'conversation_id' => $conversationId,
            'respuesta' => implode('', $tokens),
            'success' => true,
            'done' => $doneEvent,
            'data_event_count' => $dataEventCount,
            'token_event_count' => $tokenEventCount,
        ];
    }
}
