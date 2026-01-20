<?php

namespace Tests\Feature;

use App\Enums\VoiceAgentProvider;
use App\Http\Requests\StoreVoiceAgentRequest;
use App\Http\Requests\UpdateVoiceAgentRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceAgentRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_request_validation_requires_name()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'provider' => VoiceAgentProvider::VAPI->value,
            'service_value' => 'https://api.vapi.ai',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_store_request_validation_requires_unique_name_per_tenant()
    {
        $this->actingAs($this->user);

        // Create an existing agent with same name in same tenant
        \App\Models\VoiceAgent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Agent',
        ]);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Existing Agent',
            'provider' => VoiceAgentProvider::VAPI->value,
            'service_value' => 'https://api.vapi.ai',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_store_request_validation_allows_same_name_in_different_tenant()
    {
        $this->actingAs($this->user);

        // Create an existing agent with same name in different tenant
        $otherTenant = Tenant::factory()->create();
        \App\Models\VoiceAgent::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Existing Agent',
        ]);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Existing Agent',
            'provider' => VoiceAgentProvider::VAPI->value,
            'service_value' => 'https://api.vapi.ai',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_store_request_validation_requires_valid_provider()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Test Agent',
            'provider' => 'invalid_provider',
            'service_value' => 'https://api.example.com',
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('provider', $validator->errors()->toArray());
    }

    public function test_store_request_validation_requires_service_value()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Test Agent',
            'provider' => VoiceAgentProvider::VAPI->value,
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('service_value', $validator->errors()->toArray());
    }

    public function test_store_request_validation_requires_username_for_auth_providers()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Test Agent',
            'provider' => VoiceAgentProvider::SYNTHFLOW->value,
            'service_value' => 'https://api.synthflow.ai',
            // Missing username
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('username', $validator->errors()->toArray());
    }

    public function test_store_request_validation_requires_password_for_synthyflow()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Test Agent',
            'provider' => VoiceAgentProvider::SYNTHFLOW->value,
            'service_value' => 'https://api.synthflow.ai',
            'username' => 'test@example.com',
            // Missing password
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_store_request_validation_accepts_metadata_array()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => 'Test Agent',
            'provider' => VoiceAgentProvider::VAPI->value,
            'service_value' => 'https://api.vapi.ai',
            'username' => 'test@example.com',
            'metadata' => ['key' => 'value', 'nested' => ['array' => true]],
        ]);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse($validator->fails());
    }





    public function test_store_request_custom_validation_messages()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'name' => '',
            'provider' => 'invalid',
        ]);

        $validator = validator($request->all(), $request->rules(), $request->messages());

        $this->assertEquals(
            'Voice agent name is required.',
            $validator->errors()->first('name')
        );

        $this->assertEquals(
            'Selected provider is not supported.',
            $validator->errors()->first('provider')
        );
    }

    public function test_store_request_custom_attributes()
    {
        $this->actingAs($this->user);

        $request = new StoreVoiceAgentRequest();
        $request->merge([
            'provider' => VoiceAgentProvider::SYNTHFLOW->value,
            'username' => '',
        ]);

        $attributes = $request->attributes();

        $this->assertEquals('API Key', $attributes['username']);
        $this->assertEquals('Synthflow endpoint URL', $attributes['service_value']);
    }


}