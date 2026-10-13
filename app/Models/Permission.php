<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Permission Model
 *
 * Represents granular permissions in the application that can be
 * assigned to roles to control access to specific features.
 */
class Permission extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Core permissions. The code checks them by name — hence they are
     * locked against renaming and deletion (is_system). Plugins mark
     * their own via PermissionManager::createPermission().
     */
    public const CORE = [
        // Content permissions
        'show-content', 'create-content', 'edit-content', 'delete-content',

        // User permissions
        'show-user', 'create-user', 'edit-user', 'delete-user',

        // Role permissions
        'show-role', 'create-role', 'edit-role', 'delete-role',

        // Permission management
        'show-permission', 'create-permission', 'edit-permission', 'delete-permission',

        // Settings permissions
        'show-settings', 'create-settings', 'edit-settings', 'delete-settings',

        // Page permissions
        'show-page', 'create-page', 'edit-page', 'delete-page',

        // Page category permissions
        'show-page-category', 'create-page-category', 'edit-page-category', 'delete-page-category',

        // Log permissions
        'show-logs', 'download-logs', 'clear-logs',

        // Media permissions
        'show-media', 'create-media', 'edit-media', 'delete-media',

        // System permissions
        'show-system',

        // Theme permissions
        'manage-themes',
        'edit-theme-files',

        // Plugin permissions
        'manage-plugins',

        // Updates of the CMS, plugins and themes
        'manage-updates',

        // Block permissions
        'manage-blocks',

        // API access
        'api-access',

        // Trash management
        'manage-trash',

        // Search settings
        'manage-search-settings',

        // Security settings
        'manage-security-settings',

        // SEO settings (sitemap, robots.txt)
        'manage-seo-settings',

        // Notification permissions
        'show-notifications', 'delete-notifications', 'manage-notification-settings',

        // Dashboard access
        'access-dashboard',
    ];

    /**
     * Get the roles that have this permission.
     *
     * @return BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
