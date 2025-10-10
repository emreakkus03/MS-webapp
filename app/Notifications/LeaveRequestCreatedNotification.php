<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class LeaveRequestCreatedNotification extends Notification
{
    use Queueable;

    protected $teamName;
    protected $memberName;
    protected $leaveType;

    public function __construct(string $teamName, string $memberName, string $leaveType)
    {
        $this->teamName = $teamName;
        $this->memberName = $memberName;
        $this->leaveType = $leaveType;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        // 🔹 Admins luisteren op dit kanaal
        return [new PrivateChannel('App.Models.Team.admins')];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Nieuwe verlofaanvraag van {$this->memberName} ({$this->teamName}) voor {$this->leaveType}.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => "Nieuwe verlofaanvraag van {$this->memberName} ({$this->teamName}) voor {$this->leaveType}.",
        ]);
    }
}
