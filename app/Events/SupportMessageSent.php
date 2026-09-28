<?php

namespace App\Events;

use App\Models\SupportMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportMessage $message;

    public function __construct(SupportMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('support-request.' . $this->message->support_request_id);
    }

    public function broadcastAs()
    {
        return 'support.message';
    }

    public function broadcastWith()
    {
        return [
            'support_request_id' => $this->message->support_request_id,
            'message_id' => $this->message->id,
            'sender_id' => $this->message->sender_id,
            'sender_role' => $this->message->sender_role,
            'message' => $this->message->message,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
