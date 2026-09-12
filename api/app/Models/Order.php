<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'lead_id',
        'product_sku',
        'product_name',
        'amount_minor',
        'currency',
        'lead_snapshot',
        'pricing_snapshot',
        'payment_status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'lead_snapshot' => 'array',
            'pricing_snapshot' => 'array',
            'payment_status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'version' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function uniqueIds(): array
    {
        return ['id'];
    }
}
