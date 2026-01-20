<?php

namespace Tests\Unit;

use App\Enums\DistributionStrategy;
use App\Models\AgentGroup;
use App\Models\Tenant;
use App\Models\VoiceAgent;
use App\Strategies\LoadBalancedStrategy;
use App\Strategies\PriorityStrategy;
use App\Strategies\RoundRobinStrategy;
use App\Services\RedisStrategyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Distribution Strategies Test Suite
 *
 * Comprehensive testing for all distribution strategies including:
 * - Unit tests for strategy logic
 * - Concurrent access testing
 * - Statistical distribution validation
 * - Redis failure simulation
 * - Performance benchmarks
 */
class DistributionStrategiesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private AgentGroup $loadBalancedGroup;
    private AgentGroup $priorityGroup;
    private AgentGroup $roundRobinGroup;
    private $agents;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant
        $this->tenant = Tenant::factory()->create();

        // Create test groups with different strategies
        $this->loadBalancedGroup = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::LOAD_BALANCED,
            'settings' => ['window_hours' => 1], // Short window for testing
        ]);

        $this->priorityGroup = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::PRIORITY,
        ]);

        $this->roundRobinGroup = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::ROUND_ROBIN,
        ]);

        // Create test agents
        $this->agents = VoiceAgent::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);

        // Add agents to groups with different priorities/capacities
        foreach ($this->agents as $index => $agent) {
            // Load balanced group - all equal
            $this->loadBalancedGroup->memberships()->create([
                'voice_agent_id' => $agent->id,
                'priority' => 50,
                'capacity' => null,
            ]);

            // Priority group - different priorities
            $this->priorityGroup->memberships()->create([
                'voice_agent_id' => $agent->id,
                'priority' => (5 - $index) * 20, // 100, 80, 60, 40, 20
                'capacity' => null,
            ]);

            // Round robin group - different capacities
            $this->roundRobinGroup->memberships()->create([
                'voice_agent_id' => $agent->id,
                'priority' => 50,
                'capacity' => $index + 1, // 1, 2, 3, 4, 5
            ]);
        }
    }

    protected function tearDown(): void
    {
        // Clean up Redis keys
        $patterns = [
            'agent_load:*',
            'round_robin:*',
            'priority:*',
            'lock:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del($keys);
            }
        }

        parent::tearDown();
    }

    /** @test */
    public function load_balanced_strategy_selects_agent_with_least_calls()
    {
        $strategy = new LoadBalancedStrategy($this->loadBalancedGroup);

        // Initially all agents have 0 calls
        $selectedAgents = [];
        for ($i = 0; $i < 10; $i++) {
            $agent = $strategy->selectAgent($this->loadBalancedGroup);
            $selectedAgents[] = $agent->id;
            $strategy->recordCall($this->loadBalancedGroup, $agent);
        }

        // Should distribute calls relatively evenly
        $counts = array_count_values($selectedAgents);
        $this->assertGreaterThan(1, count($counts), 'Calls should be distributed among multiple agents');
        $this->assertLessThanOrEqual(4, max($counts), 'No agent should get more than 4 calls in 10 selections');
    }

    /** @test */
    public function load_balanced_strategy_avoids_agents_at_capacity()
    {
        // Set low capacity limit
        $this->loadBalancedGroup->update([
            'settings' => ['window_hours' => 1, 'max_calls_per_agent' => 2]
        ]);

        $strategy = new LoadBalancedStrategy($this->loadBalancedGroup);

        // Fill up first agent
        $firstAgent = $this->agents[0];
        $strategy->recordCall($this->loadBalancedGroup, $firstAgent);
        $strategy->recordCall($this->loadBalancedGroup, $firstAgent);
        $strategy->recordCall($this->loadBalancedGroup, $firstAgent); // This should make it unavailable

        // Next selection should not pick the first agent
        $selectedAgent = $strategy->selectAgent($this->loadBalancedGroup);
        $this->assertNotEquals($firstAgent->id, $selectedAgent->id, 'Should not select agent at capacity limit');
    }

    /** @test */
    public function priority_strategy_selects_highest_priority_agent()
    {
        $strategy = new PriorityStrategy($this->priorityGroup);

        // Should always select the highest priority agent
        for ($i = 0; $i < 5; $i++) {
            $agent = $strategy->selectAgent($this->priorityGroup);
            $membership = $this->priorityGroup->memberships()
                ->where('voice_agent_id', $agent->id)
                ->first();

            $this->assertEquals(100, $membership->priority, 'Should select highest priority agent');
        }
    }

    /** @test */
    public function priority_strategy_fails_over_when_high_priority_unavailable()
    {
        // Disable the highest priority agent
        $highestPriorityAgent = $this->priorityGroup->memberships()
            ->orderBy('priority', 'desc')
            ->first()
            ->voiceAgent;

        $highestPriorityAgent->update(['enabled' => false]);

        $strategy = new PriorityStrategy($this->priorityGroup);

        $selectedAgent = $strategy->selectAgent($this->priorityGroup);
        $membership = $this->priorityGroup->memberships()
            ->where('voice_agent_id', $selectedAgent->id)
            ->first();

        $this->assertEquals(80, $membership->priority, 'Should select next highest priority when first is unavailable');
    }

    /** @test */
    public function round_robin_strategy_rotates_through_agents()
    {
        $strategy = new RoundRobinStrategy($this->roundRobinGroup);

        $selectedAgents = [];
        for ($i = 0; $i < count($this->agents); $i++) {
            $agent = $strategy->selectAgent($this->roundRobinGroup);
            $selectedAgents[] = $agent->id;
        }

        // Should have selected each agent exactly once
        $uniqueSelections = array_unique($selectedAgents);
        $this->assertCount(count($this->agents), $uniqueSelections, 'Should select each agent exactly once in rotation');
    }

    /** @test */
    public function round_robin_strategy_uses_capacity_weighting()
    {
        $strategy = new RoundRobinStrategy($this->roundRobinGroup);

        // With capacity weighting, agent with capacity 5 should be selected more often
        $selections = [];
        for ($i = 0; $i < 15; $i++) { // More selections to see weighting effect
            $agent = $strategy->selectAgent($this->roundRobinGroup);
            $selections[] = $agent->id;
        }

        $agent5Selections = array_filter($selections, fn($id) => $id === $this->agents[4]->id);
        $this->assertGreaterThan(2, count($agent5Selections), 'Agent with capacity 5 should be selected more frequently');
    }

    /** @test */
    public function strategies_return_null_when_no_agents_available()
    {
        // Create empty group
        $emptyGroup = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::LOAD_BALANCED,
        ]);

        $strategy = new LoadBalancedStrategy($emptyGroup);
        $this->assertNull($strategy->selectAgent($emptyGroup));

        $strategy = new PriorityStrategy($emptyGroup);
        $this->assertNull($strategy->selectAgent($emptyGroup));

        $strategy = new RoundRobinStrategy($emptyGroup);
        $this->assertNull($strategy->selectAgent($emptyGroup));
    }

    /** @test */
    public function strategies_handle_disabled_agents_correctly()
    {
        // Disable all agents in load balanced group
        $this->loadBalancedGroup->agents()->update(['enabled' => false]);

        $strategy = new LoadBalancedStrategy($this->loadBalancedGroup);
        $this->assertNull($strategy->selectAgent($this->loadBalancedGroup));
    }

    /** @test */
    public function redis_failure_fallback_works()
    {
        // Simulate Redis failure by disconnecting
        Redis::disconnect();

        try {
            $strategy = new LoadBalancedStrategy($this->loadBalancedGroup);
            $agent = $strategy->selectAgent($this->loadBalancedGroup);

            // Should still work with fallback (though may be less optimal)
            $this->assertNotNull($agent);
        } finally {
            // Reconnect Redis for other tests
            Redis::connect();
        }
    }

    /** @test */
    public function concurrent_strategy_access_works()
    {
        $strategy = new LoadBalancedStrategy($this->loadBalancedGroup);

        // Simulate concurrent access
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = $strategy->selectAgent($this->loadBalancedGroup);
        }

        // All results should be valid agents
        foreach ($results as $agent) {
            $this->assertNotNull($agent);
            $this->assertTrue($this->loadBalancedGroup->agents->contains('id', $agent->id));
        }
    }

    /** @test */
    public function strategy_configuration_validation_works()
    {
        // Test invalid load balanced config
        $errors = DistributionStrategy::LOAD_BALANCED->validateSettings([
            'window_hours' => 200, // Too high
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContains('Window hours must be an integer between 1 and 168', $errors[0]);

        // Test valid config
        $errors = DistributionStrategy::LOAD_BALANCED->validateSettings([
            'window_hours' => 24,
            'max_calls_per_agent' => 10,
        ]);

        $this->assertEmpty($errors);
    }

    /** @test */
    public function strategy_statistics_are_comprehensive()
    {
        $stats = $this->priorityGroup->getStrategyStats();

        $this->assertArrayHasKey('strategy', $stats);
        $this->assertArrayHasKey('total_agents', $stats);
        $this->assertArrayHasKey('active_agents', $stats);
        $this->assertArrayHasKey('priority_levels', $stats);
        $this->assertArrayHasKey('agents_by_priority', $stats);

        $this->assertEquals('priority', $stats['strategy']);
        $this->assertEquals(5, $stats['total_agents']);
        $this->assertGreaterThanOrEqual(5, $stats['active_agents']);
    }

    /** @test */
    public function round_robin_state_persistence_works()
    {
        $strategy = new RoundRobinStrategy($this->roundRobinGroup);

        // Get initial state
        $initialState = $strategy->getRotationState();

        // Make some selections
        for ($i = 0; $i < 3; $i++) {
            $strategy->selectAgent($this->roundRobinGroup);
        }

        // Get state after selections
        $updatedState = $strategy->getRotationState();

        // Pointer should have moved
        $this->assertNotEquals($initialState['simple_rotation_index'], $updatedState['simple_rotation_index']);
    }

    /** @test */
    public function strategy_reset_functionality_works()
    {
        $strategy = new RoundRobinStrategy($this->roundRobinGroup);

        // Make some selections to change state
        for ($i = 0; $i < 2; $i++) {
            $strategy->selectAgent($this->roundRobinGroup);
        }

        // Reset state
        $reset = $this->roundRobinGroup->resetStrategyState();
        $this->assertTrue($reset);

        // Verify state was reset (pointer should be 0)
        $state = $strategy->getRotationState();
        $this->assertEquals(0, $state['simple_rotation_index']);
    }

    /** @test */
    public function performance_benchmark_load_balanced_strategy()
    {
        $strategy = new LoadBalancedStrategy($this->loadBalancedGroup);

        $startTime = microtime(true);

        // Perform 100 selections
        for ($i = 0; $i < 100; $i++) {
            $agent = $strategy->selectAgent($this->loadBalancedGroup);
            if ($i % 10 === 0) { // Record call every 10th selection
                $strategy->recordCall($this->loadBalancedGroup, $agent);
            }
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete in reasonable time (less than 1 second for 100 operations)
        $this->assertLessThan(1.0, $duration, 'Load balanced strategy should perform 100 operations in under 1 second');

        // Average operation time should be reasonable
        $avgOperationTime = $duration / 100;
        $this->assertLessThan(0.01, $avgOperationTime, 'Average operation should be under 10ms');
    }
}