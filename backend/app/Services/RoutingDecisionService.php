<?php

namespace App\Services;

use App\Models\AgentGroup;
use App\Models\InboundRoutingRule;
use App\Models\Tenant;
use App\Models\VoiceAgent;
use App\Strategies\DistributionStrategyFactory;
use Illuminate\Support\Facades\Log;

/**
 * Routing Decision Service
 *
 * Makes routing decisions based on matched rules and executes distribution strategies.
 * Integrates with agent group load balancing and voice agent selection.
 */
class RoutingDecisionService
{
    protected DistributionStrategyFactory $strategyFactory;
    protected CxmlService $cxmlService;

    public function __construct(
        DistributionStrategyFactory $strategyFactory,
        CxmlService $cxmlService
    ) {
        $this->strategyFactory = $strategyFactory;
        $this->cxmlService = $cxmlService;
    }

    /**
     * Execute routing decision for a matched rule
     *
     * @param InboundRoutingRule $rule The matched routing rule
     * @param array $callData Call context data
     * @return array Routing result with CXML and metadata
     */
    public function executeRouting(InboundRoutingRule $rule, array $callData): array
    {
        Log::info('Executing routing decision', [
            'rule_id' => $rule->id,
            'target_type' => $rule->target_type,
            'target_id' => $rule->target_id,
            'call_data' => $callData,
        ]);

        try {
            switch ($rule->target_type) {
                case 'agent':
                    return $this->routeToVoiceAgent($rule, $callData);

                case 'group':
                    return $this->routeToAgentGroup($rule, $callData);

                default:
                    Log::warning('Unknown routing target type', [
                        'rule_id' => $rule->id,
                        'target_type' => $rule->target_type,
                    ]);

                    return $this->generateHangupResponse('Unknown routing target type');
            }
        } catch (\Exception $e) {
            Log::error('Routing decision failed', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->generateHangupResponse('Routing decision failed: ' . $e->getMessage());
        }
    }

    /**
     * Route to a specific voice agent
     */
    private function routeToVoiceAgent(InboundRoutingRule $rule, array $callData): array
    {
        $agent = VoiceAgent::find($rule->target_id);

        if (!$agent || !$agent->enabled) {
            return $this->generateHangupResponse('Voice agent not available');
        }

        // Check agent capacity/availability (basic check)
        if (!$this->isAgentAvailable($agent)) {
            Log::info('Voice agent at capacity', [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
            ]);

            return $this->generateHangupResponse('Voice agent at capacity');
        }

        // Generate CXML for voice agent routing
        $cxml = $this->cxmlService->generateVoiceAgentRouting([
            'provider' => $agent->provider->value, // Convert enum to string
            'service_value' => $agent->service_value,
            'username' => $agent->username,
            'password' => $agent->password,
        ], $callData['caller_id'] ?? null);

        Log::info('Routed to voice agent', [
            'rule_id' => $rule->id,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'provider' => $agent->provider,
        ]);

        return [
            'success' => true,
            'cxml' => $cxml,
            'routing_type' => 'voice_agent',
            'target' => $agent,
            'metadata' => [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'provider' => $agent->provider,
            ],
        ];
    }

    /**
     * Route to an agent group using distribution strategy
     */
    private function routeToAgentGroup(InboundRoutingRule $rule, array $callData): array
    {
        $group = AgentGroup::with('memberships.agent')->find($rule->target_id);

        if (!$group || !$group->enabled) {
            Log::warning('Agent group not found or inactive', [
                'rule_id' => $rule->id,
                'group_id' => $rule->target_id,
            ]);

            return $this->generateHangupResponse('Agent group not available');
        }

        // Get available agents in the group
        $availableAgents = $this->getAvailableAgentsInGroup($group);

        if (empty($availableAgents)) {
            Log::info('No available agents in group', [
                'group_id' => $group->id,
                'group_name' => $group->name,
            ]);

            return $this->generateHangupResponse('No agents available in group');
        }

        // Execute distribution strategy
        $selectedAgent = $this->executeDistributionStrategy($group, $availableAgents, $callData);

        if (!$selectedAgent) {
            Log::warning('Distribution strategy failed to select agent', [
                'group_id' => $group->id,
                'strategy' => $group->strategy->value,
                'available_agents' => count($availableAgents),
            ]);

            return $this->generateHangupResponse('Unable to select agent from group');
        }

        // Generate CXML for selected agent
        $cxml = $this->cxmlService->generateVoiceAgentRouting([
            'provider' => $selectedAgent->provider->value, // Convert enum to string
            'service_value' => $selectedAgent->service_value,
            'username' => $selectedAgent->username,
            'password' => $selectedAgent->password,
        ], $callData['caller_id'] ?? null);

        Log::info('Routed to agent group', [
            'rule_id' => $rule->id,
            'group_id' => $group->id,
            'group_name' => $group->name,
            'selected_agent_id' => $selectedAgent->id,
            'selected_agent_name' => $selectedAgent->name,
            'strategy' => $group->distribution_strategy,
        ]);

        return [
            'success' => true,
            'cxml' => $cxml,
            'routing_type' => 'agent_group',
            'target' => $group,
            'selected_agent' => $selectedAgent,
            'metadata' => [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'selected_agent_id' => $selectedAgent->id,
                'selected_agent_name' => $selectedAgent->name,
                'strategy' => $group->distribution_strategy,
            ],
        ];
    }

    /**
     * Execute the distribution strategy for agent group selection
     */
    private function executeDistributionStrategy(AgentGroup $group, array $availableAgents, array $callData): ?VoiceAgent
    {
        try {
            $strategy = $this->strategyFactory->create($group);

            // Execute strategy - it handles getting available agents internally
            $selectedAgent = $strategy->selectAgent($group);

            if (!$selectedAgent) {
                Log::warning('Distribution strategy returned no agent', [
                    'group_id' => $group->id,
                    'strategy' => $group->strategy->value,
                    'available_agents_count' => count($availableAgents),
                ]);

                return null;
            }

            // Verify the selected agent is in our available list
            $agentInList = in_array($selectedAgent->id, array_column($availableAgents, 'id'));
            if (!$agentInList) {
                Log::warning('Strategy selected agent not in available list', [
                    'group_id' => $group->id,
                    'selected_agent_id' => $selectedAgent->id,
                    'available_agent_ids' => array_column($availableAgents, 'id'),
                ]);

                return null;
            }

            return $selectedAgent;

        } catch (\Exception $e) {
            Log::error('Distribution strategy execution failed', [
                'group_id' => $group->id,
                'strategy' => $group->strategy->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get available agents in an agent group
     */
    private function getAvailableAgentsInGroup(AgentGroup $group): array
    {
        $agents = [];

        foreach ($group->memberships as $membership) {
            $agent = $membership->agent;

            if ($agent && $agent->is_active && $this->isAgentAvailable($agent)) {
                $agents[] = $agent;
            }
        }

        return $agents;
    }

    /**
     * Check if a voice agent is available for routing
     */
    private function isAgentAvailable(VoiceAgent $agent): bool
    {
        // Basic availability check - can be enhanced with real-time status
        // For now, just check if agent is enabled and not at capacity

        if (!$agent->enabled) {
            return false;
        }

        // Check current load vs capacity
        $currentLoad = $this->getAgentCurrentLoad($agent);
        $capacity = $agent->capacity ?? 1;

        return $currentLoad < $capacity;
    }

    /**
     * Get current load for an agent (simplified implementation)
     */
    private function getAgentCurrentLoad(VoiceAgent $agent): int
    {
        // TODO: Implement real load tracking based on active call sessions
        // For now, return 0 (no load tracking)
        return 0;
    }

    /**
     * Generate hangup response for routing failures
     */
    private function generateHangupResponse(string $reason): array
    {
        $cxml = $this->cxmlService->generateHangup();

        return [
            'success' => false,
            'cxml' => $cxml,
            'routing_type' => 'hangup',
            'reason' => $reason,
            'metadata' => [
                'hangup_reason' => $reason,
            ],
        ];
    }

    /**
     * Get routing statistics for a tenant
     */
    public function getRoutingStatistics(Tenant $tenant, \DateTime $startDate, \DateTime $endDate): array
    {
        // TODO: Implement statistics based on call session and routing decision data
        return [
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'tenant_id' => $tenant->id,
            // TODO: Add actual statistics
        ];
    }

    /**
     * Test routing decision for a rule
     */
    public function testRoutingDecision(InboundRoutingRule $rule, array $callData): array
    {
        // Simulate routing decision without actually executing it
        try {
            $result = $this->executeRouting($rule, $callData);
            return [
                'success' => true,
                'result' => $result,
                'test_mode' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'test_mode' => true,
            ];
        }
    }
}