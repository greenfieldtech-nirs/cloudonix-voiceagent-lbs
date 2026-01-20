<?php

namespace App\Policies;

use App\Models\CallRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Call Record Policy
 *
 * Defines authorization rules for call record access based on tenant membership
 * and user permissions.
 */
class CallRecordPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any call records.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * Determine whether the user can view the call record.
     */
    public function view(User $user, CallRecord $callRecord, Tenant $tenant): bool
    {
        return $callRecord->tenant_id === $tenant->id &&
               $user->tenants()->where('tenant_id', $tenant->id)->exists();
    }

    /**
     * Determine whether the user can export call records.
     */
    public function export(User $user, Tenant $tenant): bool
    {
        // Check if user has export permission for this tenant
        $tenantUser = $user->tenants()->where('tenant_id', $tenant->id)->first();

        if (!$tenantUser) {
            return false;
        }

        // Check tenant-specific permissions (could be stored in pivot table)
        return $tenantUser->pivot->can_export ?? true;
    }

    /**
     * Determine whether the user can manage analytics.
     */
    public function manageAnalytics(User $user, Tenant $tenant): bool
    {
        $tenantUser = $user->tenants()->where('tenant_id', $tenant->id)->first();

        if (!$tenantUser) {
            return false;
        }

        // Check for admin or analytics management permission
        return $tenantUser->pivot->is_admin ?? false;
    }
}