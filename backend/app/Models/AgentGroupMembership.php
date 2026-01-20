<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentGroupMembership extends Model
{
    protected $table = 'agent_group_memberships';

    protected $fillable = [
        'group_id',
        'agent_id',
        'priority',
        'capacity',
    ];

    protected $casts = [
        'priority' => 'integer',
        'capacity' => 'integer',
    ];

    /**
     * Get the group for this membership
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AgentGroup::class, 'group_id');
    }

    /**
     * Get the agent for this membership
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class, 'agent_id');
    }
}
