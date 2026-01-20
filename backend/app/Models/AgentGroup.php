<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\DistributionStrategy;

/**
 * Agent Group Model
 *
 * Represents a group of voice agents with a specific distribution strategy.
 * Groups allow for load balancing and routing rules to distribute calls
 * among multiple agents using different algorithms.
 */
class AgentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'strategy',
        'settings',
        'enabled',
    ];

    protected $casts = [
        'strategy' => DistributionStrategy::class,
        'settings' => 'array',
        'enabled' => 'boolean',
    ];

    /**
     * Get the tenant that owns this agent group
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the agents in this group through the membership pivot
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(VoiceAgent::class, 'agent_group_memberships')
            ->withPivot(['priority', 'capacity', 'created_at', 'updated_at'])
            ->withTimestamps()
            ->orderByPivot('priority', 'desc')
            ->orderByPivot('created_at', 'asc');
    }

    /**
     * Get the active agents in this group (enabled agents)
     */
    public function activeAgents(): BelongsToMany
    {
        return $this->agents()->where('voice_agents.enabled', true);
    }

    /**
     * Get the memberships for this group
     */
    public function memberships()
    {
        return $this->hasMany(AgentGroupMembership::class);
    }

    /**
     * Scope to only enabled groups
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to specific strategy
     */
    public function scopeStrategy($query, DistributionStrategy $strategy)
    {
        return $query->where('strategy', $strategy);
    }

    /**
     * Get strategy display name
     */
    public function getStrategyDisplayName(): string
    {
        return $this->strategy->getDisplayName();
    }

    /**
     * Get strategy description
     */
    public function getStrategyDescription(): string
    {
        return $this->strategy->getDescription();
    }

    /**
     * Check if strategy requires ordering
     */
    public function requiresOrdering(): bool
    {
        return $this->strategy->requiresOrdering();
    }

    /**
     * Get merged settings (defaults + custom)
     */
    public function getMergedSettings(): array
    {
        return array_merge(
            $this->strategy->getDefaultSettings(),
            $this->settings ?? []
        );
    }

    /**
     * Validate group settings
     */
    public function validateSettings(): array
    {
        return $this->strategy->validateSettings($this->settings ?? []);
    }

    /**
     * Check if group has any active agents
     */
    public function hasActiveAgents(): bool
    {
        return $this->activeAgents()->exists();
    }

    /**
     * Get count of active agents
     */
    public function getActiveAgentCount(): int
    {
        return $this->activeAgents()->count();
    }

    /**
     * Check if group can be used for routing
     */
    public function canRoute(): bool
    {
        return $this->enabled && $this->hasActiveAgents();
    }

    /**
     * Validate group configuration and constraints
     */
    public function validateGroupSetup(): array
    {
        $errors = [];

        // Basic validation
        if (empty($this->name)) {
            $errors[] = 'Group name is required.';
        }

        if (!$this->strategy) {
            $errors[] = 'Distribution strategy must be selected.';
        }

        // Strategy-specific validation
        $strategyErrors = $this->strategy->validateSettings($this->settings ?? []);
        $errors = array_merge($errors, $strategyErrors);

        // Get strategy instance for additional validation
        try {
            $strategy = $this->getStrategyInstance();
            if (method_exists($strategy, 'validateGroupConstraints')) {
                $constraintErrors = $strategy->validateGroupConstraints($this);
                $errors = array_merge($errors, $constraintErrors);
            }
            if (method_exists($strategy, 'validateConstraints')) {
                $strategyErrors = $strategy->validateConstraints($this);
                $errors = array_merge($errors, $strategyErrors);
            }
        } catch (\Exception $e) {
            $errors[] = 'Failed to validate strategy configuration: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Get strategy statistics for monitoring
     */
    public function getStrategyStats(): array
    {
        try {
            $strategy = $this->getStrategyInstance();

            // Try strategy-specific statistics first
            if (method_exists($strategy, 'getPriorityStatistics')) {
                return $strategy->getPriorityStatistics($this);
            }

            if (method_exists($strategy, 'getRotationState')) {
                return $strategy->getRotationState();
            }

            // Fallback to basic statistics
            return [
                'strategy' => $this->strategy->value,
                'total_agents' => $this->memberships()->count(),
                'active_agents' => $this->getActiveAgentCount(),
                'enabled' => $this->enabled,
                'can_route' => $this->canRoute(),
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get strategy statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reset strategy state (useful for maintenance)
     */
    public function resetStrategyState(): bool
    {
        try {
            $strategy = $this->getStrategyInstance();
            if (method_exists($strategy, 'resetRotationState')) {
                $strategy->resetRotationState();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to reset strategy state', [
                'group_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the distribution strategy instance for this group
     */
    public function getStrategyInstance(): \App\Strategies\DistributionStrategy
    {
        return app('distribution.strategy.factory')->create($this);
    }

    /**
     * Select next agent using the group's strategy
     */
    public function selectNextAgent(): ?VoiceAgent
    {
        if (!$this->canRoute()) {
            return null;
        }

        $strategy = $this->getStrategyInstance();
        return $strategy->selectAgent($this);
    }

    /**
     * Record a call for an agent (for load balancing metrics)
     */
    public function recordCall(VoiceAgent $agent): void
    {
        if (!$this->agents()->where('voice_agent_id', $agent->id)->exists()) {
            return; // Agent not in this group
        }

        $strategy = $this->getStrategyInstance();
        $strategy->recordCall($this, $agent);
    }
}