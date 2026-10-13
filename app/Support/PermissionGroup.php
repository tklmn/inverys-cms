<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Grouping of permissions by their subject.
 *
 * The second part of the name (explode('-', $name)[1]) would produce the
 * group "access" for `api-access` and "search" for `manage-search-settings`.
 * Instead, the leading verb or the trailing suffix is stripped here and
 * the remaining subject is mapped to a group.
 */
final class PermissionGroup
{
    /** Verbs that permission names can start with. */
    private const VERBS = [
        'show', 'create', 'edit', 'delete', 'manage', 'access',
        'clear', 'download', 'draft', 'publish', 'schedule', 'restore', 'install',
    ];

    /** Suffixes that do not denote a subject of their own. */
    private const SUFFIXES = ['settings', 'access'];

    /** Subjects that belong to a shared group. */
    private const ALIASES = [
        'page' => 'pages',
        'page-category' => 'pages',
        'content' => 'pages',
        'blocks' => 'pages',
        'media' => 'media',
        'user' => 'users',
        'role' => 'roles',
        'permission' => 'permissions',
        'settings' => 'settings',
        'search-settings' => 'settings',
        'security-settings' => 'settings',
        'seo-settings' => 'settings',
        'notification-settings' => 'settings',
        'api' => 'settings',
        'logs' => 'system',
        'system' => 'system',
        'trash' => 'system',
        'notifications' => 'system',
        'themes' => 'extensions',
        'theme-files' => 'extensions',
        'plugins' => 'extensions',
        'marketplace' => 'extensions',
        'dashboard' => 'dashboard',
        // Plugins
        'post' => 'blog',
        'blog-category' => 'blog',
        'blog' => 'blog',
        'form' => 'forms',
        'form-submission' => 'forms',
        'gallery-album' => 'gallery',
        'multilingual' => 'settings',
    ];

    /** Icon per group; unknown groups (from plugins) get the default icon. */
    private const ICONS = [
        'dashboard' => 'layout-dashboard',
        'pages' => 'file-text',
        'media' => 'image',
        'users' => 'users',
        'roles' => 'shield',
        'permissions' => 'key-round',
        'settings' => 'settings',
        'system' => 'server',
        'extensions' => 'puzzle',
        // Plugins
        'blog' => 'newspaper',
        'forms' => 'clipboard-list',
        'gallery' => 'images',
    ];

    public static function of(string $permission): string
    {
        $subject = $permission;

        foreach (self::VERBS as $verb) {
            if (str_starts_with($subject, $verb.'-')) {
                $subject = substr($subject, strlen($verb) + 1);
                break;
            }
        }

        if ($subject === $permission) {
            // No leading verb — then perhaps the ending carries no meaning
            foreach (self::SUFFIXES as $suffix) {
                if (str_ends_with($subject, '-'.$suffix)) {
                    $subject = substr($subject, 0, -(strlen($suffix) + 1));
                    break;
                }
            }
        }

        return self::ALIASES[$subject] ?? $subject;
    }

    /**
     * Readable label of a permission in the interface language:
     * edit-page → "Edit pages", blog-settings →
     * "Manage blog settings". Verbs and subjects live in
     * translation.permission_verbs / translation.permission_subjects;
     * unknown subjects (third-party plugins) appear as the plain-text name.
     */
    public static function permissionLabel(string $permission): string
    {
        [$verb, $subject] = self::split($permission);

        if ($verb === null) {
            return Str::headline($permission);
        }

        $subjectKey = 'translation.permission_subjects.'.$subject;
        $subjectLabel = __($subjectKey);
        if ($subjectLabel === $subjectKey) {
            $subjectLabel = __(Str::of($subject)->replace('-', ' ')->toString());
        }

        return __('translation.permission_label', [
            'verb' => __('translation.permission_verbs.'.$verb),
            'subject' => $subjectLabel,
        ]);
    }

    /**
     * Verb and subject of a permission name.
     * edit-page → [edit, page]; api-access → [access, api]; blog-settings → [manage, blog-settings]
     *
     * @return array{0: ?string, 1: string}
     */
    private static function split(string $permission): array
    {
        foreach (self::VERBS as $verb) {
            if (str_starts_with($permission, $verb.'-')) {
                return [$verb, substr($permission, strlen($verb) + 1)];
            }
        }

        if (str_ends_with($permission, '-settings')) {
            return ['manage', $permission];
        }

        if (str_ends_with($permission, '-access')) {
            return ['access', substr($permission, 0, -strlen('-access'))];
        }

        return [null, $permission];
    }

    public static function label(string $group): string
    {
        $key = 'translation.permission_groups.'.$group;
        $translated = __($key);

        return $translated === $key ? Str::headline($group) : $translated;
    }

    public static function icon(string $group): string
    {
        return self::ICONS[$group] ?? 'plug';
    }

    /**
     * Bundle permissions by group, sorted.
     *
     * @param  iterable<object{name: string}>  $permissions
     * @return Collection<string, Collection<int, object>>
     */
    public static function group(iterable $permissions)
    {
        return collect($permissions)
            ->groupBy(fn ($permission) => self::of($permission->name))
            ->sortKeys();
    }
}
