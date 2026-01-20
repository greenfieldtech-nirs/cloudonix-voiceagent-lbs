<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoint for Docker
Route::get('/health', function () {
    try {
        // Test database connection
        DB::connection()->getPdo();

        // Test Redis connection if configured
        if (config('cache.default') === 'redis') {
            Cache::store('redis')->get('health_check_test');
        }

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => 'connected',
                'cache' => config('cache.default') === 'redis' ? 'connected' : 'file',
            ],
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'timestamp' => now()->toISOString(),
            'error' => $e->getMessage(),
        ], 500);
    }
});
