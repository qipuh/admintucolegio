<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = ['incidencia', 'consulta', 'mejora', 'capacitacion', 'configuracion'];

    public const PRIORITIES = ['baja', 'media', 'alta', 'urgente'];

    public const STATUSES = ['abierto', 'en_progreso', 'esperando', 'resuelto', 'cerrado'];

    /** Estados que cuentan como pendientes de atención. */
    public const OPEN_STATUSES = ['abierto', 'en_progreso', 'esperando'];

    protected $fillable = [
        'school_id',
        'assigned_to',
        'created_by',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'reporter_name',
        'reporter_contact',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SupportTicketComment::class)->oldest();
    }
}
