<?php

namespace Tests\Feature;

use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $idempotencyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->idempotencyService = app(IdempotencyService::class);
    }

    public function test_event_not_processed_initially()
    {
        $isProcessed = $this->idempotencyService->isEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertFalse($isProcessed);
    }

    public function test_event_marked_as_processed()
    {
        $this->idempotencyService->markEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $isProcessed = $this->idempotencyService->isEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertTrue($isProcessed);
    }

    public function test_idempotent_execution()
    {
        $executionCount = 0;

        $result = $this->idempotencyService->executeIdempotent(
            function () use (&$executionCount) {
                $executionCount++;
                return 'success';
            },
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $executionCount);

        // Second execution should be skipped
        $result2 = $this->idempotencyService->executeIdempotent(
            function () use (&$executionCount) {
                $executionCount++;
                return 'should_not_execute';
            },
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertNull($result2); // Should return null for already processed
        $this->assertEquals(1, $executionCount); // Should not have executed again
    }

    public function test_idempotent_execution_with_exception()
    {
        $executionCount = 0;

        try {
            $this->idempotencyService->executeIdempotent(
                function () use (&$executionCount) {
                    $executionCount++;
                    throw new \Exception('Test exception');
                },
                1, 'voice.application.request', 'session_123', 'event_456'
            );
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Test exception', $e->getMessage());
        }

        // Event should not be marked as processed due to exception
        $isProcessed = $this->idempotencyService->isEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertFalse($isProcessed);
        $this->assertEquals(1, $executionCount);
    }

    public function test_webhook_idempotency_key_generation()
    {
        $webhookData1 = [
            'Session' => 'session_123',
            'CallSid' => 'call_456',
            'event_type' => 'voice.application.request',
            'timestamp' => 1640995200,
        ];

        $webhookData2 = [
            'Session' => 'session_123',
            'CallSid' => 'call_456',
            'event_type' => 'voice.application.request',
            'timestamp' => 1640995200,
        ];

        $key1 = $this->idempotencyService->generateWebhookIdempotencyKey($webhookData1);
        $key2 = $this->idempotencyService->generateWebhookIdempotencyKey($webhookData2);

        $this->assertEquals($key1, $key2);
        $this->assertIsString($key1);
        $this->assertEquals(64, strlen($key1)); // SHA256 hash length
    }

    public function test_different_webhook_data_generates_different_keys()
    {
        $webhookData1 = [
            'Session' => 'session_123',
            'CallSid' => 'call_456',
            'event_type' => 'voice.application.request',
        ];

        $webhookData2 = [
            'Session' => 'session_789',
            'CallSid' => 'call_456',
            'event_type' => 'voice.application.request',
        ];

        $key1 = $this->idempotencyService->generateWebhookIdempotencyKey($webhookData1);
        $key2 = $this->idempotencyService->generateWebhookIdempotencyKey($webhookData2);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_tenant_isolation()
    {
        // Mark event as processed for tenant 1
        $this->idempotencyService->markEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        // Same event for tenant 2 should not be processed
        $isProcessedTenant1 = $this->idempotencyService->isEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $isProcessedTenant2 = $this->idempotencyService->isEventProcessed(
            2, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertTrue($isProcessedTenant1);
        $this->assertFalse($isProcessedTenant2);
    }

    public function test_statistics_reporting()
    {
        // Add some test data
        $this->idempotencyService->markEventProcessed(1, 'voice.application.request', 'session_1', 'event_1');
        $this->idempotencyService->markEventProcessed(1, 'voice.session.update', 'session_2', 'event_2');
        $this->idempotencyService->markEventProcessed(1, 'voice.application.request', 'session_3', 'event_3');

        $stats = $this->idempotencyService->getStatistics(1);

        $this->assertGreaterThanOrEqual(3, $stats['total_keys']);
        $this->assertArrayHasKey('voice.application.request', $stats['keys_by_event_type']);
        $this->assertArrayHasKey('voice.session.update', $stats['keys_by_event_type']);
    }

    public function test_reset_idempotency()
    {
        // Mark event as processed
        $this->idempotencyService->markEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        );

        $this->assertTrue($this->idempotencyService->isEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        ));

        // Reset idempotency
        $deleted = $this->idempotencyService->resetIdempotency(1, 'voice.application.request', 'session_123');

        $this->assertGreaterThan(0, $deleted);

        // Event should no longer be processed
        $this->assertFalse($this->idempotencyService->isEventProcessed(
            1, 'voice.application.request', 'session_123', 'event_456'
        ));
    }
}
