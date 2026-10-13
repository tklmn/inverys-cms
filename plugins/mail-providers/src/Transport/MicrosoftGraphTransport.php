<?php

namespace Plugins\MailProviders\Transport;

use Illuminate\Support\Facades\Http;
use Plugins\MailProviders\Services\OAuthTokenManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class MicrosoftGraphTransport implements TransportInterface
{
    protected OAuthTokenManager $tokenManager;

    public function __construct(OAuthTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $token = $this->tokenManager->getAccessToken('microsoft');

        if (empty($token)) {
            throw new TransportException('Microsoft Graph: No valid access token available.');
        }

        $email = $message instanceof Email ? $message : null;
        if (! $email) {
            throw new TransportException('Microsoft Graph transport requires an Email instance.');
        }

        $payload = $this->buildPayload($email);

        $response = Http::withToken($token)
            ->post('https://graph.microsoft.com/v1.0/me/sendMail', $payload);

        if (! $response->successful()) {
            throw new TransportException('Microsoft Graph API error: '.$response->body());
        }

        $sentMessage = new SentMessage($message, $envelope ?? Envelope::create($message));

        return $sentMessage;
    }

    protected function buildPayload(Email $email): array
    {
        $message = [
            'subject' => $email->getSubject(),
            'body' => [
                'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                'content' => $email->getHtmlBody() ?: $email->getTextBody() ?: '',
            ],
            'toRecipients' => $this->mapAddresses($email->getTo()),
        ];

        if ($cc = $email->getCc()) {
            $message['ccRecipients'] = $this->mapAddresses($cc);
        }

        if ($bcc = $email->getBcc()) {
            $message['bccRecipients'] = $this->mapAddresses($bcc);
        }

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');
            $filename = $attachment->getFilename() ?? 'attachment';

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $filename,
                'contentType' => $headers->getHeaderBody('Content-Type'),
                'contentBytes' => base64_encode($attachment->getBody()),
                'isInline' => str_contains($disposition ?? '', 'inline'),
            ];
        }

        if ($attachments) {
            $message['attachments'] = $attachments;
        }

        return ['message' => $message, 'saveToSentItems' => true];
    }

    /** @param Address[] $addresses */
    protected function mapAddresses(array $addresses): array
    {
        return array_map(fn (Address $addr) => [
            'emailAddress' => [
                'address' => $addr->getAddress(),
                'name' => $addr->getName(),
            ],
        ], $addresses);
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
