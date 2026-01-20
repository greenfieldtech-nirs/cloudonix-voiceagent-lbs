<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CallStateMachine;
use App\Services\RedisService;

class CallSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'session_id',
        'call_id',
        'domain',
        'caller_id',
        'destination',
        'direction',
        'from_number',
        'to_number',
        'token',
        'status',
        'vapp_server',
        'state',
        'metadata',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'call_start_time',
        'call_answer_time',
        'answer_time',
        'webhook_created_at',
        'webhook_modified_at',
    ];

    protected $casts = [
        'state' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'call_start_time' => 'datetime',
        'call_answer_time' => 'datetime',
        'answer_time' => 'datetime',
        'webhook_created_at' => 'datetime',
        'webhook_modified_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this call session.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the call events for this session.
     */
    public function callEvents(): HasMany
    {
        return $this->hasMany(CallEvent::class);
    }

    /**
     * Get or create state machine for this session
     */
    public function getStateMachine(): CallStateMachine
    {
        $redis = app(RedisService::class);

        // Try to load from Redis first
        $machine = CallStateMachine::loadFromPersistence($this->session_id, $this->tenant_id);

        if (!$machine) {
            // Create new state machine
            $initialState = $this->mapStatusToState($this->status);
            $machine = new CallStateMachine($this->session_id, $this->tenant_id, $initialState);

            // Set initial metadata
            $machine->setMetadata([
                'call_id' => $this->call_id,
                'direction' => $this->direction,
                'from_number' => $this->from_number,
                'to_number' => $this->to_number,
            ]);
        }

        return $machine;
    }

    /**
     * Transition to new state
     */
    public function transitionTo(string $newState, array $metadata = []): bool
    {
        $machine = $this->getStateMachine();

        try {
            $machine->transitionTo($newState, $metadata);

            // Update database status
            $this->update([
                'status' => $this->mapStateToStatus($newState),
                'state' => $machine->getHistory(),
                'metadata' => array_merge($this->metadata ?? [], $metadata),
            ]);

            return true;
        } catch (\InvalidArgumentException $e) {
            \Log::error('Invalid state transition', [
                'session_id' => $this->session_id,
                'from_state' => $machine->getCurrentState(),
                'to_state' => $newState,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Check if session can transition to state
     */
    public function canTransitionTo(string $newState): bool
    {
        $machine = $this->getStateMachine();
        return $machine->canTransitionTo($newState);
    }

    /**
     * Get current state
     */
    public function getCurrentState(): string
    {
        $machine = $this->getStateMachine();
        return $machine->getCurrentState();
    }

    /**
     * Check if session is in terminal state
     */
    public function isTerminal(): bool
    {
        $machine = $this->getStateMachine();
        return $machine->isTerminal();
    }

    /**
     * Map database status to state machine state
     */
    private function mapStatusToState(string $status): string
    {
        return match($status) {
            'ringing' => CallStateMachine::STATE_CONNECTING,
            'answered' => CallStateMachine::STATE_CONNECTED,
            'completed' => CallStateMachine::STATE_COMPLETED,
            'failed' => CallStateMachine::STATE_FAILED,
            'busy' => CallStateMachine::STATE_BUSY,
            default => CallStateMachine::STATE_RECEIVED,
        };
    }

    /**
     * Map state machine state to database status
     */
    private function mapStateToStatus(string $state): string
    {
        return match($state) {
            CallStateMachine::STATE_RECEIVED => 'received',
            CallStateMachine::STATE_QUEUED => 'queued',
            CallStateMachine::STATE_ROUTING => 'routing',
            CallStateMachine::STATE_CONNECTING => 'ringing',
            CallStateMachine::STATE_CONNECTED => 'answered',
            CallStateMachine::STATE_COMPLETED => 'completed',
            CallStateMachine::STATE_BUSY => 'busy',
            CallStateMachine::STATE_FAILED => 'failed',
            CallStateMachine::STATE_NO_ANSWER => 'no_answer',
            default => 'unknown',
        };
    }

    /**
     * Get state history
     */
    public function getStateHistory(): array
    {
        $machine = $this->getStateMachine();
        return $machine->getHistory();
    }

    /**
     * Validate state machine integrity
     */
    public function validateStateIntegrity(): bool
    {
        $machine = $this->getStateMachine();
        return $machine->validateIntegrity();
    }
}
