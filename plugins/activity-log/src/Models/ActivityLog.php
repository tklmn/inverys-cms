<?php

namespace Plugins\ActivityLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(config('cms.models.user'));
    }

    // ─── Query scopes ────────────────────────────────────────

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeForSubject(Builder $query, string $type, ?int $id = null): Builder
    {
        $query->where('subject_type', $type);

        if ($id !== null) {
            $query->where('subject_id', $id);
        }

        return $query;
    }

    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    // ─── Change-tracking helpers ─────────────────────────────

    /**
     * Check whether this log entry contains old/new change data.
     */
    public function hasDiff(): bool
    {
        $props = $this->properties;

        return is_array($props)
            && array_key_exists('old', $props)
            && array_key_exists('new', $props);
    }

    /**
     * Get the list of field names that differ between old and new.
     */
    public function getDiffFields(): array
    {
        if (! $this->hasDiff()) {
            return [];
        }

        $old = $this->properties['old'] ?? [];
        $new = $this->properties['new'] ?? [];

        $changed = [];
        foreach ($new as $field => $value) {
            if (! array_key_exists($field, $old) || $old[$field] !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * Tone of the action for x-ui.badge.
     *
     * Returns a tone, not Tailwind colours: colours do not belong in PHP
     * and would not know the backend tokens (light and dark mode).
     */
    public function getActionTone(): string
    {
        return match ($this->action) {
            'created', 'restored', 'enabled', 'login' => 'positive',
            'updated' => 'accent',
            'deleted', 'failed_login' => 'critical',
            'disabled' => 'caution',
            default => 'neutral',
        };
    }

    /**
     * Lucide icon name matching the action.
     */
    public function getActionIcon(): string
    {
        return match ($this->action) {
            'created' => 'circle-plus',
            'updated' => 'pencil',
            'deleted' => 'trash-2',
            'restored' => 'rotate-ccw',
            'login' => 'log-in',
            'logout' => 'log-out',
            'failed_login' => 'shield-alert',
            'enabled' => 'toggle-right',
            'disabled' => 'toggle-left',
            default => 'circle',
        };
    }

    /**
     * Translated label of the action ("Created", "Failed Login").
     */
    public function getActionLabel(): string
    {
        return __('activity-log::activity.actions.'.$this->action);
    }

    /**
     * Title of an entry in lists and headers: what happened, to what.
     *
     * Without the username — that is already in the metadata, so lists
     * would show it twice per row.
     */
    public function getDisplayTitle(): string
    {
        $subject = $this->subject_label ?: $this->getSubjectShortName();

        return $subject !== ''
            ? __('activity-log::activity.entry_title', ['action' => $this->getActionLabel(), 'subject' => $subject])
            : $this->getActionLabel();
    }

    /**
     * Get the short class name of the subject.
     */
    public function getSubjectShortName(): string
    {
        if (! $this->subject_type) {
            return '';
        }

        return static::subjectTypeLabel($this->subject_type);
    }

    /**
     * Translated name of a subject type (filters, lists, detail page).
     * Unknown types — e.g. from third-party plugins — appear as the class name.
     */
    public static function subjectTypeLabel(string $type): string
    {
        $base = class_basename($type);
        $key = 'activity-log::activity.subjects.'.$base;

        return Lang::has($key) ? __($key) : $base;
    }
}
