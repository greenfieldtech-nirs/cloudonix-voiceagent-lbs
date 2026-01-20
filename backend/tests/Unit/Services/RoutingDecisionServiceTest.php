<?php

namespace Tests\Unit\Services;

use App\Models\AgentGroup;
use App\Models\InboundRoutingRule;
use App\Models\Tenant;
use App\Models\VoiceAgent;
use App\Services\RoutingDecisionService;
use App\Strategies\DistributionStrategyFactory;
use App\Services\CxmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoutingDecisionService $routingDecision;
    protected Tenant $tenant;
    protected VoiceAgent $voiceAgent;
    protected AgentGroup $agentGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routingDecision = app(RoutingDecisionService::class);
        $this->tenant = Tenant::factory()->create();

        // Create a voice agent
        $this->voiceAgent = VoiceAgent::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Agent',
            'provider' => 'vapi',
            'service_value' => 'test-agent-id',
            'username' => null,
            'password' => null,
            'capacity' => 5,
            'enabled' => true,
        ]);

        // Create an agent group
        $this->agentGroup = AgentGroup::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Group',
            'strategy' => 'round_robin',
            'enabled' => true,
        ]);

        // Add agent to group
        $this->agentGroup->memberships()->create([
            'voice_agent_id' => $this->voiceAgent->id,
            'priority' => 1,
        ]);
    }

    public function test_route_to_voice_agent()
    {
        // Create a routing rule for the agent
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => $this->voiceAgent->id,
            'priority' => 1,
            'enabled' => true,
        ]);

        // Ensure voice agent exists
        $this->assertNotNull(VoiceAgent::find($this->voiceAgent->id));

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $result = $this->routingDecision->executeRouting($rule, $callData);

        // Debug the result
        if (!$result['success']) {
            $this->fail('Routing failed: ' . json_encode($result));
        }

        $this->assertTrue($result['success']);
        $this->assertEquals('voice_agent', $result['routing_type']);
        $this->assertStringContainsString('<Dial', $result['cxml']);
        $this->assertStringContainsString('<Service', $result['cxml']);
        $this->assertEquals($this->voiceAgent->id, $result['target']->id);
    }

    public function test_route_to_agent_group()
    {
        // Create a routing rule for the group
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'group',
            'target_id' => $this->agentGroup->id,
            'priority' => 1,
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $result = $this->routingDecision->executeRouting($rule, $callData);

        $this->assertTrue($result['success']);
        $this->assertEquals('agent_group', $result['routing_type']);
        $this->assertStringContainsString('<Dial', $result['cxml']);
        $this->assertStringContainsString('<Service', $result['cxml']);
        $this->assertEquals($this->agentGroup->id, $result['target']->id);
        $this->assertEquals($this->voiceAgent->id, $result['selected_agent']->id);
    }

    public function test_hangup_when_no_agents_available()
    {
        // Create a routing rule for a non-existent agent
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => 99999, // Non-existent agent
            'priority' => 1,
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $result = $this->routingDecision->executeRouting($rule, $callData);

        $this->assertFalse($result['success']);
        $this->assertEquals('hangup', $result['routing_type']);
        $this->assertStringContainsString('<Hangup', $result['cxml']);
    }

    public function test_hangup_when_agent_inactive()
    {
        // Deactivate the agent
        $this->voiceAgent->update(['enabled' => false]);

        // Create a routing rule for the inactive agent
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => $this->voiceAgent->id,
            'priority' => 1,
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $result = $this->routingDecision->executeRouting($rule, $callData);

        $this->assertFalse($result['success']);
        $this->assertEquals('hangup', $result['routing_type']);
        $this->assertStringContainsString('<Hangup', $result['cxml']);
    }

    public function test_cxml_generation_includes_caller_id()
    {
        // Create a routing rule for the agent
        $rule = InboundRoutingRule::create([
            'tenant_id' => $this->tenant->id,
            'pattern' => '+1234567890',
            'target_type' => 'agent',
            'target_id' => $this->voiceAgent->id,
            'priority' => 1,
            'enabled' => true,
        ]);

        $callData = [
            'caller_id' => '+0987654321',
            'destination' => '+1234567890',
            'direction' => 'inbound',
        ];

        $result = $this->routingDecision->executeRouting($rule, $callData);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('callerId="+0987654321"', $result['cxml']);
    }
}