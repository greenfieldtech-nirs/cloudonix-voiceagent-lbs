<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Export Controller
 *
 * Handles data export requests and status checking.
 * Supports CSV and JSON formats with background processing.
 */
class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Request a new export
     */
    public function request(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('createExports', $tenant);

        $validated = $request->validate([
            'format' => 'required|in:csv,json',
            'filters' => 'nullable|array',
            'filters.start_date' => 'nullable|date',
            'filters.end_date' => 'nullable|date',
            'filters.status' => 'nullable|array',
            'filters.status.*' => 'in:ringing,connected,completed,failed,busy,noanswer',
            'filters.direction' => 'nullable|in:inbound,outbound',
            'filters.voice_agent_id' => 'nullable|integer|exists:voice_agents,id',
            'filters.routing_type' => 'nullable|string',
            'fields' => 'nullable|array',
            'fields.*' => 'string',
            'email' => 'nullable|email',
        ]);

        // Validate export parameters
        $validationErrors = $this->exportService->validateExportParameters(
            $validated['format'],
            $validated['filters'] ?? [],
            $validated['fields'] ?? []
        );

        if (!empty($validationErrors)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validationErrors,
            ], 422);
        }

        // Queue the export
        $exportId = $this->exportService->queueExport(
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
                'estimated_completion' => now()->addMinutes(5)->toISOString(), // Rough estimate
            ],
            'meta' => [
                'tenant_id' => $tenant->id,
            ],
        ], 202); // Accepted
    }

    /**
     * Get export status
     */
    public function status(Request $request, Tenant $tenant, string $exportId): JsonResponse
    {
        $this->authorize('viewExports', $tenant);

        $status = $this->exportService->getExportStatus($exportId);

        if (!$status) {
            return response()->json([
                'message' => 'Export not found or not yet completed',
                'data' => [
                    'export_id' => $exportId,
                    'status' => 'processing',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $status,
            'meta' => [
                'tenant_id' => $tenant->id,
            ],
        ]);
    }

    /**
     * Download export file
     */
    public function download(Request $request, Tenant $tenant, string $exportId)
    {
        $this->authorize('downloadExports', $tenant);

        $status = $this->exportService->getExportStatus($exportId);

        if (!$status || $status['status'] !== 'completed') {
            return response()->json([
                'message' => 'Export not found or not completed',
            ], 404);
        }

        // Return file download
        return response()->download(
            Storage::path($status['path']),
            $status['filename'],
            ['Content-Type' => $status['mime_type']]
        );
    }

    /**
     * Get available export fields
     */
    public function fields(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewExports', $tenant);

        $fields = $this->exportService->getAvailableFields();

        return response()->json([
            'data' => $fields,
            'meta' => [
                'tenant_id' => $tenant->id,
                'categories' => array_keys($fields),
                'total_fields' => array_sum(array_map('count', $fields)),
            ],
        ]);
    }

    /**
     * Clean up old export files (admin only)
     */
    public function cleanup(Request $request): JsonResponse
    {
        $this->authorize('manageExports', Tenant::class);

        $daysOld = $request->input('days_old', 7);
        $deletedCount = $this->exportService->cleanupOldExports($daysOld);

        return response()->json([
            'message' => 'Cleanup completed',
            'data' => [
                'directories_deleted' => $deletedCount,
                'days_old_threshold' => $daysOld,
            ],
        ]);
    }
}