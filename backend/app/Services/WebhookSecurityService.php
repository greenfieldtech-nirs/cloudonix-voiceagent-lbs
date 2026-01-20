<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Webhook Security Service
 *
 * Provides comprehensive webhook security including signature verification,
 * rate limiting, replay attack prevention, and payload validation.
 */
class WebhookSecurityService
{
    protected array $cloudonixIpRanges = [
        '104.18.0.0/20',    // Cloudflare IP range (common for webhooks)
        '172.64.0.0/13',    // Additional Cloudflare ranges
        // Add specific Cloudonix IP ranges when available
    ];

    protected array $validUserAgents = [
        'Cloudonix-Webhook/1.0',
        'Cloudonix Voice API',
        // Add official Cloudonix user agents
    ];

    /**
     * Validate webhook request security
     */
    public function validateWebhookSecurity(Request $request): array
    {
        $issues = [];
        $warnings = [];

        // Check IP address
        if (!$this->isValidSourceIp($request->ip())) {
            $issues[] = 'Request from unauthorized IP address: ' . $request->ip();
        }

        // Check User-Agent
        if (!$this->isValidUserAgent($request->userAgent())) {
            $warnings[] = 'Unrecognized User-Agent: ' . $request->userAgent();
        }

        // Check for rate limiting
        if ($this->isRateLimited($request)) {
            $issues[] = 'Rate limit exceeded for this source';
        }

        // Check for replay attacks
        if ($this->isReplayAttack($request)) {
            $issues[] = 'Potential replay attack detected';
        }

        // Validate request headers
        $headerIssues = $this->validateSecurityHeaders($request);
        $issues = array_merge($issues, $headerIssues);

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'security_score' => $this->calculateSecurityScore($issues, $warnings),
        ];
    }

    /**
     * Validate if source IP is allowed
     */
    public function isValidSourceIp(string $ip): bool
    {
        // For development, allow localhost and private networks
        if (app()->environment('local')) {
            if ($ip === '127.0.0.1' || $ip === '::1' ||
                str_starts_with($ip, '192.168.') ||
                str_starts_with($ip, '10.') ||
                str_starts_with($ip, '172.')) {
                return true;
            }
        }

        // Check against Cloudonix IP ranges (simplified check)
        foreach ($this->cloudonixIpRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        // Log unknown IP for monitoring
        Log::info('Unknown webhook source IP', [
            'ip' => $ip,
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);

        // In production, be more restrictive
        return app()->environment('local');
    }

    /**
     * Validate User-Agent header
     */
    public function isValidUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return false;
        }

        foreach ($this->validUserAgents as $validAgent) {
            if (str_contains($userAgent, $validAgent)) {
                return true;
            }
        }

        // Allow common webhook clients
        if (preg_match('/(curl|wget|httpie|postman|insomnia)/i', $userAgent)) {
            return app()->environment('local');
        }

        return false;
    }

    /**
     * Check if request exceeds rate limits
     */
    public function isRateLimited(Request $request): bool
    {
        $key = 'webhook:' . $request->ip();

        // Allow 100 requests per minute per IP
        if (RateLimiter::tooManyAttempts($key, 100)) {
            Log::warning('Webhook rate limit exceeded', [
                'ip' => $request->ip(),
                'attempts' => RateLimiter::attempts($key),
            ]);
            return true;
        }

        RateLimiter::hit($key, 60); // 1 minute window
        return false;
    }

    /**
     * Check for replay attack prevention
     */
    public function isReplayAttack(Request $request): bool
    {
        $requestId = $request->header('X-Request-ID') ?: $request->header('X-Cloudonix-Request-ID');

        if (!$requestId) {
            return false; // No request ID to check
        }

        $cacheKey = 'webhook_request:' . md5($requestId);

        // Check if this request ID was seen recently (5 minutes)
        if (Cache::has($cacheKey)) {
            Log::warning('Potential replay attack detected', [
                'request_id' => $requestId,
                'ip' => $request->ip(),
                'timestamp' => now(),
            ]);
            return true;
        }

        // Store request ID for 5 minutes
        Cache::put($cacheKey, true, 300);
        return false;
    }

    /**
     * Validate security-related headers
     */
    public function validateSecurityHeaders(Request $request): array
    {
        $issues = [];

        // Check for required headers
        if (!$request->hasHeader('User-Agent')) {
            $issues[] = 'Missing User-Agent header';
        }

        // Check Content-Type for POST requests
        if ($request->isMethod('post') && !$request->isJson()) {
            $issues[] = 'Invalid Content-Type for POST request';
        }

        // Check for suspicious headers
        $suspiciousHeaders = ['X-Forwarded-For', 'X-Real-IP'];
        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header) && !$this->isTrustedProxy($request->ip())) {
                $issues[] = "Suspicious header detected: {$header}";
            }
        }

        return $issues;
    }

    /**
     * Verify webhook signature if available
     */
    public function verifySignature(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Cloudonix-Signature') ?: $request->header('X-Signature');

        if (!$signature) {
            // No signature to verify, but that's okay
            return true;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        $verified = hash_equals($expectedSignature, $signature);

        if (!$verified) {
            Log::warning('Webhook signature verification failed', [
                'ip' => $request->ip(),
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
        }

        return $verified;
    }

    /**
     * Sanitize webhook payload
     */
    public function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                // Remove potentially dangerous characters
                $sanitized[$key] = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Calculate security score for monitoring
     */
    public function calculateSecurityScore(array $issues, array $warnings): int
    {
        $score = 100;

        // Deduct points for issues
        $score -= count($issues) * 25;

        // Deduct points for warnings
        $score -= count($warnings) * 5;

        return max(0, $score);
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $mask) = explode('/', $range);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {

            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = ~((1 << (32 - $mask)) - 1);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // For IPv6 or other cases, return false for now
        return false;
    }

    /**
     * Check if IP is a trusted proxy
     */
    private function isTrustedProxy(string $ip): bool
    {
        // Add your trusted proxy IPs here
        $trustedProxies = [
            '127.0.0.1',
            '::1',
            // Add your load balancer/proxy IPs
        ];

        return in_array($ip, $trustedProxies);
    }

    /**
     * Get security metrics for monitoring
     */
    public function getSecurityMetrics(): array
    {
        return [
            'rate_limited_requests' => Cache::get('webhook_security:rate_limited', 0),
            'invalid_signatures' => Cache::get('webhook_security:invalid_signatures', 0),
            'replay_attempts' => Cache::get('webhook_security:replay_attempts', 0),
            'suspicious_requests' => Cache::get('webhook_security:suspicious', 0),
            'average_security_score' => Cache::get('webhook_security:avg_score', 100),
        ];
    }

    /**
     * Reset security metrics (for testing)
     */
    public function resetMetrics(): void
    {
        Cache::forget('webhook_security:rate_limited');
        Cache::forget('webhook_security:invalid_signatures');
        Cache::forget('webhook_security:replay_attempts');
        Cache::forget('webhook_security:suspicious');
        Cache::forget('webhook_security:avg_score');
    }
}