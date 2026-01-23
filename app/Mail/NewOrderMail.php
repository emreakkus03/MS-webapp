<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address; // 👈 Voor de afzender
use Illuminate\Queue\SerializesModels;
use App\Models\Order; // 👈 Pas aan naar jouw Model naam

class NewOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(env('MAIL_FROM_ADDRESS'), 'MS Infra Bestellingen'),
            subject: '📦 Nieuwe bestelling #' . $this->order->id . ' - ' . ($this->order->team->name ?? 'Onbekend'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.created',
        );
    }
}