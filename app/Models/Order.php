<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'input_data',
        'status',
        'result_file_path',
        'admin_notes',
        'price_at_purchase',
        'service_cost_snapshot',
        'service_price_snapshot',
        'processed_by_api',
        'api_status',
        'api_report',
        'api_error_message',
        'api_processed_at',
        'external_provider',
        'external_order_id',
    ];

    protected $casts = [
        'input_data' => 'array',
        'processed_by_api' => 'boolean',
        'api_processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
