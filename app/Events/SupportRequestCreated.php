<?php

namespace App\Events;

use App\Models\SupportRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver submitted a new "request to chat" -- pushed to the admin.live
 * channel so anyone with the support-requests permission sees it appear
 * live, without a page refresh. The DB Notification + bell (NotificationEvent)
 * still fires separately via notifyUsers(), same as every other admin alert
 * in this app -- this event is only for live-updating the requests list/badge.
 */
class SupportRequestCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportRequest $supportRequest;

    public function __construct(SupportRequest $supportRequest)
    {
        $this->supportRequest = $supportRequest;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('admin.live');
    }

    public function broadcastAs()
    {
        return 'support.request.created';
    }

    public function broadcastWith()
    {
        return [
            'support_request_id' => $this->supportRequest->id,
            'driver_id' => $this->supportRequest->driver_id,
            'driver_name' => $this->supportRequest->driver->name,
            'subject' => $this->supportRequest->subject,
            'created_at' => $this->supportRequest->created_at->toIso8601String(),
        ];
    }
}
