<?php

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Analytics Updated Event
 *
 * Broadcasts when analytics metrics are updated in real-time.
 */
class AnalyticsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Tenant $tenant;
    public array $metrics;
    public string $updateType;

    /**
     * Create a new event instance.
     */
    public function __construct(Tenant $tenant, array $metrics, string $updateType = 'dashboard')
    {
        $this->tenant = $tenant;
        $this->metrics = $metrics;
        $this->updateType = $updateType;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenant->id . '.analytics'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'analytics.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'metrics' => $this->metrics,
            'update_type' => $this->updateType,
            'timestamp' => now()->toISOString(),
        ];
    }
}