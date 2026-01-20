<?php

namespace Database\Factories;

use App\Enums\DistributionStrategy;
use App\Models\AgentGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentGroupFactory extends Factory
{
    protected $model = AgentGroup::class;

    public function definition(): array
    {
        $strategy = fake()->randomElement(DistributionStrategy::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional(0.7)->sentence(),
            'strategy' => $strategy,
            'settings' => $this->generateStrategySettings($strategy),
            'enabled' => fake()->boolean(90), // 90% chance of being enabled
        ];
    }

    private function generateStrategySettings(DistributionStrategy $strategy): array
    {
        return match ($strategy) {
            DistributionStrategy::LOAD_BALANCED => [
                'window_hours' => fake()->randomElement([1, 6, 12, 24, 48, 72]),
                'max_calls_per_agent' => fake()->optional(0.3)->randomElement([10, 25, 50, 100]),
            ],
            DistributionStrategy::PRIORITY => [
                'failover_enabled' => fake()->boolean(95), // Usually enabled
                'round_robin_same_priority' => fake()->boolean(70),
            ],
            DistributionStrategy::ROUND_ROBIN => [
                'reset_on_agent_change' => fake()->boolean(80),
                'weighted_by_capacity' => fake()->boolean(30),
            ],
        };
    }

    public function loadBalanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => DistributionStrategy::LOAD_BALANCED,
            'settings' => $this->generateStrategySettings(DistributionStrategy::LOAD_BALANCED),
        ]);
    }

    public function priority(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => DistributionStrategy::PRIORITY,
            'settings' => $this->generateStrategySettings(DistributionStrategy::PRIORITY),
        ]);
    }

    public function roundRobin(): static
    {
        return $this->state(fn (array $attributes) => [
            'strategy' => DistributionStrategy::ROUND_ROBIN,
            'settings' => $this->generateStrategySettings(DistributionStrategy::ROUND_ROBIN),
        ]);
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

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
