<?php

namespace Tests\Feature;

use App\Enums\DistributionStrategy;
use App\Models\AgentGroup;
use App\Models\AgentGroupMembership;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agent Group Management Test Suite
 *
 * Comprehensive testing for agent group CRUD operations including:
 * - Group creation, updates, and deletion
 * - Membership management and validation
 * - Strategy integration and state management
 * - Authorization and tenant isolation
 * - Bulk operations and edge cases
 */
class AgentGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $agents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create test agents
        $this->agents = VoiceAgent::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);
    }

    /** @test */
    public function user_can_create_agent_group()
    {
        $data = [
            'name' => 'Test Load Balanced Group',
            'description' => 'A test group for load balancing',
            'strategy' => 'load_balanced',
            'settings' => [
                'window_hours' => 24,
                'max_calls_per_agent' => 50,
            ],
            'enabled' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/agent-groups', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'strategy',
                    'strategy_display_name',
                    'enabled',
                    'settings',
                    'created_at',
                ]
            ]);

        $this->assertDatabaseHas('agent_groups', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Load Balanced Group',
            'strategy' => 'load_balanced',
            'enabled' => true,
        ]);
    }

    /** @test */
    public function user_can_list_agent_groups_with_filters()
    {
        // Create groups with different strategies
        AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::LOAD_BALANCED,
            'enabled' => true,
        ]);

        AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::PRIORITY,
            'enabled' => false,
        ]);

        // Create group in different tenant (should not be visible)
        $otherTenant = Tenant::factory()->create();
        AgentGroup::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/agent-groups?enabled=1&strategy=load_balanced');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $data = $response->json('data.0');
        $this->assertEquals('load_balanced', $data['strategy']);
        $this->assertTrue($data['enabled']);
    }

    /** @test */
    public function user_can_view_agent_group_details()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/agent-groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'strategy',
                    'strategy_display_name',
                    'strategy_description',
                    'enabled',
                    'settings',
                    'can_route',
                    'memberships_count',
                    'active_agents_count',
                ]
            ]);
    }

    /** @test */
    public function user_can_update_agent_group()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::LOAD_BALANCED,
        ]);

        $updateData = [
            'name' => 'Updated Group Name',
            'strategy' => 'priority',
            'settings' => [
                'failover_enabled' => false,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/agent-groups/{$group->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Group Name')
            ->assertJsonPath('data.strategy', 'priority');

        $this->assertDatabaseHas('agent_groups', [
            'id' => $group->id,
            'name' => 'Updated Group Name',
            'strategy' => 'priority',
        ]);
    }

    /** @test */
    public function user_can_toggle_group_status()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/agent-groups/{$group->id}/toggle");

        $response->assertStatus(200)
            ->assertJsonPath('data.enabled', false);

        $this->assertDatabaseHas('agent_groups', [
            'id' => $group->id,
            'enabled' => false,
        ]);
    }

    /** @test */
    public function user_can_delete_empty_agent_group()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/agent-groups/{$group->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('agent_groups', ['id' => $group->id]);
    }

    /** @test */
    public function user_cannot_delete_group_with_memberships()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Add membership
        $group->memberships()->create([
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/agent-groups/{$group->id}");

        $response->assertStatus(422)
            ->assertJsonPath('memberships_count', 1);

        $this->assertDatabaseHas('agent_groups', ['id' => $group->id]);
    }

    /** @test */
    public function user_can_add_agent_to_group()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $data = [
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 75,
            'capacity' => 10,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/agent-groups/{$group->id}/memberships", $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'agent_group_id',
                    'voice_agent_id',
                    'priority',
                    'capacity',
                ]
            ]);

        $this->assertDatabaseHas('agent_group_memberships', [
            'agent_group_id' => $group->id,
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 75,
            'capacity' => 10,
        ]);
    }

    /** @test */
    public function user_cannot_add_same_agent_to_group_twice()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Add agent first time
        $group->memberships()->create([
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 50,
        ]);

        // Try to add same agent again
        $data = [
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 75,
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/agent-groups/{$group->id}/memberships", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['voice_agent_id']);
    }

    /** @test */
    public function user_can_update_group_membership()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $membership = $group->memberships()->create([
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 50,
            'capacity' => 5,
        ]);

        $updateData = [
            'priority' => 90,
            'capacity' => 15,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/agent-groups/{$group->id}/memberships/{$membership->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('agent_group_memberships', [
            'id' => $membership->id,
            'priority' => 90,
            'capacity' => 15,
        ]);
    }

    /** @test */
    public function user_can_remove_agent_from_group()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $membership = $group->memberships()->create([
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/agent-groups/{$group->id}/memberships/{$membership->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('agent_group_memberships', ['id' => $membership->id]);
    }

    /** @test */
    public function user_can_get_available_agents_for_group()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Add one agent to group
        $group->memberships()->create([
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/agent-groups/{$group->id}/available-agents");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data'); // Should have 2 agents available (3 total - 1 assigned)

        $data = $response->json('data');
        $agentIds = collect($data)->pluck('id')->sort()->values();
        $expectedIds = collect([$this->agents[1]->id, $this->agents[2]->id])->sort()->values();

        $this->assertEquals($expectedIds, $agentIds);
    }

    /** @test */
    public function user_can_bulk_update_memberships()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $memberships = [];
        foreach ($this->agents as $agent) {
            $memberships[] = $group->memberships()->create([
                'voice_agent_id' => $agent->id,
                'priority' => 50,
            ]);
        }

        $bulkData = [
            'memberships' => [
                [
                    'id' => $memberships[0]->id,
                    'priority' => 100,
                    'capacity' => 20,
                ],
                [
                    'id' => $memberships[1]->id,
                    'priority' => 80,
                    'capacity' => 15,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->patchJson("/api/agent-groups/{$group->id}/memberships/bulk-update", $bulkData);

        $response->assertStatus(200)
            ->assertJsonPath('data.updated_count', 2);

        $this->assertDatabaseHas('agent_group_memberships', [
            'id' => $memberships[0]->id,
            'priority' => 100,
            'capacity' => 20,
        ]);

        $this->assertDatabaseHas('agent_group_memberships', [
            'id' => $memberships[1]->id,
            'priority' => 80,
            'capacity' => 15,
        ]);
    }

    /** @test */
    public function user_can_get_group_statistics()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
            'strategy' => DistributionStrategy::PRIORITY,
        ]);

        // Add some memberships
        foreach ($this->agents as $index => $agent) {
            $group->memberships()->create([
                'voice_agent_id' => $agent->id,
                'priority' => (3 - $index) * 25, // 75, 50, 25
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson("/api/agent-groups/{$group->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'strategy',
                    'total_agents',
                    'active_agents',
                    'priority_levels',
                    'agents_by_priority',
                ],
                'meta' => [
                    'group_name',
                    'strategy',
                    'enabled',
                    'can_route',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals('priority', $data['strategy']);
        $this->assertEquals(3, $data['total_agents']);
        $this->assertGreaterThanOrEqual(3, $data['active_agents']);
    }

    /** @test */
    public function user_cannot_access_other_tenant_groups()
    {
        $otherTenant = Tenant::factory()->create();
        $otherGroup = AgentGroup::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/agent-groups/{$otherGroup->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function validation_errors_for_invalid_group_data()
    {
        $data = [
            'name' => '', // Empty name
            'strategy' => 'invalid_strategy', // Invalid strategy
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/agent-groups', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'strategy']);
    }

    /** @test */
    public function membership_validation_errors()
    {
        $group = AgentGroup::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $data = [
            'voice_agent_id' => $this->agents[0]->id,
            'priority' => 150, // Invalid priority (> 100)
            'capacity' => -5, // Invalid capacity
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/agent-groups/{$group->id}/memberships", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority', 'capacity']);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_endpoints()
    {
        $response = $this->getJson('/api/agent-groups');
        $response->assertStatus(401);

        $response = $this->postJson('/api/agent-groups', []);
        $response->assertStatus(401);
    }
}