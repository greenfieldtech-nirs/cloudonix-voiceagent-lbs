<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Isolation Middleware
 *
 * Ensures users can only access resources within their assigned tenants.
 * Implements multi-tenant security at the HTTP layer.
 */
class TenantIsolation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Extract tenant ID from route parameters
        $tenantId = $request->route('tenant');

        if (!$tenantId) {
            // If no tenant in route, check if user has access to any tenant
            if ($user->tenants()->exists()) {
                // User has tenants but route doesn't specify one
                return response()->json(['message' => 'Tenant required'], 400);
            }
            return $next($request);
        }

        // Check if user has access to this tenant
        $hasAccess = $user->tenants()->where('tenant_id', $tenantId)->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Access denied to tenant'], 403);
        }

        // Add tenant to request for easy access in controllers
        $request->merge(['current_tenant_id' => $tenantId]);

        return $next($request);
    }
}