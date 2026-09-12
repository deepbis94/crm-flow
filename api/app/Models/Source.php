<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'form_id',
        'campaign_id',
        'api_key_prefix',
        'api_key_hash',
        'rate_limit_per_second',
        'rate_limit_burst',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_limit_per_second' => 'integer',
            'rate_limit_burst' => 'integer',
        ];
    }

    public static function hashApiKey(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function matchesPlainKey(string $plain): bool
    {
        return hash_equals($this->api_key_hash, self::hashApiKey($plain));
    }
}
