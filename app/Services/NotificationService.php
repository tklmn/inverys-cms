<?php

namespace App\Services;

use App\Models\CmsNotification;
use App\Models\CmsNotificationRead;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function create(string $type, string $title, ?string $body = null, array $data = []): CmsNotification
    {
        return CmsNotification::create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);
    }

    public function unreadCountFor(User $user): int
    {
        $types = $this->getEnabledTypesFor($user);

        if (empty($types)) {
            return 0;
        }

        return CmsNotification::ofTypes($types)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();
    }

    public function recentFor(User $user, int $limit = 15): Collection
    {
        $types = $this->getEnabledTypesFor($user);

        if (empty($types)) {
            return collect();
        }

        $notifications = CmsNotification::ofTypes($types)
            ->latest()
            ->limit($limit)
            ->get();

        $readIds = CmsNotificationRead::where('user_id', $user->id)
            ->whereIn('notification_id', $notifications->pluck('id'))
            ->pluck('notification_id')
            ->flip();

        return $notifications->map(function ($n) use ($readIds) {
            $n->is_read = $readIds->has($n->id);

            return $n;
        });
    }

    public function paginatedFor(User $user, int $perPage = 25)
    {
        $types = $this->getEnabledTypesFor($user);

        if (empty($types)) {
            return CmsNotification::whereRaw('1 = 0')->paginate($perPage);
        }

        $paginator = CmsNotification::ofTypes($types)
            ->latest()
            ->paginate($perPage);

        $readIds = CmsNotificationRead::where('user_id', $user->id)
            ->whereIn('notification_id', $paginator->pluck('id'))
            ->pluck('notification_id')
            ->flip();

        $paginator->getCollection()->transform(function ($n) use ($readIds) {
            $n->is_read = $readIds->has($n->id);

            return $n;
        });

        return $paginator;
    }

    public function markAsRead(int $notificationId, User $user): void
    {
        CmsNotificationRead::updateOrCreate(
            ['notification_id' => $notificationId, 'user_id' => $user->id],
            ['read_at' => now()]
        );
    }

    public function markAllAsRead(User $user): void
    {
        $types = $this->getEnabledTypesFor($user);

        $unreadIds = CmsNotification::ofTypes($types)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $rows = $unreadIds->map(fn ($id) => [
            'notification_id' => $id,
            'user_id' => $user->id,
            'read_at' => now(),
        ])->toArray();

        if (! empty($rows)) {
            // insertOrIgnore avoids a unique-constraint crash on (notification_id, user_id)
            // when concurrent requests mark the same notifications read.
            CmsNotificationRead::insertOrIgnore($rows);
        }
    }

    public function prune(?int $daysOld = null): int
    {
        $daysOld = $daysOld ?? max(7, (int) setting('security.notification_retention_days', '30'));

        return CmsNotification::where('created_at', '<', now()->subDays($daysOld))->delete();
    }

    /**
     * Which permission a notification type requires. All types are system
     * notifications (HTTP errors and failed logins contain IP addresses and
     * emails) — whoever may not see the system does not get them.
     */
    public const TYPE_PERMISSIONS = [
        'log_change' => 'show-logs',
        'laravel_update' => 'show-system',
        'cms_update' => 'manage-updates',
        'http_error' => 'show-system',
        'failed_login' => 'show-system',
    ];

    public function getEnabledTypesFor(User $user): array
    {
        $types = [];

        foreach (self::TYPE_PERMISSIONS as $type => $permission) {
            if ($user->hasPermission($permission) && $user->getSetting('notification.'.$type, 'on') === 'on') {
                $types[] = $type;
            }
        }

        return $types;
    }
}
