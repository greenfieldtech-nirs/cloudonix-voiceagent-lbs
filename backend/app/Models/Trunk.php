<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trunk Model
 *
 * Represents a Cloudonix trunk configuration for outbound call routing.
 * Trunks define the outbound pathways for calls and their capabilities.
 */
class Trunk extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'cloudonix_trunk_id',
        'description',
        'configuration',
        'priority',
        'capacity',
        'enabled',
        'is_default',
    ];

    protected $casts = [
        'configuration' => 'array',
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'capacity' => 'integer',
    ];

    /**
     * Get the tenant that owns this trunk
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to only enabled trunks
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to default trunks
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope ordered by priority (highest first)
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if trunk has available capacity
     */
    public function hasCapacity(int $currentUsage = 0): bool
    {
        if ($this->capacity <= 0) {
            return true; // Unlimited capacity
        }

        return $currentUsage < $this->capacity;
    }

    /**
     * Get trunk configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
    }

    /**
     * Set trunk configuration value
     */
    public function setConfig(string $key, $value): void
    {
        $config = $this->configuration ?? [];
        $config[$key] = $value;
        $this->configuration = $config;
    }

    /**
     * Get trunk display name
     */
    public function getDisplayName(): string
    {
        return $this->name . ' (' . $this->cloudonix_trunk_id . ')';
    }

    /**
     * Check if trunk is available for routing
     */
    public function isAvailable(): bool
    {
        return $this->enabled;
    }

    /**
     * Get trunk priority for sorting
     */
    public function getRoutingPriority(): int
    {
        return $this->priority;
    }
}