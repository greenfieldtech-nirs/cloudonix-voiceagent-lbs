<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundRoutingRule extends Model
{
    protected $fillable = [
        'tenant_id',
        'pattern',
        'target_type',
        'target_id',
        'priority',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Target types for routing
     */
    public const TARGET_TYPES = [
        'agent',
        'group',
    ];

    /**
     * Get the tenant that owns this rule
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the target agent (polymorphic relationship)
     */
    public function targetAgent()
    {
        return $this->belongsTo(VoiceAgent::class, 'target_id');
    }

    /**
     * Get the target group (polymorphic relationship)
     */
    public function targetGroup()
    {
        return $this->belongsTo(AgentGroup::class, 'target_id');
    }

    /**
     * Get the target model based on target_type
     */
    public function getTarget()
    {
        return match($this->target_type) {
            'agent' => $this->targetAgent,
            'group' => $this->targetGroup,
            default => null,
        };
    }

    /**
     * Scope to only enabled rules
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope ordered by priority
     */
    public function scopeOrderedByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if pattern matches a phone number
     */
    public function matchesNumber(string $phoneNumber): bool
    {
        // For now, implement exact match or prefix match
        // Could be extended to support regex patterns
        if (str_starts_with($this->pattern, '+')) {
            // Exact match for full numbers
            return $phoneNumber === $this->pattern;
        } else {
            // Prefix match (e.g., "123" matches "+1234567890")
            return str_starts_with($phoneNumber, '+' . $this->pattern) ||
                   str_starts_with($phoneNumber, $this->pattern);
        }
    }

    /**
     * Get target display name
     */
    public function getTargetDisplayName(): string
    {
        $target = $this->getTarget();
        return $target ? $target->name : 'Unknown';
    }

    /**
     * Get target type display name
     */
    public function getTargetTypeDisplayName(): string
    {
        return match($this->target_type) {
            'agent' => 'Voice Agent',
            'group' => 'Agent Group',
            default => ucfirst($this->target_type),
        };
    }
}
