<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'api_base_url',
        'logo_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
