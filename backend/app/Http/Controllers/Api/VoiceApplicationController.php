<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallEvent;
use App\Models\CallSession;
use App\Models\Tenant;
use App\Models\VoiceApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * VoiceApplicationController handles webhook endpoints for Cloudonix Voice Applications
 *
 * This controller processes:
 * - Voice Application requests (initial call handling)
 * - Session update webhooks (status changes)
 * - CDR callbacks (call detail records)
 */
class VoiceApplicationController extends Controller
{
    /**
     * Handle initial Voice Application request from Cloudonix
     *
     * This endpoint receives the initial HTTP request when a call is made to a voice application.
     * It should return CXML instructions for how Cloudonix should handle the call.
     */
    public function handleApplication(Request $request, $applicationId)
    {
        try {
            // Basic webhook validation - check for Cloudonix headers
            $this->validateWebhookRequest($request);

            // Find the voice application by provider_app_id
            $voiceApplication = VoiceApplication::where('provider_app_id', $applicationId)
                ->where('is_active', true)
                ->first();

            if (! $voiceApplication) {
                Log::warning('Voice application not found', [
                    'application_id' => $applicationId,
                    'request_data' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Application not found', 404);
            }

            // Log the incoming request
            Log::info('Voice application request received', [
                'application_id' => $applicationId,
                'tenant_id' => $voiceApplication->tenant_id,
                'request_data' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Store the call session if this is a new call
            $this->storeOrUpdateCallSession($voiceApplication, $request);

            // Return the CXML definition for this application
            $cxml = $voiceApplication->cxml_definition;

            if (is_array($cxml)) {
                // If stored as array, convert back to XML string
                $cxml = $this->arrayToXml($cxml);
            }

            return response($cxml, 200, ['Content-Type' => 'application/xml']);

        } catch (\Exception $e) {
            Log::error('Voice application request failed', [
                'application_id' => $applicationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a simple hangup response on error
            return response('<?xml version="1.0" encoding="UTF-8"?><Response><Hangup/></Response>', 200, [
                'Content-Type' => 'application/xml',
            ]);
        }
    }

    /**
     * Handle session update webhooks from Cloudonix
     *
     * This endpoint receives session status updates and other call events.
     */
    public function handleSessionUpdate(Request $request)
    {
        try {
            // Basic webhook validation
            $this->validateWebhookRequest($request);

            // Validate Cloudonix webhook payload structure
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer', // Cloudonix session ID
                'domain' => 'required|string', // Domain for tenant resolution
                'token' => 'required|string', // Unique session token
                'status' => 'required|string', // Call status
                'callerId' => 'nullable|string',
                'destination' => 'nullable|string',
                'direction' => 'nullable|string',
                'createdAt' => 'nullable|date',
                'modifiedAt' => 'nullable|date',
                'callStartTime' => 'nullable|integer',
                'answerTime' => 'nullable|date',
                'vappServer' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid Cloudonix session update webhook payload', [
                    'errors' => $validator->errors(),
                    'payload' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid payload', 400);
            }

            $webhookData = $validator->validated();

            // Resolve tenant from domain
            $tenant = \App\Models\Tenant::where('domain', $webhookData['domain'])->first();
            if (! $tenant) {
                Log::warning('Tenant not found for domain in webhook', [
                    'domain' => $webhookData['domain'],
                    'session_id' => $webhookData['id'],
                ]);

                return response('Tenant not found', 404);
            }

            // Map Cloudonix status to internal status
            $internalStatus = $this->mapCloudonixStatus($webhookData['status']);

            // Calculate duration if we have timestamps
            $durationSeconds = null;
            if ($webhookData['answerTime'] && $webhookData['callStartTime']) {
                $answerTime = strtotime($webhookData['answerTime']);
                $callStartTime = $webhookData['callStartTime'] / 1000; // Convert from milliseconds
                $durationSeconds = max(0, $answerTime - $callStartTime);
            }

            // Upsert call session (single row per token)
            $callSession = CallSession::updateOrCreate(
                ['token' => $webhookData['token']], // Use token as unique identifier
                [
                    'tenant_id' => $tenant->id,
                    'session_id' => (string) $webhookData['id'],
                    'domain' => $webhookData['domain'],
                    'caller_id' => $webhookData['callerId'],
                    'destination' => $webhookData['destination'],
                    'direction' => $webhookData['direction'] ?? 'inbound',
                    'status' => $internalStatus,
                    'vapp_server' => $webhookData['vappServer'],
                    'call_start_time' => $webhookData['callStartTime'] ? now()->createFromTimestampMs($webhookData['callStartTime']) : null,
                    'call_answer_time' => $webhookData['answerTime'] ? now()->parse($webhookData['answerTime']) : null,
                    'answer_time' => $webhookData['answerTime'] ? now()->parse($webhookData['answerTime']) : null,
                    'webhook_created_at' => $webhookData['createdAt'] ? now()->parse($webhookData['createdAt']) : null,
                    'webhook_modified_at' => $webhookData['modifiedAt'] ? now()->parse($webhookData['modifiedAt']) : null,
                    'duration_seconds' => $durationSeconds,
                    'metadata' => $request->all(), // Store complete webhook payload
                ]
            );

            // Store webhook event for audit trail
            CallEvent::create([
                'tenant_id' => $tenant->id,
                'call_session_id' => $callSession->id,
                'event_type' => 'session_update',
                'event_id' => 'session_update_'.$webhookData['token'].'_'.now()->timestamp,
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
                'occurred_at' => $webhookData['modifiedAt'] ? now()->parse($webhookData['modifiedAt']) : now(),
                'processing_status' => 'completed',
            ]);

            // Broadcast WebSocket event for real-time updates
            $this->broadcastCallUpdate($callSession);

            Log::info('Cloudonix session update webhook processed', [
                'session_id' => $webhookData['id'],
                'token' => $webhookData['token'],
                'domain' => $webhookData['domain'],
                'status' => $webhookData['status'],
                'internal_status' => $internalStatus,
                'tenant_id' => $tenant->id,
                'duration_seconds' => $durationSeconds,
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Session update webhook failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Internal server error', 500);
        }
    }

    /**
     * Handle CDR (Call Detail Record) callbacks from Cloudonix
     *
     * This endpoint receives final call detail records when calls complete.
     */
    public function handleCdrCallback(Request $request)
    {
        try {
            // Basic webhook validation
            $this->validateWebhookRequest($request);

            // Validate Cloudonix CDR webhook payload structure
            $validator = Validator::make($request->all(), [
                'call_id' => 'required|string',
                'domain' => 'required|string',
                'from' => 'nullable|string',
                'to' => 'nullable|string',
                'disposition' => 'required|string',
                'duration' => 'nullable|integer',
                'billsec' => 'nullable|integer',
                'timestamp' => 'nullable|integer',
                'session' => 'nullable|array',
                'session.token' => 'nullable|string',
                'session.callStartTime' => 'nullable|integer',
                'session.callAnswerTime' => 'nullable|integer',
                'session.callEndTime' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid Cloudonix CDR webhook payload', [
                    'errors' => $validator->errors(),
                    'payload' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid payload', 400);
            }

            $cdrData = $validator->validated();

            // Resolve tenant from domain
            $tenant = \App\Models\Tenant::where('domain', $cdrData['domain'])->first();
            if (! $tenant) {
                Log::warning('Tenant not found for domain in CDR webhook', [
                    'domain' => $cdrData['domain'],
                    'call_id' => $cdrData['call_id'],
                ]);

                return response('Tenant not found', 404);
            }

            // Map Cloudonix disposition to standardized disposition
            $disposition = $this->mapCdrDisposition($cdrData['disposition']);

            // Determine direction from session data if available
            $direction = 'inbound'; // Default
            if (isset($cdrData['session'])) {
                // Try to determine direction from session data
                // This might need adjustment based on actual Cloudonix data
                $direction = 'inbound'; // Placeholder
            }

            // Prepare CDR data for storage
            $cdrRecordData = [
                'tenant_id' => $tenant->id,
                'call_id' => $cdrData['call_id'],
                'session_token' => $cdrData['session']['token'] ?? null,
                'from_number' => $cdrData['from'],
                'to_number' => $cdrData['to'],
                'direction' => $direction,
                'disposition' => $disposition,
                'start_time' => isset($cdrData['session']['callStartTime'])
                    ? now()->createFromTimestampMs($cdrData['session']['callStartTime'])
                    : null,
                'answer_time' => isset($cdrData['session']['callAnswerTime'])
                    ? now()->createFromTimestampMs($cdrData['session']['callAnswerTime'])
                    : null,
                'end_time' => isset($cdrData['session']['callEndTime'])
                    ? now()->createFromTimestampMs($cdrData['session']['callEndTime'])
                    : null,
                'duration_seconds' => $cdrData['duration'] ?? null,
                'billsec' => $cdrData['billsec'] ?? null,
                'domain' => $cdrData['domain'],
                'subscriber' => $cdrData['subscriber'] ?? null,
                'cx_trunk_id' => $cdrData['cx_trunk_id'] ?? null,
                'application' => $cdrData['application'] ?? null,
                'route' => $cdrData['route'] ?? null,
                'vapp_server' => $cdrData['vapp_server'] ?? $cdrData['session']['vappServer'] ?? null,
                'raw_cdr' => $request->all(), // Store complete webhook payload
            ];

            // Store CDR record (upsert to prevent duplicates)
            $cdrLog = \App\Models\CdrLog::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'call_id' => $cdrData['call_id'],
                ],
                $cdrRecordData
            );

            Log::info('Cloudonix CDR webhook processed', [
                'call_id' => $cdrData['call_id'],
                'disposition' => $cdrData['disposition'],
                'mapped_disposition' => $disposition,
                'domain' => $cdrData['domain'],
                'tenant_id' => $tenant->id,
                'duration' => $cdrData['duration'],
                'cdr_log_id' => $cdrLog->id,
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('CDR webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Internal server error', 500);
        }
    }

    /**
     * Store or update call session from initial voice application request
     */
    private function storeOrUpdateCallSession(VoiceApplication $voiceApplication, Request $request)
    {
        // Extract call data from the request (Cloudonix voice application request format)
        $callData = [
            'session_id' => $request->input('CallSid') ?: 'unknown_'.now()->timestamp,
            'call_id' => $request->input('CallSid'),
            'direction' => $request->input('Direction', 'inbound'),
            'from_number' => $request->input('From'),
            'to_number' => $request->input('To'),
            'status' => 'ringing',
        ];

        $callSession = CallSession::firstOrCreate(
            ['session_id' => $callData['session_id']],
            array_merge($callData, [
                'tenant_id' => $voiceApplication->tenant_id,
                'started_at' => now(),
                'state' => ['initial_request' => true],
                'metadata' => [
                    'voice_application_id' => $voiceApplication->id,
                    'request_data' => $request->all(),
                    'headers' => $request->headers->all(),
                ],
            ])
        );

        return $callSession;
    }

    /**
     * Update call session state based on webhook event
     */
    private function updateCallSessionFromEvent(CallSession $callSession, array $eventData)
    {
        $updates = [];

        switch ($eventData['event_type']) {
            case 'answered':
                $updates['status'] = 'answered';
                $updates['answered_at'] = $eventData['occurred_at'];
                break;

            case 'completed':
            case 'ended':
                $updates['status'] = 'completed';
                $updates['ended_at'] = $eventData['occurred_at'];
                if (isset($eventData['data']['duration'])) {
                    $updates['duration_seconds'] = $eventData['data']['duration'];
                }
                break;

            case 'failed':
                $updates['status'] = 'failed';
                $updates['ended_at'] = $eventData['occurred_at'];
                break;

            case 'busy':
                $updates['status'] = 'busy';
                $updates['ended_at'] = $eventData['occurred_at'];
                break;
        }

        if (! empty($updates)) {
            $callSession->update($updates);
        }
    }

    /**
     * Convert array CXML definition back to XML string
     */
    private function arrayToXml(array $cxmlArray): string
    {
        // Simple conversion - in production you might want a proper XML builder
        // For now, assume it's stored as XML string in the database
        return json_encode($cxmlArray);
    }

    /**
     * Map Cloudonix status to internal status
     */
    private function mapCloudonixStatus(string $cloudonixStatus): string
    {
        $statusMap = [
            'ringing' => 'ringing',
            'connected' => 'connected',  // Keep as connected (not answered)
            'processing' => 'ringing',
            'answer' => 'answered',
            'answered' => 'answered',
            'new' => 'ringing',
            'noanswer' => 'failed',
            'busy' => 'busy',
            'nocredit' => 'failed',
            'cancel' => 'failed',
            'external' => 'failed',
            'error' => 'failed',
            'completed' => 'completed',
            'failed' => 'failed',
        ];

        return $statusMap[$cloudonixStatus] ?? 'ringing'; // Default to ringing
    }

    /**
     * Broadcast call update via WebSocket
     */
    private function broadcastCallUpdate(CallSession $callSession): void
    {
        try {
            // TODO: Implement WebSocket broadcasting in Phase 4
            // For now, just log that we would broadcast
            Log::info('Call update broadcast (WebSocket not yet implemented)', [
                'session_id' => $callSession->session_id,
                'token' => $callSession->token,
                'status' => $callSession->status,
                'tenant_id' => $callSession->tenant_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast call update', [
                'session_id' => $callSession->session_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map Cloudonix CDR disposition to standardized disposition
     */
    private function mapCdrDisposition(string $cloudonixDisposition): string
    {
        $dispositionMap = [
            'CONNECTED' => 'ANSWER',
            'ANSWERED' => 'ANSWER',
            'ANSWER' => 'ANSWER',
            'BUSY' => 'BUSY',
            'CANCEL' => 'CANCEL',
            'FAILED' => 'FAILED',
            'CONGESTION' => 'CONGESTION',
            'NOANSWER' => 'NOANSWER',
            'NO ANSWER' => 'NOANSWER',
            // Add other mappings as needed
        ];

        return $dispositionMap[strtoupper($cloudonixDisposition)] ?? 'FAILED'; // Default to FAILED
    }

    /**
     * Validate incoming webhook request
     *
     * Performs basic validation to ensure the request appears to be from Cloudonix
     */
    private function validateWebhookRequest(Request $request): void
    {
        // Check for Cloudonix-specific headers (adjust based on actual Cloudonix headers)
        $cloudonixHeaders = [
            'x-cloudonix-signature', // If they provide signature validation
            'x-cloudonix-request-id',
            'user-agent', // Should contain Cloudonix
        ];

        $hasCloudonixHeader = false;
        foreach ($cloudonixHeaders as $header) {
            if ($request->hasHeader($header)) {
                $hasCloudonixHeader = true;
                break;
            }
        }

        // Check User-Agent for Cloudonix
        $userAgent = $request->userAgent();
        if ($userAgent && str_contains(strtolower($userAgent), 'cloudonix')) {
            $hasCloudonixHeader = true;
        }

        if (! $hasCloudonixHeader) {
            Log::warning('Webhook request missing Cloudonix headers', [
                'headers' => $request->headers->all(),
                'user_agent' => $userAgent,
                'ip' => $request->ip(),
            ]);

            // For now, we'll allow requests without strict validation
            // In production, you should implement proper signature validation
            // throw new \Exception('Invalid webhook source');
        }

        // Rate limiting could be added here
        // You might want to implement per-tenant rate limiting for webhooks
    }
}
