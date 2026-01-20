<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Display a listing of the tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->has('active'), function ($query) use ($request) {
                return $query->where('is_active', $request->boolean('active'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($tenants);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'trial_ends_at' => 'nullable|date',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Ensure slug is unique
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Tenant::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug.'-'.$counter;
            $counter++;
        }

        $tenant = Tenant::create($validated);

        return response()->json($tenant, 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json($tenant->load(['users', 'integrations', 'phoneNumbers']));
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants,domain,'.$tenant->id,
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'trial_ends_at' => 'nullable|date',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);

            // Ensure slug is unique
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Tenant::where('slug', $validated['slug'])->where('id', '!=', $tenant->id)->exists()) {
                $validated['slug'] = $originalSlug.'-'.$counter;
                $counter++;
            }
        }

        $tenant->update($validated);

        return response()->json($tenant);
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        // Soft delete or check for dependencies
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted successfully']);
    }
}
