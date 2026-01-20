<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecord extends Model
{
    protected $fillable = [
        'tenant_id',
        'session_token',
        'direction',
        'from_number',
        'to_number',
        'agent_id',
        'group_id',
        'status',
        'start_time',
        'end_time',
        'duration',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
    ];

    /**
     * Call directions
     */
    public const DIRECTIONS = [
        'inbound',
        'outbound',
    ];

    /**
     * Call statuses
     */
    public const STATUSES = [
        'queued',
        'ringing',
        'in_progress',
        'completed',
        'busy',
        'failed',
        'no_answer',
    ];

    /**
     * Get the tenant that owns this record
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the agent for this call
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'agent_id');
    }

    /**
     * Get the group for this call
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AgentGroup::class, 'group_id');
    }

    /**
     * Scope to specific direction
     */
    public function scopeDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope to specific status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }

    /**
     * Scope to specific agent
     */
    public function scopeAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope to specific group
     */
    public function scopeGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    /**
     * Calculate duration if not set
     */
    public function getDurationAttribute($value): ?int
    {
        if ($value !== null) {
            return $value;
        }

        if ($this->start_time && $this->end_time) {
            return $this->start_time->diffInSeconds($this->end_time);
        }

        return null;
    }

    /**
     * Check if call is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'busy', 'failed', 'no_answer']);
    }

    /**
     * Check if call was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get status display name
     */
    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            'queued' => 'Queued',
            'ringing' => 'Ringing',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'busy' => 'Busy',
            'failed' => 'Failed',
            'no_answer' => 'No Answer',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Get direction display name
     */
    public function getDirectionDisplayName(): string
    {
        return match($this->direction) {
            'inbound' => 'Inbound',
            'outbound' => 'Outbound',
            default => ucfirst($this->direction),
        };
    }
}
