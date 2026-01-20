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
use App\Services\CloudonixWebhookValidator;
use App\Services\PatternMatchingService;
use App\Services\RoutingDecisionService;
use App\Services\OutboundRoutingEngine;

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
            // Comprehensive webhook validation using service
            $validator = app(CloudonixWebhookValidator::class);
            $validationReport = $validator->generateValidationReport($request);

            if (!$validationReport['overall_valid']) {
                Log::warning('Invalid Cloudonix webhook request', [
                    'application_id' => $applicationId,
                    'validation_report' => $validationReport,
                    'request_data' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid webhook request', 400);
            }

            // Extract and validate tenant
            $tenantDomain = $validationReport['tenant_domain'];
            if (!$tenantDomain) {
                Log::warning('No tenant domain found in webhook', [
                    'application_id' => $applicationId,
                    'validation_report' => $validationReport,
                ]);

                return response('Tenant domain required', 400);
            }

            $tenantValidation = $validator->validateTenantDomain($tenantDomain);
            if (!$tenantValidation['valid']) {
                Log::warning('Invalid tenant domain in webhook', [
                    'application_id' => $applicationId,
                    'domain' => $tenantDomain,
                    'error' => $tenantValidation['error'],
                ]);

                return response('Invalid tenant domain', 400);
            }

            $tenant = $tenantValidation['tenant'];

            // Validate voice application request payload
            $payloadValidation = $validator->validateVoiceApplicationRequest($request);
            if (!$payloadValidation['valid']) {
                Log::warning('Invalid voice application request payload', [
                    'application_id' => $applicationId,
                    'tenant_id' => $tenant->id,
                    'validation_errors' => $payloadValidation['errors'],
                    'request_data' => $request->all(),
                ]);

                return response('Invalid request payload', 400);
            }

            $validatedData = $payloadValidation['data'];

            // Check if this is an outbound call
            $outboundRoutingEngine = app(OutboundRoutingEngine::class);
            $isOutboundCall = $outboundRoutingEngine->isOutboundCall($tenant, $validatedData);

            if ($isOutboundCall) {
                // Handle outbound call routing
                Log::info('Detected outbound call, executing outbound routing', [
                    'application_id' => $applicationId,
                    'tenant_id' => $tenant->id,
                    'caller_id' => $validatedData['From'] ?? null,
                    'destination' => $validatedData['To'] ?? null,
                ]);

                $routingResult = $this->executeOutboundRouting($tenant, $validatedData);
            } else {
                // Handle inbound call routing
                Log::info('Detected inbound call, executing inbound routing', [
                    'application_id' => $applicationId,
                    'tenant_id' => $tenant->id,
                    'caller_id' => $validatedData['From'] ?? null,
                    'destination' => $validatedData['To'] ?? null,
                ]);

                // Find the voice application by provider_app_id for inbound calls
                $voiceApplication = VoiceApplication::where('provider_app_id', $applicationId)
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->first();

                if (! $voiceApplication) {
                    Log::warning('Voice application not found for inbound call', [
                        'application_id' => $applicationId,
                        'tenant_id' => $tenant->id,
                        'request_data' => $validatedData,
                        'validation_report' => $validationReport,
                    ]);

                    return response('Application not found', 404);
                }

                // Store the call session for inbound calls
                $this->storeOrUpdateCallSession($voiceApplication, $request);

                // Execute dynamic inbound routing decision
                $routingResult = $this->executeDynamicRouting($tenant, $validatedData);
            }

            // Log routing decision
            Log::info('Voice application routing completed', [
                'application_id' => $applicationId,
                'tenant_id' => $tenant->id,
                'routing_success' => $routingResult['success'],
                'routing_type' => $routingResult['routing_type'] ?? 'unknown',
                'cxml_length' => strlen($routingResult['cxml']),
            ]);

            return response($routingResult['cxml'], 200, ['Content-Type' => 'application/xml']);

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
            // Comprehensive webhook validation using service
            $validator = app(CloudonixWebhookValidator::class);
            $validationReport = $validator->generateValidationReport($request);

            if (!$validationReport['overall_valid']) {
                Log::warning('Invalid Cloudonix session update webhook', [
                    'validation_report' => $validationReport,
                    'request_data' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid webhook request', 400);
            }

            // Validate session update payload
            $payloadValidation = $validator->validateSessionUpdate($request);
            if (!$payloadValidation['valid']) {
                Log::warning('Invalid Cloudonix session update payload', [
                    'validation_errors' => $payloadValidation['errors'],
                    'request_data' => $request->all(),
                    'validation_report' => $validationReport,
                ]);

                return response('Invalid payload', 400);
            }

            $webhookData = $payloadValidation['data'];

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
            // Comprehensive webhook validation using service
            $validator = app(CloudonixWebhookValidator::class);
            $validationReport = $validator->generateValidationReport($request);

            if (!$validationReport['overall_valid']) {
                Log::warning('Invalid Cloudonix CDR webhook', [
                    'validation_report' => $validationReport,
                    'request_data' => $request->all(),
                    'headers' => $request->headers->all(),
                ]);

                return response('Invalid webhook request', 400);
            }

            // Validate CDR payload
            $payloadValidation = $validator->validateCdrCallback($request);
            if (!$payloadValidation['valid']) {
                Log::warning('Invalid Cloudonix CDR payload', [
                    'validation_errors' => $payloadValidation['errors'],
                    'request_data' => $request->all(),
                    'validation_report' => $validationReport,
                ]);

                return response('Invalid payload', 400);
            }

            $cdrData = $payloadValidation['data'];

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
            // Broadcast call status update
            broadcast(new \App\Events\CallRecordUpdated($callSession->callRecord ?? $callSession, [
                'status' => $callSession->status,
                'direction' => $callSession->direction,
                'updated_at' => $callSession->updated_at,
            ]));

            Log::info('Call update broadcasted', [
                'session_id' => $callSession->session_id,
                'status' => $callSession->status,
                'direction' => $callSession->direction,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast call update', [
                'session_id' => $callSession->session_id,
                'error' => $e->getMessage(),
            ]);
        }
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
     * Execute dynamic routing decision for incoming call
     */
    private function executeDynamicRouting(Tenant $tenant, array $callData): array
    {
        try {
            // Get routing services
            $patternMatcher = app(PatternMatchingService::class);
            $routingDecision = app(RoutingDecisionService::class);

            // Evaluate routing rules against call data
            $matchedRule = $patternMatcher->evaluateRules($tenant, $callData);

            if ($matchedRule) {
                // Execute routing decision for matched rule
                return $routingDecision->executeRouting($matchedRule, $callData);
            } else {
                // No rule matched - return hangup
                Log::info('No routing rule matched, returning hangup', [
                    'tenant_id' => $tenant->id,
                    'call_data' => $callData,
                ]);

                $cxmlService = app(CxmlService::class);
                return [
                    'success' => false,
                    'cxml' => $cxmlService->generateHangup(),
                    'routing_type' => 'hangup',
                    'reason' => 'No matching routing rule found',
                    'metadata' => [
                        'hangup_reason' => 'No matching routing rule found',
                    ],
                ];
            }

        } catch (\Exception $e) {
            Log::error('Dynamic routing execution failed', [
                'tenant_id' => $tenant->id,
                'call_data' => $callData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to hangup on error
            $cxmlService = app(CxmlService::class);
            return [
                'success' => false,
                'cxml' => $cxmlService->generateHangup(),
                'routing_type' => 'hangup',
                'reason' => 'Routing execution failed: ' . $e->getMessage(),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Execute outbound routing decision for outbound calls
     */
    private function executeOutboundRouting(Tenant $tenant, array $callData): array
    {
        try {
            // Get outbound routing engine
            $outboundRoutingEngine = app(OutboundRoutingEngine::class);

            // Execute outbound routing evaluation
            $routingResult = $outboundRoutingEngine->evaluateOutboundRouting($tenant, [
                'caller_id' => $callData['From'] ?? null,
                'destination' => $callData['To'] ?? null,
                'direction' => 'outbound',
            ]);

            if ($routingResult['success']) {
                // Generate CXML for outbound routing
                $cxmlService = app(CxmlService::class);

                if (isset($routingResult['selected_trunk'])) {
                    $cxml = $cxmlService->generateOutboundTrunkRoutingFromModel(
                        $routingResult['selected_trunk'],
                        $routingResult['destination'],
                        $routingResult['caller_id']
                    );
                } else {
                    // Fallback to hangup if no trunk selected
                    $cxml = $cxmlService->generateHangup();
                    $routingResult['success'] = false;
                    $routingResult['reason'] = $routingResult['reason'] ?? 'No trunk available';
                }

                $routingResult['cxml'] = $cxml;

            // Store outbound call session
            $callSession = $this->storeOutboundCallSession($tenant, $routingResult);

            // Broadcast real-time event
            broadcast(new \App\Events\CallRecordCreated($callSession));

            } else {
                // Generate hangup CXML for failed routing
                $cxmlService = app(CxmlService::class);
                $routingResult['cxml'] = $cxmlService->generateHangup();
            }

            return $routingResult;

        } catch (\Exception $e) {
            Log::error('Outbound routing execution failed', [
                'tenant_id' => $tenant->id,
                'call_data' => $callData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback to hangup on error
            $cxmlService = app(CxmlService::class);
            return [
                'success' => false,
                'cxml' => $cxmlService->generateHangup(),
                'routing_type' => 'hangup',
                'reason' => 'Outbound routing execution failed: ' . $e->getMessage(),
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Store outbound call session
     */
    private function storeOutboundCallSession(Tenant $tenant, array $routingResult): CallSession
    {
        try {
            $callSession = CallSession::create([
                'tenant_id' => $tenant->id,
                'session_id' => 'outbound_' . uniqid(),
                'call_id' => 'outbound_' . uniqid(),
                'direction' => 'outbound',
                'from_number' => $routingResult['caller_id'],
                'to_number' => $routingResult['destination'],
                'status' => 'ringing',
                'started_at' => now(),
                'state' => ['outbound_routing' => true],
                'metadata' => [
                    'routing_result' => $routingResult,
                    'trunk_id' => $routingResult['selected_trunk']->id ?? null,
                    'trunk_name' => $routingResult['selected_trunk']->name ?? null,
                ],
            ]);

            Log::info('Outbound call session created', [
                'session_id' => $callSession->session_id,
                'tenant_id' => $tenant->id,
                'routing_type' => $routingResult['routing_type'],
            ]);

            return $callSession;

        } catch (\Exception $e) {
            Log::error('Failed to store outbound call session', [
                'tenant_id' => $tenant->id,
                'routing_result' => $routingResult,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

}
