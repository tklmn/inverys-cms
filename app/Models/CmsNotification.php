<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CmsNotification extends Model
{
    protected $table = 'cms_notifications';

    protected $fillable = ['type', 'title', 'body', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function reads()
    {
        return $this->hasMany(CmsNotificationRead::class, 'notification_id');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfTypes(Builder $query, array $types): Builder
    {
        return $query->whereIn('type', $types);
    }

    public function isReadBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
