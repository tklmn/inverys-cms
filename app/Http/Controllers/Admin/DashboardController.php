<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ExtensionRegistrar;
use App\Http\Controllers\Controller;
use App\Models\CmsNotification;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Sbom\Inventory;
use App\Support\SystemHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const VALID_LAYOUTS = ['100', '50-50', '33-33-33', '25-25-25-25', '66-33', '33-66'];

    private const COLUMN_COUNTS = [
        '100' => 1, '50-50' => 2, '33-33-33' => 3, '25-25-25-25' => 4, '66-33' => 2, '33-66' => 2,
    ];

    private const VALID_WIDTHS = [25, 33, 50, 66, 100];

    private function getCoreWidgets(): array
    {
        return [
            'welcome' => [
                'id' => 'welcome',
                'title' => 'translation.dashboard.title',
                'icon' => 'bi-house',
                'view' => 'backend.partials.dashboard.widgets.welcome',
                'permission' => 'access-dashboard',
                'transparent' => true,
            ],
            'stats' => [
                'id' => 'stats',
                'title' => 'translation.dashboard.stats',
                'icon' => 'bi-bar-chart',
                'view' => 'backend.partials.dashboard.widgets.stats',
                'permission' => 'access-dashboard',
                'transparent' => true,
            ],
            'quick-actions' => [
                'id' => 'quick-actions',
                'title' => 'translation.dashboard.quick_actions',
                'icon' => 'bi-lightning',
                'view' => 'backend.partials.dashboard.widgets.quick-actions',
                'permission' => 'access-dashboard',
            ],
            'system-info' => [
                'id' => 'system-info',
                'title' => 'translation.dashboard.system_information',
                'icon' => 'bi-info-circle',
                'view' => 'backend.partials.dashboard.widgets.system-info',
                'permission' => 'show-system',
            ],
            'system-security' => [
                'id' => 'system-security',
                'title' => 'translation.dashboard.system_security',
                'icon' => 'shield-check',
                'view' => 'backend.partials.dashboard.widgets.system-security',
                'permission' => 'show-system',
                // Added later: do not push into already saved layouts,
                // offer it in the widget picker instead
                'opt_in' => true,
            ],
            'recent-pages' => [
                'id' => 'recent-pages',
                'title' => 'translation.dashboard.recent_pages',
                'icon' => 'bi-clock-history',
                'view' => 'backend.partials.dashboard.widgets.recent-pages',
                'permission' => 'show-page',
            ],
            'notifications' => [
                'id' => 'notifications',
                'title' => 'translation.dashboard.notifications_widget',
                'icon' => 'bi-bell',
                'view' => 'backend.partials.dashboard.widgets.notifications',
                'permission' => 'access-dashboard',
            ],
            'media-overview' => [
                'id' => 'media-overview',
                'title' => 'translation.dashboard.media_overview',
                'icon' => 'bi-images',
                'view' => 'backend.partials.dashboard.widgets.media-overview',
                'permission' => 'show-media',
                // Not in the default layout, only in the widget picker
                'default_hidden' => true,
            ],
        ];
    }

    private function getAvailableWidgets(): array
    {
        $user = auth()->user();
        $widgets = $this->getCoreWidgets();

        $registered = app(ExtensionRegistrar::class)->getDashboardWidgets();
        foreach ($registered as $pw) {
            if (($pw['type'] ?? '') === 'widget' && ! empty($pw['view'])) {
                $permission = $pw['permission'] ?? 'access-dashboard';

                // Filter by permission before building the widget so its (potentially
                // expensive) data callable never runs for a user who can't see it.
                if (! $user->hasPermission($permission)) {
                    continue;
                }

                // Stable ID derived from the unique view name, independent of
                // registration order, so saved layouts keep pointing at the right
                // widget when plugins are enabled/disabled or reordered.
                $id = 'plugin-'.($pw['id'] ?? Str::slug($pw['view']));
                $widgets[$id] = [
                    'id' => $id,
                    'title' => $pw['label'] ?? $pw['title'] ?? $id,
                    'icon' => $pw['icon'] ?? 'bi-puzzle',
                    'view' => $pw['view'],
                    'permission' => $permission,
                    'plugin_data' => is_callable($pw['data'] ?? null) ? ($pw['data'])() : ($pw['data'] ?? []),
                ];
            }
        }

        return array_filter($widgets, fn ($w) => $user->hasPermission($w['permission']));
    }

    private function getUserLayout(array $available): array
    {
        $saved = auth()->user()->getSetting('dashboard_layout');
        if ($saved) {
            $decoded = json_decode($saved, true);
            if (! is_array($decoded)) {
                return $this->getDefaultLayout($available);
            }

            if (isset($decoded['rows'])) {
                return $this->parseRowLayout($decoded, $available) + ['saved' => true];
            }

            if (isset($decoded['widgets'])) {
                return $this->migrateOldLayout($decoded, $available) + ['saved' => true];
            }
        }

        return $this->getDefaultLayout($available);
    }

    /**
     * Normalize column items: accept both string IDs and {id, width} objects.
     */
    private function normalizeWidget($item): ?array
    {
        if (is_string($item)) {
            return ['id' => $item, 'width' => 100];
        }
        if (is_array($item) && isset($item['id'])) {
            $width = (int) ($item['width'] ?? 100);
            if (! in_array($width, self::VALID_WIDTHS)) {
                $width = 100;
            }

            return ['id' => $item['id'], 'width' => $width];
        }

        return null;
    }

    private function parseRowLayout(array $decoded, array $available): array
    {
        $rows = [];
        foreach ($decoded['rows'] as $row) {
            if (! isset($row['id'], $row['layout'], $row['columns'])) {
                continue;
            }
            if (! in_array($row['layout'], self::VALID_LAYOUTS)) {
                continue;
            }

            $expectedCols = self::COLUMN_COUNTS[$row['layout']];
            $columns = [];
            foreach (array_slice((array) $row['columns'], 0, $expectedCols) as $col) {
                $normalized = [];
                foreach ((array) $col as $item) {
                    $w = $this->normalizeWidget($item);
                    if ($w && (isset($available[$w['id']]) || str_starts_with($w['id'], 'spacer-'))) {
                        $normalized[] = $w;
                    }
                }
                $columns[] = $normalized;
            }
            while (count($columns) < $expectedCols) {
                $columns[] = [];
            }

            $rows[] = ['id' => $row['id'], 'layout' => $row['layout'], 'columns' => $columns];
        }

        return ['rows' => $rows, 'hidden' => $decoded['hidden'] ?? []];
    }

    private function migrateOldLayout(array $old, array $available): array
    {
        $rows = [];
        $pending = [];

        foreach ($old['widgets'] ?? [] as $w) {
            $id = $w['id'] ?? null;
            if (! $id) {
                continue;
            }
            if (! isset($available[$id]) && ! str_starts_with($id, 'spacer-')) {
                continue;
            }

            $cols = (int) ($w['cols'] ?? 3);

            if ($cols >= 3) {
                if (! empty($pending)) {
                    $rows[] = $this->packPendingWidgets($pending);
                    $pending = [];
                }
                $rows[] = ['id' => 'row-'.uniqid(), 'layout' => '100', 'columns' => [[['id' => $id, 'width' => 100]]]];
            } elseif ($cols === 2) {
                if (! empty($pending)) {
                    $rows[] = ['id' => 'row-'.uniqid(), 'layout' => '66-33', 'columns' => [[['id' => $id, 'width' => 100]], $pending]];
                    $pending = [];
                } else {
                    $rows[] = ['id' => 'row-'.uniqid(), 'layout' => '66-33', 'columns' => [[['id' => $id, 'width' => 100]], []]];
                }
            } else {
                $pending[] = ['id' => $id, 'width' => 100];
            }
        }

        if (! empty($pending)) {
            $rows[] = $this->packPendingWidgets($pending);
        }

        return ['rows' => $rows, 'hidden' => $old['hidden'] ?? []];
    }

    private function packPendingWidgets(array $items): array
    {
        $count = count($items);
        if ($count === 1) {
            return ['id' => 'row-'.uniqid(), 'layout' => '100', 'columns' => [$items]];
        }
        if ($count === 2) {
            return ['id' => 'row-'.uniqid(), 'layout' => '50-50', 'columns' => [[$items[0]], [$items[1]]]];
        }

        return ['id' => 'row-'.uniqid(), 'layout' => '33-33-33', 'columns' => array_map(fn ($i) => [$i], array_slice($items, 0, 3))];
    }

    private function getDefaultLayout(array $available): array
    {
        $pluginIds = array_keys(array_filter($available, fn ($w) => str_starts_with($w['id'], 'plugin-')));

        $rows = [
            ['id' => 'row-'.uniqid(), 'layout' => '100', 'columns' => [[['id' => 'welcome', 'width' => 100]]]],
            ['id' => 'row-'.uniqid(), 'layout' => '100', 'columns' => [[['id' => 'stats', 'width' => 100]]]],
        ];

        // One big row: left 66% stacked content, right 33% stacked sidebar
        // Left: activity (plugins) first, then pages, then remaining plugins
        $activityIds = array_filter($pluginIds, fn ($id) => str_contains($id, 'activity') || str_contains($id, 'plugin-0'));
        $otherPluginIds = array_diff($pluginIds, $activityIds);
        $leftCol = [];
        foreach ($activityIds as $pid) {
            $leftCol[] = ['id' => $pid, 'width' => 100];
        }
        $leftCol[] = ['id' => 'recent-pages', 'width' => 100];
        foreach ($otherPluginIds as $pid) {
            $leftCol[] = ['id' => $pid, 'width' => 100];
        }

        $rows[] = [
            'id' => 'row-'.uniqid(),
            'layout' => '66-33',
            'columns' => [
                $leftCol,
                [
                    ['id' => 'quick-actions', 'width' => 100],
                    ['id' => 'system-info', 'width' => 100],
                    ['id' => 'system-security', 'width' => 100],
                    ['id' => 'notifications', 'width' => 100],
                ],
            ],
        ];

        // Widgets added only when needed: in the picker instead of the layout
        $hidden = array_keys(array_filter($available, fn ($w) => ! empty($w['default_hidden'])));

        return ['rows' => $rows, 'hidden' => $hidden];
    }

    public function index(): View
    {
        $available = $this->getAvailableWidgets();
        $layout = $this->getUserLayout($available);
        $rows = $layout['rows'];
        $hiddenIds = $layout['hidden'] ?? [];

        // Collect placed IDs
        $placedIds = $hiddenIds;
        foreach ($rows as $row) {
            foreach ($row['columns'] as $col) {
                foreach ($col as $item) {
                    $placedIds[] = $item['id'];
                }
            }
        }

        // Unplaced widgets
        $unplaced = array_diff(array_keys($available), $placedIds);

        // Users who already saved their layout only get new opt-in widgets
        // offered in the picker — not appended at the bottom unasked
        if (! empty($layout['saved'])) {
            $optIn = array_values(array_filter($unplaced, fn ($id) => ! empty($available[$id]['opt_in'])));
            $hiddenIds = array_merge($hiddenIds, $optIn);
            $unplaced = array_diff($unplaced, $optIn);
        }
        if (! empty($unplaced)) {
            $items = array_map(fn ($id) => ['id' => $id, 'width' => 100], array_values($unplaced));
            $rows[] = ['id' => 'row-'.uniqid(), 'layout' => '100', 'columns' => [$items]];
        }

        $hiddenWidgets = array_filter($available, fn ($w) => in_array($w['id'], $hiddenIds));

        // Widget data
        $pageCount = Page::count();
        $mediaCount = Media::count();
        $userCount = User::count();

        $registered = app(ExtensionRegistrar::class)->getDashboardWidgets();
        $statCards = [];
        $quickActions = [];
        $user = auth()->user();
        foreach ($registered as $pw) {
            if (isset($pw['permission']) && ! $user->hasPermission($pw['permission'])) {
                continue;
            }
            match ($pw['type'] ?? null) {
                'stat' => $statCards[] = array_merge($pw, [
                    'count' => is_callable($pw['count'] ?? null) ? ($pw['count'])() : ($pw['count'] ?? 0),
                ]),
                'quick_action' => $quickActions[] = $pw,
                default => null,
            };
        }

        $recentUsers = User::latest()->take(5)->get();
        $recentPages = Page::latest('updated_at')->take(5)->get();
        $recentMedia = Media::latest()->take(5)->get();
        $notificationService = app(NotificationService::class);
        $unreadNotificationCount = $notificationService->unreadCountFor($user);
        // Show unread notifications first, fill up to 5 with recent
        $enabledTypes = $notificationService->getEnabledTypesFor($user);
        $notifications = collect();
        if (! empty($enabledTypes)) {
            $unread = CmsNotification::ofTypes($enabledTypes)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
                ->latest()->take(5)->get();
            $unread->each(fn ($n) => $n->is_read = false);
            $notifications = $unread;
            if ($notifications->count() < 5) {
                $readIds = $unread->pluck('id');
                $read = CmsNotification::ofTypes($enabledTypes)
                    ->whereNotIn('id', $readIds)
                    ->latest()->take(5 - $notifications->count())->get();
                $read->each(fn ($n) => $n->is_read = true);
                $notifications = $notifications->concat($read);
            }
        }
        $widgetData = compact('pageCount', 'mediaCount', 'userCount', 'recentUsers', 'recentPages', 'recentMedia', 'notifications', 'unreadNotificationCount', 'statCards', 'quickActions');

        // Bill of materials and checks only if the widget is relevant at all
        if (isset($available['system-security'])) {
            $widgetData['systemSecurity'] = [
                'summary' => app(Inventory::class)->summary(),
                'checks' => SystemHealth::checks(),
            ];
        }

        foreach ($available as $w) {
            if (isset($w['plugin_data'])) {
                $widgetData = array_merge($widgetData, $w['plugin_data']);
            }
        }

        return view('backend.dashboard', [
            'availableWidgets' => $available,
            'rows' => $rows,
            'hiddenWidgets' => $hiddenWidgets,
            'widgetData' => $widgetData,
        ]);
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => 'required|array',
            'rows.*.id' => 'required|string|max:50',
            'rows.*.layout' => ['required', 'string', Rule::in(self::VALID_LAYOUTS)],
            'rows.*.columns' => 'required|array',
            'rows.*.columns.*' => 'present|array',
            'rows.*.columns.*.*.id' => 'required|string|max:100',
            'rows.*.columns.*.*.width' => 'required|integer|in:25,33,50,66,100',
            'hidden' => 'present|array',
            'hidden.*' => 'string|max:100',
        ]);

        auth()->user()->setSetting('dashboard_layout', json_encode($request->only('rows', 'hidden')));

        return response()->json(['ok' => true]);
    }

    public function resetLayout(): JsonResponse
    {
        auth()->user()->setSetting('dashboard_layout', '');

        return response()->json(['ok' => true]);
    }
}
