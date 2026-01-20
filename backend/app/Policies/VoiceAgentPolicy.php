<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoiceAgent;
use Illuminate\Auth\Access\Response;

class VoiceAgentPolicy
{
    /**
     * Determine whether the user can view any voice agents.
     *
     * Users can view voice agents within their tenant.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the voice agent.
     *
     * Users can only view voice agents within their own tenant.
     */
    public function view(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->tenant_id === $voiceAgent->tenant_id;
    }

    /**
     * Determine whether the user can create voice agents.
     *
     * Users can create voice agents within their tenant.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the voice agent.
     *
     * Users can only update voice agents within their own tenant.
     */
    public function update(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->tenant_id === $voiceAgent->tenant_id;
    }

    /**
     * Determine whether the user can toggle the voice agent status.
     *
     * Users can only toggle status of voice agents within their own tenant.
     */
    public function toggle(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->tenant_id === $voiceAgent->tenant_id;
    }

    /**
     * Determine whether the user can validate the voice agent configuration.
     *
     * Users can only validate voice agents within their own tenant.
     */
    public function validateConfig(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->tenant_id === $voiceAgent->tenant_id;
    }

    /**
     * Determine whether the user can delete the voice agent.
     *
     * Users can only delete voice agents within their own tenant.
     * Additional checks should be performed in the controller (e.g., not used in groups).
     */
    public function delete(User $user, VoiceAgent $voiceAgent): bool
    {
        return $user->tenant_id === $voiceAgent->tenant_id;
    }

    /**
     * Determine whether the user can restore the voice agent.
     *
     * Soft delete restoration is not currently implemented.
     */
    public function restore(User $user, VoiceAgent $voiceAgent): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the voice agent.
     *
     * Hard delete is not currently implemented.
     */
    public function forceDelete(User $user, VoiceAgent $voiceAgent): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view provider information.
     *
     * All authenticated users can view provider information.
     */
    public function viewProviders(User $user): bool
    {
        return $user->tenant_id !== null;
    }
}
