<?php

namespace Plugins\ActivityLog\Traits;

trait LogsActivity
{
    /**
     * Fields to ignore when logging changes.
     */
    public function getActivityLogIgnoredFields(): array
    {
        return ['updated_at', 'created_at', 'deleted_at', 'remember_token', 'password'];
    }

    /**
     * Get a human-readable label for this model in the activity log.
     */
    public function getActivityLogLabel(): string
    {
        return $this->title ?? $this->name ?? $this->slug ?? (string) $this->getKey();
    }

    /**
     * Get the events that should be logged for this model.
     */
    public function getActivityLogEvents(): array
    {
        return ['created', 'updated', 'deleted', 'restored'];
    }
}
