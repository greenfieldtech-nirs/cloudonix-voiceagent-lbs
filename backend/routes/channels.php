<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Tenant-specific analytics channels
Broadcast::channel('tenant.{tenantId}.analytics', function ($user, $tenantId) {
    return $user->tenants()->where('tenant_id', $tenantId)->exists();
});

Broadcast::channel('tenant.{tenantId}.calls', function ($user, $tenantId) {
    return $user->tenants()->where('tenant_id', $tenantId)->exists();
});

Broadcast::channel('tenant.{tenantId}.agents', function ($user, $tenantId) {
    return $user->tenants()->where('tenant_id', $tenantId)->exists();
});

// Private channels for real-time updates
Broadcast::channel('private-tenant.{tenantId}', function ($user, $tenantId) {
    return $user->tenants()->where('tenant_id', $tenantId)->exists();
});