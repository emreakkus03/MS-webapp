<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;
use App\Models\Address;

class TaskNoteAddedNotification extends Notification
{
    use Queueable;

    protected $team;
    protected $address;

    public function __construct(string $team, ?Address $address)
    {
        $this->team = $team;
        $this->address = $address;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        // 🔹 Kanaal voor admin-taken
        return [new PrivateChannel('admin-tasks')];
    }

    public function toDatabase(object $notifiable): array
    {
        $adresString = $this->address
            ? "{$this->address->street} {$this->address->number}, {$this->address->zipcode} {$this->address->city}"
            : "Onbekend adres";

        return [
            'message' => "{$this->team} heeft een notitie gezet bij {$adresString}.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $adresString = $this->address
            ? "{$this->address->street} {$this->address->number}, {$this->address->zipcode} {$this->address->city}"
            : "Onbekend adres";

        return new BroadcastMessage([
            'message' => "{$this->team} heeft een notitie gezet bij {$adresString}.",
        ]);
    }
}
