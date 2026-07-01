<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->notification->user_id);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'type' => $this->notification->type,
            'message' => $this->notification->message,
            'sender_id' => $this->notification->sender_id,
            'recipient_role' => $this->notification->recipient_role,
            'related_request_id' => $this->notification->related_request_id,
            'read_at' => optional($this->notification->read_at)->toDateTimeString(),
            'created_at' => $this->notification->created_at->toDateTimeString(),
        ];
    }
}
