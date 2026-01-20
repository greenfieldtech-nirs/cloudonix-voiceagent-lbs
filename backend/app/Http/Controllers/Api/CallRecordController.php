<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallRecord;
use App\Models\Tenant;
use App\Services\FilterBuilderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Call Record Controller
 *
 * Provides comprehensive API endpoints for call record management with advanced filtering,
 * pagination, and performance optimizations.
 */
class CallRecordController extends Controller
{
    protected FilterBuilderService $filterBuilder;

    public function __construct(FilterBuilderService $filterBuilder)
    {
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Get paginated call records with advanced filtering
     */
    public function index(Request $request, Tenant $tenant): AnonymousResourceCollection
    {
        $this->authorize('viewCallRecords', $tenant);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:id,start_time,end_time,duration,status,direction',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'filters' => 'nullable|array',
            'filters.start_date' => 'nullable|date',
            'filters.end_date' => 'nullable|date',
            'filters.status' => 'nullable|array',
            'filters.status.*' => 'string|in:queued,ringing,in_progress,completed,busy,failed,no_answer',
            'filters.direction' => 'nullable|string|in:inbound,outbound',
            'filters.agent_id' => 'nullable|integer|exists:voice_agents,id',
            'filters.group_id' => 'nullable|integer|exists:agent_groups,id',
            'filters.from_number' => 'nullable|string|max:20',
            'filters.to_number' => 'nullable|string|max:20',
            'filters.min_duration' => 'nullable|integer|min:0',
            'filters.max_duration' => 'nullable|integer|min:0',
            'filters.session_token' => 'nullable|string|max:255',
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 25;
        $sortBy = $validated['sort_by'] ?? 'start_time';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        // Build query using filter builder
        $query = $this->filterBuilder->buildCallRecordQuery(
            $tenant,
            $validated['filters'] ?? []
        );

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        // Paginate results
        $records = $query->paginate($perPage, ['*'], 'page', $page);

        return \App\Http\Resources\CallRecordResource::collection($records);
    }

    /**
     * Get a specific call record
     */
    public function show(Request $request, Tenant $tenant, CallRecord $callRecord): \App\Http\Resources\CallRecordResource
    {
        $this->authorize('viewCallRecords', $tenant);

        // Ensure the call record belongs to the tenant
        if ($callRecord->tenant_id !== $tenant->id) {
            abort(404);
        }

        return new \App\Http\Resources\CallRecordResource($callRecord->load(['agent', 'group']));
    }

    /**
     * Get call record statistics
     */
    public function statistics(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewCallRecords', $tenant);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'filters' => 'nullable|array',
        ]);

