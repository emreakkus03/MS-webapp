<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use App\Models\LeaveRequest;

class LeaveApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public LeaveRequest $leaveRequest;

    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(env('MAIL_FROM_ADDRESS'), 'MS Infra Verlof'),
            subject: '✅ Verlof goedgekeurd: ' . $this->leaveRequest->member_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.leave.approved',
        );
    }
}