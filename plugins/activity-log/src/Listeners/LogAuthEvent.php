<?php

namespace Plugins\ActivityLog\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Plugins\ActivityLog\Services\ActivityLogger;

class LogAuthEvent
{
    public function handleLogin(Login $event): void
    {
        ActivityLogger::log('login', $event->user, [], $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            ActivityLogger::log('logout', $event->user, [], $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        ActivityLogger::log('failed_login', null, [
            'email' => $event->credentials['email'] ?? 'unknown',
        ]);
    }
}
