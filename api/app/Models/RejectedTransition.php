<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RejectedTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'expected_state',
        'actual_state',
        'attempted_to',
        'agent_id',
        'reason_code',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
