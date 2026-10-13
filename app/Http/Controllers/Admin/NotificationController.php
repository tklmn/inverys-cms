<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsNotification;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

#[Middleware('permission:show-notifications')]
#[Middleware('permission:delete-notifications', only: ['destroy', 'destroyAll'])]
#[Middleware('permission:manage-notification-settings', only: ['settings', 'saveSettings'])]
class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    public function index(): View
    {
        $paginator = $this->service->paginatedFor(auth()->user(), 25);

        $grouped = $paginator->getCollection()->groupBy(
            fn ($n) => $n->created_at->format('Y-m-d')
        );

        return view('backend.notifications.index', [
            'notifications' => $paginator,
            'grouped' => $grouped,
        ]);
    }

    public function data(): JsonResponse
    {
        $items = $this->service->recentFor(auth()->user(), 15);

        return response()->json($items->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'is_read' => $n->is_read,
            'created_at' => $n->created_at->diffForHumans(),
        ]));
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => $this->service->unreadCountFor(auth()->user()),
        ]);
    }

    public function markAsRead(int $id): JsonResponse
    {
        $this->service->markAsRead($id, auth()->user());

        return response()->json(['ok' => true]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $this->service->markAllAsRead(auth()->user());

        return response()->json(['ok' => true]);
    }

    public function destroy(CmsNotification $notification): RedirectResponse
    {
        // Only notifications the user may see (system notifications need their permission)
        $permission = NotificationService::TYPE_PERMISSIONS[$notification->type] ?? 'show-system';
        abort_unless(auth()->user()->hasPermission($permission), 403);

        $notification->delete();

        return redirect()->route('backend.notifications.index')
            ->with('success', __('translation.notifications.deleted'));
    }

    public function destroyAll(): RedirectResponse
    {
        $types = $this->service->getEnabledTypesFor(auth()->user());
        CmsNotification::ofTypes($types)->delete();

        return redirect()->route('backend.notifications.index')
            ->with('success', __('translation.notifications.deleted_all'));
    }

    public static function httpStatusCodes(): array
    {
        return [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            419 => 'Page Expired (CSRF)',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
        ];
    }

    public function settings(): View
    {
        $user = auth()->user();

        // Every type the service knows, so a new type cannot be missing here
        $userSettings = [];
        foreach (array_keys(NotificationService::TYPE_PERMISSIONS) as $type) {
            $userSettings[$type] = $user->getSetting('notification.'.$type, 'on');
        }

        $enabledCodes = array_map('intval', array_filter(explode(',',
            Setting::where('key', 'notification.http_status_codes')->value('value') ?? ''
        )));

        return view('backend.notifications.settings', [
            'userSettings' => $userSettings,
            'allStatusCodes' => self::httpStatusCodes(),
            'enabledCodes' => $enabledCodes,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $user = auth()->user();

        foreach (array_keys(NotificationService::TYPE_PERMISSIONS) as $type) {
            $user->setSetting('notification.'.$type, $request->input($type) === 'on' ? 'on' : 'off');
        }

        if ($user->hasPermission('manage-notification-settings')) {
            $selected = $request->input('http_codes', []);
            $valid = array_keys(self::httpStatusCodes());
            $sanitized = implode(',', array_intersect(array_map('intval', $selected), $valid));
            Setting::updateOrCreate(
                ['key' => 'notification.http_status_codes'],
                ['value' => $sanitized]
            );
        }

        return redirect()->route('backend.notifications.settings')
            ->with('success', __('translation.notifications.settings_saved'));
    }
}
