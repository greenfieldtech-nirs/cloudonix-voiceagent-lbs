<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceAgent;
use App\Policies\VoiceAgentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceAgentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private VoiceAgentPolicy $policy;
    private User $user;
    private User $otherUser;
    private VoiceAgent $voiceAgent;
    private VoiceAgent $otherVoiceAgent;
    private Tenant $tenant;
    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new VoiceAgentPolicy();

        // Create tenants
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        // Create users
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->otherUser = User::factory()->create(['tenant_id' => $this->otherTenant->id]);

        // Create voice agents
        $this->voiceAgent = VoiceAgent::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->otherVoiceAgent = VoiceAgent::factory()->create(['tenant_id' => $this->otherTenant->id]);
    }

    public function test_user_can_view_any_voice_agents_in_their_tenant()
    {
        $this->assertTrue($this->policy->viewAny($this->user));
    }

    public function test_user_can_view_voice_agent_in_their_tenant()
    {
        $this->assertTrue($this->policy->view($this->user, $this->voiceAgent));
    }

    public function test_user_cannot_view_voice_agent_in_other_tenant()
    {
        $this->assertFalse($this->policy->view($this->user, $this->otherVoiceAgent));
    }

    public function test_user_can_create_voice_agents_in_their_tenant()
    {
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_can_update_voice_agent_in_their_tenant()
    {
        $this->assertTrue($this->policy->update($this->user, $this->voiceAgent));
    }

    public function test_user_cannot_update_voice_agent_in_other_tenant()
    {
        $this->assertFalse($this->policy->update($this->user, $this->otherVoiceAgent));
    }

    public function test_user_can_toggle_voice_agent_in_their_tenant()
    {
        $this->assertTrue($this->policy->toggle($this->user, $this->voiceAgent));
    }

    public function test_user_cannot_toggle_voice_agent_in_other_tenant()
    {
        $this->assertFalse($this->policy->toggle($this->user, $this->otherVoiceAgent));
    }

    public function test_user_can_validate_config_voice_agent_in_their_tenant()
    {
        $this->assertTrue($this->policy->validateConfig($this->user, $this->voiceAgent));
    }

    public function test_user_cannot_validate_config_voice_agent_in_other_tenant()
    {
        $this->assertFalse($this->policy->validateConfig($this->user, $this->otherVoiceAgent));
    }

    public function test_user_can_delete_voice_agent_in_their_tenant()
    {
        $this->assertTrue($this->policy->delete($this->user, $this->voiceAgent));
    }

    public function test_user_cannot_delete_voice_agent_in_other_tenant()
    {
        $this->assertFalse($this->policy->delete($this->user, $this->otherVoiceAgent));
    }

    public function test_user_can_view_providers_if_authenticated()
    {
        $this->assertTrue($this->policy->viewProviders($this->user));
        $this->assertTrue($this->policy->viewProviders($this->otherUser));
    }

    public function test_user_without_tenant_cannot_access_voice_agents()
    {
        $userWithoutTenant = User::factory()->create(['tenant_id' => null]);

        $this->assertFalse($this->policy->viewAny($userWithoutTenant));
        $this->assertFalse($this->policy->create($userWithoutTenant));
        $this->assertFalse($this->policy->view($userWithoutTenant, $this->voiceAgent));
        $this->assertFalse($this->policy->update($userWithoutTenant, $this->voiceAgent));
        $this->assertFalse($this->policy->delete($userWithoutTenant, $this->voiceAgent));
        $this->assertFalse($this->policy->viewProviders($userWithoutTenant));
    }

    public function test_restore_and_force_delete_not_allowed()
    {
        $this->assertFalse($this->policy->restore($this->user, $this->voiceAgent));
        $this->assertFalse($this->policy->forceDelete($this->user, $this->voiceAgent));
    }

    public function test_policy_methods_exist_and_are_callable()
    {
        $reflection = new \ReflectionClass(VoiceAgentPolicy::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $expectedMethods = [
            'viewAny', 'view', 'create', 'update', 'toggle',
            'validateConfig', 'delete', 'restore', 'forceDelete', 'viewProviders'
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                collect($methods)->contains(fn($m) => $m->getName() === $method),
                "Method {$method} should exist in VoiceAgentPolicy"
            );
        }
    }
}
