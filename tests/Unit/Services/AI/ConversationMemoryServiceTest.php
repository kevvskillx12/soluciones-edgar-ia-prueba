<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\ConversationMemoryService;
use Tests\TestCase;

class ConversationMemoryServiceTest extends TestCase
{
    protected ConversationMemoryService $memoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memoryService = app(ConversationMemoryService::class);
    }

    public function test_estimate_tokens_calculates_correctly(): void
    {
        $text = 'Hello world';
        $estimatedTokens = $this->memoryService->estimateTokens($text);

        $this->assertEquals(2, $estimatedTokens);
    }

    public function test_estimate_tokens_with_empty_text(): void
    {
        $text = '';
        $estimatedTokens = $this->memoryService->estimateTokens($text);

        $this->assertEquals(0, $estimatedTokens);
    }

    public function test_estimate_tokens_with_long_text(): void
    {
        $text = str_repeat('This is a longer text. ', 100);
        $estimatedTokens = $this->memoryService->estimateTokens($text);

        $this->assertGreaterThan(100, $estimatedTokens);
    }

    public function test_generate_summary_returns_empty_string_when_no_messages(): void
    {
        $messages = [];
        $summary = $this->memoryService->generateSummary($messages);

        $this->assertEquals('', $summary);
    }

    public function test_generate_summary_includes_last_user_message(): void
    {
        $messages = [
            ['role' => 'assistant', 'content' => 'Hello'],
            ['role' => 'user', 'content' => 'How are you?'],
        ];

        $summary = $this->memoryService->generateSummary($messages);

        $this->assertStringContainsString('Última pregunta del usuario', $summary);
        $this->assertStringContainsString('How are you?', $summary);
    }

    public function test_generate_summary_truncates_long_messages(): void
    {
        $longMessage = str_repeat('A', 300);
        $messages = [
            ['role' => 'user', 'content' => $longMessage],
        ];

        $summary = $this->memoryService->generateSummary($messages);

        $this->assertLessThanOrEqual(235, strlen($summary));
    }
}