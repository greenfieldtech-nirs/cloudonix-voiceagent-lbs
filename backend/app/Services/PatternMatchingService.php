<?php

namespace App\Services;

use App\Models\InboundRoutingRule;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Pattern Matching Service for Inbound Call Routing
 *
 * Evaluates routing rules against call data to determine routing targets.
 * Supports various matching patterns and prioritization.
 */
class PatternMatchingService
{
    /**
     * Evaluate routing rules for an incoming call
     *
     * @param Tenant $tenant The tenant context
     * @param array $callData Call data including caller ID, destination, etc.
     * @return InboundRoutingRule|null The matching rule or null if no match
     */
    public function evaluateRules(Tenant $tenant, array $callData): ?InboundRoutingRule
    {
        // Get all enabled rules for this tenant, ordered by priority
        $rules = InboundRoutingRule::where('tenant_id', $tenant->id)
            ->enabled()
            ->orderedByPriority()
            ->get();

        if ($rules->isEmpty()) {
            Log::info('No routing rules found for tenant', [
                'tenant_id' => $tenant->id,
                'call_data' => $callData,
            ]);
            return null;
        }

        // Evaluate each rule against the call data
        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $callData)) {
                Log::info('Routing rule matched', [
                    'rule_id' => $rule->id,
                    'pattern' => $rule->pattern,
                    'target_type' => $rule->target_type,
                    'target_id' => $rule->target_id,
                    'call_data' => $callData,
                ]);

                return $rule;
            }
        }

        Log::info('No routing rules matched for call', [
            'tenant_id' => $tenant->id,
            'call_data' => $callData,
            'rules_evaluated' => $rules->count(),
        ]);

        return null;
    }

    /**
     * Check if a specific rule matches the call data
     */
    private function ruleMatches(InboundRoutingRule $rule, array $callData): bool
    {
        // Extract relevant data for pattern matching
        $callerId = $callData['caller_id'] ?? $callData['From'] ?? '';
        $destination = $callData['destination'] ?? $callData['To'] ?? '';
        $direction = $callData['direction'] ?? 'inbound';

        // For inbound calls, match against the destination (DID)
        // For outbound calls, match against the caller ID
        $matchTarget = ($direction === 'inbound') ? $destination : $callerId;

        if (empty($matchTarget)) {
            return false;
        }

        // Use the rule's matching logic
        return $rule->matchesNumber($matchTarget);
    }

    /**
     * Get all routing rules for a tenant with their targets resolved
     */
    public function getResolvedRules(Tenant $tenant): array
    {
        $rules = InboundRoutingRule::where('tenant_id', $tenant->id)
            ->enabled()
            ->orderedByPriority()
            ->get();

        return $rules->map(function ($rule) {
            return [
                'id' => $rule->id,
                'pattern' => $rule->pattern,
                'target_type' => $rule->target_type,
                'target_id' => $rule->target_id,
                'priority' => $rule->priority,
                'target_display_name' => $rule->getTargetDisplayName(),
                'target_type_display_name' => $rule->getTargetTypeDisplayName(),
                'target' => $rule->getTarget(),
            ];
        })->toArray();
    }

    /**
     * Validate a routing rule pattern
     */
    public function validatePattern(string $pattern): array
    {
        $issues = [];

        if (empty($pattern)) {
            $issues[] = 'Pattern cannot be empty';
        }

        // Check for valid phone number patterns
        if (!preg_match('/^[+\d\s\-\(\)\[\]\{\}\*\?]+$/', $pattern)) {
            $issues[] = 'Pattern contains invalid characters';
        }

        // Check length
        if (strlen($pattern) > 20) {
            $issues[] = 'Pattern is too long (max 20 characters)';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Test a pattern against sample data
     */
    public function testPattern(string $pattern, array $testNumbers): array
    {
        $results = [];

        foreach ($testNumbers as $number) {
            $rule = new InboundRoutingRule(['pattern' => $pattern]);
            $matches = $rule->matchesNumber($number);
            $results[] = [
                'number' => $number,
                'matches' => $matches,
            ];
        }

        return $results;
    }

    /**
     * Get pattern matching statistics for a tenant
     */
    public function getMatchingStatistics(Tenant $tenant, \DateTime $startDate, \DateTime $endDate): array
    {
        // This would analyze call session data to show which rules are most effective
        // For now, return basic rule counts
        $ruleCount = InboundRoutingRule::where('tenant_id', $tenant->id)->count();
        $enabledRuleCount = InboundRoutingRule::where('tenant_id', $tenant->id)->enabled()->count();

        return [
            'total_rules' => $ruleCount,
            'enabled_rules' => $enabledRuleCount,
            'disabled_rules' => $ruleCount - $enabledRuleCount,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            // TODO: Add actual matching statistics from call session data
        ];
    }
}