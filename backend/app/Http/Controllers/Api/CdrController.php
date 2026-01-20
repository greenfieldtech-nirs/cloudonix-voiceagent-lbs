<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CdrLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CdrController extends Controller
{
    /**
     * Get paginated CDR records with filtering
     */
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'from' => 'nullable|string|max:255',
            'to' => 'nullable|string|max:255',
            'disposition' => ['nullable', Rule::in(['ANSWER', 'BUSY', 'CANCEL', 'FAILED', 'CONGESTION', 'NOANSWER'])],
            'token' => 'nullable|string|max:255',
            'start_date' => 'nullable|date|before_or_equal:end_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'sort_by' => 'nullable|string|in:id,call_id,start_time,duration_seconds,disposition,created_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Build query with tenant scoping
        $query = CdrLog::forTenant($request->user()->tenant->id);

        // Apply filters
        if (! empty($validated['from'])) {
            $query->where('from_number', 'like', '%'.$validated['from'].'%');
        }

        if (! empty($validated['to'])) {
            $query->where('to_number', 'like', '%'.$validated['to'].'%');
        }

        if (! empty($validated['disposition'])) {
            $query->where('disposition', $validated['disposition']);
        }

        if (! empty($validated['token'])) {
            $query->where('session_token', 'like', '%'.$validated['token'].'%');
        }

        // Date range filtering
        if (! empty($validated['start_date']) || ! empty($validated['end_date'])) {
            $startDate = $validated['start_date'] ?? '1970-01-01';
            $endDate = $validated['end_date'] ?? now()->toDateString();
            $query->whereBetween('start_time', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        }

        // Time range filtering (within the date range)
        if (! empty($validated['start_time']) || ! empty($validated['end_time'])) {
            $startTime = $validated['start_time'] ?? '00:00';
            $endTime = $validated['end_time'] ?? '23:59';

            $query->whereRaw('TIME(start_time) BETWEEN ? AND ?', [$startTime, $endTime]);
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'start_time';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $validated['per_page'] ?? 50;
        $paginatedResults = $query->paginate($perPage);

        // Build applied filters summary
        $appliedFilters = [];
        if (! empty($validated['from'])) {
            $appliedFilters['from'] = $validated['from'];
        }
        if (! empty($validated['to'])) {
            $appliedFilters['to'] = $validated['to'];
        }
        if (! empty($validated['disposition'])) {
            $appliedFilters['disposition'] = $validated['disposition'];
        }
        if (! empty($validated['token'])) {
            $appliedFilters['token'] = $validated['token'];
        }
        if (! empty($validated['start_date'])) {
            $appliedFilters['start_date'] = $validated['start_date'];
        }
        if (! empty($validated['end_date'])) {
            $appliedFilters['end_date'] = $validated['end_date'];
        }
        if (! empty($validated['start_time'])) {
            $appliedFilters['start_time'] = $validated['start_time'];
        }
        if (! empty($validated['end_time'])) {
            $appliedFilters['end_time'] = $validated['end_time'];
        }

        return response()->json([
            'data' => $paginatedResults->items(),
            'meta' => [
                'current_page' => $paginatedResults->currentPage(),
                'per_page' => $paginatedResults->perPage(),
                'total' => $paginatedResults->total(),
                'last_page' => $paginatedResults->lastPage(),
                'from' => $paginatedResults->firstItem(),
                'to' => $paginatedResults->lastItem(),
            ],
            'filters_applied' => $appliedFilters,
        ]);
    }

    /**
     * Get single CDR record
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $cdr = CdrLog::forTenant($request->user()->tenant->id)
            ->findOrFail($id);

        return response()->json([
            'data' => $cdr,
        ]);
    }

    /**
     * Export CDR records to CSV
     */
    public function export(Request $request): JsonResponse
    {
        // For now, return a placeholder response
        // Full implementation will be in Phase 7
        return response()->json([
            'message' => 'Export functionality will be implemented in Phase 7',
            'status' => 'not_implemented',
        ]);
    }
}
