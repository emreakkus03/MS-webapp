<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Broadcasting\PrivateChannel; // 👈 1. Importeer dit!

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
        // 👈 2. Zet 'broadcast' erbij!
        return ['database', 'broadcast']; 
    }

    // 👈 3. DIT IS NIEUW: HET UNIEKE KANAAL
    public function broadcastOn(): array
    {
        // We sturen dit naar het unieke kanaal van de gebruiker (team).
        // Standaard Laravel conventie: App.Models.User.{id}
        // Omdat in jouw app team_id = user_id:
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