<?php

namespace Plugins\FormBuilder\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;

class FormSubmissionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Form $form;

    public FormSubmission $submission;

    public bool $isSenderCopy;

    public function __construct(Form $form, FormSubmission $submission, bool $isSenderCopy = false)
    {
        $this->form = $form;
        $this->submission = $submission;
        $this->isSenderCopy = $isSenderCopy;
    }

    public function envelope(): Envelope
    {
        $settings = $this->form->settings ?? [];

        // Per-form overrides, then global defaults, then system config.
        // Use ?: so an empty per-form value falls through instead of shadowing the global default.
        // Fallback as shown in the form settings: the sender from the
        // mail settings (mail plugin, otherwise .env), finally the app name
        $fromName = ($settings['from_name'] ?? '') ?: (cms()->settings()->get('form_builder.from_name', '') ?: (config('mail.from.name') ?: config('app.name')));
        $fromEmail = ($settings['from_email'] ?? '') ?: (cms()->settings()->get('form_builder.from_email', '') ?: config('mail.from.address'));

        if ($this->isSenderCopy) {
            $subject = __('form-builder::forms.sender_copy_subject', ['form' => $this->form->name]);
        } else {
            $subject = $settings['email_subject'] ?? '';
            if (empty($subject)) {
                $subject = __('form-builder::forms.new_submission').': '.$this->form->name;
            }
        }

        $envelope = new Envelope(
            from: new Address($fromEmail ?: config('mail.from.address'), $fromName ?: config('app.name')),
            subject: $subject,
        );

        // Per-form reply-to (validated)
        $replyTo = $settings['reply_to'] ?? '';
        if (! empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $envelope->replyTo = [new Address($replyTo)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    public function attachments(): array
    {
        // The sender copy goes to any address the visitor entered —
        // without attachments, otherwise the website would send uploaded
        // files to third parties in its own name
        if ($this->isSenderCopy) {
            return [];
        }

        $attachments = [];
        $files = $this->submission->files ?? [];

        foreach ($files as $fieldName => $path) {
            if (! empty($path) && Storage::disk('local')->exists($path)) {
                $attachments[] = Attachment::fromStorageDisk('local', $path)
                    ->as(basename($path));
            }
        }

        return $attachments;
    }

    protected function buildHtml(): string
    {
        // Confirmation to the sender: only a fixed text, never the input —
        // otherwise the form could be used to send any text to other people's
        // addresses (with this website as the sender)
        if ($this->isSenderCopy) {
            $message = trim((string) ($this->form->settings['copy_message'] ?? '')) ?: __('form-builder::forms.sender_copy_text');

            return '<h2>'.e($this->form->name).'</h2>'
                .'<p style="color:#374151;font-family:sans-serif;line-height:1.6;">'.nl2br(e($message)).'</p>'
                .'<p style="margin-top:16px;font-size:12px;color:#6b7280;font-family:sans-serif;">'.e($this->submission->created_at?->format('Y-m-d H:i') ?? '').'</p>';
        }

        $fields = $this->form->getDataFields();
        $data = $this->submission->data ?? [];

        $html = '<h2>'.e($this->form->name).'</h2>';
        $html .= '<table style="width:100%;border-collapse:collapse;font-family:sans-serif;">';

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $label = $field['label'] ?? $name;
            $value = $data[$name] ?? '';

            if (empty($name) || ($field['type'] ?? '') === 'hidden') {
                continue;
            }

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $html .= '<tr style="border-bottom:1px solid #e5e7eb;">';
            $html .= '<td style="padding:8px 12px;font-weight:600;color:#374151;white-space:nowrap;vertical-align:top;">'.e($label).'</td>';
            $html .= '<td style="padding:8px 12px;color:#4b5563;">'.nl2br(e($value)).'</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        $html .= '<p style="margin-top:16px;font-size:12px;color:#9ca3af;">';
        $html .= 'IP: '.e($this->submission->ip_address ?? '').' | ';
        $html .= $this->submission->created_at?->format('Y-m-d H:i:s') ?? '';
        $html .= '</p>';

        return $html;
    }
}
