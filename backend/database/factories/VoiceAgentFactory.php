<?php

namespace Database\Factories;

use App\Enums\VoiceAgentProvider;
use App\Models\Tenant;
use App\Models\VoiceAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoiceAgentFactory extends Factory
{
    protected $model = VoiceAgent::class;

    public function definition(): array
    {
        $provider = fake()->randomElement(VoiceAgentProvider::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'provider' => $provider,
            'service_value' => $this->generateServiceValue($provider),
            'username' => $provider->requiresAuthentication() ? fake()->email() : null,
            'password' => $provider->requiresAuthentication() && in_array($provider->value, ['synthflow', 'superdash.ai'])
                ? fake()->password()
                : null,
            'enabled' => fake()->boolean(80), // 80% chance of being enabled
            'metadata' => fake()->boolean(30) ? [
                'description' => fake()->sentence(),
                'tags' => fake()->words(3),
            ] : null,
        ];
    }

    private function generateServiceValue(VoiceAgentProvider $provider): string
    {
        return match ($provider->value) {
            'vapi' => 'https://api.vapi.ai',
            'synthflow' => 'https://api.synthflow.ai',
            'superdash.ai' => 'https://api.superdash.ai',
            'elevenlabs' => 'https://api.elevenlabs.io',
            'neuphonic' => 'https://api.neuphonic.com',
            'blandai' => 'https://api.bland.ai',
            'retellai' => 'https://api.retell.ai',
            'synthesia' => 'https://api.synthesia.io',
            'replicant' => 'https://api.replicant.ai',
            'wellsaid' => 'https://api.wellsaidlabs.com',
            'playht' => 'https://api.play.ht',
            'azure' => 'https://speech.microsoft.com',
            'google' => 'https://speech.googleapis.com',
            'aws' => 'https://polly.us-east-1.amazonaws.com',
            'openai' => 'https://api.openai.com',
            'anthropic' => 'https://api.anthropic.com',
            'cohere' => 'https://api.cohere.ai',
            'together' => 'https://api.together.xyz',
            default => fake()->url(),
        };
    }

    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function forProvider(VoiceAgentProvider $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
            'service_value' => $this->generateServiceValue($provider),
            'username' => $provider->requiresAuthentication() ? fake()->email() : null,
            'password' => $provider->requiresAuthentication() && in_array($provider->value, ['synthflow', 'superdash.ai'])
                ? fake()->password()
                : null,
        ]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}