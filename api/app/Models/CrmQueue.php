<?php

namespace App\Models;

use App\Enums\AssignmentStrategyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmQueue extends Model
{
    use HasFactory;

    protected $table = 'queues';

    protected $fillable = [
        'team_id',
        'source_id',
        'name',
        'scope',
        'product_line',
        'priority_weight',
        'max_concurrency_per_agent',
        'assignment_strategy',
        'first_touch_sla_seconds',
        'followup_sla_seconds',
        'idle_recycle_seconds',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assignment_strategy' => AssignmentStrategyName::class,
            'is_active' => 'boolean',
            'priority_weight' => 'integer',
            'max_concurrency_per_agent' => 'integer',
            'first_touch_sla_seconds' => 'integer',
            'followup_sla_seconds' => 'integer',
            'idle_recycle_seconds' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'queue_id');
    }
}
