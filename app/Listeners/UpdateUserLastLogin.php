<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateUserLastLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // Only update the timestamp for user model
        if (get_class($event->user) === 'App\\Models\\User') {
            $event->user->last_login_at = now();
            $event->user->save();
        }
    }
}
