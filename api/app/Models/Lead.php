<?php

namespace App\Models;

use App\Enums\LeadState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'intake_id',
        'source_id',
        'queue_id',
        'assigned_agent_id',
        'state',
        'name',
        'email',
        'phone',
        'campaign_id',
        'form_id',
        'product_line',
        'tags',
        'suspected_duplicate_of',
        'priority',
        'last_touched_at',
        'claimed_at',
        'sla_first_touch_due_at',
        'sla_followup_due_at',
        'sla_status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'state' => LeadState::class,
            'tags' => 'array',
            'last_touched_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sla_first_touch_due_at' => 'datetime',
            'sla_followup_due_at' => 'datetime',
            'priority' => 'integer',
            'version' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(CrmQueue::class, 'queue_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(LeadTransition::class)->orderBy('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->orderBy('created_at');
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }
}
