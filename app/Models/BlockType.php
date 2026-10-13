<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'icon',
        'is_core',
        'plugin_slug',
        'schema',
    ];

    protected $casts = [
        'is_core' => 'boolean',
        'schema' => 'array',
    ];
}
