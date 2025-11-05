<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class LeaveRequestStatusUpdatedNotification extends Notification
{
    use Queueable;

    protected string $status;
    protected string $memberName;
    protected string $leaveType;
    protected int $teamId;

    public function __construct(string $memberName, string $leaveType, string $status, int $teamId)
    {
        $this->memberName = $memberName;
        $this->leaveType = $leaveType;
        $this->status = $status;
        $this->teamId = $teamId;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.Team.' . $this->teamId)];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Je verlofaanvraag voor {$this->leaveType} is {$this->status}.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => "Je verlofaanvraag voor {$this->leaveType} is {$this->status}.",
        ]);
    }
}
