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

        // Get agents ordered by priority with failover logic
        $prioritizedAgents = $this->getPrioritizedAgentsWithFailover($activeAgents);

        // Return the first available agent
        return $prioritizedAgents->first();
    }

    /**
     * Record a call (used for analytics and priority validation)
     */
    public function recordCall(AgentGroup $group, VoiceAgent $agent): void
    {
        // Log priority-based routing for analytics
        \Illuminate\Support\Facades\Log::info('Priority strategy routed call', [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'strategy' => $this->getStrategyIdentifier(),
            'priority_level' => $this->getAgentPriority($agent),
            'timestamp' => now()->toISOString(),
        ]);

        // Could also update agent performance metrics here if needed
    }

    /**
     * Get the priority level for a specific agent
     */
    private function getAgentPriority(VoiceAgent $agent): int
    {
        $membership = $agent->groups->find($this->group->id);
        return $membership ? $membership->pivot->priority : 50;
    }

    /**
     * Validate priority constraints for the group
     */
    public function validateGroupConstraints(AgentGroup $group): array
    {
        $errors = [];

        $memberships = $group->memberships()->with('voiceAgent')->get();

        // Check for duplicate priorities if round-robin is disabled
        if (!$this->config['round_robin_same_priority']) {
            $priorities = $memberships->pluck('priority');
            if ($priorities->duplicates()->isNotEmpty()) {
                $errors[] = 'Duplicate priorities found. Enable round-robin for same priority or use unique priority values.';
            }
        }

        // Validate priority ranges
        $invalidPriorities = $memberships->filter(function ($membership) {
            return !\App\Models\AgentGroupMembership::validatePriority($membership->priority);
        });

        if ($invalidPriorities->isNotEmpty()) {
            $errors[] = 'All priorities must be between 1 and 100.';
        }

        return $errors;
    }

    /**
     * Get priority distribution statistics
     */
    public function getPriorityStatistics(AgentGroup $group): array
    {
        $memberships = $group->memberships()->with('voiceAgent')->get();

        return [
            'total_agents' => $memberships->count(),
            'active_agents' => $memberships->where('voiceAgent.enabled', true)->count(),
            'priority_levels' => $memberships->pluck('priority')->unique()->sort()->values(),
            'agents_by_priority' => $memberships->groupBy('priority')->map->count(),
            'failover_enabled' => $this->config['failover_enabled'],
            'round_robin_enabled' => $this->config['round_robin_same_priority'],
        ];
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
     * Get agents organized by priority levels for UI display
     */
    public function getAgentsByPriority(AgentGroup $group): array
    {
        $memberships = $group->memberships()
            ->with('voiceAgent')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $agentsByPriority = [];

        foreach ($memberships as $membership) {
            $priority = $membership->priority;
            $agent = $membership->voiceAgent;

            if (!isset($agentsByPriority[$priority])) {
                $agentsByPriority[$priority] = [
                    'priority' => $priority,
                    'agents' => [],
                    'rotation_index' => 0, // For round-robin tracking
                ];
            }

            $agentsByPriority[$priority]['agents'][] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'enabled' => $agent->enabled,
                'provider' => $agent->provider->value,
                'capacity' => $membership->capacity,
                'created_at' => $membership->created_at,
            ];
        }

        return array_values($agentsByPriority);
    }

    /**
     * Validate and prepare configuration
     */
    public static function prepareConfiguration(array $config): array
    {
        return array_merge([
            'failover_enabled' => true,
            'round_robin_same_priority' => true,
        ], $config);
    }

    /**
     * Get agents ordered by priority with advanced failover logic
     */
    private function getPrioritizedAgentsWithFailover(Collection $agents): Collection
    {
        $prioritized = collect();

        // Group agents by priority level (highest first)
        $agentsByPriority = $agents->groupBy(function ($agent) {
            return $agent->groups->find($this->group->id)?->pivot?->priority ?? 50;
        })->sortKeysDesc();

        foreach ($agentsByPriority as $priority => $priorityAgents) {
            if ($priorityAgents->isEmpty()) {
                continue;
            }

            if ($this->config['round_robin_same_priority'] && $priorityAgents->count() > 1) {
                // Implement round-robin rotation within priority level
                $rotatedAgents = $this->rotateAgentsInPriorityLevel($priorityAgents, $priority);
                $prioritized = $prioritized->merge($rotatedAgents);
            } else {
                // Use consistent ordering for same priority
                $prioritized = $prioritized->merge($this->orderAgentsByCreationTime($priorityAgents));
            }

            // If failover is disabled, stop after first priority level
            if (!$this->config['failover_enabled']) {
                break;
            }
        }

        return $prioritized;
    }

    /**
     * Rotate agents within the same priority level using Redis for state persistence
     */
    private function rotateAgentsInPriorityLevel(Collection $agents, int $priority): Collection
    {
        if ($agents->count() <= 1) {
            return $agents;
        }

        // Sort agents by ID for consistent ordering
        $sortedAgents = $agents->sortBy('id')->values();
        $agentCount = $sortedAgents->count();

        // Get rotation index from Redis
        $rotationKey = $this->getPriorityRotationKey($priority);
        $currentIndex = (int) \Illuminate\Support\Facades\Redis::get($rotationKey) ?? 0;

        // Rotate the array
        $rotatedAgents = collect([
            ...$sortedAgents->slice($currentIndex),
            ...$sortedAgents->slice(0, $currentIndex)
        ]);

        // Update rotation index for next call
        $nextIndex = ($currentIndex + 1) % $agentCount;
        \Illuminate\Support\Facades\Redis::set($rotationKey, $nextIndex);

        return $rotatedAgents;
    }

    /**
     * Order agents by creation time for consistent fallback ordering
     */
    private function orderAgentsByCreationTime(Collection $agents): Collection
    {
        return $agents->sortBy(function ($agent) {
            $membership = $agent->groups->find($this->group->id);
            return $membership ? $membership->pivot->created_at->timestamp : $agent->created_at->timestamp;
        })->values();
    }

    /**
     * Get Redis key for priority level rotation
     */
    private function getPriorityRotationKey(int $priority): string
    {
        return "priority:{$this->group->id}:level_{$priority}:rotation";
    }
}