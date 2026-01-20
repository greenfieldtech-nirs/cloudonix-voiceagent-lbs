<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CdrController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\VoiceApplicationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    // User profile management
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::get('/profile/setup-status', [AuthController::class, 'needsSetup']);

    // Settings management
    Route::get('/settings', [AuthController::class, 'getSettings']);
    Route::put('/settings', [AuthController::class, 'updateSettings']);

    // Tenant management (except store which is public)
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
    Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
    Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);

    // User management within tenant context
    Route::get('/tenants/{tenant}/users', [TenantController::class, 'users']);
    Route::post('/tenants/{tenant}/users', [TenantController::class, 'addUser']);
    Route::delete('/tenants/{tenant}/users/{user}', [TenantController::class, 'removeUser']);

    // Call monitoring and statistics
    Route::get('/calls/active', [CallController::class, 'active']);
    Route::get('/calls/statistics', [CallController::class, 'statistics']);

    // CDR (Call Detail Records) management
    Route::get('/cdr', [CdrController::class, 'index']);
    Route::get('/cdr/{id}', [CdrController::class, 'show']);
    Route::get('/cdr/export', [CdrController::class, 'export']);
});

// Voice Application Webhook Endpoints (Public - no authentication required)
// These endpoints receive webhooks from Cloudonix and must be accessible externally
Route::prefix('voice')->group(function () {
    // Initial voice application request (when call is made to application)
    Route::post('/application/{applicationId}', [VoiceApplicationController::class, 'handleApplication']);

    // Session update webhooks (status changes, events)
    Route::post('/session/update', [VoiceApplicationController::class, 'handleSessionUpdate']);

    // CDR (Call Detail Record) callbacks (final call data)
    Route::post('/session/cdr', [VoiceApplicationController::class, 'handleCdrCallback']);
});
