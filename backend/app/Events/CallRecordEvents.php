<?php

namespace App\Events;

use App\Models\CallRecord;
use App\Models\Tenant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Call Record Created Event
 *
 * Broadcasts when a new call record is created.
 */
class CallRecordCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CallRecord $callRecord;
    public Tenant $tenant;

    public function __construct(CallRecord $callRecord)
    {
        $this->callRecord = $callRecord;
        $this->tenant = $callRecord->tenant;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenant->id . '.calls'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.created';
    }

    public function broadcastWith(): array
    {
        return [
            'call_record' => $this->callRecord->toArray(),
            'action' => 'created',
            'timestamp' => now()->toISOString(),
        ];
    }
}

/**
 * Call Record Updated Event
 *
 * Broadcasts when a call record is updated (e.g., status change).
 */
class CallRecordUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CallRecord $callRecord;
    public Tenant $tenant;
    public array $changes;

    public function __construct(CallRecord $callRecord, array $changes = [])
    {
        $this->callRecord = $callRecord;
        $this->tenant = $callRecord->tenant;
        $this->changes = $changes;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenant->id . '.calls'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'call_record' => $this->callRecord->toArray(),
            'changes' => $this->changes,
            'action' => 'updated',
            'timestamp' => now()->toISOString(),
        ];
    }
}

/**
 * Agent Status Updated Event
 *
 * Broadcasts when an agent's status changes (online/offline, availability).
 */
class AgentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public \App\Models\VoiceAgent $agent;
    public Tenant $tenant;
    public array $statusData;

    public function __construct(\App\Models\VoiceAgent $agent, array $statusData = [])
    {
        $this->agent = $agent;
        $this->tenant = $agent->tenant;
        $this->statusData = $statusData;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenant->id . '.agents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'agent' => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'enabled' => $this->agent->enabled,
            ],
            'status_data' => $this->statusData,
            'timestamp' => now()->toISOString(),
        ];
    }
}