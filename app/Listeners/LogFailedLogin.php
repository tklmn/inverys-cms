<?php

namespace App\Listeners;

use App\Services\NotificationService;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';
        $ip = request()->ip();

        $this->notificationService->create(
            type: 'failed_login',
            title: __('translation.notifications.failed_login_title', ['email' => $email]),
            body: __('translation.notifications.failed_login_body', ['ip' => $ip]),
            data: [
                'email' => $email,
                'ip' => $ip,
                'user_agent' => request()->userAgent(),
            ]
        );
    }
}
