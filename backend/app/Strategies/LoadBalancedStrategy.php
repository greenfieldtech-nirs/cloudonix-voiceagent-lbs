<?php

namespace App\Strategies;

use App\Models\AgentGroup;
use App\Models\VoiceAgent;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

/**
 * Load Balanced Distribution Strategy
 *
 * Distributes calls based on agent load over a rolling time window.
 * Agents with fewer calls in the recent window receive priority.
 *
 * Configuration options:
 * - window_hours: Number of hours to look back for call counting (default: 24)
 * - max_calls_per_agent: Maximum calls per agent in window (optional, unlimited if null)
 */
class LoadBalancedStrategy implements DistributionStrategy
{
    private AgentGroup $group;
    private array $config;

    public function __construct(AgentGroup $group)
    {
        $this->group = $group;
        $this->config = $group->getMergedSettings();
    }

    /**
     * Select the agent with the least load in the rolling window
     */
    public function selectAgent(AgentGroup $group): ?VoiceAgent
    {
        $activeAgents = $group->activeAgents()->get();

        if ($activeAgents->isEmpty()) {
            return null;
        }

        // Get call counts for each agent in the window
        $agentLoads = $this->getAgentLoads($activeAgents);

        // Find agents with minimum load
        $minLoad = min($agentLoads);
        $availableAgents = collect($agentLoads)
            ->filter(fn($load) => $load === $minLoad)
            ->keys()
            ->map(fn($agentId) => $activeAgents->find($agentId))
            ->filter()
            ->values();

        // If multiple agents have the same load, pick randomly
        return $availableAgents->random();
    }

    /**
     * Record a call for load balancing metrics
     */
    public function recordCall(AgentGroup $group, VoiceAgent $agent): void
    {
        $key = $this->getAgentCallKey($agent->id);
        $windowSeconds = $this->config['window_hours'] * 3600;

        // Add call to sorted set with current timestamp
        Redis::zadd($key, now()->timestamp, uniqid());

        // Remove calls older than window
        $cutoffTime = now()->timestamp - $windowSeconds;
        Redis::zremrangebyscore($key, '-inf', $cutoffTime);

        // Set expiration on the key (window + buffer)
        Redis::expire($key, $windowSeconds + 3600);
    }

    /**
     * Get the strategy identifier
     */
    public function getStrategyIdentifier(): string
    {
        return 'load_balanced';
    }

    /**
     * Get strategy configuration
     */
    public function getConfiguration(): array
    {
        return $this->config;
    }

    /**
     * Get call counts for all agents in the group
     */
    private function getAgentLoads(Collection $agents): array
    {
        $loads = [];
        $windowSeconds = $this->config['window_hours'] * 3600;
        $cutoffTime = now()->timestamp - $windowSeconds;

        foreach ($agents as $agent) {
            $key = $this->getAgentCallKey($agent->id);
            $callCount = Redis::zcount($key, $cutoffTime, '+inf');
            $loads[$agent->id] = $callCount;

            // Apply capacity limits if configured
            if (isset($this->config['max_calls_per_agent']) &&
                $this->config['max_calls_per_agent'] !== null &&
                $callCount >= $this->config['max_calls_per_agent']) {
                $loads[$agent->id] = PHP_INT_MAX; // Mark as unavailable
            }
        }

        return $loads;
    }

    /**
     * Get Redis key for agent call tracking
     */
    private function getAgentCallKey(int $agentId): string
    {
        return "agent_load:{$this->group->id}:{$agentId}";
    }
}