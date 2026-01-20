<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Broadcasting\PrivateChannel;

class OrderReady extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        // 👇 HIER ZIT DE FIX: We gebruiken 'App.Models.Team'
        return [new PrivateChannel('App.Models.Team.' . $this->order->team_id)];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'title' => 'Bestelling Klaar! ✅',
            'message' => "Je bestelling #{$this->order->id} staat klaar om afgehaald te worden.",
            'url' => route('shop.history'),
            'type' => 'success'
        ];
    }
    
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => "Je bestelling #{$this->order->id} staat klaar om afgehaald te worden!",
            'url' => route('shop.history'),
        ]);
    }
}