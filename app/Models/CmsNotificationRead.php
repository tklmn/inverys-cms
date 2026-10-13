<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsNotificationRead extends Model
{
    public $timestamps = false;

    protected $table = 'cms_notification_reads';

    protected $fillable = ['notification_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function notification()
    {
        return $this->belongsTo(CmsNotification::class, 'notification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
