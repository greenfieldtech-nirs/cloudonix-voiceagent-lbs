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
use Illuminate\Validation\Rule;

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

            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string',
                'event_type' => 'required|string',
                'event_id' => 'required|string',
                'occurred_at' => 'required|date',
                'data' => 'required|array',
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid session update webhook payload', [
                    'errors' => $validator->errors(),
                    'payload' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid payload', 400);
            }

            $data = $validator->validated();

            // Find the call session
            $callSession = CallSession::where('session_id', $data['session_id'])->first();

            if (! $callSession) {
                Log::warning('Call session not found for webhook', [
                    'session_id' => $data['session_id'],
                    'event_type' => $data['event_type'],
                ]);

                return response('Session not found', 404);
            }

            // Store the event (idempotent - will skip if event_id already exists)
            $callEvent = CallEvent::firstOrCreate(
                ['event_id' => $data['event_id']],
                [
                    'tenant_id' => $callSession->tenant_id,
                    'call_session_id' => $callSession->id,
                    'event_type' => $data['event_type'],
                    'payload' => $data['data'],
                    'headers' => $request->headers->all(),
                    'occurred_at' => $data['occurred_at'],
                    'processing_status' => 'completed',
                ]
            );

            // Update call session state based on event type
            $this->updateCallSessionFromEvent($callSession, $data);

            Log::info('Session update webhook processed', [
                'session_id' => $data['session_id'],
                'event_type' => $data['event_type'],
                'event_id' => $data['event_id'],
                'tenant_id' => $callSession->tenant_id,
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

            $validator = Validator::make($request->all(), [
                'session_id' => 'required|string',
                'cdr' => 'required|array',
                'cdr.call_id' => 'required|string',
                'cdr.from' => 'required|string',
                'cdr.to' => 'required|string',
                'cdr.direction' => ['required', Rule::in(['inbound', 'outbound'])],
                'cdr.status' => 'required|string',
                'cdr.started_at' => 'nullable|date',
                'cdr.answered_at' => 'nullable|date',
                'cdr.ended_at' => 'nullable|date',
                'cdr.duration' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid CDR webhook payload', [
                    'errors' => $validator->errors(),
                    'payload' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid payload', 400);
            }

            $data = $validator->validated();
            $cdr = $data['cdr'];

            // Find or create the call session
            $callSession = CallSession::firstOrCreate(
                ['session_id' => $data['session_id']],
                [
                    'tenant_id' => 1, // TODO: Determine tenant from webhook context
                    'call_id' => $cdr['call_id'],
                    'direction' => $cdr['direction'],
                    'from_number' => $cdr['from'],
                    'to_number' => $cdr['to'],
                    'status' => $cdr['status'],
                ]
            );

            // Update call session with final CDR data
            $callSession->update([
                'status' => $cdr['status'],
                'started_at' => $cdr['started_at'] ?? $callSession->started_at,
                'answered_at' => $cdr['answered_at'] ?? $callSession->answered_at,
                'ended_at' => $cdr['ended_at'] ?? $callSession->ended_at,
                'duration_seconds' => $cdr['duration'] ?? $callSession->duration_seconds,
                'state' => array_merge($callSession->state ?? [], ['cdr_received' => true]),
                'metadata' => array_merge($callSession->metadata ?? [], [
                    'cdr_data' => $cdr,
                    'webhook_headers' => $request->headers->all(),
                ]),
            ]);

            // Store CDR event
            CallEvent::create([
                'tenant_id' => $callSession->tenant_id,
                'call_session_id' => $callSession->id,
                'event_type' => 'cdr',
                'event_id' => 'cdr_'.$data['session_id'].'_'.now()->timestamp,
                'payload' => $cdr,
                'headers' => $request->headers->all(),
                'occurred_at' => $cdr['ended_at'] ?? now(),
                'processing_status' => 'completed',
            ]);

            Log::info('CDR callback processed', [
                'session_id' => $data['session_id'],
                'call_id' => $cdr['call_id'],
                'status' => $cdr['status'],
                'duration' => $cdr['duration'],
                'tenant_id' => $callSession->tenant_id,
            ]);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('CDR callback failed', [
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
