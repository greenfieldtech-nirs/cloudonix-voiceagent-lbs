<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CdrController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\VoiceAgentController;
use App\Http\Controllers\Api\VoiceApplicationController;
use App\Http\Controllers\Api\AgentGroupController;
use App\Http\Controllers\Api\AgentGroupMembershipController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ExportController;
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
Route::get('/test', function () {
    return response()->json(['message' => 'API routes are working']);
});
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

    // Voice Agent Management
    Route::get('voice-agents/providers', [VoiceAgentController::class, 'providers']);
    Route::apiResource('voice-agents', VoiceAgentController::class);
    Route::patch('voice-agents/{voice_agent}/toggle', [VoiceAgentController::class, 'toggleStatus']);
    Route::post('voice-agents/{voice_agent}/validate', [VoiceAgentController::class, 'validateConfig']);

    // Agent Group Management
    Route::apiResource('agent-groups', AgentGroupController::class);
    Route::patch('agent-groups/{agent_group}/toggle', [AgentGroupController::class, 'toggleStatus']);
    Route::get('agent-groups/{agent_group}/stats', [AgentGroupController::class, 'stats']);
    Route::post('agent-groups/{agent_group}/reset-strategy', [AgentGroupController::class, 'resetStrategy']);

    // Agent Group Membership Management
    Route::get('agent-groups/{agent_group}/memberships', [AgentGroupMembershipController::class, 'index']);
    Route::post('agent-groups/{agent_group}/memberships', [AgentGroupMembershipController::class, 'store']);
    Route::get('agent-groups/{agent_group}/memberships/{membership}', [AgentGroupMembershipController::class, 'show']);
    Route::put('agent-groups/{agent_group}/memberships/{membership}', [AgentGroupMembershipController::class, 'update']);
    Route::delete('agent-groups/{agent_group}/memberships/{membership}', [AgentGroupMembershipController::class, 'destroy']);
    Route::patch('agent-groups/{agent_group}/memberships/bulk-update', [AgentGroupMembershipController::class, 'bulkUpdate']);
    Route::get('agent-groups/{agent_group}/available-agents', [AgentGroupMembershipController::class, 'availableAgents']);
    Route::patch('agent-groups/{agent_group}/memberships/reorder', [AgentGroupMembershipController::class, 'reorder']);

    // Analytics & Dashboard
    Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('analytics/trends', [AnalyticsController::class, 'trends']);
    Route::get('analytics/agents', [AnalyticsController::class, 'agents']);
    Route::get('analytics/groups', [AnalyticsController::class, 'groups']);
    Route::get('analytics/realtime', [AnalyticsController::class, 'realtime']);
    Route::get('analytics/metrics', [AnalyticsController::class, 'metrics']);
    Route::post('analytics/clear-cache', [AnalyticsController::class, 'clearCache']);

    // Data Export
    Route::post('exports', [ExportController::class, 'request']);
    Route::get('exports/fields', [ExportController::class, 'fields']);
    Route::get('exports/{exportId}/status', [ExportController::class, 'status']);
    Route::get('exports/{exportId}/download', [ExportController::class, 'download']);
    Route::post('exports/cleanup', [ExportController::class, 'cleanup']);
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
