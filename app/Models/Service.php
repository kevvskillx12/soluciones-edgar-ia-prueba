<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'code',
        'name', 
        'description', 
        'price',
        'cost',
        'service_type',
        'schedule_notice',
        'processing_time',
        'image_path',
        'is_active',
        'active_schedule',
        'form_schema',
        // Campos de automatización por API externa
        'automation_type',
        'external_provider',
        'external_service_id',
        'api_handler',
    ];

    protected $casts = [
        'form_schema' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
