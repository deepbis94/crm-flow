<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaBreach extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'queue_id',
        'type',
        'notified_supervisor_id',
        'escalated_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'escalated_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(CrmQueue::class, 'queue_id');
    }
}
