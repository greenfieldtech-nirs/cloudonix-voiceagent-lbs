#!/usr/bin/env php
<?php

/**
 * Cloudonix Webhook Simulator
 *
 * Simulates Cloudonix webhook events for testing the Voice Agent Load Balancer.
 * Can send various webhook types to test inbound/outbound call processing.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WebhookSimulator
{
    private Client $httpClient;
    private string $baseUrl;
    private array $simulatedEvents = [
        'voice.application.request' => [
            'EventType' => 'voice.application.request',
            'ApplicationId' => 'test-app-123',
            'From' => '+1234567890',
            'To' => '+1987654321',
            'Direction' => 'inbound',
            'CallId' => 'call_' . uniqid(),
            'Timestamp' => date('c'),
        ],
        'voice.session.update' => [
            'EventType' => 'voice.session.update',
            'ApplicationId' => 'test-app-123',
            'SessionId' => 'session_' . uniqid(),
            'CallId' => 'call_' . uniqid(),
            'Status' => 'connected',
            'From' => '+1234567890',
            'To' => '+1987654321',
            'Direction' => 'inbound',
            'ConnectedAt' => date('c'),
            'Duration' => 0,
        ],
        'voice.session.cdr' => [
            'EventType' => 'voice.session.cdr',
            'ApplicationId' => 'test-app-123',
            'SessionId' => 'session_' . uniqid(),
            'CallId' => 'call_' . uniqid(),
            'From' => '+1234567890',
            'To' => '+1987654321',
            'Direction' => 'inbound',
            'Status' => 'completed',
            'StartTime' => date('c', strtotime('-5 minutes')),
            'EndTime' => date('c'),
            'Duration' => 300,
            'HangupCause' => 'NORMAL_CLEARING',
        ],
        'outbound.call.request' => [
            'EventType' => 'voice.application.request',
            'ApplicationId' => 'outbound-app-456',
            'From' => '+1555123456', // Outbound caller ID
            'To' => '+1555987654',   // Destination number
            'Direction' => 'outbound',
            'CallId' => 'outbound_call_' . uniqid(),
            'Timestamp' => date('c'),
        ],
    ];

    public function __construct(string $baseUrl = 'http://localhost')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->httpClient = new Client([
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Cloudonix-Webhook-Simulator/1.0',
                'Content-Type' => 'application/json',
                'X-Cloudonix-Signature' => 'test-signature', // Mock signature
            ],
        ]);
    }

    /**
     * Send a webhook event
     */
    public function sendEvent(string $eventType, array $overrides = []): array
    {
        if (!isset($this->simulatedEvents[$eventType])) {
            throw new InvalidArgumentException("Unknown event type: {$eventType}");
        }

        $payload = array_merge($this->simulatedEvents[$eventType], $overrides);
        $endpoint = $this->getEndpointForEvent($eventType);

        echo "📤 Sending {$eventType} to {$endpoint}\n";
        echo "📋 Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";

        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $payload,
            ]);

            $result = [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_body' => (string) $response->getBody(),
                'headers' => $response->getHeaders(),
            ];

            echo "✅ Success: HTTP {$result['status_code']}\n";
            echo "📄 Response: {$result['response_body']}\n";

            return $result;

        } catch (RequestException $e) {
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $e->getResponse() ? (string) $e->getResponse()->getBody() : null,
            ];

            echo "❌ Error: {$result['error']}\n";
            if ($result['response_body']) {
                echo "📄 Response: {$result['response_body']}\n";
            }

            return $result;
        }
    }

    /**
     * Send multiple events in sequence
     */
    public function sendEventSequence(array $events): array
    {
        $results = [];

        foreach ($events as $event) {
            $eventType = $event['type'];
            $overrides = $event['overrides'] ?? [];
            $delay = $event['delay'] ?? 0;

            if ($delay > 0) {
                echo "⏳ Waiting {$delay} seconds...\n";
                sleep($delay);
            }

            $results[] = $this->sendEvent($eventType, $overrides);
        }

        return $results;
    }

    /**
     * Simulate a complete call flow
     */
    public function simulateCallFlow(string $direction = 'inbound'): array
    {
        $sessionId = 'session_' . uniqid();
        $callId = 'call_' . uniqid();

        if ($direction === 'inbound') {
            $events = [
                [
                    'type' => 'voice.application.request',
                    'overrides' => [
                        'SessionId' => $sessionId,
                        'CallId' => $callId,
                    ],
                ],
                [
                    'type' => 'voice.session.update',
                    'overrides' => [
                        'SessionId' => $sessionId,
                        'CallId' => $callId,
                        'Status' => 'connected',
                    ],
                    'delay' => 2,
                ],
                [
                    'type' => 'voice.session.cdr',
                    'overrides' => [
                        'SessionId' => $sessionId,
                        'CallId' => $callId,
                        'Status' => 'completed',
                        'Duration' => 120,
                    ],
                    'delay' => 1,
                ],
            ];
        } else {
            // Outbound call flow
            $events = [
                [
                    'type' => 'outbound.call.request',
                    'overrides' => [
                        'SessionId' => $sessionId,
                        'CallId' => $callId,
                    ],
                ],
            ];
        }

        echo "🎭 Simulating {$direction} call flow...\n";
        return $this->sendEventSequence($events);
    }

    /**
     * Load test with multiple concurrent requests
     */
    public function loadTest(string $eventType, int $concurrentRequests = 10, int $totalRequests = 100): array
    {
        echo "🚀 Starting load test: {$concurrentRequests} concurrent, {$totalRequests} total {$eventType} events\n";

        $results = [];
        $completed = 0;

        // Simple load test implementation
        for ($i = 0; $i < $totalRequests; $i++) {
            try {
                $result = $this->sendEvent($eventType);
                $results[] = $result;
                $completed++;

                if ($completed % 10 === 0) {
                    echo "📊 Progress: {$completed}/{$totalRequests} completed\n";
                }

                // Small delay to avoid overwhelming
                usleep(100000); // 0.1 seconds

            } catch (Exception $e) {
                echo "❌ Load test error: {$e->getMessage()}\n";
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
        $avgResponseTime = array_sum(array_map(fn($r) => $r['response_time'] ?? 0, $results)) / count($results);

        echo "📈 Load test completed: {$successCount}/{$totalRequests} successful\n";
        echo "⏱️  Average response time: " . number_format($avgResponseTime, 2) . "ms\n";

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successCount,
            'failed_requests' => $totalRequests - $successCount,
            'average_response_time' => $avgResponseTime,
            'results' => $results,
        ];
    }

    /**
     * Get available event types
     */
    public function getAvailableEvents(): array
    {
        return array_keys($this->simulatedEvents);
    }

    /**
     * Get endpoint for event type
     */
    private function getEndpointForEvent(string $eventType): string
    {
        // Map event types to endpoints
        $endpointMap = [
            'voice.application.request' => '/api/voice/application/test-app-123',
            'voice.session.update' => '/api/voice/session/test-app-123',
            'voice.session.cdr' => '/api/voice/cdr/test-app-123',
            'outbound.call.request' => '/api/voice/application/outbound-app-456',
        ];

        return $this->baseUrl . ($endpointMap[$eventType] ?? '/api/voice/webhook');
    }

    /**
     * Display usage information
     */
    public static function showUsage(): void
    {
        echo "Cloudonix Webhook Simulator\n";
        echo "===========================\n\n";
        echo "Usage: php webhook-simulator.php [command] [options]\n\n";
        echo "Commands:\n";
        echo "  send <event_type>          Send a single webhook event\n";
        echo "  flow [inbound|outbound]    Simulate a complete call flow\n";
        echo "  load <event_type> [count]  Run load test with specified event type\n";
        echo "  list                       List available event types\n\n";
        echo "Available event types:\n";

        $simulator = new self();
        foreach ($simulator->getAvailableEvents() as $event) {
            echo "  - {$event}\n";
        }

        echo "\nOptions:\n";
        echo "  --url=<url>                Base URL for webhook endpoint (default: http://localhost)\n";
        echo "  --help, -h                 Show this help message\n\n";
        echo "Examples:\n";
        echo "  php webhook-simulator.php send voice.application.request\n";
        echo "  php webhook-simulator.php flow inbound\n";
        echo "  php webhook-simulator.php load voice.application.request 50\n";
        echo "  php webhook-simulator.php send voice.application.request --url=http://production.example.com\n";
    }
}

