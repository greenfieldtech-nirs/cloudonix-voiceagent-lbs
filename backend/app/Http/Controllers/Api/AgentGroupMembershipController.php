<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentGroup;
use App\Models\AgentGroupMembership;
use App\Models\VoiceAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Agent Group Membership Controller
 *
 * Manages agent memberships within groups, including priority and capacity settings.
 * Handles CRUD operations with proper authorization and validation.
 */
class AgentGroupMembershipController extends Controller
{
    /**
     * Get all memberships for a specific group
     */
    public function index(AgentGroup $group): JsonResponse
    {
        Gate::authorize('view', $group);

        $memberships = $group->memberships()
            ->with('voiceAgent')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $memberships->map(function ($membership) {
                return [
                    'id' => $membership->id,
                    'agent_group_id' => $membership->agent_group_id,
                    'voice_agent_id' => $membership->voice_agent_id,
                    'priority' => $membership->priority,
                    'capacity' => $membership->capacity,
                    'created_at' => $membership->created_at,
                    'updated_at' => $membership->updated_at,
                    'voice_agent' => [
                        'id' => $membership->voiceAgent->id,
                        'name' => $membership->voiceAgent->name,
                        'provider' => $membership->voiceAgent->provider->value,
                        'enabled' => $membership->enabled,
                    ],
                ];
            }),
            'meta' => [
                'group_name' => $group->name,
                'strategy' => $group->strategy->value,
                'total_members' => $memberships->count(),
                'active_members' => $memberships->where('voiceAgent.enabled', true)->count(),
            ],
        ]);
    }

    /**
     * Add an agent to a group
     */
    public function store(Request $request, AgentGroup $group): JsonResponse
    {
        Gate::authorize('update', $group);

        $request->validate([
            'voice_agent_id' => 'required|exists:voice_agents,id',
            'priority' => 'integer|min:1|max:100',
            'capacity' => 'nullable|integer|min:1|max:1000',
        ]);

        $voiceAgentId = $request->voice_agent_id;

        // Check if agent is already in the group
        if ($group->memberships()->where('voice_agent_id', $voiceAgentId)->exists()) {
            return response()->json([
                'message' => 'Agent is already a member of this group',
                'errors' => ['voice_agent_id' => ['Agent is already in the group']],
            ], 422);
        }

        // Check if agent belongs to current tenant
        $agent = VoiceAgent::findOrFail($voiceAgentId);
        if ($agent->tenant_id !== $group->tenant_id) {
            return response()->json([
                'message' => 'Agent does not belong to the same tenant as the group',
            ], 403);
        }

        $membership = $group->memberships()->create([
            'voice_agent_id' => $voiceAgentId,
            'priority' => $request->priority ?? 50,
            'capacity' => $request->capacity,
        ]);

        // Reset group strategy state if needed
        $group->resetStrategyState();

        Log::info('Agent added to group', [
            'group_id' => $group->id,
            'agent_id' => $voiceAgentId,
            'priority' => $membership->priority,
            'capacity' => $membership->capacity,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Agent added to group successfully',
            'data' => $membership->load('voiceAgent'),
        ], 201);
    }

    /**
     * Get a specific membership
     */
    public function show(AgentGroup $group, AgentGroupMembership $membership): JsonResponse
    {
        Gate::authorize('view', $group);

        // Ensure membership belongs to the group
        if ($membership->agent_group_id !== $group->id) {
            abort(404);
        }

        return response()->json([
            'data' => $membership->load('voiceAgent', 'agentGroup'),
        ]);
    }

    /**
     * Update a membership
     */
    public function update(Request $request, AgentGroup $group, AgentGroupMembership $membership): JsonResponse
    {
        Gate::authorize('update', $group);

        // Ensure membership belongs to the group
        if ($membership->agent_group_id !== $group->id) {
            abort(404);
        }

        $request->validate([
            'priority' => 'integer|min:1|max:100',
            'capacity' => 'nullable|integer|min:1|max:1000',
        ]);

        $oldPriority = $membership->priority;
        $oldCapacity = $membership->capacity;

        $membership->update([
            'priority' => $request->priority ?? $membership->priority,
            'capacity' => $request->capacity,
        ]);

        // Reset group strategy state if priority/capacity changed
        if ($oldPriority !== $membership->priority || $oldCapacity !== $membership->capacity) {
            $group->resetStrategyState();
        }

        Log::info('Group membership updated', [
            'group_id' => $group->id,
            'membership_id' => $membership->id,
            'agent_id' => $membership->voice_agent_id,
            'old_priority' => $oldPriority,
            'new_priority' => $membership->priority,
            'old_capacity' => $oldCapacity,
            'new_capacity' => $membership->capacity,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Membership updated successfully',
            'data' => $membership->load('voiceAgent'),
        ]);
    }

    /**
     * Remove an agent from a group
     */
    public function destroy(AgentGroup $group, AgentGroupMembership $membership): JsonResponse
    {
        Gate::authorize('update', $group);

        // Ensure membership belongs to the group
        if ($membership->agent_group_id !== $group->id) {
            abort(404);
        }

        $agentId = $membership->voice_agent_id;
        $membership->delete();

        // Reset group strategy state
        $group->resetStrategyState();

        Log::info('Agent removed from group', [
            'group_id' => $group->id,
            'agent_id' => $agentId,
            'membership_id' => $membership->id,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Agent removed from group successfully',
        ]);
    }

    /**
     * Bulk update memberships
     */
    public function bulkUpdate(Request $request, AgentGroup $group): JsonResponse
    {
        Gate::authorize('update', $group);

        $request->validate([
            'memberships' => 'required|array',
            'memberships.*.id' => 'required|exists:agent_group_memberships,id',
            'memberships.*.priority' => 'integer|min:1|max:100',
            'memberships.*.capacity' => 'nullable|integer|min:1|max:1000',
        ]);

        $updatedCount = 0;
        $errors = [];

        foreach ($request->memberships as $membershipData) {
            try {
                $membership = AgentGroupMembership::findOrFail($membershipData['id']);

                // Ensure membership belongs to the group
                if ($membership->agent_group_id !== $group->id) {
                    $errors[] = "Membership {$membershipData['id']} does not belong to this group";
                    continue;
                }

                $membership->update([
                    'priority' => $membershipData['priority'] ?? $membership->priority,
                    'capacity' => $membershipData['capacity'] ?? $membership->capacity,
                ]);

                $updatedCount++;

            } catch (\Exception $e) {
                $errors[] = "Failed to update membership {$membershipData['id']}: {$e->getMessage()}";
            }
        }

        // Reset group strategy state
        $group->resetStrategyState();

        Log::info('Bulk membership update completed', [
            'group_id' => $group->id,
            'updated_count' => $updatedCount,
            'error_count' => count($errors),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => "Bulk update completed: {$updatedCount} updated, " . count($errors) . ' errors',
            'data' => [
                'updated_count' => $updatedCount,
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * Get available agents for adding to the group
     */
    public function availableAgents(AgentGroup $group): JsonResponse
    {
        Gate::authorize('view', $group);

        // Get agents from same tenant that are not already in the group
        $existingAgentIds = $group->memberships()->pluck('voice_agent_id');

        $availableAgents = VoiceAgent::where('tenant_id', $group->tenant_id)
            ->whereNotIn('id', $existingAgentIds)
            ->orderBy('name')
            ->get(['id', 'name', 'provider', 'enabled']);

        return response()->json([
            'data' => $availableAgents->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'provider' => $agent->provider->value,
                    'enabled' => $agent->enabled,
                ];
            }),
            'meta' => [
                'total_available' => $availableAgents->count(),
            ],
        ]);
    }

    /**
     * Reorder memberships by priority
     */
    public function reorder(Request $request, AgentGroup $group): JsonResponse
    {
        Gate::authorize('update', $group);

        $request->validate([
            'memberships' => 'required|array',
            'memberships.*.id' => 'required|exists:agent_group_memberships,id',
            'memberships.*.priority' => 'required|integer|min:1|max:100',
        ]);

        $updatedCount = 0;

        foreach ($request->memberships as $membershipData) {
            $membership = AgentGroupMembership::findOrFail($membershipData['id']);

            // Ensure membership belongs to the group
            if ($membership->agent_group_id !== $group->id) {
                continue;
            }

            $membership->update(['priority' => $membershipData['priority']]);
            $updatedCount++;
        }

        // Reset group strategy state
        $group->resetStrategyState();

        Log::info('Group memberships reordered', [
            'group_id' => $group->id,
            'updated_count' => $updatedCount,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Memberships reordered successfully',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ]);
    }
}
