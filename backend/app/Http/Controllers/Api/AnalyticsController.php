<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Analytics Controller
 *
 * Provides REST API endpoints for analytics and dashboard data.
 * Includes filtering, caching, and pagination support.
 */
class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get dashboard overview metrics
     */
    public function overview(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAnalytics', $tenant);

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        $data = $this->analyticsService->getDashboardOverview($tenant, $startDate, $endDate);

        return response()->json([
            'data' => $data,
            'meta' => [
                'tenant_id' => $tenant->id,
                'date_range' => [
                    'start' => $startDate?->toDateString(),
                    'end' => $endDate?->toDateString(),
                ],
                'cached' => true, // Indicate that data may be cached
            ],
        ]);
    }

    /**
     * Get call trends data
     */
    public function trends(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAnalytics', $tenant);

        $days = $request->input('days', 30);
        $days = min(max($days, 1), 365); // Limit between 1 and 365 days

        $trends = $this->analyticsService->getCallTrends($tenant, $days);

        return response()->json([
            'data' => $trends,
            'meta' => [
                'tenant_id' => $tenant->id,
                'days' => $days,
                'total_data_points' => count($trends),
            ],
        ]);
    }

    /**
     * Get agent performance metrics
     */
    public function agents(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAnalytics', $tenant);

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        $limit = $request->input('limit', 20);
        $limit = min(max($limit, 1), 100); // Limit between 1 and 100

        $agents = $this->analyticsService->getTopAgents($tenant, $startDate, $endDate, $limit);

        return response()->json([
            'data' => $agents,
            'meta' => [
                'tenant_id' => $tenant->id,
                'limit' => $limit,
                'date_range' => [
                    'start' => $startDate?->toDateString(),
                    'end' => $endDate?->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Get agent group metrics
     */
    public function groups(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAnalytics', $tenant);

        $startDate = $request->has('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        $groups = $this->analyticsService->getAgentGroupMetrics($tenant, $startDate, $endDate);

        return response()->json([
            'data' => $groups,
            'meta' => [
                'tenant_id' => $tenant->id,
                'date_range' => [
                    'start' => $startDate?->toDateString(),
                    'end' => $endDate?->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Get real-time metrics for WebSocket updates
     */
    public function realtime(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAnalytics', $tenant);

        $metrics = $this->analyticsService->getRealtimeMetrics($tenant);

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'tenant_id' => $tenant->id,
                'realtime' => true,
            ],
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Get detailed metrics with custom filtering
     */
    public function metrics(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAnalytics', $tenant);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'group_by' => 'nullable|in:hour,day,week,month',
            'metrics' => 'nullable|array',
            'metrics.*' => 'in:total_calls,success_rate,avg_duration,active_calls',
        ]);

        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;
        $groupBy = $validated['group_by'] ?? 'day';
        $requestedMetrics = $validated['metrics'] ?? ['total_calls', 'success_rate', 'avg_duration'];

        $data = [];

        if (in_array('total_calls', $requestedMetrics)) {
            $data['total_calls'] = $this->analyticsService->getTotalCalls($tenant, $startDate, $endDate);
        }

        if (in_array('success_rate', $requestedMetrics)) {
            $data['success_rate'] = $this->analyticsService->getSuccessRate($tenant, $startDate, $endDate);
        }

        if (in_array('avg_duration', $requestedMetrics)) {
            $data['avg_duration'] = $this->analyticsService->getAverageCallDuration($tenant, $startDate, $endDate);
        }

        if (in_array('active_calls', $requestedMetrics)) {
            $data['active_calls'] = $this->analyticsService->getActiveCallsCount($tenant);
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'tenant_id' => $tenant->id,
                'group_by' => $groupBy,
                'metrics' => $requestedMetrics,
                'date_range' => [
                    'start' => $startDate?->toDateString(),
                    'end' => $endDate?->toDateString(),
                ],
            ],
        ]);
    }

    /**
     * Clear analytics cache for the tenant
     */
    public function clearCache(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('manageAnalytics', $tenant);

        $this->analyticsService->clearTenantCache($tenant);

        return response()->json([
            'message' => 'Analytics cache cleared successfully',
            'meta' => [
                'tenant_id' => $tenant->id,
                'cleared_at' => now()->toISOString(),
            ],
        ]);
    }
}