// CLI interface
if ($argc < 2 || in_array($argv[1], ['--help', '-h'])) {
    WebhookSimulator::showUsage();
    exit(0);
}

$command = $argv[1];
$url = 'http://localhost';

// Parse URL option
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--url=')) {
        $url = substr($arg, 6);
    }
}

$simulator = new WebhookSimulator($url);

try {
    switch ($command) {
        case 'send':
            if (!isset($argv[2])) {
                throw new InvalidArgumentException('Event type required for send command');
            }
            $overrides = [];
            if (isset($argv[3])) {
                parse_str($argv[3], $overrides);
            }
            $simulator->sendEvent($argv[2], $overrides);
            break;

        case 'flow':
            $direction = $argv[2] ?? 'inbound';
            $simulator->simulateCallFlow($direction);
            break;

        case 'load':
            if (!isset($argv[2])) {
                throw new InvalidArgumentException('Event type required for load command');
            }
            $count = (int) ($argv[3] ?? 100);
            $simulator->loadTest($argv[2], min(10, $count), $count);
            break;

        case 'list':
            echo "Available event types:\n";
            foreach ($simulator->getAvailableEvents() as $event) {
                echo "  - {$event}\n";
            }
            break;

        default:
            throw new InvalidArgumentException("Unknown command: {$command}");
    }

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Use --help for usage information\n";
    exit(1);
}

echo "\n✅ Webhook simulation completed!\n";