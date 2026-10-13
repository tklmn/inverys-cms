<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Revision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'user_id',
        'data',
        'note',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
