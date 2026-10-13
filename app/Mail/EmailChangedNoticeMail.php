<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To the OLD address: notice with "This wasn't me" (App\Support\EmailChange) */
class EmailChangedNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $oldEmail,
        public string $newEmail,
        public string $url,
        public int $days,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('translation.email_change.notice_subject', ['app' => config('app.name')]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.email-changed-notice');
    }
}
