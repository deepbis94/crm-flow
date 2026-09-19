<?php

namespace App\Models;

use App\Enums\OutboxStatus;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = [
        'event_id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'next_attempt_at',
        'last_error',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => OutboxStatus::class,
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
