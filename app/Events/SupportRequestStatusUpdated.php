<?php

namespace App\Events;

use App\Models\SupportRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Approved/rejected/closed, broadcast on the request's own thread channel
 * (for the driver's chat screen, if open) and on driver.{id} (the channel
 * already used for ride offers/cancellations) so the driver is notified
 * live even if they're not currently viewing this specific thread.
 */
class SupportRequestStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportRequest $supportRequest;

    public function __construct(SupportRequest $supportRequest)
    {
        $this->supportRequest = $supportRequest;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('support-request.' . $this->supportRequest->id),
            new PrivateChannel('driver.' . $this->supportRequest->driver_id),
        ];
    }

    public function broadcastAs()
    {
        return 'support.request.status';
    }

    public function broadcastWith()
    {
        return [
            'support_request_id' => $this->supportRequest->id,
            'status' => $this->supportRequest->status,
            'rejection_reason' => $this->supportRequest->rejection_reason,
        ];
    }
}
