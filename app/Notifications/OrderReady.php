<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        return ['database']; // Voeg 'broadcast' toe indien gewenst
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'title' => 'Bestelling Klaar! ✅',
            'message' => "Je bestelling #{$this->order->id} staat klaar om afgehaald te worden.",
            'url' => route('shop.history'), // Klikken gaat naar historiek
            'type' => 'success'
        ];
    }
    
   public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            // Dit is wat jouw JS verwacht:
            'message' => "Je bestelling #{$this->order->id} staat klaar om afgehaald te worden!",
            'url' => route('shop.history'),
        ]);
    }
}