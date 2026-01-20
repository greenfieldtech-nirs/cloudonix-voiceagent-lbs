<?php

namespace App\Strategies;

use App\Models\AgentGroup;
use App\Models\VoiceAgent;
use Illuminate\Support\Collection;

/**
 * Priority Distribution Strategy
 *
 * Routes calls to the highest priority agent first, with failover to lower priority agents.
 * Agents are ordered by their priority value in the group membership.
 *
 * Configuration options:
 * - failover_enabled: Whether to failover to lower priority agents (default: true)
 * - round_robin_same_priority: Whether to rotate among agents with same priority (default: true)
 */
class PriorityStrategy implements DistributionStrategy
{
    private AgentGroup $group;
    private array $config;

    public function __construct(AgentGroup $group)
    {
        $this->group = $group;
        $this->config = $group->getMergedSettings();
    }

    /**
     * Select the highest priority available agent
     */
    public function selectAgent(AgentGroup $group): ?VoiceAgent
    {
        $activeAgents = $group->activeAgents()->with('groups')->get();

        if ($activeAgents->isEmpty()) {
            return null;
        }

        // Get agents ordered by priority (highest first)
        $prioritizedAgents = $this->getPrioritizedAgents($activeAgents);

        // Return the first available agent
        return $prioritizedAgents->first();
    }

    /**
     * Record a call (no-op for priority strategy)
     */
    public function recordCall(AgentGroup $group, VoiceAgent $agent): void
    {
        // Priority strategy doesn't track metrics, no action needed
    }

    /**
     * Get the strategy identifier
     */
    public function getStrategyIdentifier(): string
    {
        return 'priority';
    }

    /**
     * Get strategy configuration
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Get agents ordered by priority with failover logic
     */
    private function getPrioritizedAgents(Collection $agents): Collection
    {
        $prioritized = collect();

        // Group agents by priority level
        $agentsByPriority = $agents->groupBy(function ($agent) {
            return $agent->groups->find($this->group->id)?->pivot?->priority ?? 50;
        })->sortKeysDesc(); // Highest priority first

        foreach ($agentsByPriority as $priority => $priorityAgents) {
            if ($this->config['round_robin_same_priority']) {
                // Rotate among agents with same priority
                $prioritized = $prioritized->merge($this->rotateAgents($priorityAgents));
            } else {
                // Keep original order for same priority
                $prioritized = $prioritized->merge($priorityAgents);
            }
        }

        return $prioritized;
    }

    /**
     * Rotate agents within the same priority level
     * This is a simple implementation - in production you'd want to persist rotation state
     */
    private function rotateAgents(Collection $agents): Collection
    {
        if ($agents->count() <= 1) {
            return $agents;
        }

        // For now, just randomize the order
        // In a real implementation, you'd track the last used agent per priority level
        return $agents->shuffle();
    }
}