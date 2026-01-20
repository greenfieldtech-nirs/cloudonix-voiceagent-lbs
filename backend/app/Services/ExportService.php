<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\Tenant;
use App\Jobs\ProcessExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Export Service
 *
 * Handles data export operations with background job processing.
 * Supports CSV and JSON formats with filtering and field selection.
 */
class ExportService
{
    protected array $defaultCallFields = [
        'id',
        'session_id',
        'call_id',
        'direction',
        'from_number',
        'to_number',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'voice_agent_id',
        'voice_agent_name',
        'routing_type',
        'group_id',
        'group_name',
    ];

    /**
     * Queue an export job
     */
    public function queueExport(
        Tenant $tenant,
        string $format,
        array $filters = [],
        array $fields = [],
        string $email = null
    ): string {
        $exportId = Str::uuid()->toString();

        $jobData = [
            'export_id' => $exportId,
            'tenant_id' => $tenant->id,
            'format' => $format,
            'filters' => $filters,
            'fields' => $fields,
            'email' => $email,
        ];

        // Queue the export job
        ProcessExport::dispatch($jobData);

        return $exportId;
    }

    /**
     * Process export synchronously (for small datasets)
     */
    public function processExport(array $jobData): array
    {
        $tenant = Tenant::findOrFail($jobData['tenant_id']);
        $format = $jobData['format'];
        $filters = $jobData['filters'];
        $fields = $jobData['fields'];

        // Build query
        $query = $this->buildExportQuery($tenant, $filters);

        // Get data
        $data = $query->get()->toArray();

        // Format data
        if ($format === 'csv') {
            $content = $this->formatAsCsv($data, $fields);
            $filename = 'call_sessions_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $mimeType = 'text/csv';
        } else {
            $content = $this->formatAsJson($data, $fields);
            $filename = 'call_sessions_' . now()->format('Y-m-d_H-i-s') . '.json';
            $mimeType = 'application/json';
        }

        // Store file
        $path = 'exports/' . $jobData['export_id'] . '/' . $filename;
        Storage::put($path, $content);

        return [
            'export_id' => $jobData['export_id'],
            'filename' => $filename,
            'path' => $path,
            'size' => strlen($content),
            'record_count' => count($data),
            'format' => $format,
            'mime_type' => $mimeType,
            'completed_at' => now(),
        ];
    }

