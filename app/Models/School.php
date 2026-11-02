<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'api_base_url',
        'logo_url',
        'api_key',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $appends = [
        'has_api_key',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
        'stats' => 'array',
        'health' => 'array',
        'synced_at' => 'datetime',
    ];

    public function getHasApiKeyAttribute(): bool
    {
        return filled($this->getRawOriginal('api_key'));
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
