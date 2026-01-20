<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent Group Membership Model
 *
 * Represents the many-to-many relationship between AgentGroups and VoiceAgents
 * with additional metadata like priority and capacity.
 */
class AgentGroupMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_group_id',
        'voice_agent_id',
        'priority',
        'capacity',
    ];

    protected $casts = [
        'priority' => 'integer',
        'capacity' => 'integer',
    ];

    /**
     * Get the agent group this membership belongs to
     */
    public function agentGroup(): BelongsTo
    {
        return $this->belongsTo(AgentGroup::class);
    }

    /**
     * Get the voice agent this membership belongs to
     */
    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class);
    }

    /**
     * Scope to order by priority (highest first)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Scope to order by creation time (oldest first for stability)
     */
    public function scopeByCreationTime($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Scope to filter by priority level
     */
    public function scopeWithPriority($query, int $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Check if this membership has unlimited capacity
     */
    public function hasUnlimitedCapacity(): bool
    {
        return $this->capacity === null;
    }

    /**
     * Get effective capacity (null means unlimited)
     */
    public function getEffectiveCapacity(): ?int
    {
        return $this->capacity;
    }

    /**
     * Validate priority value
     */
    public static function validatePriority(int $priority): bool
    {
        return $priority >= 1 && $priority <= 100;
    }

    /**
     * Validate capacity value
     */
    public static function validateCapacity(?int $capacity): bool
    {
        return $capacity === null || ($capacity >= 1 && $capacity <= 1000);
    }
}