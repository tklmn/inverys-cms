<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plugin extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'version',
        'description',
        'author',
        'is_enabled',
        'installed_at',
        'enabled_at',
        'settings',
        'order',
        'is_system',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'is_system' => 'boolean',
        'settings' => 'array',
        'installed_at' => 'datetime',
        'enabled_at' => 'datetime',
    ];
}
