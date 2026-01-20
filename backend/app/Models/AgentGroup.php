<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AgentGroup extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'strategy',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Supported distribution strategies
     */
    public const STRATEGIES = [
        'load_balanced',
        'priority',
        'round_robin',
    ];

    /**
     * Default settings for strategies
     */
    public const DEFAULT_SETTINGS = [
        'load_balanced' => [
            'window_hours' => 24,
            'fallback_enabled' => true,
        ],
        'priority' => [
            'fallback_enabled' => true,
        ],
        'round_robin' => [
            'fallback_enabled' => true,
        ],
    ];

    /**
     * Get the tenant that owns this group
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the agents in this group
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(VoiceAgent::class, 'agent_group_memberships')
            ->withPivot('priority', 'capacity')
            ->withTimestamps()
            ->orderBy('agent_group_memberships.priority')
            ->orderBy('agent_group_memberships.capacity', 'desc');
    }

    /**
     * Get the call records for this group
     */
    public function callRecords()
    {
        return $this->hasMany(CallRecord::class, 'group_id');
    }

    /**
     * Get enabled agents in this group
     */
    public function enabledAgents()
    {
        return $this->agents()->enabled();
    }

    /**
     * Get strategy display name
     */
    public function getStrategyDisplayName(): string
    {
        return match($this->strategy) {
            'load_balanced' => 'Load Balanced',
            'priority' => 'Priority',
            'round_robin' => 'Round Robin',
            default => ucfirst(str_replace('_', ' ', $this->strategy)),
        };
    }

    /**
     * Get merged settings with defaults
     */
    public function getMergedSettings(): array
    {
        return array_merge(
            self::DEFAULT_SETTINGS[$this->strategy] ?? [],
            $this->settings ?? []
        );
    }

    /**
     * Check if fallback is enabled
     */
    public function hasFallbackEnabled(): bool
    {
        return $this->getMergedSettings()['fallback_enabled'] ?? true;
    }

    /**
     * Get window hours for load balanced strategy
     */
    public function getLoadBalanceWindowHours(): int
    {
        return $this->getMergedSettings()['window_hours'] ?? 24;
    }
}
