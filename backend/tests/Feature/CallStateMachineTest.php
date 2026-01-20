<?php

namespace Tests\Feature;

use App\Services\CallStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private CallStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new CallStateMachine('test_session_123', 1);
    }

    public function test_initial_state_is_received()
    {
        $this->assertEquals(CallStateMachine::STATE_RECEIVED, $this->stateMachine->getCurrentState());
    }

    public function test_valid_transitions_from_received()
    {
        $this->assertTrue($this->stateMachine->canTransitionTo(CallStateMachine::STATE_QUEUED));
        $this->assertFalse($this->stateMachine->canTransitionTo(CallStateMachine::STATE_ROUTING));
    }

    public function test_successful_state_transition()
    {
        $this->stateMachine->transitionTo(CallStateMachine::STATE_QUEUED);

        $this->assertEquals(CallStateMachine::STATE_QUEUED, $this->stateMachine->getCurrentState());

        $history = $this->stateMachine->getHistory();
        $this->assertCount(2, $history); // Initial + transition
        $this->assertEquals(CallStateMachine::STATE_QUEUED, $history[1]['to_state']);
    }

    public function test_invalid_state_transition_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_ROUTING);
    }

    public function test_complete_call_flow()
    {
        // received -> queued -> routing -> connecting -> connected -> completed
        $this->stateMachine->transitionTo(CallStateMachine::STATE_QUEUED);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_ROUTING);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_CONNECTING);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_CONNECTED);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_COMPLETED);

        $this->assertEquals(CallStateMachine::STATE_COMPLETED, $this->stateMachine->getCurrentState());
        $this->assertTrue($this->stateMachine->isTerminal());
    }

    public function test_terminal_states()
    {
        $terminalStates = [
            CallStateMachine::STATE_COMPLETED,
            CallStateMachine::STATE_BUSY,
            CallStateMachine::STATE_FAILED,
            CallStateMachine::STATE_NO_ANSWER,
        ];

        foreach ($terminalStates as $state) {
            $machine = new CallStateMachine('test_' . $state, 1, $state);
            $this->assertTrue($machine->isTerminal());
            $this->assertEmpty(CallStateMachine::getValidTransitions($state));
        }
    }

    public function test_non_terminal_states()
    {
        $nonTerminalStates = [
            CallStateMachine::STATE_RECEIVED,
            CallStateMachine::STATE_QUEUED,
            CallStateMachine::STATE_ROUTING,
            CallStateMachine::STATE_CONNECTING,
            CallStateMachine::STATE_CONNECTED,
        ];

        foreach ($nonTerminalStates as $state) {
            $machine = new CallStateMachine('test_' . $state, 1, $state);
            $this->assertFalse($machine->isTerminal());
            $this->assertNotEmpty(CallStateMachine::getValidTransitions($state));
        }
    }

    public function test_state_metadata()
    {
        $metadata = $this->stateMachine->getStateMetadata(CallStateMachine::STATE_COMPLETED);
        $this->assertEquals('Call completed successfully', $metadata['description']);
        $this->assertEquals('green', $metadata['color']);
    }

    public function test_metadata_preservation()
    {
        $this->stateMachine->setMetadata(['agent_id' => 123, 'group_id' => 456]);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_QUEUED, ['priority' => 1]);

        $history = $this->stateMachine->getHistory();
        $transition = $history[1];

        $this->assertEquals(123, $transition['metadata']['agent_id']);
        $this->assertEquals(456, $transition['metadata']['group_id']);
        $this->assertEquals(1, $transition['metadata']['priority']);
    }

    public function test_all_states_have_metadata()
    {
        foreach (CallStateMachine::getAllStates() as $state) {
            $metadata = $this->stateMachine->getStateMetadata($state);
            $this->assertArrayHasKey('description', $metadata);
            $this->assertArrayHasKey('color', $metadata);
        }
    }

    public function test_state_machine_integrity_validation()
    {
        // Valid flow should pass integrity check
        $this->stateMachine->transitionTo(CallStateMachine::STATE_QUEUED);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_ROUTING);

        $this->assertTrue($this->stateMachine->validateIntegrity());
    }

    public function test_get_all_states()
    {
        $states = CallStateMachine::getAllStates();
        $this->assertContains(CallStateMachine::STATE_RECEIVED, $states);
        $this->assertContains(CallStateMachine::STATE_COMPLETED, $states);
        $this->assertCount(9, $states);
    }

    public function test_get_valid_transitions()
    {
        $transitions = CallStateMachine::getValidTransitions(CallStateMachine::STATE_RECEIVED);
        $this->assertContains(CallStateMachine::STATE_QUEUED, $transitions);
        $this->assertNotContains(CallStateMachine::STATE_ROUTING, $transitions);
    }

    public function test_failed_call_flow()
    {
        // received -> queued -> routing -> failed
        $this->stateMachine->transitionTo(CallStateMachine::STATE_QUEUED);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_ROUTING);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_FAILED);

        $this->assertEquals(CallStateMachine::STATE_FAILED, $this->stateMachine->getCurrentState());
        $this->assertTrue($this->stateMachine->isTerminal());
    }

    public function test_busy_call_flow()
    {
        // received -> queued -> routing -> connecting -> busy
        $this->stateMachine->transitionTo(CallStateMachine::STATE_QUEUED);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_ROUTING);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_CONNECTING);
        $this->stateMachine->transitionTo(CallStateMachine::STATE_BUSY);

        $this->assertEquals(CallStateMachine::STATE_BUSY, $this->stateMachine->getCurrentState());
        $this->assertTrue($this->stateMachine->isTerminal());
    }
}
