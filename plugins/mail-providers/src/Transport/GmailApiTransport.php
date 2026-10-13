<?php

namespace Plugins\MailProviders\Transport;

use Illuminate\Support\Facades\Http;
use Plugins\MailProviders\Services\OAuthTokenManager;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

class GmailApiTransport implements TransportInterface
{
    protected OAuthTokenManager $tokenManager;

    public function __construct(OAuthTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $token = $this->tokenManager->getAccessToken('gmail');

        if (empty($token)) {
            throw new TransportException('Gmail API: No valid access token available.');
        }

        $rawMime = $message->toString();
        $encoded = rtrim(strtr(base64_encode($rawMime), '+/', '-_'), '=');

        $response = Http::withToken($token)
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                'raw' => $encoded,
            ]);

        if (! $response->successful()) {
            throw new TransportException('Gmail API error: '.$response->body());
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }
}
