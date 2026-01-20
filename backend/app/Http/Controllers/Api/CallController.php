<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    /**
     * Get active calls for the authenticated tenant
     *
     * Active calls are those with status 'ringing' or 'connected'
     */
    public function active(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $activeCalls = CallSession::where('tenant_id', $tenant->id)
            ->whereIn('status', ['ringing', 'connected'])
            ->orderBy('webhook_modified_at', 'desc')
            ->get()
            ->map(function ($call) {
                return [
                    'id' => $call->id,
                    'session_id' => $call->session_id,
                    'domain' => $call->domain,
                    'caller_id' => $call->caller_id,
                    'destination' => $call->destination,
                    'direction' => $call->direction,
                    'status' => $call->status,
                    'duration_seconds' => $this->calculateDuration($call),
                    'call_start_time' => $call->call_start_time,
                    'created_at' => $call->webhook_created_at ?? $call->created_at,
                ];
            });

        return response()->json([
            'data' => $activeCalls,
            'meta' => [
                'total' => $activeCalls->count(),
                'active_count' => $activeCalls->count(),
            ],
        ]);
    }

    /**
     * Get call statistics for dashboard
     */
    public function statistics(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $today = now()->toDateString();

        // Active calls count
        $activeCalls = CallSession::where('tenant_id', $tenant->id)
            ->whereIn('status', ['ringing', 'connected'])
            ->count();

        // Today's statistics
        $todayStats = CallSession::where('tenant_id', $tenant->id)
            ->whereDate('webhook_created_at', $today)
            ->selectRaw('
                COUNT(*) as total_today,
                AVG(duration_seconds) as avg_duration_today,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_today,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_today
            ')
            ->first();

        return response()->json([
            'active_calls' => $activeCalls,
            'total_today' => (int) ($todayStats->total_today ?? 0),
            'avg_duration' => round($todayStats->avg_duration_today ?? 0, 1),
            'completed_today' => (int) ($todayStats->completed_today ?? 0),
            'failed_today' => (int) ($todayStats->failed_today ?? 0),
        ]);
    }

    /**
     * Calculate current duration for an active call
     */
    private function calculateDuration(CallSession $call): int
    {
        $startTime = $call->call_start_time ?? $call->webhook_created_at ?? $call->created_at;

        if (! $startTime) {
            return 0;
        }

        return now()->diffInSeconds($startTime);
    }
}
