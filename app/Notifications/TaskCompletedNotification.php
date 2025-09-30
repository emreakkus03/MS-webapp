<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    protected $team;
    protected $task;

    public function __construct(string $team, string $task)
    {
        $this->team = $team;
        $this->task = $task;
    }

    // ✅ Broadcast naar ALLE admins via één kanaal
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.Team.admins')];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "{$this->team} heeft adres '{$this->task}' voltooid.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => "{$this->team} heeft adres '{$this->task}' voltooid.",
        ]);
    }
}
