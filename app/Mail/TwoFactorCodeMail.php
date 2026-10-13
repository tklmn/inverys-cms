<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $lifetime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('auth.two_factor.email_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.two-factor-code',
        );
    }
}
