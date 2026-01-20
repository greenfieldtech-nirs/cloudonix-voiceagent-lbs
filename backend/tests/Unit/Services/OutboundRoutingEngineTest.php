<?php

namespace Tests\Unit\Services;

use App\Models\OutboundRoutingRule;
use App\Models\Tenant;
use App\Models\Trunk;
use App\Services\OutboundRoutingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboundRoutingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected OutboundRoutingEngine $outboundRouting;
    protected Tenant $tenant;
    protected Trunk $trunk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outboundRouting = app(OutboundRoutingEngine::class);
        $this->tenant = Tenant::factory()->create();

        // Create a test trunk
        $this->trunk = Trunk::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Trunk',
            'cloudonix_trunk_id' => 'trunk_123',
            'description' => 'Test trunk for outbound routing',
            'configuration' => ['timeout' => 30],
            'priority' => 10,
            'capacity' => 10,
            'enabled' => true,
            'is_default' => true,
        ]);
    }

    public function test_evaluate_outbound_routing_with_matching_rule()
    {
        // Create an outbound routing rule
        $rule = OutboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'caller_id' => '+1234567890',
            'destination_pattern' => '+1',
            'trunk_config' => ['trunk_ids' => [$this->trunk->id]],
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+1234567890',
            'destination' => '+1987654321',
            'direction' => 'outbound',
        ];

        $result = $this->outboundRouting->evaluateOutboundRouting($this->tenant, $callData);

        $this->assertTrue($result['success']);
        $this->assertEquals('outbound_rule', $result['routing_type']);
        $this->assertEquals($rule->id, $result['rule']->id);
        $this->assertEquals($this->trunk->id, $result['selected_trunk']->id);
    }

    public function test_evaluate_outbound_routing_with_no_matching_rule_uses_default_trunk()
    {
        $callData = [
            'caller_id' => '+1234567890',
            'destination' => '+1987654321',
            'direction' => 'outbound',
        ];

        $result = $this->outboundRouting->evaluateOutboundRouting($this->tenant, $callData);

        $this->assertTrue($result['success']);
        $this->assertEquals('default_trunk', $result['routing_type']);
        $this->assertEquals($this->trunk->id, $result['selected_trunk']->id);
    }

    public function test_evaluate_outbound_routing_with_no_available_trunks()
    {
        // Disable the default trunk
        $this->trunk->update(['enabled' => false]);

        $callData = [
            'caller_id' => '+1234567890',
            'destination' => '+1987654321',
            'direction' => 'outbound',
        ];

        $result = $this->outboundRouting->evaluateOutboundRouting($this->tenant, $callData);

        $this->assertFalse($result['success']);
        $this->assertEquals('fallback', $result['routing_type']);
        $this->assertStringContainsString('No trunks available', $result['reason']);
    }

    public function test_is_outbound_call_detection()
    {
        // Create an outbound routing rule
        OutboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'caller_id' => '+1234567890',
            'destination_pattern' => '+1',
            'trunk_config' => ['trunk_ids' => [$this->trunk->id]],
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+1234567890',
            'destination' => '+1987654321',
        ];

        $isOutbound = $this->outboundRouting->isOutboundCall($this->tenant, $callData);

        $this->assertTrue($isOutbound);
    }

    public function test_is_outbound_call_detection_false_for_unknown_caller()
    {
        $callData = [
            'caller_id' => '+9999999999', // Unknown caller
            'destination' => '+1987654321',
        ];

        $isOutbound = $this->outboundRouting->isOutboundCall($this->tenant, $callData);

        $this->assertFalse($isOutbound);
    }

    public function test_rule_caller_id_matching()
    {
        $rule = new OutboundRoutingRule([
            'caller_id' => '+1234567890',
        ]);

        $this->assertTrue($rule->matchesCallerId('+1234567890'));
        $this->assertFalse($rule->matchesCallerId('+0987654321'));
    }

    public function test_rule_destination_matching()
    {
        $rule = new OutboundRoutingRule([
            'destination_pattern' => '+1',
        ]);

        $this->assertTrue($rule->matchesDestination('+1987654321'));
        $this->assertTrue($rule->matchesDestination('+1123456789'));
        $this->assertFalse($rule->matchesDestination('+44123456789'));
    }

    public function test_get_tenant_trunks()
    {
        $trunks = $this->outboundRouting->getTenantTrunks($this->tenant);

        $this->assertCount(1, $trunks);
        $this->assertEquals($this->trunk->id, $trunks[0]['id']);
        $this->assertEquals($this->trunk->name, $trunks[0]['name']);
    }

    public function test_get_tenant_rules()
    {
        // Create a rule
        $rule = OutboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'caller_id' => '+1234567890',
            'destination_pattern' => '+1',
            'trunk_config' => ['trunk_ids' => [$this->trunk->id]],
            'enabled' => true,
        ]);

        $rules = $this->outboundRouting->getTenantRules($this->tenant);

        $this->assertCount(1, $rules);
        $this->assertEquals($rule->id, $rules[0]['id']);
        $this->assertEquals('+1234567890', $rules[0]['caller_id']);
    }
}