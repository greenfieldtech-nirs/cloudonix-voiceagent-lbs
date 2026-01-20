<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\Tenant;
use App\Models\VoiceAgent;
use App\Models\AgentGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Analytics Service
 *
 * Provides comprehensive analytics and metrics calculation for the voice routing system.
 * Handles real-time and historical data aggregation with caching for performance.
 */
class AnalyticsService
{
    protected int $cacheTtl = 300; // 5 minutes for real-time data
    protected int $historicalCacheTtl = 3600; // 1 hour for historical data

    /**
     * Get dashboard overview metrics
     */
    public function getDashboardOverview(Tenant $tenant, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $cacheKey = "analytics.dashboard.{$tenant->id}." . ($startDate?->format('Y-m-d') ?: 'all') . "." . ($endDate?->format('Y-m-d') ?: 'all');

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tenant, $startDate, $endDate) {
            return [
                'total_calls' => $this->getTotalCalls($tenant, $startDate, $endDate),
                'active_calls' => $this->getActiveCallsCount($tenant),
                'success_rate' => $this->getSuccessRate($tenant, $startDate, $endDate),
                'average_duration' => $this->getAverageCallDuration($tenant, $startDate, $endDate),
                'calls_today' => $this->getCallsToday($tenant),
                'calls_this_week' => $this->getCallsThisWeek($tenant),
                'calls_this_month' => $this->getCallsThisMonth($tenant),
                'top_agents' => $this->getTopAgents($tenant, $startDate, $endDate, 5),
                'call_trends' => $this->getCallTrends($tenant, 30), // Last 30 days
            ];
        });
    }

    /**
     * Get total calls for a tenant within date range
     */
    public function getTotalCalls(Tenant $tenant, ?Carbon $startDate = null, ?Carbon $endDate = null): int
    {
        $query = CallSession::where('tenant_id', $tenant->id);

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        return $query->count();
    }

    /**
     * Get active calls count
     */
    public function getActiveCallsCount(Tenant $tenant): int
    {
        return CallSession::where('tenant_id', $tenant->id)
            ->whereIn('status', ['ringing', 'connected', 'in_progress'])
            ->count();
    }

    /**
     * Get success rate (connected calls / total calls)
     */
    public function getSuccessRate(Tenant $tenant, ?Carbon $startDate = null, ?Carbon $endDate = null): float
    {
        $query = CallSession::where('tenant_id', $tenant->id);

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $successful = (clone $query)->whereIn('status', ['connected', 'completed'])->count();

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get average call duration in seconds
     */
    public function getAverageCallDuration(Tenant $tenant, ?Carbon $startDate = null, ?Carbon $endDate = null): int
    {
        $query = CallSession::where('tenant_id', $tenant->id)
            ->whereNotNull('ended_at')
            ->whereIn('status', ['completed', 'failed']);

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $avgDuration = $query->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as avg_duration')
            ->first()
            ->avg_duration;

        return (int) ($avgDuration ?? 0);
    }

    /**
     * Get calls today
     */
    public function getCallsToday(Tenant $tenant): int
    {
        return CallSession::where('tenant_id', $tenant->id)
            ->whereDate('started_at', Carbon::today())
            ->count();
    }

    /**
     * Get calls this week
     */
    public function getCallsThisWeek(Tenant $tenant): int
    {
        return CallSession::where('tenant_id', $tenant->id)
            ->whereBetween('started_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ])
            ->count();
    }

    /**
     * Get calls this month
     */
    public function getCallsThisMonth(Tenant $tenant): int
    {
        return CallSession::where('tenant_id', $tenant->id)
            ->whereYear('started_at', Carbon::now()->year)
            ->whereMonth('started_at', Carbon::now()->month)
            ->count();
    }

    /**
     * Get top performing agents
     */
    public function getTopAgents(Tenant $tenant, ?Carbon $startDate = null, ?Carbon $endDate = null, int $limit = 10): array
    {
        $query = CallSession::select('voice_agent_id', DB::raw('COUNT(*) as total_calls'))
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('voice_agent_id')
            ->groupBy('voice_agent_id')
            ->orderBy('total_calls', 'desc')
            ->limit($limit);

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $results = $query->get();

        return $results->map(function ($result) {
            $agent = VoiceAgent::find($result->voice_agent_id);
            return [
                'agent_id' => $result->voice_agent_id,
                'agent_name' => $agent?->name ?? 'Unknown',
                'total_calls' => $result->total_calls,
                'success_rate' => $this->getAgentSuccessRate($result->voice_agent_id, $startDate, $endDate),
                'avg_duration' => $this->getAgentAvgDuration($result->voice_agent_id, $startDate, $endDate),
            ];
        })->toArray();
    }

    /**
     * Get call trends for the last N days
     */
    public function getCallTrends(Tenant $tenant, int $days = 30): array
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays($days);

        $trends = CallSession::select(
                DB::raw('DATE(started_at) as date'),
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('COUNT(CASE WHEN status IN ("connected", "completed") THEN 1 END) as successful_calls')
            )
            ->where('tenant_id', $tenant->id)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $trends->map(function ($trend) {
            return [
                'date' => $trend->date,
                'total_calls' => $trend->total_calls,
                'successful_calls' => $trend->successful_calls,
                'success_rate' => $trend->total_calls > 0
                    ? round(($trend->successful_calls / $trend->total_calls) * 100, 2)
                    : 0,
            ];
        })->toArray();
    }

    /**
     * Get agent success rate
     */
    private function getAgentSuccessRate(int $agentId, ?Carbon $startDate = null, ?Carbon $endDate = null): float
    {
        $query = CallSession::where('voice_agent_id', $agentId);

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0.0;
        }

        $successful = (clone $query)->whereIn('status', ['connected', 'completed'])->count();

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get agent average duration
     */
    private function getAgentAvgDuration(int $agentId, ?Carbon $startDate = null, ?Carbon $endDate = null): int
    {
        $query = CallSession::where('voice_agent_id', $agentId)
            ->whereNotNull('ended_at')
            ->whereIn('status', ['completed', 'failed']);

        if ($startDate) {
            $query->where('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('started_at', '<=', $endDate);
        }

        $avgDuration = $query->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as avg_duration')
            ->first()
            ->avg_duration;

        return (int) ($avgDuration ?? 0);
    }

    /**
     * Get agent group performance metrics
     */
    public function getAgentGroupMetrics(Tenant $tenant, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $cacheKey = "analytics.groups.{$tenant->id}." . ($startDate?->format('Y-m-d') ?: 'all') . "." . ($endDate?->format('Y-m-d') ?: 'all');

        return Cache::remember($cacheKey, $this->historicalCacheTtl, function () use ($tenant, $startDate, $endDate) {
            $groups = AgentGroup::where('tenant_id', $tenant->id)->get();

            return $groups->map(function ($group) use ($startDate, $endDate) {
                $callCount = CallSession::where('tenant_id', $tenant->id)
                    ->where('metadata->group_id', $group->id)
                    ->when($startDate, fn($q) => $q->where('started_at', '>=', $startDate))
                    ->when($endDate, fn($q) => $q->where('started_at', '<=', $endDate))
                    ->count();

                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'total_calls' => $callCount,
                    'active_agents' => $group->activeAgents()->count(),
                    'strategy' => $group->strategy->value,
                ];
            })->toArray();
        });
    }

    /**
     * Clear analytics cache for a tenant
     */
    public function clearTenantCache(Tenant $tenant): void
    {
        $cacheKeys = [
            "analytics.dashboard.{$tenant->id}.*",
            "analytics.groups.{$tenant->id}.*",
        ];

        foreach ($cacheKeys as $pattern) {
            Cache::store('redis')->deleteMultiple(
                Cache::store('redis')->keys($pattern)
            );
        }

        // Broadcast cache cleared event
        broadcast(new \App\Events\AnalyticsUpdated($tenant, [
            'cache_cleared' => true,
            'timestamp' => now()->toISOString(),
        ], 'cache_cleared'));
    }

    /**
     * Get real-time metrics for WebSocket broadcasting
     */
    public function getRealtimeMetrics(Tenant $tenant): array
    {
        return [
            'active_calls' => $this->getActiveCallsCount($tenant),
            'calls_today' => $this->getCallsToday($tenant),
            'timestamp' => now()->toISOString(),
        ];
    }
}