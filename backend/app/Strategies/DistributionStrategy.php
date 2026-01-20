<?php

namespace App\Strategies;

use App\Models\AgentGroup;
use App\Models\VoiceAgent;

/**
 * Distribution Strategy Interface
 *
 * Defines the contract for all distribution strategies used in agent groups.
 * Each strategy implements a different algorithm for selecting the next agent
 * to handle an incoming call.
 */
interface DistributionStrategy
{
    /**
     * Select the next agent to handle a call based on the strategy
     *
     * @param AgentGroup $group The agent group to select from
     * @return VoiceAgent|null The selected agent, or null if no suitable agent found
     */
    public function selectAgent(AgentGroup $group): ?VoiceAgent;

    /**
     * Record that a call was handled by an agent (for metrics/algorithms)
     *
     * @param AgentGroup $group The agent group
     * @param VoiceAgent $agent The agent that handled the call
     * @return void
     */
    public function recordCall(AgentGroup $group, VoiceAgent $agent): void;

    /**
     * Get the strategy identifier
     *
     * @return string
     */
    public function getStrategyIdentifier(): string;

    /**
     * Get strategy-specific configuration
     *
     * @return array
     */
    public function getConfiguration(): array;
}