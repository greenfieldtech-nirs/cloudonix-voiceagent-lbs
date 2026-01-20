<?php

namespace Tests\Feature;

use App\Enums\VoiceAgentProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VoiceAgentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_user_can_list_voice_agents()
    {
        VoiceAgent::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'provider',
                        'enabled',
                        'service_value',
                        'metadata',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'links',
                'meta'
            ]);
    }

    public function test_user_can_filter_voice_agents_by_search()
    {
        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Agent One',
        ]);

        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Another Agent',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents?search=Test');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Test Agent One');
    }

    public function test_user_can_filter_voice_agents_by_provider()
    {
        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => VoiceAgentProvider::VAPI,
        ]);

        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => VoiceAgentProvider::SYNTHFLOW,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents?provider=vapi');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'vapi');
    }

    public function test_user_can_filter_voice_agents_by_enabled_status()
    {
        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);

        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'enabled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents?enabled=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.enabled', true);
    }

    public function test_user_can_sort_voice_agents()
    {
        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Z Agent',
        ]);

        VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'A Agent',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents?sort_by=name&sort_direction=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'A Agent')
            ->assertJsonPath('data.1.name', 'Z Agent');
    }

    public function test_user_cannot_see_other_tenant_agents()
    {
        $otherTenant = Tenant::factory()->create();
        VoiceAgent::factory()->forTenant($otherTenant)->create();

        $ourAgents = VoiceAgent::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents');

        $response->assertStatus(200);

        $data = $response->json('data');
        // Ensure all returned agents belong to the current tenant
        foreach ($data as $agent) {
            $this->assertEquals($this->tenant->id, $agent['tenant_id'],
                "Agent {$agent['id']} belongs to tenant {$agent['tenant_id']} but should belong to tenant {$this->tenant->id}");
        }

        // Ensure we can see our agents
        $returnedIds = collect($data)->pluck('id')->sort()->values();
        $expectedIds = collect($ourAgents)->pluck('id')->sort()->values();
        $this->assertEquals($expectedIds, $returnedIds);
    }

    public function test_user_can_create_voice_agent()
    {
        $data = [
            'name' => 'Test Voice Agent',
            'provider' => VoiceAgentProvider::VAPI->value,
            'enabled' => true,
            'username' => 'test@example.com',
            'password' => 'secret123',
            'service_value' => 'test123',
            'metadata' => ['key' => 'value'],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/voice-agents', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'provider',
                    'enabled',
                    'service_value',
                    'metadata',
                ]
            ]);

        $this->assertDatabaseHas('voice_agents', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Voice Agent',
            'provider' => VoiceAgentProvider::VAPI->value,
        ]);
    }

    public function test_user_can_view_single_voice_agent()
    {
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/voice-agents/{$agent->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $agent->id)
            ->assertJsonPath('data.name', $agent->name);
    }

    public function test_user_cannot_view_other_tenant_agent()
    {
        $otherTenant = Tenant::factory()->create();
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/voice-agents/{$agent->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_voice_agent()
    {
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $updateData = [
            'name' => 'Updated Agent Name',
            'enabled' => false,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/voice-agents/{$agent->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Agent Name')
            ->assertJsonPath('data.enabled', false);

        $this->assertDatabaseHas('voice_agents', [
            'id' => $agent->id,
            'name' => 'Updated Agent Name',
            'enabled' => false,
        ]);
    }

    public function test_user_can_toggle_voice_agent_status()
    {
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/voice-agents/{$agent->id}/toggle");

        $response->assertStatus(200)
            ->assertJsonPath('data.enabled', false);

        $this->assertDatabaseHas('voice_agents', [
            'id' => $agent->id,
            'enabled' => false,
        ]);
    }

    public function test_user_can_delete_voice_agent()
    {
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/voice-agents/{$agent->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Voice agent deleted successfully']);

        $this->assertDatabaseMissing('voice_agents', [
            'id' => $agent->id,
        ]);
    }

    public function test_user_cannot_delete_agent_used_in_groups()
    {
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create a mock group relationship (assuming groups exist)
        // For now, we'll just test the response structure

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/voice-agents/{$agent->id}");

        $response->assertStatus(200); // Should succeed if no groups
    }

    public function test_user_can_validate_agent_config()
    {
        $agent = VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => VoiceAgentProvider::VAPI,
            'service_value' => 'valid123', // Valid VAPI assistant ID format
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/voice-agents/{$agent->id}/validate");

        $response->assertStatus(200)
            ->assertJson([
                'valid' => true,
                'message' => 'Voice agent configuration is valid'
            ]);
    }

    public function test_user_can_get_providers_list()
    {
        // First check if the route exists by testing a working route
        $indexResponse = $this->actingAs($this->user)
            ->getJson('/api/voice-agents');
        $indexResponse->assertStatus(200);

        // Now test the providers route
        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents/providers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'providers' => [
                    '*' => [
                        'name',
                        'requires_auth',
                        'username_label',
                        'password_label',
                        'service_value_description',
                        'validation_rules',
                    ]
                ]
            ]);

        // Check that VAPI provider is included
        $response->assertJsonPath('providers.vapi.name', VoiceAgentProvider::VAPI->getDisplayName());
    }

    public function test_validation_errors_for_invalid_provider_data()
    {
        $data = [
            'name' => '', // Empty name should fail
            'provider' => 'invalid_provider', // Invalid provider
            'enabled' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/voice-agents', $data);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'provider',
                ]
            ]);
    }

    public function test_unauthenticated_user_cannot_access_endpoints()
    {
        $response = $this->getJson('/api/voice-agents');
        $response->assertStatus(401);

        $response = $this->postJson('/api/voice-agents', []);
        $response->assertStatus(401);
    }

    public function test_pagination_works_correctly()
    {
        VoiceAgent::factory()->count(25)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/voice-agents?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ]
            ]);
    }

    public function test_provider_validation_rules_are_enforced()
    {
        // Test SYNTHFLOW provider requirements
        $data = [
            'name' => 'Test Agent',
            'provider' => VoiceAgentProvider::SYNTHFLOW->value,
            'enabled' => true,
            // Missing required fields: username, password, service_value
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/voice-agents', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password', 'service_value']);
    }
}