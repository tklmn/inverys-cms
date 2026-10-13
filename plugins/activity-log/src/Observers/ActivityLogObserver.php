<?php

namespace Plugins\ActivityLog\Observers;

use Illuminate\Database\Eloquent\Model;
use Plugins\ActivityLog\Services\ActivityLogger;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        if (! $this->shouldLog($model, 'created')) {
            return;
        }

        $attributes = $this->filterAttributes($model, $model->getAttributes());

        ActivityLogger::log('created', $model, [
            'attributes' => $attributes,
        ]);
    }

    public function updated(Model $model): void
    {
        if (! $this->shouldLog($model, 'updated')) {
            return;
        }

        $dirty = $model->getDirty();
        $ignoredFields = $this->getIgnoredFields($model);

        // Filter out ignored fields
        $changedFields = array_diff_key($dirty, array_flip($ignoredFields));

        // Skip if no meaningful fields changed
        if (empty($changedFields)) {
            return;
        }

        $old = [];
        $new = [];

        foreach ($changedFields as $field => $newValue) {
            $old[$field] = $model->getOriginal($field);
            $new[$field] = $newValue;
        }

        $old = $this->maskSecrets($model, $old);
        $new = $this->maskSecrets($model, $new);

        ActivityLogger::log('updated', $model, [
            'old' => $old,
            'new' => $new,
        ]);
    }

    public function deleted(Model $model): void
    {
        if (! $this->shouldLog($model, 'deleted')) {
            return;
        }

        ActivityLogger::log('deleted', $model);
    }

    public function restored(Model $model): void
    {
        if (! $this->shouldLog($model, 'restored')) {
            return;
        }

        ActivityLogger::log('restored', $model);
    }

    /**
     * Check whether this event should be logged for the given model.
     */
    protected function shouldLog(Model $model, string $event): bool
    {
        // Don't log during installation or CLI seeders
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        // Don't log if no user is authenticated (installer, seeders, etc.)
        if (! auth()->check()) {
            return false;
        }

        if (method_exists($model, 'getActivityLogEvents')) {
            return in_array($event, $model->getActivityLogEvents(), true);
        }

        return true;
    }

    /**
     * Get ignored fields for the model.
     */
    protected function getIgnoredFields(Model $model): array
    {
        if (method_exists($model, 'getActivityLogIgnoredFields')) {
            return $model->getActivityLogIgnoredFields();
        }

        return ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    /**
     * Filter out ignored fields from an attribute array.
     */
    protected function filterAttributes(Model $model, array $attributes): array
    {
        $ignoredFields = $this->getIgnoredFields($model);

        return $this->maskSecrets($model, array_diff_key($attributes, array_flip($ignoredFields)));
    }

    /**
     * Do not write secret settings (captcha secrets, SMTP password, OAuth tokens …)
     * to the log in plain text — anyone with show-activity-log can read the log,
     * not only those who manage the settings.
     */
    protected function maskSecrets(Model $model, array $values): array
    {
        if (! array_key_exists('value', $values) || ! $model->getAttribute('key')) {
            return $values;
        }

        $isSecret = $model->getAttribute('type') === 'encrypted'
            || preg_match('/(secret|password|token|\.key$)/i', (string) $model->getAttribute('key'));

        if ($isSecret) {
            $values['value'] = '********';
        }

        return $values;
    }
}
