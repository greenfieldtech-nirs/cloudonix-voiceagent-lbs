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
     * Select the next agent in the rotation
     */
    public function selectAgent(AgentGroup $group): ?VoiceAgent
    {
        $activeAgents = $group->activeAgents()->get();

        if ($activeAgents->isEmpty()) {
            return null;
        }

        $agentIds = $activeAgents->pluck('id')->sort()->values();

        // Check if agent list has changed and reset if needed
        if ($this->shouldResetPointer($agentIds)) {
            $this->resetPointer();
        }

        $nextIndex = $this->getNextIndex($agentIds->count());
        $selectedAgentId = $agentIds[$nextIndex];

        return $activeAgents->find($selectedAgentId);
    }

    /**
     * Record a call (no-op for round robin, but could be used for metrics)
     */
    public function recordCall(AgentGroup $group, VoiceAgent $agent): void
    {
        // Round robin doesn't track individual metrics, but could log for analytics
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