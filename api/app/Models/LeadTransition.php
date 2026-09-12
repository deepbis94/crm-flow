<?php

namespace App\Models;

use App\Enums\LeadState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'from_state',
        'to_state',
        'agent_id',
        'reason_code',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_state' => LeadState::class,
            'to_state' => LeadState::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
