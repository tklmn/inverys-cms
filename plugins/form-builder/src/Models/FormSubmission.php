<?php

namespace Plugins\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FormSubmission extends Model
{
    protected $table = 'form_submissions';

    protected $fillable = [
        'form_id', 'data', 'files', 'ip_address', 'user_agent',
        'is_read', 'is_spam', 'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'files' => 'array',
        'is_read' => 'boolean',
        'is_spam' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Delete any uploaded files when the submission is removed, so personal data
     * does not linger on disk after the record is gone (GDPR erasure).
     */
    protected static function booted(): void
    {
        static::deleted(function (FormSubmission $submission) {
            foreach ((array) $submission->files as $path) {
                if (is_string($path) && $path !== '' && Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
        });
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
