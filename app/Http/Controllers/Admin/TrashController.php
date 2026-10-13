<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

#[Middleware('permission:manage-trash')]
class TrashController extends Controller
{
    /**
     * Permission per trash type. manage-trash alone is not enough: otherwise
     * an editor could restore or permanently remove deleted users (admins too),
     * roles, permissions, plugins and themes. Plugin tabs bring their
     * permission via the 'permission' key.
     */
    private const TYPE_PERMISSIONS = [
        'page' => 'delete-page',
        'user' => 'delete-user',
        'media' => 'delete-media',
        'role' => 'delete-role',
        'permission' => 'delete-permission',
        'plugin' => 'manage-plugins',
        'theme' => 'manage-themes',
    ];

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'pages');

        $pages = $tab === 'pages' && $this->mayManage('page') ? Page::onlyTrashed()->with('parent')->orderByDesc('deleted_at')->get() : collect();
        $users = $tab === 'users' && $this->mayManage('user') ? User::onlyTrashed()->orderByDesc('deleted_at')->get() : collect();
        $media = $tab === 'media' && $this->mayManage('media') ? Media::onlyTrashed()->orderByDesc('deleted_at')->get() : collect();
        $roles = $tab === 'roles' && $this->mayManage('role') ? Role::onlyTrashed()->orderByDesc('deleted_at')->get() : collect();
        $permissions = $tab === 'permissions' && $this->mayManage('permission') ? Permission::onlyTrashed()->orderByDesc('deleted_at')->get() : collect();
        $plugins = $tab === 'plugins' && $this->mayManage('plugin') ? Plugin::onlyTrashed()->orderByDesc('deleted_at')->get() : collect();

        // Trashed themes = groups of soft-deleted theme.{slug}.* settings
        if ($tab === 'themes' && $this->mayManage('theme')) {
            $themeManager = app(ThemeManager::class);
            $allThemes = collect($themeManager->all())->keyBy('slug');

            $trashedThemes = Setting::onlyTrashed()
                ->where('key', 'like', 'theme.%')
                ->get()
                ->groupBy(function ($setting) {
                    return explode('.', $setting->key)[1] ?? 'unknown';
                })
                ->map(function ($settings, $slug) use ($allThemes) {
                    $theme = $allThemes->get($slug);
                    // Exclude the _trashed marker from settings count
                    $realSettings = $settings->reject(fn ($s) => str_ends_with($s->key, '._trashed'));

                    return (object) [
                        'slug' => $slug,
                        'name' => __($theme['name'] ?? ucfirst($slug)),
                        'deleted_at' => $settings->max('deleted_at'),
                        'settings_count' => $realSettings->count(),
                    ];
                })
                ->values();
        } else {
            $trashedThemes = collect();
        }

        // Plugin-registered trash tabs (e.g. blog posts)
        $pluginTabs = array_values(array_filter($this->pluginTabs(), fn ($t) => $this->mayManage($t['type'])));
        $pluginTrash = [];
        foreach ($pluginTabs as $tab_def) {
            $pluginTrash[] = array_merge($tab_def, [
                'items' => $tab === $tab_def['type'].'s' ? $tab_def['model']::onlyTrashed()->orderByDesc('deleted_at')->get() : collect(),
            ]);
        }

        // Only the active tab is loaded — all are counted, so the tabs
        // show where something is
        $counts = [
            'pages' => Page::onlyTrashed()->count(),
            'users' => User::onlyTrashed()->count(),
            'media' => Media::onlyTrashed()->count(),
            'roles' => Role::onlyTrashed()->count(),
            'permissions' => Permission::onlyTrashed()->count(),
            'plugins' => Plugin::onlyTrashed()->count(),
            'themes' => Setting::onlyTrashed()->where('key', 'like', 'theme.%')->pluck('key')
                ->map(fn ($key) => explode('.', $key)[1] ?? '')->unique()->count(),
        ];
        foreach ($pluginTabs as $tab_def) {
            $counts[$tab_def['type'].'s'] = $tab_def['model']::onlyTrashed()->count();
        }

        // Only tabs whose type the user may manage (the view hides
        // tabs without an entry in $counts)
        $counts = array_filter(
            $counts,
            fn ($key) => $this->mayManage($key === 'media' ? 'media' : substr($key, 0, -1)),
            ARRAY_FILTER_USE_KEY
        );
        abort_if($counts === [], 403);

        return view('backend.trash.index', compact(
            'tab', 'pages', 'users', 'media', 'roles', 'permissions',
            'plugins', 'trashedThemes', 'pluginTrash', 'counts'
        ));
    }

    public function restore(Request $request, string $type, string $id): RedirectResponse
    {
        $this->authorizeType($type, $id);

        if ($type === 'theme') {
            $count = Setting::onlyTrashed()->where('key', 'like', "theme.{$id}.%")->restore();
            if ($count === 0) {
                abort(404);
            }
            // Remove the _trashed marker since theme is restored
            Setting::where('key', "theme.{$id}._trashed")->forceDelete();
            // Invalidate the cached theme settings/regions/css so restored values show immediately
            app(ThemeManager::class)->clearCache($id);

            return redirect()->route('backend.trash.index', ['tab' => 'themes'])->with('success', __('translation.restored_successfully'));
        }

        $model = $this->resolveModel($type, (int) $id);
        if ($denied = $this->denyUserAccount($model)) {
            return $denied;
        }
        $model->restore();

        return redirect()->route('backend.trash.index', ['tab' => $this->tabFor($type)])->with('success', __('translation.restored_successfully'));
    }

    /**
     * Tab of a type — "media" has no plural, otherwise you would land on
     * the empty "medias" tab.
     */
    protected function tabFor(string $type): string
    {
        return $type === 'media' ? 'media' : $type.'s';
    }

    public function forceDelete(Request $request, string $type, string $id): RedirectResponse
    {
        $this->authorizeType($type, $id);

        if ($type === 'theme') {
            $count = Setting::onlyTrashed()->where('key', 'like', "theme.{$id}.%")->forceDelete();
            if ($count === 0) {
                abort(404);
            }

            return redirect()->route('backend.trash.index', ['tab' => 'themes'])->with('success', __('translation.deleted_permanently'));
        }

        $model = $this->resolveModel($type, (int) $id);
        if ($denied = $this->denyUserAccount($model)) {
            return $denied;
        }
        $model->forceDelete();

        return redirect()->route('backend.trash.index', ['tab' => $this->tabFor($type)])->with('success', __('translation.deleted_permanently'));
    }

    /**
     * Deleted users are subject to the same limits as deletion
     * (UserController::destroy): only admins restore or permanently remove
     * admin accounts, and accounts with permissions the actor does not hold
     * cannot be touched at all. Otherwise a non-admin with delete-user
     * could bring back an admin account deleted by an admin.
     */
    protected function denyUserAccount(mixed $model): ?RedirectResponse
    {
        if (! $model instanceof User) {
            return null;
        }

        $actor = auth()->user();
        $back = redirect()->route('backend.trash.index', ['tab' => 'users']);

        if ($model->hasRole('admin') && ! $actor->hasRole('admin')) {
            return $back->with('error', __('translation.users.error.admin_trash'));
        }

        if (! $actor->canGrant($model->permissionNames())) {
            return $back->with('error', __('translation.users.error.more_rights'));
        }

        return null;
    }

    protected function resolveModel(string $type, int $id)
    {
        $coreTypes = [
            'page' => Page::class,
            'user' => User::class,
            'media' => Media::class,
            'role' => Role::class,
            'permission' => Permission::class,
            'plugin' => Plugin::class,
        ];

        if (isset($coreTypes[$type])) {
            return $coreTypes[$type]::onlyTrashed()->findOrFail($id);
        }

        // Plugin-registered types
        foreach ($this->pluginTabs() as $tab) {
            if ($tab['type'] === $type) {
                return $tab['model']::onlyTrashed()->findOrFail($id);
            }
        }

        abort(404);
    }

    /**
     * Unknown type → 404, missing permission for the type → 403.
     */
    protected function authorizeType(string $type, string $id): void
    {
        $known = isset(self::TYPE_PERMISSIONS[$type])
            || collect($this->pluginTabs())->contains('type', $type);

        abort_unless($known, 404);
        abort_unless($this->mayManage($type), 403);

        // Theme IDs are slugs and end up in a LIKE — "%" would otherwise
        // match the settings of all themes
        abort_if($type === 'theme' && ! preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $id), 404);
    }

    protected function mayManage(string $type): bool
    {
        $permission = self::TYPE_PERMISSIONS[$type]
            ?? collect($this->pluginTabs())->firstWhere('type', $type)['permission']
            ?? null;

        // Plugin tabs without their own permission: manage-trash (middleware) suffices
        return $permission === null || auth()->user()->hasPermission($permission);
    }

    protected function pluginTabs(): array
    {
        return app()->bound('plugin.trash_tabs') ? app('plugin.trash_tabs') : [];
    }
}
