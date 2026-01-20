<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentGroup;
use App\Enums\DistributionStrategy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Agent Group Controller
 *
 * Manages agent groups with their distribution strategies and configurations.
 * Provides CRUD operations and group-specific management features.
 */
class AgentGroupController extends Controller
{
    /**
     * Get all agent groups for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AgentGroup::class);

        $query = AgentGroup::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('memberships');

        // Apply filters
        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }

        if ($request->has('strategy')) {
            $query->where('strategy', $request->strategy);
        }

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $groups = $query->orderBy('name')->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'strategy' => $group->strategy->value,
                    'strategy_display_name' => $group->strategy->getDisplayName(),
                    'enabled' => $group->enabled,
                    'settings' => $group->getMergedSettings(),
                    'can_route' => $group->canRoute(),
                    'memberships_count' => $group->memberships_count,
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                ];
            }),
            'links' => [
                'first' => $groups->url(1),
                'last' => $groups->url($groups->lastPage()),
                'prev' => $groups->previousPageUrl(),
                'next' => $groups->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $groups->currentPage(),
                'from' => $groups->firstItem(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'to' => $groups->lastItem(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * Create a new agent group
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', AgentGroup::class);

        $request->validate([
            'name' => 'required|string|max:255|unique:agent_groups,name,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'description' => 'nullable|string|max:1000',
            'strategy' => 'required|in:' . implode(',', DistributionStrategy::all()),
            'settings' => 'nullable|array',
            'enabled' => 'boolean',
        ]);

        $group = AgentGroup::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'description' => $request->description,
            'strategy' => DistributionStrategy::from($request->strategy),
            'settings' => $request->settings,
            'enabled' => $request->enabled ?? true,
        ]);

        Log::info('Agent group created', [
            'group_id' => $group->id,
            'name' => $group->name,
            'strategy' => $group->strategy->value,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Agent group created successfully',
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'strategy' => $group->strategy->value,
                'strategy_display_name' => $group->strategy->getDisplayName(),
                'enabled' => $group->enabled,
                'settings' => $group->getMergedSettings(),
                'created_at' => $group->created_at,
            ],
        ], 201);
    }

    /**
     * Get a specific agent group
     */
    public function show(AgentGroup $group): JsonResponse
    {
        Gate::authorize('view', $group);

        return response()->json([
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'strategy' => $group->strategy->value,
                'strategy_display_name' => $group->strategy->getDisplayName(),
                'strategy_description' => $group->strategy->getDescription(),
                'enabled' => $group->enabled,
                'settings' => $group->getMergedSettings(),
                'can_route' => $group->canRoute(),
                'memberships_count' => $group->memberships()->count(),
                'active_agents_count' => $group->getActiveAgentCount(),
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ],
        ]);
    }

    /**
     * Update an agent group
     */
    public function update(Request $request, AgentGroup $group): JsonResponse
    {
        Gate::authorize('update', $group);

        $request->validate([
            'name' => 'string|max:255|unique:agent_groups,name,' . $group->id . ',id,tenant_id,' . auth()->user()->tenant_id,
            'description' => 'nullable|string|max:1000',
            'strategy' => 'in:' . implode(',', DistributionStrategy::all()),
            'settings' => 'nullable|array',
            'enabled' => 'boolean',
        ]);

        $oldStrategy = $group->strategy->value;

        $group->update([
            'name' => $request->name ?? $group->name,
            'description' => $request->description,
            'strategy' => isset($request->strategy) ? DistributionStrategy::from($request->strategy) : $group->strategy,
            'settings' => $request->settings ?? $group->settings,
            'enabled' => $request->enabled ?? $group->enabled,
        ]);

        // Reset strategy state if strategy changed
        if ($oldStrategy !== $group->strategy->value) {
            $group->resetStrategyState();
        }

        Log::info('Agent group updated', [
            'group_id' => $group->id,
            'name' => $group->name,
            'old_strategy' => $oldStrategy,
            'new_strategy' => $group->strategy->value,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Agent group updated successfully',
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'strategy' => $group->strategy->value,
                'strategy_display_name' => $group->strategy->getDisplayName(),
                'enabled' => $group->enabled,
                'settings' => $group->getMergedSettings(),
            ],
        ]);
    }

    /**
     * Delete an agent group
     */
    public function destroy(AgentGroup $group): JsonResponse
    {
        Gate::authorize('delete', $group);

        // Check if group has memberships
        if ($group->memberships()->exists()) {
            return response()->json([
                'message' => 'Cannot delete group that has agent memberships',
                'memberships_count' => $group->memberships()->count(),
            ], 422);
        }

        $groupName = $group->name;
        $group->delete();

        Log::info('Agent group deleted', [
            'group_id' => $group->id,
            'name' => $groupName,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Agent group deleted successfully',
        ]);
    }

    /**
     * Toggle group enabled status
     */
    public function toggleStatus(AgentGroup $group): JsonResponse
    {
        Gate::authorize('update', $group);

        $group->update(['enabled' => !$group->enabled]);

        $status = $group->enabled ? 'enabled' : 'disabled';

        Log::info("Agent group {$status}", [
            'group_id' => $group->id,
            'name' => $group->name,
            'enabled' => $group->enabled,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => "Agent group {$status} successfully",
            'data' => [
                'id' => $group->id,
                'enabled' => $group->enabled,
            ],
        ]);
    }

    /**
     * Get group statistics and monitoring data
     */
    public function stats(AgentGroup $group): JsonResponse
    {
        Gate::authorize('view', $group);

        return response()->json([
            'data' => $group->getStrategyStats(),
            'meta' => [
                'group_name' => $group->name,
                'strategy' => $group->strategy->value,
                'enabled' => $group->enabled,
                'can_route' => $group->canRoute(),
                'memberships_count' => $group->memberships()->count(),
                'active_agents_count' => $group->getActiveAgentCount(),
            ],
        ]);
    }

    /**
     * Reset group strategy state
     */
    public function resetStrategy(AgentGroup $group): JsonResponse
    {
        Gate::authorize('update', $group);

        $reset = $group->resetStrategyState();

        Log::info('Group strategy state reset', [
            'group_id' => $group->id,
            'name' => $group->name,
            'reset_successful' => $reset,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => $reset ? 'Strategy state reset successfully' : 'Strategy does not support state reset',
            'data' => [
                'reset_successful' => $reset,
            ],
        ]);
    }
}
