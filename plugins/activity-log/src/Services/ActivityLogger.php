<?php

namespace Plugins\ActivityLog\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Plugins\ActivityLog\Models\ActivityLog;

class ActivityLogger
{
    /**
     * Log an activity. $user is an optional authenticated user (a core
     * App\Models\User / Authenticatable); typed mixed so the plugin stays
     * decoupled from the core model. Falls back to the current auth user.
     */
    public static function log(string $action, ?Model $subject = null, array $properties = [], mixed $user = null): void
    {
        // Safety check: don't log if the table doesn't exist yet
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        // Prevent recursive logging of ActivityLog model changes
        if ($subject instanceof ActivityLog) {
            return;
        }

        $user = $user ?? Auth::user();

        $data = [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => null,
            'properties' => ! empty($properties) ? $properties : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ];

        // Get subject label from trait method or fallback
        if ($subject) {
            if (method_exists($subject, 'getActivityLogLabel')) {
                $data['subject_label'] = $subject->getActivityLogLabel();
            } else {
                $data['subject_label'] = $subject->title ?? $subject->name ?? $subject->slug ?? (string) $subject->getKey();
            }
        }

        try {
            ActivityLog::create($data);
        } catch (\Throwable $e) {
            // Silently fail — logging should never break the application
            report($e);
        }
    }
}
