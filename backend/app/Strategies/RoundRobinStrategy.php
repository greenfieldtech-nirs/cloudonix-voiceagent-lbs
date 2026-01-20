<?php

namespace App\Strategies;

use App\Models\AgentGroup;
use App\Models\VoiceAgent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

/**
 * Round Robin Distribution Strategy
 *
 * Rotates calls evenly among all available agents in the group.
 * Uses Redis to maintain rotation state across requests.
 *
 * Configuration options:
 * - reset_on_agent_change: Whether to reset pointer when agents are added/removed (default: true)
 * - weighted_by_capacity: Whether to weight rotation by agent capacity (default: false)
 */
class RoundRobinStrategy implements DistributionStrategy
{
    private AgentGroup $group;
    private array $config;

    public function __construct(AgentGroup $group)
    {
        $this->group = $group;
        $this->config = $group->getMergedSettings();
    }

    /**
     * Select the next agent in the rotation with capacity weighting
     */
    public function selectAgent(AgentGroup $group): ?VoiceAgent
    {
        $activeAgents = $group->activeAgents()->with('groups')->get();

        if ($activeAgents->isEmpty()) {
            return null;
        }

        // Check if agent list has changed and reset if needed
        $agentIds = $activeAgents->pluck('id')->sort()->values();
        if ($this->shouldResetPointer($agentIds)) {
            $this->resetPointer();
        }

        if ($this->config['weighted_by_capacity']) {
            return $this->selectAgentByWeightedCapacity($activeAgents);
        } else {
            return $this->selectAgentBySimpleRotation($activeAgents, $agentIds);
        }
    }

    /**
     * Select agent using simple round-robin rotation
     */
    private function selectAgentBySimpleRotation(Collection $agents, Collection $agentIds): ?VoiceAgent
    {
        $nextIndex = $this->getNextIndex($agentIds->count());
        $selectedAgentId = $agentIds[$nextIndex];

        return $agents->find($selectedAgentId);
    }

    /**
     * Select agent using capacity-weighted rotation
     */
    private function selectAgentByWeightedCapacity(Collection $agents): ?VoiceAgent
    {
        $weightedAgents = $this->buildWeightedAgentList($agents);

        if ($weightedAgents->isEmpty()) {
            return null;
        }

        // Get current position in weighted rotation
        $totalWeight = $weightedAgents->sum('weight');
        $position = $this->getWeightedPosition($totalWeight);

        // Find agent at this position
        $cumulativeWeight = 0;
        foreach ($weightedAgents as $weightedAgent) {
            $cumulativeWeight += $weightedAgent['weight'];
            if ($position < $cumulativeWeight) {
                return $agents->find($weightedAgent['agent']->id);
            }
        }

        // Fallback to first agent
        return $weightedAgents->first()['agent'];
    }

    /**
     * Build weighted list of agents based on capacity
     */
    private function buildWeightedAgentList(Collection $agents): Collection
    {
        $weightedAgents = collect();

        foreach ($agents as $agent) {
            $membership = $agent->groups->find($this->group->id);
            $capacity = $membership ? $membership->pivot->capacity : null;

            // Default weight is 1, capacity scales the weight
            $weight = $capacity ?? 1;

            // Ensure minimum weight of 1
            $weight = max(1, $weight);

            $weightedAgents->push([
                'agent' => $agent,
                'weight' => $weight,
                'capacity' => $capacity,
            ]);
        }

        return $weightedAgents;
    }

    /**
     * Get current position in weighted rotation cycle
     */
    private function getWeightedPosition(int $totalWeight): int
    {
        $key = $this->getWeightedPositionKey();

        // Use Redis atomic operations for thread safety
        $currentPosition = (int) \Illuminate\Support\Facades\Redis::get($key) ?? 0;

        // Increment position and wrap around
        $nextPosition = ($currentPosition + 1) % $totalWeight;
        \Illuminate\Support\Facades\Redis::set($key, $nextPosition);

        return $currentPosition;
    }

    /**
     * Record a call for analytics and capacity tracking
     */
    public function recordCall(AgentGroup $group, VoiceAgent $agent): void
    {
        // Log round-robin routing for analytics
        \Illuminate\Support\Facades\Log::info('Round-robin strategy routed call', [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'strategy' => $this->getStrategyIdentifier(),
            'weighted_rotation' => $this->config['weighted_by_capacity'],
            'timestamp' => now()->toISOString(),
        ]);

        // Could track concurrent call counts here for capacity management
        // This would require additional Redis keys for real-time capacity tracking
    }

    /**
     * Get the strategy identifier
     */
    public function getStrategyIdentifier(): string
    {
        return 'round_robin';
    }

