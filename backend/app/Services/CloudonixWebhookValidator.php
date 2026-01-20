<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Cloudonix Webhook Validation Service
 *
 * Validates incoming webhooks from Cloudonix Voice Applications.
 * Ensures requests are properly formatted and contain required data.
 */
class CloudonixWebhookValidator
{
    /**
     * Validation rules for initial voice application requests
     */
    public const VOICE_APPLICATION_REQUEST_RULES = [
        'CallSid' => 'required|string|max:255',
        'From' => 'required|string|max:20',
        'To' => 'required|string|max:20',
        'Direction' => 'nullable|string|in:inbound,outbound',
        'CallerName' => 'nullable|string|max:255',
        'ForwardedFrom' => 'nullable|string|max:20',
        'Digits' => 'nullable|string|max:10',
        'RecordingUrl' => 'nullable|url',
        'RecordingDuration' => 'nullable|integer|min:0',
        'TranscriptionText' => 'nullable|string',
        'TranscriptionStatus' => 'nullable|string|in:completed,failed',
        'SipDomain' => 'nullable|string|max:255',
        'SipUserAgent' => 'nullable|string|max:500',
        'SipCallId' => 'nullable|string|max:255',
        'SipFromTag' => 'nullable|string|max:255',
        'SipToTag' => 'nullable|string|max:255',
        'Pdd' => 'nullable|integer|min:0|max:30000', // Post Dial Delay in milliseconds
        'RingTime' => 'nullable|integer|min:0|max:300000', // Ring time in milliseconds
        'Duration' => 'nullable|integer|min:0|max:86400', // Call duration in seconds
        'BillDuration' => 'nullable|integer|min:0|max:86400', // Billed duration in seconds
        'HangupCause' => 'nullable|string|max:100',
        'HangupCauseCode' => 'nullable|integer',
        'HangupSource' => 'nullable|string|in:caller,callee,system',
        'ApiVersion' => 'nullable|string|max:10',
        'AccountSid' => 'nullable|string|max:255',
        'ApplicationSid' => 'nullable|string|max:255',
        'Timestamp' => 'nullable|integer',
        'RequestId' => 'nullable|string|max:255',
        'SessionId' => 'nullable|string|max:255',
    ];

    /**
     * Validation rules for session update webhooks
     */
    public const SESSION_UPDATE_RULES = [
        'id' => 'required|integer',
        'domain' => 'required|string|max:255',
        'token' => 'required|string|max:255',
        'status' => 'required|string|max:50',
        'callerId' => 'nullable|string|max:20',
        'destination' => 'nullable|string|max:20',
        'direction' => 'nullable|string|in:inbound,outbound',
        'createdAt' => 'nullable|date',
        'modifiedAt' => 'nullable|date',
        'callStartTime' => 'nullable|integer',
        'answerTime' => 'nullable|date',
        'vappServer' => 'nullable|string|max:255',
        'duration' => 'nullable|integer|min:0',
        'billDuration' => 'nullable|integer|min:0',
        'hangupCause' => 'nullable|string|max:100',
        'hangupCauseCode' => 'nullable|integer',
        'hangupSource' => 'nullable|string|in:caller,callee,system',
        'metadata' => 'nullable|array',
        'events' => 'nullable|array',
        'events.*.type' => 'nullable|string|max:50',
        'events.*.timestamp' => 'nullable|integer',
        'events.*.data' => 'nullable|array',
    ];

    /**
     * Validation rules for CDR callback webhooks
     */
    public const CDR_CALLBACK_RULES = [
        'call_id' => 'required|string|max:255',
        'domain' => 'required|string|max:255',
        'from' => 'nullable|string|max:20',
        'to' => 'nullable|string|max:20',
        'disposition' => 'required|string|max:50',
        'duration' => 'nullable|integer|min:0|max:86400',
        'billsec' => 'nullable|integer|min:0|max:86400',
        'timestamp' => 'nullable|integer',
        'subscriber' => 'nullable|string|max:255',
        'cx_trunk_id' => 'nullable|string|max:255',
        'application' => 'nullable|string|max:255',
        'route' => 'nullable|string|max:255',
        'vapp_server' => 'nullable|string|max:255',
        'session' => 'nullable|array',
        'session.token' => 'nullable|string|max:255',
        'session.callStartTime' => 'nullable|integer',
        'session.callAnswerTime' => 'nullable|integer',
        'session.callEndTime' => 'nullable|integer',
        'session.vappServer' => 'nullable|string|max:255',
        'session.metadata' => 'nullable|array',
        'recording' => 'nullable|array',
        'recording.url' => 'nullable|url',
        'recording.duration' => 'nullable|integer|min:0',
        'recording.size' => 'nullable|integer|min:0',
        'transcription' => 'nullable|array',
        'transcription.text' => 'nullable|string',
        'transcription.status' => 'nullable|string|in:completed,failed,processing',
        'transcription.confidence' => 'nullable|numeric|min:0|max:1',
    ];

    /**
     * Validate voice application request
     */
    public function validateVoiceApplicationRequest(Request $request): array
    {
        return $this->validateRequest($request, self::VOICE_APPLICATION_REQUEST_RULES);
    }

    /**
     * Validate session update webhook
     */
    public function validateSessionUpdate(Request $request): array
    {
        return $this->validateRequest($request, self::SESSION_UPDATE_RULES);
    }

    /**
     * Validate CDR callback webhook
     */
    public function validateCdrCallback(Request $request): array
    {
        return $this->validateRequest($request, self::CDR_CALLBACK_RULES);
    }

