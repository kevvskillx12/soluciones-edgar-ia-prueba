<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

class ConversationMemoryService
{
    public function startOrResume(?string $conversationId, $user = null, string $channel = 'admin_chat'): AiConversation
    {
        if ($conversationId) {
            $conversation = AiConversation::with('messages')->where('conversation_id', $conversationId)->first();
            if ($conversation) {
                return $conversation;
            }
        }

        $conversation = AiConversation::create([
            'conversation_id' => $conversationId ?? 'conv_' . uniqid(),
            'user_id' => $user ? $user->id : null,
            'channel' => $channel,
        ]);

        return $conversation->load('messages');
    }

    public function addUserMessage(AiConversation $conversation, string $content): void
    {
        $this->saveMessage($conversation, 'user', $content);
    }

    public function addAssistantMessage(AiConversation $conversation, string $content, array $metadata = []): void
    {
        $this->saveMessage($conversation, 'assistant', $content, $metadata);
    }

    public function addToolMessage(AiConversation $conversation, string $toolName, string $content, string $status = 'success'): void
    {
        $this->saveMessage($conversation, 'tool', $content, [
            'tool_name' => $toolName,
            'tool_status' => $status,
        ]);
    }

    public function getPromptBuffer(AiConversation $conversation, int $maxMessages = 20): array
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit($maxMessages)
            ->get()
            ->reverse()
            ->values();

        $buffer = [];

        if ($conversation->summary) {
            $buffer[] = ['role' => 'system', 'content' => "Resumen de la conversación: {$conversation->summary}"];
        }

        foreach ($messages as $message) {
            $buffer[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $buffer;
    }

    public function estimateTokens(string $text): int
    {
        return (int) (strlen($text) / 4);
    }

    public function shouldSummarize(AiConversation $conversation): bool
    {
        $totalTokens = $conversation->messages()->sum('token_estimate') ?? 0;
        return $totalTokens > 1000;
    }

    public function updateSummary(AiConversation $conversation): void
    {
        $messages = $conversation->messages()
            ->where('role', 'user')
            ->orWhere('role', 'assistant')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        $summary = $this->generateSummary($messages->pluck('content')->toArray());

        $conversation->update(['summary' => $summary]);
    }

    public function getPendingOrderState(AiConversation $conversation): ?array
    {
        $metadata = $conversation->metadata ?? [];
        return $metadata['pending_order'] ?? null;
    }

    public function savePendingOrderState(AiConversation $conversation, array $state): void
    {
        $metadata = $conversation->metadata ?? [];
        $metadata['pending_order'] = $state;
        $conversation->update(['metadata' => $metadata]);
    }

    public function clearPendingOrderState(AiConversation $conversation): void
    {
        $metadata = $conversation->metadata ?? [];
        unset($metadata['pending_order']);
        $conversation->update(['metadata' => $metadata]);
    }

    public function createOrderFromMemory(AiConversation $conversation): ?array
    {
        $pendingOrder = $this->getPendingOrderState($conversation);
        if (!$pendingOrder) {
            return null;
        }

        $service = Service::find($pendingOrder['service_id']);
        if (!$service) {
            return null;
        }

        return [
            'service' => $service,
            'input_data' => $pendingOrder['input_data'],
            'service_id' => $pendingOrder['service_id'],
        ];
    }

    private function saveMessage(AiConversation $conversation, string $role, string $content, array $metadata = []): void
    {
        $tokenEstimate = $this->estimateTokens($content);

        $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'tool_name' => $metadata['tool_name'] ?? null,
            'tool_status' => $metadata['tool_status'] ?? null,
            'token_estimate' => $tokenEstimate,
            'metadata' => $metadata,
        ]);

        if ($this->shouldSummarize($conversation)) {
            $this->updateSummary($conversation);
        }
    }

    public function generateSummary(array $messages): string
    {
        if (empty($messages)) {
            return '';
        }

        $lastUserMessage = collect($messages)->last(function ($message) {
            return $message['role'] === 'user';
        });

        if ($lastUserMessage) {
            return 'Última pregunta del usuario: ' . substr($lastUserMessage['content'], 0, 200) . '...';
        }

        return 'Resumen de conversación truncado';
    }
}