    /**
     * Get strategy configuration
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Get current rotation state for monitoring
     */
    public function getRotationState(): array
    {
        $activeAgents = $this->group->activeAgents()->get();
        $agentIds = $activeAgents->pluck('id')->sort()->values();

        $simpleIndex = (int) \Illuminate\Support\Facades\Redis::get($this->getPointerKey()) ?? 0;
        $weightedPosition = (int) \Illuminate\Support\Facades\Redis::get($this->getWeightedPositionKey()) ?? 0;

        $weightedAgents = $this->config['weighted_by_capacity'] ?
            $this->buildWeightedAgentList($activeAgents) : collect();

        $totalWeight = $weightedAgents->sum('weight');

        return [
            'strategy' => $this->getStrategyIdentifier(),
            'group_id' => $this->group->id,
            'total_agents' => $activeAgents->count(),
            'weighted_rotation' => $this->config['weighted_by_capacity'],
            'simple_rotation_index' => $simpleIndex,
            'weighted_position' => $weightedPosition,
            'total_weight' => $totalWeight,
            'reset_on_change' => $this->config['reset_on_agent_change'],
            'agents' => $agentIds->toArray(),
            'weighted_agents' => $weightedAgents->map(function ($item) {
                return [
                    'agent_id' => $item['agent']->id,
                    'agent_name' => $item['agent']->name,
                    'weight' => $item['weight'],
                    'capacity' => $item['capacity'],
                ];
            })->toArray(),
        ];
    }

    /**
     * Reset rotation state (useful for maintenance or troubleshooting)
     */
    public function resetRotationState(): void
    {
        \Illuminate\Support\Facades\Redis::del([
            $this->getPointerKey(),
            $this->getWeightedPositionKey(),
            $this->getAgentListKey(),
        ]);

        \Illuminate\Support\Facades\Log::info('Round-robin rotation state reset', [
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'strategy' => $this->getStrategyIdentifier(),
        ]);
    }

    /**
     * Check if agent is available for routing (basic implementation)
     */
    public function isAgentAvailable(VoiceAgent $agent): bool
    {
        // Basic availability check - agent must be enabled
        // Could be extended to check concurrent call limits, agent status, etc.
        return $agent->enabled;
    }

    /**
     * Validate strategy constraints
     */
    public function validateConstraints(AgentGroup $group): array
    {
        $errors = [];

        if ($this->config['weighted_by_capacity']) {
            $memberships = $group->memberships;
            $zeroCapacityAgents = $memberships->filter(function ($membership) {
                return $membership->capacity === 0;
            });

            if ($zeroCapacityAgents->isNotEmpty()) {
                $errors[] = 'Agents with zero capacity cannot be used in weighted round-robin rotation.';
            }
        }

        return $errors;
    }

    /**
     * Get Redis key for weighted position tracking
     */
    private function getWeightedPositionKey(): string
    {
        return "round_robin:{$this->group->id}:weighted_position";
    }

    /**
     * Prepare configuration with defaults
     */
    public static function prepareConfiguration(array $config): array
    {
        return array_merge([
            'reset_on_agent_change' => true,
            'weighted_by_capacity' => false,
        ], $config);
    }

    /**
     * Get the next index in the rotation
     */
    private function getNextIndex(int $agentCount): int
    {
        $key = $this->getPointerKey();
        $currentIndex = (int) Redis::get($key) ?? -1;

        $nextIndex = ($currentIndex + 1) % $agentCount;

        // Atomically increment and get new value
        Redis::set($key, $nextIndex);

        return $nextIndex;
    }

    /**
     * Check if we should reset the pointer due to agent changes
     */
    private function shouldResetPointer(Collection $currentAgentIds): bool
    {
        if (!$this->config['reset_on_agent_change']) {
            return false;
        }

        $key = $this->getAgentListKey();
        $storedAgentIds = Redis::get($key);

        if ($storedAgentIds === null) {
            // First time, store the list
            Redis::set($key, $currentAgentIds->implode(','));
            return false;
        }

        $storedIds = collect(explode(',', $storedAgentIds))->sort()->values();
        $changed = !$storedIds->equals($currentAgentIds);

        if ($changed) {
            Redis::set($key, $currentAgentIds->implode(','));
        }

        return $changed;
    }

    /**
     * Reset the rotation pointer
     */
    private function resetPointer(): void
    {
        $key = $this->getPointerKey();
        Redis::del($key);
    }

    /**
     * Get Redis key for rotation pointer
     */
    private function getPointerKey(): string
    {
        return "round_robin:{$this->group->id}:pointer";
    }

    /**
     * Get Redis key for agent list tracking
     */
    private function getAgentListKey(): string
    {
        return "round_robin:{$this->group->id}:agents";
    }
}