<?php

namespace App\Policies;

use App\Models\AgentGroup;
use App\Models\User;

/**
 * Agent Group Policy
 *
 * Handles authorization for agent group operations based on tenant ownership.
 */
class AgentGroupPolicy
{
    /**
     * Determine whether the user can view any agent groups.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view groups in their tenant
    }

    /**
     * Determine whether the user can view the agent group.
     */
    public function view(User $user, AgentGroup $agentGroup): bool
    {
        return $user->tenant_id === $agentGroup->tenant_id;
    }

    /**
     * Determine whether the user can create agent groups.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create groups in their tenant
    }

    /**
     * Determine whether the user can update the agent group.
     */
    public function update(User $user, AgentGroup $agentGroup): bool
    {
        return $user->tenant_id === $agentGroup->tenant_id;
    }

    /**
     * Determine whether the user can delete the agent group.
     */
    public function delete(User $user, AgentGroup $agentGroup): bool
    {
        return $user->tenant_id === $agentGroup->tenant_id;
    }

    /**
     * Determine whether the user can view stats for the agent group.
     */
    public function viewStats(User $user, AgentGroup $agentGroup): bool
    {
        return $user->tenant_id === $agentGroup->tenant_id;
    }

    /**
     * Determine whether the user can reset strategy for the agent group.
     */
    public function resetStrategy(User $user, AgentGroup $agentGroup): bool
    {
        return $user->tenant_id === $agentGroup->tenant_id;
    }
}
