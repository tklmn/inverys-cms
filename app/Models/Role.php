<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Role Model
 *
 * Represents user roles in the application with associated permissions.
 * System roles (is_system=true) cannot be deleted.
 */
class Role extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Roles the code addresses by name: hasRole('admin'), registration
     * ('user') and plugins that grant their permissions to the 'editor' role
     * on activation (assignToRole). Whoever names a role like this would get
     * those permissions — so only admins assign and change these names.
     */
    public const CODE_NAMES = ['admin', 'editor', 'user'];

    public static function isCodeName(?string $name): bool
    {
        return in_array(Str::lower(trim((string) $name)), self::CODE_NAMES, true);
    }

    /**
     * Role with exactly this name. Don't compare via SQL alone: MySQL and
     * MariaDB (utf8mb4_unicode_ci) ignore case and accents —
     * where('name', 'editor') would otherwise also match a role 'Éditor',
     * and plugins would grant it editor permissions on activation.
     */
    public static function named(string $name): ?self
    {
        return static::where('name', $name)->get()
            ->first(fn (self $role) => $role->name === $name);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'is_system'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Name for the UI: translated for the bundled roles
     * (translation.roles.names.admin …), otherwise the name itself in words.
     * The technical name stays unchanged — the code checks it.
     */
    public function getLabelAttribute(): string
    {
        $key = 'translation.roles.names.'.$this->name;

        return __($key) !== $key ? __($key) : Str::headline($this->name);
    }

    /**
     * Get the permissions associated with this role.
     *
     * @return BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Get the users who have this role.
     *
     * @return BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role');
    }
}
