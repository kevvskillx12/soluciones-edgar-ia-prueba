<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiObservabilityLog extends Model
{
    protected $fillable = [
        'session_id',
        'timestamp',
        'user_prompt',
        'system_response',
        'ttft_ms',
        'total_latency_ms',
        'tokens_per_second',
        'was_blocked',
        'tools_executed',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'was_blocked' => 'boolean',
        'tools_executed' => 'array',
        'ttft_ms' => 'float',
        'total_latency_ms' => 'float',
        'tokens_per_second' => 'float',
    ];
}
