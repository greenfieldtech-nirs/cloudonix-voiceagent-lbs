<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookAudit extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_type',
        'session_token',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Common webhook event types
     */
    public const EVENT_TYPES = [
        'voice.application.request',
        'voice.session.update',
        'voice.session.cdr',
        'voice.dial.result',
        'voice.record.result',
    ];

    /**
     * Get the tenant that owns this audit record
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to specific event type
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to specific session
     */
    public function scopeSessionToken($query, string $sessionToken)
    {
        return $query->where('session_token', $sessionToken);
    }

    /**
     * Scope to date range
     */
    public function scopeProcessedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('processed_at', [$startDate, $endDate]);
    }

    /**
     * Get payload as formatted JSON
     */
    public function getFormattedPayload(): string
    {
        return json_encode($this->payload, JSON_PRETTY_PRINT);
    }

    /**
     * Check if processing was successful
     */
    public function isProcessedSuccessfully(): bool
    {
        // Could be extended with error flags
        return $this->processed_at !== null;
    }

    /**
     * Get processing duration in seconds
     */
    public function getProcessingDuration(): ?float
    {
        if (!$this->processed_at || !$this->created_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->processed_at);
    }
}