    /**
     * Build the export query with filters
     */
    private function buildExportQuery(Tenant $tenant, array $filters)
    {
        $query = CallSession::where('tenant_id', $tenant->id)
            ->with(['voiceAgent:id,name', 'tenant:id,name'])
            ->select([
                'id',
                'session_id',
                'call_id',
                'direction',
                'from_number',
                'to_number',
                'status',
                'started_at',
                'ended_at',
                'voice_agent_id',
                'metadata',
                'created_at',
                'updated_at',
            ]);

        // Apply filters
        if (isset($filters['start_date'])) {
            $query->where('started_at', '>=', Carbon::parse($filters['start_date']));
        }

        if (isset($filters['end_date'])) {
            $query->where('started_at', '<=', Carbon::parse($filters['end_date']));
        }

        if (isset($filters['status']) && is_array($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (isset($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (isset($filters['voice_agent_id'])) {
            $query->where('voice_agent_id', $filters['voice_agent_id']);
        }

        if (isset($filters['routing_type'])) {
            $query->where('metadata->routing_type', $filters['routing_type']);
        }

        // Order by most recent first
        $query->orderBy('started_at', 'desc');

        return $query;
    }

    /**
     * Format data as CSV
     */
    private function formatAsCsv(array $data, array $fields): string
    {
        $selectedFields = empty($fields) ? $this->defaultCallFields : $fields;

        // Add headers
        $csv = implode(',', array_map(function ($field) {
            return '"' . str_replace('"', '""', $field) . '"';
        }, $selectedFields)) . "\n";

        // Add data rows
        foreach ($data as $row) {
            $values = [];
            foreach ($selectedFields as $field) {
                $value = $this->getFieldValue($row, $field);
                $values[] = '"' . str_replace('"', '""', (string) $value) . '"';
            }
            $csv .= implode(',', $values) . "\n";
        }

        return $csv;
    }

    /**
     * Format data as JSON
     */
    private function formatAsJson(array $data, array $fields): string
    {
        $selectedFields = empty($fields) ? $this->defaultCallFields : $fields;

        $formattedData = array_map(function ($row) use ($selectedFields) {
            $formattedRow = [];
            foreach ($selectedFields as $field) {
                $formattedRow[$field] = $this->getFieldValue($row, $field);
            }
            return $formattedRow;
        }, $data);

        return json_encode($formattedData, JSON_PRETTY_PRINT);
    }

    /**
     * Get field value from row data
     */
    private function getFieldValue(array $row, string $field)
    {
        switch ($field) {
            case 'voice_agent_name':
                return $row['voice_agent']['name'] ?? null;

            case 'duration_seconds':
                if ($row['started_at'] && $row['ended_at']) {
                    return Carbon::parse($row['started_at'])->diffInSeconds(Carbon::parse($row['ended_at']));
                }
                return null;

            case 'routing_type':
                return $row['metadata']['routing_type'] ?? null;

            case 'group_id':
                return $row['metadata']['group_id'] ?? null;

            case 'group_name':
                // This would require additional query, simplified for now
                return null;

            default:
                return $row[$field] ?? null;
        }
    }

    /**
     * Get export status
     */
    public function getExportStatus(string $exportId): ?array
    {
        $files = Storage::files('exports/' . $exportId);

        if (empty($files)) {
            return null; // Export not completed or doesn't exist
        }

        $file = $files[0];
        $metadata = json_decode(Storage::get('exports/' . $exportId . '/metadata.json'), true);

        return [
            'export_id' => $exportId,
            'status' => 'completed',
            'filename' => basename($file),
            'size' => Storage::size($file),
            'url' => Storage::url($file),
            'completed_at' => $metadata['completed_at'] ?? null,
            'record_count' => $metadata['record_count'] ?? null,
        ];
    }

    /**
     * Clean up old export files
     */
    public function cleanupOldExports(int $daysOld = 7): int
    {
        $deletedCount = 0;
        $directories = Storage::directories('exports');

        foreach ($directories as $directory) {
            $files = Storage::files($directory);
            $isOld = true;

            foreach ($files as $file) {
                $lastModified = Storage::lastModified($file);
                if ($lastModified > now()->subDays($daysOld)->timestamp) {
                    $isOld = false;
                    break;
                }
            }

            if ($isOld) {
                Storage::deleteDirectory($directory);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Get available export fields
     */
    public function getAvailableFields(): array
    {
        return [
            'basic' => [
                'id',
                'session_id',
                'call_id',
                'direction',
                'status',
                'started_at',
                'ended_at',
            ],
            'numbers' => [
                'from_number',
                'to_number',
            ],
            'agents' => [
                'voice_agent_id',
                'voice_agent_name',
            ],
            'routing' => [
                'routing_type',
                'group_id',
                'group_name',
            ],
            'timing' => [
                'duration_seconds',
                'created_at',
                'updated_at',
            ],
            'metadata' => [
                'metadata', // Full metadata JSON
            ],
        ];
    }

    /**
     * Validate export parameters
     */
    public function validateExportParameters(string $format, array $filters, array $fields): array
    {
        $errors = [];

        // Validate format
        if (!in_array($format, ['csv', 'json'])) {
            $errors[] = 'Format must be csv or json';
        }

        // Validate date filters
        if (isset($filters['start_date']) && !strtotime($filters['start_date'])) {
            $errors[] = 'Invalid start_date format';
        }

        if (isset($filters['end_date']) && !strtotime($filters['end_date'])) {
            $errors[] = 'Invalid end_date format';
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            if (strtotime($filters['start_date']) > strtotime($filters['end_date'])) {
                $errors[] = 'Start date cannot be after end date';
            }
        }

        // Validate status filter
        $validStatuses = ['ringing', 'connected', 'completed', 'failed', 'busy', 'noanswer'];
        if (isset($filters['status']) && is_array($filters['status'])) {
            foreach ($filters['status'] as $status) {
                if (!in_array($status, $validStatuses)) {
                    $errors[] = "Invalid status: {$status}";
                }
            }
        }

        // Validate direction filter
        if (isset($filters['direction']) && !in_array($filters['direction'], ['inbound', 'outbound'])) {
            $errors[] = 'Direction must be inbound or outbound';
        }

        // Validate fields
        $availableFields = array_merge(...array_values($this->getAvailableFields()));
        foreach ($fields as $field) {
            if (!in_array($field, $availableFields)) {
                $errors[] = "Invalid field: {$field}";
            }
        }

        return $errors;
    }
}