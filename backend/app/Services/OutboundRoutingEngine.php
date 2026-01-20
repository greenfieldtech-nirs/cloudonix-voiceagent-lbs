<?php

namespace App\Services;

use App\Models\OutboundRoutingRule;
use App\Models\Tenant;
use App\Models\Trunk;
use Illuminate\Support\Facades\Log;

/**
 * Outbound Routing Engine Service
 *
 * Handles outbound call routing decisions based on caller ID and destination patterns.
 * Evaluates routing rules and selects appropriate trunks for outbound calls.
 */
class OutboundRoutingEngine
{
    /**
     * Evaluate outbound routing for a call
     *
     * @param Tenant $tenant The tenant context
     * @param array $callData Call data including caller_id and destination
     * @return array Routing result with trunk selection and metadata
     */
    public function evaluateOutboundRouting(Tenant $tenant, array $callData): array
    {
        Log::info('Evaluating outbound routing', [
            'tenant_id' => $tenant->id,
            'caller_id' => $callData['caller_id'] ?? null,
            'destination' => $callData['destination'] ?? null,
        ]);

        try {
            // Get all enabled outbound rules for this tenant
            $rules = OutboundRoutingRule::where('tenant_id', $tenant->id)
                ->enabled()
                ->orderBy('id') // Simple ordering for now
                ->get();

            if ($rules->isEmpty()) {
                Log::info('No outbound routing rules found, using default trunk', [
                    'tenant_id' => $tenant->id,
                ]);

                return $this->getDefaultTrunkRouting($tenant, $callData);
            }

            // Evaluate rules against call data
            foreach ($rules as $rule) {
                if ($this->ruleMatches($rule, $callData)) {
                    Log::info('Outbound routing rule matched', [
                        'rule_id' => $rule->id,
                        'caller_id_pattern' => $rule->caller_id,
                        'destination_pattern' => $rule->destination_pattern,
                    ]);

                    return $this->executeRuleRouting($rule, $callData);
                }
            }

            // No rule matched, fall back to default
            Log::info('No outbound routing rules matched, using default trunk', [
                'tenant_id' => $tenant->id,
                'rules_evaluated' => $rules->count(),
            ]);

            return $this->getDefaultTrunkRouting($tenant, $callData);

        } catch (\Exception $e) {
            Log::error('Outbound routing evaluation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFallbackRouting($callData, 'Routing evaluation failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if a rule matches the call data
     */
    private function ruleMatches(OutboundRoutingRule $rule, array $callData): bool
    {
        $callerId = $callData['caller_id'] ?? '';
        $destination = $callData['destination'] ?? '';

        // Must match both caller ID and destination patterns
        $callerIdMatches = $rule->matchesCallerId($callerId);
        $destinationMatches = $rule->matchesDestination($destination);

        return $callerIdMatches && $destinationMatches;
    }

    /**
     * Execute routing for a matched rule
     */
    private function executeRuleRouting(OutboundRoutingRule $rule, array $callData): array
    {
        $trunkConfig = $rule->getTrunkConfig();
        $trunkIds = $rule->getTrunkIds();

        if (empty($trunkIds)) {
            Log::warning('Rule matched but no trunks configured', [
                'rule_id' => $rule->id,
            ]);

            return $this->getFallbackRouting($callData, 'No trunks configured for rule');
        }

        // Get available trunks from the configured list
        $trunks = Trunk::whereIn('id', $trunkIds)
            ->where('tenant_id', $rule->tenant_id)
            ->enabled()
            ->orderedByPriority()
            ->get();

        if ($trunks->isEmpty()) {
            Log::warning('Rule matched but no available trunks found', [
                'rule_id' => $rule->id,
                'requested_trunk_ids' => $trunkIds,
            ]);

            return $this->getFallbackRouting($callData, 'No available trunks for rule');
        }

        // Select the best available trunk
        $selectedTrunk = $this->selectBestTrunk($trunks);

        return [
            'success' => true,
            'routing_type' => 'outbound_rule',
            'rule' => $rule,
            'selected_trunk' => $selectedTrunk,
            'caller_id' => $callData['caller_id'] ?? null,
            'destination' => $callData['destination'] ?? null,
            'metadata' => [
                'rule_id' => $rule->id,
                'trunk_id' => $selectedTrunk->id,
                'trunk_name' => $selectedTrunk->name,
                'cloudonix_trunk_id' => $selectedTrunk->cloudonix_trunk_id,
            ],
        ];
    }

    /**
     * Get default trunk routing when no rules match
     */
    private function getDefaultTrunkRouting(Tenant $tenant, array $callData): array
    {
        // Try to find a default trunk for this tenant
        $defaultTrunk = Trunk::where('tenant_id', $tenant->id)
            ->enabled()
            ->where('is_default', true)
            ->first();

        if ($defaultTrunk) {
            return [
                'success' => true,
                'routing_type' => 'default_trunk',
                'selected_trunk' => $defaultTrunk,
                'caller_id' => $callData['caller_id'] ?? null,
                'destination' => $callData['destination'] ?? null,
                'metadata' => [
                    'trunk_id' => $defaultTrunk->id,
                    'trunk_name' => $defaultTrunk->name,
                    'cloudonix_trunk_id' => $defaultTrunk->cloudonix_trunk_id,
                    'reason' => 'default_trunk',
                ],
            ];
        }

        // No default trunk, try any available trunk
        $anyTrunk = Trunk::where('tenant_id', $tenant->id)
            ->enabled()
            ->orderedByPriority()
            ->first();

        if ($anyTrunk) {
            return [
                'success' => true,
                'routing_type' => 'fallback_trunk',
                'selected_trunk' => $anyTrunk,
                'caller_id' => $callData['caller_id'] ?? null,
                'destination' => $callData['destination'] ?? null,
                'metadata' => [
                    'trunk_id' => $anyTrunk->id,
                    'trunk_name' => $anyTrunk->name,
                    'cloudonix_trunk_id' => $anyTrunk->cloudonix_trunk_id,
                    'reason' => 'fallback_trunk',
                ],
            ];
        }

        // No trunks available at all
        return $this->getFallbackRouting($callData, 'No trunks available for tenant');
    }

    /**
     * Select the best available trunk from a list
     */
    private function selectBestTrunk(\Illuminate\Database\Eloquent\Collection $trunks): ?Trunk
    {
        // For now, return the highest priority trunk
        // In the future, this could consider capacity, load balancing, etc.
        return $trunks->first();
    }

    /**
     * Get fallback routing when no proper routing is available
     */
    private function getFallbackRouting(array $callData, string $reason): array
    {
        return [
            'success' => false,
            'routing_type' => 'fallback',
            'caller_id' => $callData['caller_id'] ?? null,
            'destination' => $callData['destination'] ?? null,
            'reason' => $reason,
            'metadata' => [
                'reason' => $reason,
                'fallback' => true,
            ],
        ];
    }

    /**
     * Detect if a call is outbound based on caller ID patterns
     */
    public function isOutboundCall(Tenant $tenant, array $callData): bool
    {
        $callerId = $callData['caller_id'] ?? '';

        if (empty($callerId)) {
            return false;
        }

        // Check if caller ID matches any outbound routing rule
        $ruleCount = OutboundRoutingRule::where('tenant_id', $tenant->id)
            ->enabled()
            ->where(function ($query) use ($callerId) {
                // Check for exact or prefix matches
                $query->where('caller_id', $callerId)
                      ->orWhere(function ($subQuery) use ($callerId) {
                          $subQuery->where('caller_id', 'not like', '+%')
                                   ->whereRaw('? LIKE CONCAT("+", caller_id)', [$callerId])
                                   ->orWhereRaw('? LIKE CONCAT(caller_id)', [$callerId]);
                      });
            })
            ->count();

        return $ruleCount > 0;
    }

    /**
     * Get all outbound routing rules for a tenant
     */
    public function getTenantRules(Tenant $tenant): array
    {
        $rules = OutboundRoutingRule::where('tenant_id', $tenant->id)
            ->with('tenant')
            ->orderBy('id')
            ->get();

        return $rules->map(function ($rule) {
            return [
                'id' => $rule->id,
                'caller_id' => $rule->caller_id,
                'destination_pattern' => $rule->destination_pattern,
                'trunk_config' => $rule->getTrunkConfig(),
                'trunk_ids' => $rule->getTrunkIds(),
                'enabled' => $rule->enabled,
                'created_at' => $rule->created_at,
                'updated_at' => $rule->updated_at,
            ];
        })->toArray();
    }

    /**
     * Get available trunks for a tenant
     */
    public function getTenantTrunks(Tenant $tenant): array
    {
        $trunks = Trunk::where('tenant_id', $tenant->id)
            ->orderBy('priority', 'desc')
            ->get();

        return $trunks->map(function ($trunk) {
            return [
                'id' => $trunk->id,
                'name' => $trunk->name,
                'cloudonix_trunk_id' => $trunk->cloudonix_trunk_id,
                'description' => $trunk->description,
                'priority' => $trunk->priority,
                'capacity' => $trunk->capacity,
                'enabled' => $trunk->enabled,
                'is_default' => $trunk->is_default,
                'configuration' => $trunk->configuration,
            ];
        })->toArray();
    }

    /**
     * Test outbound routing rule matching
     */
    public function testRuleMatching(OutboundRoutingRule $rule, array $testCalls): array
    {
        $results = [];

        foreach ($testCalls as $call) {
            $matches = $this->ruleMatches($rule, $call);
            $results[] = [
                'caller_id' => $call['caller_id'] ?? '',
                'destination' => $call['destination'] ?? '',
                'matches' => $matches,
            ];
        }

        return $results;
    }
}