    /**
     * Generic request validation method
     */
    private function validateRequest(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->toArray(),
                'message' => 'Request validation failed',
            ];
        }

        return [
            'valid' => true,
            'data' => $validator->validated(),
            'message' => 'Request validation successful',
        ];
    }

    /**
     * Validate Cloudonix webhook source
     */
    public function validateWebhookSource(Request $request): array
    {
        $issues = [];

        // Check for Cloudonix-specific headers
        $cloudonixHeaders = [
            'x-cloudonix-signature',
            'x-cloudonix-request-id',
            'x-cloudonix-timestamp',
            'x-cloudonix-version',
        ];

        $hasCloudonixHeaders = false;
        foreach ($cloudonixHeaders as $header) {
            if ($request->hasHeader($header)) {
                $hasCloudonixHeaders = true;
                break;
            }
        }

        // Check User-Agent for Cloudonix
        $userAgent = $request->userAgent();
        $hasCloudonixUserAgent = $userAgent &&
            str_contains(strtolower($userAgent), 'cloudonix');

        // Check for Cloudonix-specific IP ranges (example ranges - would need actual Cloudonix IPs)
        $clientIp = $request->ip();
        $isKnownCloudonixIp = $this->isCloudonixIp($clientIp);

        if (!$hasCloudonixHeaders && !$hasCloudonixUserAgent && !$isKnownCloudonixIp) {
            $issues[] = 'No Cloudonix identification found (headers, user-agent, or IP)';
        }

        // Check for required domain header for tenant resolution
        if (!$request->hasHeader('x-cloudonix-domain') && !$request->input('domain')) {
            $issues[] = 'Missing domain header or parameter for tenant resolution';
        }

        // Check request method
        if (!in_array($request->method(), ['GET', 'POST'])) {
            $issues[] = 'Invalid HTTP method for webhook';
        }

        // Check content type for POST requests
        if ($request->method() === 'POST') {
            $contentType = $request->header('content-type', '');
            if (!str_contains(strtolower($contentType), 'application/x-www-form-urlencoded') &&
                !str_contains(strtolower($contentType), 'application/json') &&
                !str_contains(strtolower($contentType), 'multipart/form-data')) {
                $issues[] = 'Invalid content type for webhook payload';
            }
        }

        // Check for reasonable payload size
        $contentLength = $request->header('content-length', 0);
        if ($contentLength > 1000000) { // 1MB limit
            $issues[] = 'Request payload too large';
        }

        // Log validation issues
        if (!empty($issues)) {
            Log::warning('Cloudonix webhook source validation issues', [
                'issues' => $issues,
                'headers' => array_intersect_key($request->headers->all(), array_flip([
                    'user-agent', 'x-cloudonix-signature', 'x-cloudonix-request-id',
                    'x-cloudonix-timestamp', 'x-cloudonix-domain', 'content-type'
                ])),
                'client_ip' => $clientIp,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ]);
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'confidence' => $this->calculateConfidence($hasCloudonixHeaders, $hasCloudonixUserAgent, $isKnownCloudonixIp),
        ];
    }

    /**
     * Check if IP address is known to belong to Cloudonix
     */
    private function isCloudonixIp(string $ip): bool
    {
        // This would contain actual Cloudonix IP ranges
        // For now, return false (would be implemented with real IP ranges)
        $cloudonixRanges = [
            // Example ranges - replace with actual Cloudonix IPs
            // '192.0.2.0/24', // Example RFC 5737 documentation range
        ];

        // Simple implementation - in production use a proper IP range checker
        return false;
    }

    /**
     * Calculate confidence score for webhook source validation
     */
    private function calculateConfidence(bool $hasHeaders, bool $hasUserAgent, bool $hasKnownIp): float
    {
        $score = 0;

        if ($hasHeaders) $score += 0.4;
        if ($hasUserAgent) $score += 0.3;
        if ($hasKnownIp) $score += 0.3;

        return min(1.0, $score);
    }

    /**
     * Sanitize webhook payload data
     */
    public function sanitizeWebhookData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remove null bytes and sanitize
                $sanitized[$key] = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeWebhookData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Extract tenant domain from webhook request
     */
    public function extractTenantDomain(Request $request): ?string
    {
        // Try different sources for domain
        $sources = [
            $request->header('x-cloudonix-domain'),
            $request->input('domain'),
            $request->input('SipDomain'),
            $request->input('sip_domain'),
        ];

        foreach ($sources as $domain) {
            if (!empty($domain) && is_string($domain)) {
                // Basic domain validation
                if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    return strtolower($domain);
                }
            }
        }

        return null;
    }

    /**
     * Validate tenant domain exists
     */
    public function validateTenantDomain(string $domain): array
    {
        $tenant = \App\Models\Tenant::where('domain', $domain)->first();

        if (!$tenant) {
            return [
                'valid' => false,
                'error' => 'Tenant not found for domain: ' . $domain,
            ];
        }

        if (!$tenant->is_active) {
            return [
                'valid' => false,
                'error' => 'Tenant is not active: ' . $domain,
            ];
        }

        return [
            'valid' => true,
            'tenant' => $tenant,
            'message' => 'Tenant domain validated successfully',
        ];
    }

    /**
     * Generate comprehensive webhook validation report
     */
    public function generateValidationReport(Request $request): array
    {
        $sourceValidation = $this->validateWebhookSource($request);
        $tenantDomain = $this->extractTenantDomain($request);

        $report = [
            'timestamp' => now()->toISOString(),
            'request_id' => $request->header('x-cloudonix-request-id') ?: uniqid('req_', true),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source_validation' => $sourceValidation,
            'tenant_domain' => $tenantDomain,
        ];

        if ($tenantDomain) {
            $report['tenant_validation'] = $this->validateTenantDomain($tenantDomain);
        }

        $report['overall_valid'] = $sourceValidation['valid'] &&
            ($report['tenant_validation']['valid'] ?? false);

        return $report;
    }
}