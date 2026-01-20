<?php

namespace App\Jobs;

use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Process Export Job
 *
 * Handles background processing of data exports.
 * Supports CSV and JSON formats with email notifications.
 */
class ProcessExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes
    public array $jobData;

    public function __construct(array $jobData)
    {
        $this->jobData = $jobData;
    }

    /**
     * Execute the job
     */
    public function handle(ExportService $exportService): void
    {
        Log::info('Starting export job', [
            'export_id' => $this->jobData['export_id'],
            'format' => $this->jobData['format'],
            'tenant_id' => $this->jobData['tenant_id'],
        ]);

        try {
            // Process the export
            $result = $exportService->processExport($this->jobData);

            // Store metadata
            $metadataPath = 'exports/' . $result['export_id'] . '/metadata.json';
            Storage::put($metadataPath, json_encode($result));

            Log::info('Export job completed successfully', [
                'export_id' => $result['export_id'],
                'record_count' => $result['record_count'],
                'file_size' => $result['size'],
                'filename' => $result['filename'],
            ]);

            // Send email notification if requested
            if (!empty($this->jobData['email'])) {
                $this->sendCompletionEmail($result);
            }

        } catch (Throwable $e) {
            Log::error('Export job failed', [
                'export_id' => $this->jobData['export_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Store error metadata
            $errorData = [
                'export_id' => $this->jobData['export_id'],
                'status' => 'failed',
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ];

            $metadataPath = 'exports/' . $this->jobData['export_id'] . '/metadata.json';
            Storage::put($metadataPath, json_encode($errorData));

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Export job failed permanently', [
            'export_id' => $this->jobData['export_id'],
            'error' => $exception->getMessage(),
        ]);

        // Send failure notification if email was provided
        if (!empty($this->jobData['email'])) {
            $this->sendFailureEmail($exception);
        }
    }

    /**
     * Send completion email notification
     */
    private function sendCompletionEmail(array $result): void
    {
        // This would integrate with Laravel's mail system
        // For now, just log the notification
        Log::info('Export completion notification would be sent', [
            'export_id' => $result['export_id'],
            'email' => $this->jobData['email'],
            'filename' => $result['filename'],
            'size' => $result['size'],
            'record_count' => $result['record_count'],
        ]);
    }

    /**
     * Send failure email notification
     */
    private function sendFailureEmail(Throwable $exception): void
    {
        // This would integrate with Laravel's mail system
        // For now, just log the notification
        Log::error('Export failure notification would be sent', [
            'export_id' => $this->jobData['export_id'],
            'email' => $this->jobData['email'],
            'error' => $exception->getMessage(),
        ]);
    }
}