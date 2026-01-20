<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundRoutingRule extends Model
{
    protected $fillable = [
        'tenant_id',
        'caller_id',
        'destination_pattern',
        'trunk_config',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'trunk_config' => 'array',
    ];

    /**
     * Get the tenant that owns this rule
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to only enabled rules
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Check if caller ID matches this rule
     */
    public function matchesCallerId(string $callerId): bool
    {
        // For now, implement exact match or prefix match
        if (str_starts_with($this->caller_id, '+')) {
            return $callerId === $this->caller_id;
        } else {
            return str_starts_with($callerId, '+' . $this->caller_id) ||
                   str_starts_with($callerId, $this->caller_id);
        }
    }

    /**
     * Check if destination matches this rule
     */
    public function matchesDestination(string $destination): bool
    {
        // Simple prefix matching for destination
        if (str_starts_with($this->destination_pattern, '+')) {
            return str_starts_with($destination, $this->destination_pattern);
        } else {
            return str_starts_with($destination, '+' . $this->destination_pattern) ||
                   str_starts_with($destination, $this->destination_pattern);
        }
    }

    /**
     * Get trunk configuration for routing
     */
    public function getTrunkConfig(): array
    {
        return $this->trunk_config ?? [];
    }

    /**
     * Get trunk IDs from configuration
     */
    public function getTrunkIds(): array
    {
        return $this->trunk_config['trunk_ids'] ?? [];
    }

    /**
     * Get trunk priority from configuration
     */
    public function getTrunkPriority(): int
    {
        return $this->trunk_config['priority'] ?? 0;
    }
}
