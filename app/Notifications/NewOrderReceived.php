<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Broadcasting\PrivateChannel;

class NewOrderReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via(object $notifiable): array
    {
        // Pas 'broadcast' toe als je pusher/reverb gebruikt, anders alleen 'database'
        return ['database', 'broadcast']; 
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('warehouseman-orders')];
    }

    public function toDatabase(object $notifiable): array
    {
        // Aanname: je relatie in Order model heet 'team' (of user)
        $teamName = $this->order->team->name ?? 'Ploeg ' . $this->order->team_id;

        return [
            'order_id' => $this->order->id,
            'title' => 'Nieuwe Bestelling',
            'message' => "{$teamName} heeft een bestelling geplaatst voor " . $this->order->pickup_date->format('d/m') . ".",
            'url' => route('warehouse.index'), // Klikken gaat naar dashboard
            'type' => 'info'
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $teamName = $this->order->team->name ?? 'Ploeg ' . $this->order->team_id;
        
        return new BroadcastMessage([
            // Dit is wat jouw JS verwacht:
            'message' => "Nieuwe bestelling van {$teamName} (Order #{$this->order->id})",
            'url' => route('warehouse.index'),
        ]);
    }
}