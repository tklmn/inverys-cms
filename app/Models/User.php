<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Locales;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected ?array $cachedPermissions = null;

    protected ?array $cachedRoleNames = null;

    protected array $cachedSettings = [];

    /**
     * A deleted account loses all existing access: sessions, "remember me"
     * and API tokens. Otherwise they would come back to life after a restore
     * from the trash, and a session of the deleted account would persist
     * with its data (e.g. the 2FA state).
     * The same on restore — for accounts deleted before this rule
     * existed.
     */
    protected static function booted(): void
    {
        $endAccess = function (User $user): void {
            $user->revokeApiTokens();
            $user->endOtherSessions();
        };

        static::softDeleted($endAccess);
        static::restored($endAccess);
    }

    /**
     * Language for mails and notifications to this user — Laravel then
     * sends them in that language automatically. Installed languages
     * only; otherwise the site's default language.
     */
    public function preferredLocale(): string
    {
        $locales = app(Locales::class);
        $language = $this->getSetting('language');

        return $locales->has($language) ? $language : $locales->default();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'bio',
        'is_active',
        'avatar',
        'password',
        'two_factor_method',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the roles assigned to this user.
     *
     * @return BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * Check if the user has a specific role.
     * Loads all role names once per request and caches them.
     */
    public function hasRole(string $roleName): bool
    {
        if ($this->cachedRoleNames === null) {
            $this->cachedRoleNames = $this->roles()->pluck('name')->all();
        }

        return in_array($roleName, $this->cachedRoleNames, true);
    }

    /**
     * Clear the cached role names and permissions.
     * Call this after assigning or revoking roles.
     */
    public function clearRoleCache(): void
    {
        $this->cachedRoleNames = null;
        $this->cachedPermissions = null;
    }

    /**
     * Check if the user has a specific permission through any of their roles.
     * Loads all permissions once per request and caches them.
     */
    public function hasPermission(string $permissionName): bool
    {
        return isset($this->loadPermissions()[$permissionName]);
    }

    /** All permissions from all roles. @return list<string> */
    public function permissionNames(): array
    {
        return array_keys($this->loadPermissions());
    }

    /** @return array<string, int> permission name => index */
    private function loadPermissions(): array
    {
        if ($this->cachedPermissions === null) {
            $permissions = $this->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->unique()
                ->all();
            $this->cachedPermissions = array_flip($permissions);
        }

        return $this->cachedPermissions;
    }

    /**
     * May this user grant these permissions (via a role to users or to a
     * role)? Admins may grant anything, everyone else only permissions they
     * hold themselves — otherwise edit-user/edit-role would be a path to admin.
     *
     * @param  iterable<string>  $permissionNames
     */
    public function canGrant(iterable $permissionNames): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        foreach ($permissionNames as $name) {
            if (! $this->hasPermission($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }

        return $this->name;
    }

    /**
     * User settings relationship
     */
    public function userSettings()
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Get a setting for the user (user override or global default)
     */
    public function getSetting(string $key, $default = null): mixed
    {
        if (array_key_exists($key, $this->cachedSettings)) {
            return $this->cachedSettings[$key] ?? $default;
        }

        $userSetting = $this->userSettings()->where('key', $key)->first();
        if ($userSetting) {
            return $this->cachedSettings[$key] = $userSetting->value;
        }
        $global = Setting::where('key', $key)->first();
        $this->cachedSettings[$key] = $global?->value;

        return $this->cachedSettings[$key] ?? $default;
    }

    /**
     * Check if the user has TOTP two-factor authentication enabled and confirmed.
     */
    public function hasTotpEnabled(): bool
    {
        return $this->two_factor_method === 'totp' && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Set or update a user-specific setting
     */
    public function setSetting(string $key, $value): void
    {
        UserSetting::updateOrCreate([
            'user_id' => $this->id,
            'key' => $key,
        ], [
            'value' => $value,
        ]);

        // Keep the in-request cache consistent so a subsequent getSetting() in the
        // same request returns the value just written instead of a stale one.
        $this->cachedSettings[$key] = $value;
    }

    /**
     * Remove a personal setting — the site default applies again afterwards
     * (getSetting() falls back to the global setting).
     */
    public function forgetSetting(string $key): void
    {
        $this->userSettings()->where('key', $key)->delete();

        unset($this->cachedSettings[$key]);
    }

    /**
     * Only the user's own values, without falling back to the site.
     * The profile thus shows "site default" instead of a copied value.
     *
     * @return array<string, string|null>
     */
    public function ownSettings(array $keys): array
    {
        $own = $this->userSettings()->whereIn('key', $keys)->pluck('value', 'key')->all();

        return array_map(fn ($key) => $own[$key] ?? null, array_combine($keys, $keys));
    }

    /**
     * End all other logins after a password change: whoever knew the old
     * password must not stay logged in. The current session
     * ($keepSessionId) remains; the "remember me" token is renewed.
     */
    /**
     * Revoke API tokens — after a password was reset or newly set by an
     * admin: whoever had taken over the account must not keep access
     * through a token.
     */
    public function revokeApiTokens(): void
    {
        ApiToken::where('user_id', $this->getKey())->delete();
    }

    public function endOtherSessions(?string $keepSessionId = null): void
    {
        $this->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->getKey())
            ->when($keepSessionId, fn ($query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }
}
