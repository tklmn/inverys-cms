<?php

namespace App\Models;

use App\Services\Cms\EloquentSettingRepository;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model for global application settings (e.g. language, theme, timezone).
 * Used to store and retrieve key-value pairs for system-wide configuration.
 */
class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key', 'value', 'type', 'description',
    ];

    public $timestamps = true;

    protected static function booted(): void
    {
        static::saved(function () {
            EloquentSettingRepository::clearCache();
        });

        static::deleted(function () {
            EloquentSettingRepository::clearCache();
        });
    }
}
