<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for user-specific settings.
 * Allows users to override global settings with their own preferences.
 */
class UserSetting extends Model
{
    protected $fillable = [
        'user_id', 'key', 'value',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
