<?php

namespace App\Services;

/**
 * Call State Machine for Cloudonix Voice Application
 *
 * Manages call lifecycle states and enforces valid state transitions.
 * Provides state persistence, validation, and recovery mechanisms.
 */
class CallStateMachine
{
    /**
     * Call Lifecycle States
     */
    public const STATE_RECEIVED = 'received';
    public const STATE_QUEUED = 'queued';
    public const STATE_ROUTING = 'routing';
    public const STATE_CONNECTING = 'connecting';
    public const STATE_CONNECTED = 'connected';
    public const STATE_COMPLETED = 'completed';
    public const STATE_BUSY = 'busy';
    public const STATE_FAILED = 'failed';
    public const STATE_NO_ANSWER = 'no_answer';

    /**
     * Terminal States (call ends)
     */
    public const TERMINAL_STATES = [
        self::STATE_COMPLETED,
        self::STATE_BUSY,
        self::STATE_FAILED,
        self::STATE_NO_ANSWER,
    ];

    /**
     * Valid State Transitions
     * Maps current state to array of allowed next states
     */
    public const VALID_TRANSITIONS = [
        self::STATE_RECEIVED => [self::STATE_QUEUED],
        self::STATE_QUEUED => [self::STATE_ROUTING],
        self::STATE_ROUTING => [self::STATE_CONNECTING, self::STATE_FAILED],
        self::STATE_CONNECTING => [self::STATE_CONNECTED, self::STATE_BUSY, self::STATE_FAILED, self::STATE_NO_ANSWER],
        self::STATE_CONNECTED => [self::STATE_COMPLETED, self::STATE_BUSY, self::STATE_FAILED],
        // Terminal states have no transitions
        self::STATE_COMPLETED => [],
        self::STATE_BUSY => [],
        self::STATE_FAILED => [],
        self::STATE_NO_ANSWER => [],
    ];

    /**
     * State Metadata
     */
    public const STATE_METADATA = [
        self::STATE_RECEIVED => ['description' => 'Call webhook received', 'color' => 'blue'],
        self::STATE_QUEUED => ['description' => 'Call queued for routing', 'color' => 'yellow'],
        self::STATE_ROUTING => ['description' => 'Routing decision in progress', 'color' => 'orange'],
        self::STATE_CONNECTING => ['description' => 'Connecting to voice agent', 'color' => 'purple'],
        self::STATE_CONNECTED => ['description' => 'Call connected to agent', 'color' => 'green'],
        self::STATE_COMPLETED => ['description' => 'Call completed successfully', 'color' => 'green'],
        self::STATE_BUSY => ['description' => 'Agent returned busy signal', 'color' => 'red'],
        self::STATE_FAILED => ['description' => 'Call failed to connect', 'color' => 'red'],
        self::STATE_NO_ANSWER => ['description' => 'Agent did not answer', 'color' => 'red'],
    ];

    /**
     * Current state
     */
    private string $currentState;

    /**
     * Session token
     */
    private string $sessionToken;

    /**
     * Tenant ID
     */
    private int $tenantId;

    /**
     * State change history
     */
    private array $history = [];

    /**
     * Additional state data
     */
    private array $metadata = [];

    /**
     * Constructor
     */
    public function __construct(string $sessionToken, int $tenantId, string $initialState = self::STATE_RECEIVED)
    {
        $this->sessionToken = $sessionToken;
        $this->tenantId = $tenantId;
        $this->currentState = $initialState;
        $this->history[] = [
            'state' => $initialState,
            'timestamp' => now(),
            'metadata' => ['initial' => true]
        ];
    }

    /**
     * Get current state
     */
    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    /**
     * Check if current state is terminal
     */
    public function isTerminal(): bool
    {
        return in_array($this->currentState, self::TERMINAL_STATES);
    }

    /**
     * Check if transition to new state is valid
     */
    public function canTransitionTo(string $newState): bool
    {
        return in_array($newState, self::VALID_TRANSITIONS[$this->currentState] ?? []);
    }

    /**
     * Attempt to transition to new state
     */
    public function transitionTo(string $newState, array $metadata = []): bool
    {
        if (!$this->canTransitionTo($newState)) {
            throw new \InvalidArgumentException(
                "Invalid state transition from {$this->currentState} to {$newState}"
            );
        }

        $oldState = $this->currentState;
        $this->currentState = $newState;

        $transitionData = [
            'from_state' => $oldState,
            'to_state' => $newState,
            'timestamp' => now(),
            'metadata' => array_merge($this->metadata, $metadata)
        ];

        $this->history[] = $transitionData;

        // Persist state change
        $this->persistState();

        // Log transition
        $this->logTransition($transitionData);

        return true;
    }

    /**
     * Set metadata for current state
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Get state history
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Get state metadata
     */
    public function getStateMetadata(string $state = null): array
    {
        $state = $state ?? $this->currentState;
        return self::STATE_METADATA[$state] ?? [];
    }

    /**
     * Get all valid states
     */
    public static function getAllStates(): array
    {
        return array_keys(self::VALID_TRANSITIONS);
    }

    /**
     * Get valid transitions for a state
     */
    public static function getValidTransitions(string $state): array
    {
        return self::VALID_TRANSITIONS[$state] ?? [];
    }

    /**
     * Persist current state to Redis
     */
    private function persistState(): void
    {
        $redis = app(RedisService::class);

        $stateData = [
            'current_state' => $this->currentState,
            'session_token' => $this->sessionToken,
            'last_transition' => now()->toISOString(),
            'metadata' => json_encode($this->metadata),
            'history_count' => count($this->history)
        ];

        $redis->updateSessionState($this->tenantId, $this->sessionToken, $stateData);
    }

    /**
     * Log transition to audit system
     */
    private function logTransition(array $transitionData): void
    {
        // Could integrate with WebhookAudit or separate logging system
        \Log::info('Call state transition', [
            'tenant_id' => $this->tenantId,
            'session_token' => $this->sessionToken,
            'transition' => $transitionData
        ]);
    }

    /**
     * Load state machine from persisted data
     */
    public static function loadFromPersistence(string $sessionToken, int $tenantId): ?self
    {
        $redis = app(RedisService::class);
        $stateData = $redis->getSessionState($tenantId, $sessionToken);

        if (!$stateData) {
            return null;
        }

        $machine = new self($sessionToken, $tenantId, $stateData['current_state']);

        if (isset($stateData['metadata'])) {
            $machine->metadata = json_decode($stateData['metadata'], true) ?? [];
        }

        return $machine;
    }

    /**
     * Validate state machine integrity
     */
    public function validateIntegrity(): bool
    {
        // Check that all transitions in history are valid
        $currentState = null;

        foreach ($this->history as $transition) {
            if (isset($transition['from_state'])) {
                // This is a transition record
                if ($currentState && !in_array($transition['to_state'], self::VALID_TRANSITIONS[$currentState] ?? [])) {
                    return false;
                }
                $currentState = $transition['to_state'];
            } else {
                // This is the initial state
                $currentState = $transition['state'];
            }
        }

        return $currentState === $this->currentState;
    }
}