        $startDate = isset($validated['start_date']) ? \Carbon\Carbon::parse($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? \Carbon\Carbon::parse($validated['end_date']) : null;
        $filters = $validated['filters'] ?? [];

        $stats = [
            'total_records' => $this->getTotalRecords($tenant, $startDate, $endDate, $filters),
            'status_breakdown' => $this->getStatusBreakdown($tenant, $startDate, $endDate, $filters),
            'direction_breakdown' => $this->getDirectionBreakdown($tenant, $startDate, $endDate, $filters),
            'duration_stats' => $this->getDurationStatistics($tenant, $startDate, $endDate, $filters),
            'agent_stats' => $this->getAgentStatistics($tenant, $startDate, $endDate, $filters),
            'hourly_distribution' => $this->getHourlyDistribution($tenant, $startDate, $endDate, $filters),
            'date_range' => [
                'start' => $startDate?->toDateString(),
                'end' => $endDate?->toDateString(),
            ],
        ];

        return response()->json([
            'data' => $stats,
            'meta' => [
                'tenant_id' => $tenant->id,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Export call records
     */
    public function export(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('exportCallRecords', $tenant);

        $validated = $request->validate([
            'format' => 'required|in:csv,json',
            'filters' => 'nullable|array',
            'fields' => 'nullable|array',
            'email' => 'nullable|email',
        ]);

        // Use the existing ExportService for call records
        $exportService = app(\App\Services\ExportService::class);

        $exportId = $exportService->queueExport(
            $tenant,
            $validated['format'],
            $validated['filters'] ?? [],
            $validated['fields'] ?? [],
            $validated['email'] ?? null
        );

        return response()->json([
            'message' => 'Export queued successfully',
            'data' => [
                'export_id' => $exportId,
                'status' => 'queued',
            ],
            'meta' => [
                'tenant_id' => $tenant->id,
            ],
        ], 202);
    }

    /**
     * Get available filter options
     */
    public function filters(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewCallRecords', $tenant);

        return response()->json([
            'data' => [
                'statuses' => CallRecord::STATUSES,
                'directions' => CallRecord::DIRECTIONS,
                'agents' => $tenant->voiceAgents()->select('id', 'name')->get(),
                'groups' => $tenant->agentGroups()->select('id', 'name')->get(),
                'date_range' => [
                    'min' => CallRecord::where('tenant_id', $tenant->id)->min('start_time'),
                    'max' => CallRecord::where('tenant_id', $tenant->id)->max('start_time'),
                ],
            ],
            'meta' => [
                'tenant_id' => $tenant->id,
            ],
        ]);
    }

    /**
     * Get total records count
     */
    private function getTotalRecords(Tenant $tenant, ?\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate, array $filters): int
    {
        $query = $this->filterBuilder->buildCallRecordQuery($tenant, $filters);

        if ($startDate) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_time', '<=', $endDate);
        }

        return $query->count();
    }

    /**
     * Get status breakdown
     */
    private function getStatusBreakdown(Tenant $tenant, ?\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate, array $filters): array
    {
        $query = $this->filterBuilder->buildCallRecordQuery($tenant, $filters);

        if ($startDate) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_time', '<=', $endDate);
        }

        return $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get direction breakdown
     */
    private function getDirectionBreakdown(Tenant $tenant, ?\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate, array $filters): array
    {
        $query = $this->filterBuilder->buildCallRecordQuery($tenant, $filters);

        if ($startDate) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_time', '<=', $endDate);
        }

        return $query->selectRaw('direction, COUNT(*) as count')
            ->groupBy('direction')
            ->pluck('count', 'direction')
            ->toArray();
    }

    /**
     * Get duration statistics
     */
    private function getDurationStatistics(Tenant $tenant, ?\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate, array $filters): array
    {
        $query = $this->filterBuilder->buildCallRecordQuery($tenant, $filters)
            ->whereNotNull('duration')
            ->where('duration', '>', 0);

        if ($startDate) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_time', '<=', $endDate);
        }

        $stats = $query->selectRaw('
                COUNT(*) as count,
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                STDDEV(duration) as std_duration
            ')
            ->first();

        return [
            'count' => (int) ($stats->count ?? 0),
            'average' => (int) ($stats->avg_duration ?? 0),
            'minimum' => (int) ($stats->min_duration ?? 0),
            'maximum' => (int) ($stats->max_duration ?? 0),
            'standard_deviation' => (float) ($stats->std_duration ?? 0),
        ];
    }

    /**
     * Get agent statistics
     */
    private function getAgentStatistics(Tenant $tenant, ?\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate, array $filters): array
    {
        $query = $this->filterBuilder->buildCallRecordQuery($tenant, $filters)
            ->whereNotNull('agent_id');

        if ($startDate) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_time', '<=', $endDate);
        }

        return $query->selectRaw('agent_id, COUNT(*) as call_count')
            ->groupBy('agent_id')
            ->orderBy('call_count', 'desc')
            ->limit(10)
            ->with('agent:id,name')
            ->get()
            ->map(function ($stat) {
                return [
                    'agent_id' => $stat->agent_id,
                    'agent_name' => $stat->agent?->name ?? 'Unknown',
                    'call_count' => $stat->call_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get hourly distribution
     */
    private function getHourlyDistribution(Tenant $tenant, ?\Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate, array $filters): array
    {
        $query = $this->filterBuilder->buildCallRecordQuery($tenant, $filters);

        if ($startDate) {
            $query->where('start_time', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('start_time', '<=', $endDate);
        }

        return $query->selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
    }
}