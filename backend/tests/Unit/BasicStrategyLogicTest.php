<?php

namespace Tests\Unit;

use App\Enums\DistributionStrategy;
use App\Models\AgentGroup;
use App\Models\Tenant;
use App\Models\VoiceAgent;
use App\Strategies\LoadBalancedStrategy;
use App\Strategies\PriorityStrategy;
use App\Strategies\RoundRobinStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Basic Strategy Logic Tests
 *
 * Simple tests for strategy core logic without complex database setup
 */
class BasicStrategyLogicTest extends TestCase
{
    /** @test */
    public function strategy_enum_validation_works()
    {
        $this->assertEquals('Load Balanced', DistributionStrategy::LOAD_BALANCED->getDisplayName());
        $this->assertEquals('Priority', DistributionStrategy::PRIORITY->getDisplayName());
        $this->assertEquals('Round Robin', DistributionStrategy::ROUND_ROBIN->getDisplayName());

        $this->assertTrue(DistributionStrategy::PRIORITY->requiresOrdering());
        $this->assertFalse(DistributionStrategy::LOAD_BALANCED->requiresOrdering());

        $allStrategies = DistributionStrategy::all();
        $this->assertCount(3, $allStrategies);
        $this->assertContains('load_balanced', $allStrategies);
        $this->assertContains('priority', $allStrategies);
        $this->assertContains('round_robin', $allStrategies);
    }

    /** @test */
    public function strategy_configuration_defaults_work()
    {
        $loadBalancedDefaults = DistributionStrategy::LOAD_BALANCED->getDefaultSettings();
        $this->assertEquals(24, $loadBalancedDefaults['window_hours']);
        $this->assertNull($loadBalancedDefaults['max_calls_per_agent']);

        $priorityDefaults = DistributionStrategy::PRIORITY->getDefaultSettings();
        $this->assertTrue($priorityDefaults['failover_enabled']);
        $this->assertTrue($priorityDefaults['round_robin_same_priority']);

        $roundRobinDefaults = DistributionStrategy::ROUND_ROBIN->getDefaultSettings();
        $this->assertTrue($roundRobinDefaults['reset_on_agent_change']);
        $this->assertFalse($roundRobinDefaults['weighted_by_capacity']);
    }

    /** @test */
    public function strategy_configuration_validation_works()
    {
        // Valid config
        $errors = DistributionStrategy::LOAD_BALANCED->validateSettings([
            'window_hours' => 24,
            'max_calls_per_agent' => 10,
        ]);
        $this->assertEmpty($errors);

        // Invalid config - window too long
        $errors = DistributionStrategy::LOAD_BALANCED->validateSettings([
            'window_hours' => 200,
        ]);
        $this->assertNotEmpty($errors);
        $this->assertTrue(str_contains($errors[0] ?? '', 'Window hours must be an integer between 1 and 168'));

        // Invalid config - negative capacity
        $errors = DistributionStrategy::LOAD_BALANCED->validateSettings([
            'max_calls_per_agent' => -1,
        ]);
        $this->assertNotEmpty($errors);
        $this->assertTrue(str_contains($errors[0] ?? '', 'Max calls per agent must be null or a positive integer'));
    }

    /** @test */
    public function strategy_identifiers_are_correct()
    {
        $loadBalanced = new LoadBalancedStrategy(null);
        $this->assertEquals('load_balanced', $loadBalanced->getStrategyIdentifier());

        $priority = new PriorityStrategy(null);
        $this->assertEquals('priority', $priority->getStrategyIdentifier());

        $roundRobin = new RoundRobinStrategy(null);
        $this->assertEquals('round_robin', $roundRobin->getStrategyIdentifier());
    }
}