<?php

namespace App\Strategies;

use App\Enums\DistributionStrategy as StrategyEnum;
use App\Models\AgentGroup;
use Illuminate\Support\Facades\Log;

/**
 * Distribution Strategy Factory
 *
 * Factory for creating distribution strategy instances based on the group's strategy.
 * Provides a centralized way to instantiate the appropriate strategy implementation.
 */
class DistributionStrategyFactory
{
    /**
     * Create a distribution strategy instance for the given agent group
     *
     * @param AgentGroup $group
     * @return DistributionStrategy
     */
    public function create(AgentGroup $group): DistributionStrategy
    {
        return match ($group->strategy) {
            StrategyEnum::LOAD_BALANCED => app(LoadBalancedStrategy::class, ['group' => $group]),
            StrategyEnum::PRIORITY => app(PriorityStrategy::class, ['group' => $group]),
            StrategyEnum::ROUND_ROBIN => app(RoundRobinStrategy::class, ['group' => $group]),
            default => throw new \InvalidArgumentException("Unknown strategy: {$group->strategy->value}"),
        };
    }

    /**
     * Get all available strategy classes
     *
     * @return array<string, class-string<DistributionStrategy>>
     */
    public static function getAvailableStrategies(): array
    {
        return [
            StrategyEnum::LOAD_BALANCED->value => LoadBalancedStrategy::class,
            StrategyEnum::PRIORITY->value => PriorityStrategy::class,
            StrategyEnum::ROUND_ROBIN->value => RoundRobinStrategy::class,
        ];
    }

    /**
     * Validate that a strategy class exists and implements the interface
     *
     * @param string $strategyClass
     * @return bool
     */
    public static function validateStrategyClass(string $strategyClass): bool
    {
        return class_exists($strategyClass) &&
               in_array(DistributionStrategy::class, class_implements($strategyClass), true);
    }

    /**
     * Get strategy description for a given enum
     *
     * @param StrategyEnum $strategy
     * @return string
     */
    public static function getStrategyDescription(StrategyEnum $strategy): string
    {
        return $strategy->getDescription();
    }
}