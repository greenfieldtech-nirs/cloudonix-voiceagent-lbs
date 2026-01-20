<?php

namespace App\Enums;

/**
 * Agent Group Distribution Strategy Enum
 *
 * Defines the available distribution strategies for routing calls within agent groups.
 * Each strategy determines how calls are distributed among available agents.
 */
enum DistributionStrategy: string
{
    case LOAD_BALANCED = 'load_balanced';
    case PRIORITY = 'priority';
    case ROUND_ROBIN = 'round_robin';

    /**
     * Get all supported strategies as an array
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get display name for the strategy
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::LOAD_BALANCED => 'Load Balanced',
            self::PRIORITY => 'Priority',
            self::ROUND_ROBIN => 'Round Robin',
        };
    }

    /**
     * Get description for the strategy
     */
    public function getDescription(): string
    {
        return match($this) {
            self::LOAD_BALANCED => 'Distributes calls based on agent load over the last 24 hours. Agents with fewer calls receive priority.',
            self::PRIORITY => 'Routes calls to the highest priority agent first, with failover to lower priority agents.',
            self::ROUND_ROBIN => 'Rotates calls evenly among all available agents in the group.',
        };
    }

    /**
     * Check if strategy requires ordering of agents
     */
    public function requiresOrdering(): bool
    {
        return match($this) {
            self::PRIORITY => true,
            default => false,
        };
    }

    /**
     * Get default settings for the strategy
     */
    public function getDefaultSettings(): array
    {
        return match($this) {
            self::LOAD_BALANCED => [
                'window_hours' => 24,
                'max_calls_per_agent' => null, // unlimited
            ],
            self::PRIORITY => [
                'failover_enabled' => true,
                'round_robin_same_priority' => true,
            ],
            self::ROUND_ROBIN => [
                'reset_on_agent_change' => true,
                'weighted_by_capacity' => false,
            ],
        };
    }

    /**
     * Validate strategy-specific settings
     */
    public function validateSettings(array $settings): array
    {
        $errors = [];

        switch ($this) {
            case self::LOAD_BALANCED:
                if (isset($settings['window_hours']) && (!is_int($settings['window_hours']) || $settings['window_hours'] < 1 || $settings['window_hours'] > 168)) {
                    $errors[] = 'Window hours must be an integer between 1 and 168';
                }
                if (isset($settings['max_calls_per_agent']) && $settings['max_calls_per_agent'] !== null && (!is_int($settings['max_calls_per_agent']) || $settings['max_calls_per_agent'] < 1)) {
                    $errors[] = 'Max calls per agent must be null or a positive integer';
                }
                break;

            case self::PRIORITY:
                // Settings validation for priority strategy
                break;

            case self::ROUND_ROBIN:
                // Settings validation for round robin strategy
                break;
        }

        return $errors;
    }
}