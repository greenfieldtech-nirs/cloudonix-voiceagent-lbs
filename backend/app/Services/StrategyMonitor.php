<?php

namespace App\Services;

use App\Models\AgentGroup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Strategy Monitor Service
 *
 * Monitors distribution strategy performance, memory usage, and provides alerting.
 * Tracks strategy effectiveness and system health.
 */
class StrategyMonitor
{
    private RedisStrategyService $redisService;

    public function __construct(RedisStrategyService $redisService)
    {
        $this->redisService = $redisService;
    }

    /**
     * Get comprehensive strategy health report
     */
    public function getHealthReport(): array
    {
        $report = [
            'timestamp' => now()->toISOString(),
            'redis_health' => $this->redisService->checkHealth(),
            'memory_stats' => $this->redisService->getMemoryStats(),
            'performance_metrics' => $this->redisService->getPerformanceMetrics(),
            'strategy_stats' => $this->getAllStrategyStats(),
            'alerts' => $this->checkForAlerts(),
        ];

        return $report;
    }

    /**
     * Get statistics for all active strategies
     */
    public function getAllStrategyStats(): array
    {
        $groups = AgentGroup::with('memberships')->get();
        $stats = [];

        foreach ($groups as $group) {
            try {
                $strategyStats = $group->getStrategyStats();
                $stats[$group->id] = [
                    'group_name' => $group->name,
                    'strategy' => $group->strategy->value,
                    'enabled' => $group->enabled,
                    'can_route' => $group->canRoute(),
                    'stats' => $strategyStats,
                ];
            } catch (\Exception $e) {
                $stats[$group->id] = [
                    'group_name' => $group->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * Check for system alerts and issues
     */
    public function checkForAlerts(): array
    {
        $alerts = [];

        // Check Redis connectivity
        $redisHealth = $this->redisService->checkHealth();
        if (!$redisHealth['redis_connected']) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'redis_disconnected',
                'message' => 'Redis is not connected - strategies may not work properly',
                'details' => $redisHealth,
            ];
        } elseif (($redisHealth['response_time_ms'] ?? 0) > 100) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'redis_slow',
                'message' => 'Redis response time is high: ' . $redisHealth['response_time_ms'] . 'ms',
            ];
        }

        // Check memory usage
        $memoryStats = $this->redisService->getMemoryStats();
        if (isset($memoryStats['redis_memory_used'])) {
            $usedMB = round($memoryStats['redis_memory_used'] / 1024 / 1024, 2);
            if ($usedMB > 500) { // Over 500MB
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'high_memory_usage',
                    'message' => "Redis memory usage is high: {$usedMB}MB",
                ];
            }
        }

        // Check strategy-specific issues
        $strategyStats = $this->getAllStrategyStats();
        foreach ($strategyStats as $groupId => $groupStats) {
            if (isset($groupStats['error'])) {
                $alerts[] = [
                    'level' => 'error',
                    'type' => 'strategy_error',
                    'message' => "Strategy error in group {$groupStats['group_name']}: {$groupStats['error']}",
                    'group_id' => $groupId,
                ];
            } elseif ($groupStats['enabled'] && !$groupStats['can_route']) {
                $alerts[] = [
                    'level' => 'warning',
                    'type' => 'group_cannot_route',
                    'message' => "Group '{$groupStats['group_name']}' is enabled but cannot route calls",
                    'group_id' => $groupId,
                ];
            }
        }

        return $alerts;
    }

    /**
     * Get load distribution analysis
     */
    public function getLoadAnalysis(): array
    {
        $groups = AgentGroup::where('strategy', 'load_balanced')->get();
        $analysis = [];

        foreach ($groups as $group) {
            try {
                $agentLoads = $this->getAgentLoadDistribution($group);
                $analysis[$group->id] = [
                    'group_name' => $group->name,
                    'total_agents' => $group->memberships()->count(),
                    'active_agents' => $group->getActiveAgentCount(),
                    'load_distribution' => $agentLoads,
                    'balance_score' => $this->calculateBalanceScore($agentLoads),
                ];
            } catch (\Exception $e) {
                $analysis[$group->id] = [
                    'group_name' => $group->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $analysis;
    }

    /**
     * Get agent load distribution for a group
     */
    private function getAgentLoadDistribution(AgentGroup $group): array
    {
        $memberships = $group->memberships()->with('voiceAgent')->get();
        $loads = [];

        foreach ($memberships as $membership) {
            $agent = $membership->voiceAgent;
            $agentId = $agent->id;

            // Get call count from Redis (simplified - would need actual strategy logic)
            $key = "agent_load:{$group->id}:{$agentId}";
            $callCount = \Illuminate\Support\Facades\Redis::zcount($key, '-inf', '+inf');

            $loads[$agent->name] = [
                'calls' => $callCount,
                'enabled' => $agent->enabled,
                'priority' => $membership->priority,
                'capacity' => $membership->capacity,
            ];
        }

        return $loads;
    }

    /**
     * Calculate balance score (0-100, higher is better balanced)
     */
    private function calculateBalanceScore(array $loads): float
    {
        if (empty($loads)) {
            return 0.0;
        }

        $callCounts = array_column($loads, 'calls');
        $avgCalls = array_sum($callCounts) / count($callCounts);
        $variance = 0;

        foreach ($callCounts as $calls) {
            $variance += pow($calls - $avgCalls, 2);
        }

        $stdDev = sqrt($variance / count($callCounts));

        // Convert to balance score (lower std dev = higher score)
        if ($avgCalls == 0) {
            return 100.0; // Perfect balance if no calls
        }

        $cv = ($stdDev / $avgCalls) * 100; // Coefficient of variation
        $balanceScore = max(0, 100 - $cv); // Convert to 0-100 scale

        return round($balanceScore, 2);
    }

    /**
     * Run maintenance operations
     */
    public function runMaintenance(): array
    {
        $results = [
            'timestamp' => now()->toISOString(),
            'operations' => [],
        ];

        try {
            // Clean up expired keys
            $cleanedKeys = $this->redisService->cleanupExpiredKeys();
            $results['operations'][] = [
                'operation' => 'cleanup_expired_keys',
                'keys_cleaned' => $cleanedKeys,
                'status' => 'completed',
            ];
        } catch (\Exception $e) {
            $results['operations'][] = [
                'operation' => 'cleanup_expired_keys',
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }

        // Log maintenance results
        Log::info('Strategy maintenance completed', $results);

        return $results;
    }

    /**
     * Get strategy performance trends
     */
    public function getPerformanceTrends(int $hours = 24): array
    {
        // This would require historical data storage
        // For now, return current snapshot
        return [
            'period_hours' => $hours,
            'current_stats' => $this->getHealthReport(),
            'note' => 'Historical trend analysis requires additional data collection infrastructure',
        ];
    }
}