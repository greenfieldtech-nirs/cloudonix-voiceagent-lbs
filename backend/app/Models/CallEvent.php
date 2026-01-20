<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallEvent extends Model
{
    protected $fillable = [
        'tenant_id',
        'call_session_id',
        'event_type',
        'event_id',
        'payload',
        'headers',
        'occurred_at',
        'processed_at',
        'processing_status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this call event.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the call session this event belongs to.
     */
    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }
}
