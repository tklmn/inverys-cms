<?php

namespace App\Models;

use App\Support\Redirects;
use Illuminate\Database\Eloquent\Model;

/**
 * An old address that leads to a new one (App\Support\Redirects).
 *
 * @property string $source_path
 * @property ?string $locale
 * @property string $target
 * @property int $status_code
 * @property int $hits
 */
class Redirect extends Model
{
    public const STATUS_CODES = [301, 302];

    protected $fillable = ['source_path', 'locale', 'target', 'status_code', 'is_automatic'];

    protected $casts = [
        'status_code' => 'integer',
        'hits' => 'integer',
        'is_automatic' => 'boolean',
        'last_hit_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
            $redirect->source_path = Redirects::normalizePath($redirect->source_path);
            $redirect->target = Redirects::normalizeTarget($redirect->target);
            $redirect->locale = $redirect->locale ?: null;
        });
    }

    /** Is the target an address on this site (path)? */
    public function isInternal(): bool
    {
        return Redirects::isInternal($this->target);
    }
}
