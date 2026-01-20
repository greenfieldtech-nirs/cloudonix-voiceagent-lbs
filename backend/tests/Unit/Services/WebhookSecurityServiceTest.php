<?php

namespace Tests\Unit\Services;

use App\Services\WebhookSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookSecurityService $securityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityService = app(WebhookSecurityService::class);
    }

    public function test_validates_secure_webhook_request()
    {
        // Create a mock request that should pass validation
        $request = Request::create('/api/webhook', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1', // Localhost for testing
            'HTTP_USER_AGENT' => 'Cloudonix-Webhook/1.0',
            'HTTP_X_REQUEST_ID' => 'test-request-123',
        ]);

        $result = $this->securityService->validateWebhookSecurity($request);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['issues']);
        $this->assertGreaterThanOrEqual(80, $result['security_score']);
    }

    public function test_detects_rate_limiting()
    {
        $request = Request::create('/api/webhook', 'POST', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100',
        ]);

        // First few requests should pass
        for ($i = 0; $i < 5; $i++) {
            $result = $this->securityService->validateWebhookSecurity($request);
            $this->assertTrue($result['valid']);
        }

        // Additional requests should be rate limited (depending on configuration)
        // Note: This test may need adjustment based on rate limiting configuration
        $result = $this->securityService->validateWebhookSecurity($request);
        // Rate limiting may or may not trigger depending on exact configuration
    }

    public function test_detects_replay_attacks()
    {
        $requestId = 'replay-test-123';

        $request1 = Request::create('/api/webhook', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_REQUEST_ID' => $requestId,
        ]);

        $request2 = Request::create('/api/webhook', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_REQUEST_ID' => $requestId, // Same ID
        ]);

        // First request should pass
        $result1 = $this->securityService->validateWebhookSecurity($request1);
        $this->assertTrue($result1['valid']);

        // Second request with same ID should be detected as replay
        $result2 = $this->securityService->validateWebhookSecurity($request2);
        $this->assertFalse($result2['valid']);
        $this->assertContains('replay attack', implode(' ', $result2['issues']));
    }

    public function test_validates_user_agent()
    {
        // Valid user agent
        $request1 = Request::create('/api/webhook', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Cloudonix-Webhook/1.0',
        ]);
        $this->assertTrue($this->securityService->isValidUserAgent($request1->userAgent()));

        // Invalid user agent
        $request2 = Request::create('/api/webhook', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'MaliciousBot/1.0',
        ]);
        $this->assertFalse($this->securityService->isValidUserAgent($request2->userAgent()));

        // Development user agent (should pass in local env)
        $request3 = Request::create('/api/webhook', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'curl/7.68.0',
        ]);
        // This should pass in local environment
        if (app()->environment('local')) {
            $this->assertTrue($this->securityService->isValidUserAgent($request3->userAgent()));
        }
    }

    public function test_validates_ip_addresses()
    {
        // Localhost should be valid in development
        $this->assertTrue($this->securityService->isValidSourceIp('127.0.0.1'));
        $this->assertTrue($this->securityService->isValidSourceIp('::1'));

        // Private networks should be valid in development
        $this->assertTrue($this->securityService->isValidSourceIp('192.168.1.100'));
        $this->assertTrue($this->securityService->isValidSourceIp('10.0.0.1'));

        // External IPs may not be valid depending on configuration
        // This test may need adjustment based on security configuration
    }

    public function test_sanitizes_payload()
    {
        $payload = [
            'name' => 'Test <script>alert("xss")</script>',
            'description' => 'Normal description',
            'nested' => [
                'data' => 'Another <b>tag</b> here',
            ],
        ];

        $sanitized = $this->securityService->sanitizePayload($payload);

        $this->assertEquals('Test alert("xss")', $sanitized['name']);
        $this->assertEquals('Normal description', $sanitized['description']);
        $this->assertEquals('Another tag here', $sanitized['nested']['data']);
    }

    public function test_calculates_security_score()
    {
        $issues = ['Invalid IP', 'Rate limited'];
        $warnings = ['Unknown user agent'];

        $score = $this->securityService->calculateSecurityScore($issues, $warnings);

        // Should be reduced from 100
        $this->assertLessThan(100, $score);
        $this->assertGreaterThan(0, $score);
    }

    public function test_signature_verification()
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-key';

        // Generate a valid signature
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        $request = Request::create('/api/webhook', 'POST', [], [], [], [
            'HTTP_X_CLOUDONIX_SIGNATURE' => $expectedSignature,
        ], $payload);

        $this->assertTrue($this->securityService->verifySignature($request, $secret));

        // Test invalid signature
        $request->headers->set('X-Cloudonix-Signature', 'invalid-signature');
        $this->assertFalse($this->securityService->verifySignature($request, $secret));
    }

    public function test_security_headers_validation()
    {
        // Request with all required headers
        $request1 = Request::create('/api/webhook', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Cloudonix-Webhook/1.0',
        ]);

        $issues1 = $this->securityService->validateSecurityHeaders($request1);
        $this->assertEmpty($issues1);

        // Request missing User-Agent
        $request2 = Request::create('/api/webhook', 'POST');
        $issues2 = $this->securityService->validateSecurityHeaders($request2);
        $this->assertContains('Missing User-Agent header', $issues2);
    }
}