<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CdrLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test CDR webhook callback processing
     */
    public function test_cdr_webhook_callback_processes_valid_payload(): void
    {
        // Skip if database is not available (Docker not running)
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for integration test');
        }
        // Skip if database is not available (Docker not running)
        try {
            \DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for integration test');
        }

        $tenant = Tenant::factory()->create(['domain' => 'test.cloudonix.com']);

        $cdrPayload = [
            'call_id' => 'test-call-123',
            'domain' => 'test.cloudonix.com',
            'from' => '+1234567890',
            'to' => '+0987654321',
            'disposition' => 'ANSWERED',
            'duration' => 45,
            'billsec' => 42,
            'timestamp' => 1640995200,
            'session' => [
                'token' => 'session-token-123',
                'callStartTime' => 1640995200000,
                'callAnswerTime' => 1640995210000,
                'callEndTime' => 1640995245000,
            ],
        ];

        $response = $this->postJson('/api/voice/session/cdr', $cdrPayload, [
            'User-Agent' => 'Cloudonix-Webhook/1.0',
        ]);

        $response->assertStatus(200)
                ->assertSee('OK');

        // Verify CDR record was stored
        $this->assertDatabaseHas('cdr_logs', [
            'tenant_id' => $tenant->id,
            'call_id' => 'test-call-123',
            'session_token' => 'session-token-123',
            'from_number' => '+1234567890',
            'to_number' => '+0987654321',
            'disposition' => 'ANSWER',
            'duration_seconds' => 45,
            'billsec' => 42,
            'domain' => 'test.cloudonix.com',
        ]);
    }

    /**
     * Test CDR webhook rejects invalid payload
     */
    public function test_cdr_webhook_rejects_invalid_payload(): void
    {
        $invalidPayload = [
            'call_id' => '', // Missing required field
            'domain' => 'test.cloudonix.com',
        ];

        $response = $this->postJson('/api/voice/session/cdr', $invalidPayload);

        $response->assertStatus(400);
    }

    /**
     * Test disposition mapping logic
     */
    public function test_cdr_disposition_mapping(): void
    {
        $controller = new \App\Http\Controllers\Api\VoiceApplicationController();

        // Test various disposition mappings using reflection to access private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('mapCdrDisposition');
        $method->setAccessible(true);

        $this->assertEquals('ANSWER', $method->invoke($controller, 'ANSWERED'));
        $this->assertEquals('BUSY', $method->invoke($controller, 'BUSY'));
        $this->assertEquals('NOANSWER', $method->invoke($controller, 'NOANSWER'));
        $this->assertEquals('FAILED', $method->invoke($controller, 'UNKNOWN'));
    }

    /**
     * Test Cloudonix status mapping logic
     */
    public function test_cloudonix_status_mapping(): void
    {
        $controller = new \App\Http\Controllers\Api\VoiceApplicationController();

        // Test various status mappings using reflection to access private method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('mapCloudonixStatus');
        $method->setAccessible(true);

        $this->assertEquals('answered', $method->invoke($controller, 'answered'));
        $this->assertEquals('ringing', $method->invoke($controller, 'ringing'));
        $this->assertEquals('completed', $method->invoke($controller, 'completed'));
        $this->assertEquals('ringing', $method->invoke($controller, 'unknown'));
    }
}