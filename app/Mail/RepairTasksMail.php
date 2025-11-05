<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RepairTasksMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection|array $tasks;
    public string $sendTime;
    public string $periodStart;
    public string $periodEnd;

    public function __construct(Collection|array $tasks, string $periodStart, string $periodEnd)
    {
        $this->tasks = $tasks;
        $this->sendTime = now('Europe/Brussels')->format('H:i');
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dagelijkse herstel overzicht',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.repair_tasks',
            with: [
                'tasks' => $this->tasks,
                'sendTime' => $this->sendTime,
                'periodStart' => $this->periodStart,
                'periodEnd' => $this->periodEnd,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
