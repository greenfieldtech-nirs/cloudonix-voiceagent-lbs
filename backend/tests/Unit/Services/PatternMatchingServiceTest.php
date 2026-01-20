<?php

namespace Tests\Unit\Services;

use App\Models\InboundRoutingRule;
use App\Models\Tenant;
use App\Services\PatternMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatternMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PatternMatchingService $patternMatcher;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patternMatcher = app(PatternMatchingService::class);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_exact_number_match()
    {
        // Create a rule for exact number match
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => 1,
            'priority' => 1,
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $matchedRule = $this->patternMatcher->evaluateRules($this->tenant, $callData);

        $this->assertNotNull($matchedRule);
        $this->assertEquals($rule->id, $matchedRule->id);
    }

    public function test_prefix_match()
    {
        // Create a rule for prefix match
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '123',
            'target_type' => 'agent',
            'target_id' => 1,
            'priority' => 1,
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $matchedRule = $this->patternMatcher->evaluateRules($this->tenant, $callData);

        $this->assertNotNull($matchedRule);
        $this->assertEquals($rule->id, $matchedRule->id);
    }

    public function test_no_match_when_disabled()
    {
        // Create a disabled rule
        InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => 1,
            'priority' => 1,
            'enabled' => false, // Disabled
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $matchedRule = $this->patternMatcher->evaluateRules($this->tenant, $callData);

        $this->assertNull($matchedRule);
    }

    public function test_priority_ordering()
    {
        // Create two rules with different priorities
        $lowPriorityRule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '123',
            'target_type' => 'agent',
            'target_id' => 1,
            'priority' => 1, // Lower priority
            'enabled' => true,
        ]);

        $highPriorityRule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => 2,
            'priority' => 10, // Higher priority
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $matchedRule = $this->patternMatcher->evaluateRules($this->tenant, $callData);

        // Should match the higher priority rule
        $this->assertNotNull($matchedRule);
        $this->assertEquals($highPriorityRule->id, $matchedRule->id);
    }

    public function test_pattern_validation()
    {
        // Valid patterns
        $this->assertTrue($this->patternMatcher->validatePattern('+1234567890')['valid']);
        $this->assertTrue($this->patternMatcher->validatePattern('123')['valid']);

        // Invalid patterns
        $this->assertFalse($this->patternMatcher->validatePattern('')['valid']);
        $this->assertFalse($this->patternMatcher->validatePattern('invalid@pattern')['valid']);
        $this->assertFalse($this->patternMatcher->validatePattern(str_repeat('1', 25))['valid']); // Too long
    }

    public function test_pattern_testing()
    {
        $testNumbers = ['+1234567890', '+1234567891', '+0987654321'];
        $results = $this->patternMatcher->testPattern('123', $testNumbers);

        $this->assertCount(3, $results);

        // First two should match (start with 123), third should not
        $this->assertTrue($results[0]['matches']);
        $this->assertTrue($results[1]['matches']);
        $this->assertFalse($results[2]['matches']);
    }
}