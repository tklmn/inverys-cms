<?php

namespace App\Traits;

use App\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Revisions: states before an edit, per language.
 *
 * Revisions in the original language hold the model's fields; revisions of a
 * translation (models with HasTranslations) hold "_locale" and the
 * translated fields of that language. One editing session — same user,
 * same language, within cms.revisions.session_minutes — yields one
 * revision.
 */
trait HasRevisions
{
    /** No automatic revisions while restoring */
    protected bool $revisionsPaused = false;

    public static function bootHasRevisions(): void
    {
        static::updating(function ($model) {
            // Only create revision if relevant fields changed and a user is authenticated
            if (! auth()->check() || $model->revisionsPaused) {
                return;
            }

            $dirty = array_keys($model->getDirty());
            $tracked = $model->getRevisionableFields();
            $changed = array_intersect($dirty, $tracked);

            if (empty($changed)) {
                return;
            }

            // One editing session, one revision: the page builder saves on
            // every change — without this, second-by-second states would fill the
            // list and push out older, useful ones
            if ($model->revisionTakenRecently()) {
                return;
            }

            // Snapshot the current (pre-update) state
            $model->createRevision();
        });

        // Permanently deleted: take the revisions along, otherwise they are left orphaned
        // (with SoftDeletes only on permanent deletion, not when moved to the trash)
        $purge = fn ($model) => $model->revisions()->delete();
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::forceDeleted($purge);
        } else {
            static::deleted($purge);
        }
    }

    public function revisions(): MorphMany
    {
        // Within the same second the id decides — otherwise "the newest" revision is random
        return $this->morphMany(Revision::class, 'revisionable')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Fields to include in revision snapshots.
     */
    public function getRevisionableFields(): array
    {
        return $this->fillable ?? [];
    }

    /**
     * Create a revision snapshot of the current state.
     */
    public function createRevision(?string $note = null): Revision
    {
        $fields = $this->getRevisionableFields();
        $data = [];

        // Only fields the model knows: after create(), columns with a database
        // default are missing, and a null in them would make restoring fail
        $known = $this->getRawOriginal();
        foreach ($fields as $field) {
            if (array_key_exists($field, $known)) {
                $value = $this->getOriginal($field);
                // Date as in the database — as JSON it would become UTC with "Z" and come
                // back shifted if the app uses a different time zone
                $data[$field] = $value instanceof \DateTimeInterface ? $this->fromDateTime($value) : $value;
            }
        }

        return $this->storeRevision($data, $note);
    }

    /**
     * Save the state of a translation (the values before saving).
     *
     * @param  array<string, mixed>  $values
     */
    public function createTranslationRevision(string $locale, array $values, ?string $note = null): Revision
    {
        return $this->storeRevision(['_locale' => $locale] + $values, $note);
    }

    /**
     * Called by HasTranslations::saveTranslations() before saving:
     * saves the language's previous state if anything changes.
     *
     * @param  array<string, mixed>  $values  the new values
     */
    public function snapshotTranslation(string $locale, array $values): void
    {
        if (! auth()->check() || $this->revisionsPaused || ! method_exists($this, 'translationsFor')) {
            return;
        }

        $current = $this->translationsFor($locale);
        // Nothing translated yet: no state anyone would want to return to
        if (array_filter($current, fn ($value) => $value !== null && $value !== '' && $value !== []) === []) {
            return;
        }
        $norm = fn ($value) => is_array($value) ? json_encode($value) : (string) ($value ?? '');

        $changed = false;
        foreach ($values as $field => $value) {
            if ($norm($value) !== $norm($current[$field] ?? null)) {
                $changed = true;
                break;
            }
        }

        if ($changed && ! $this->revisionTakenRecently($locale)) {
            $this->createTranslationRevision($locale, $current);
        }
    }

    /**
     * Has the same user already saved a state of this language in the last
     * few minutes? (config cms.revisions.session_minutes, 0 = every change)
     */
    public function revisionTakenRecently(?string $locale = null): bool
    {
        $minutes = (int) config('cms.revisions.session_minutes', 10);
        if ($minutes <= 0) {
            return false;
        }

        // The latest revision of the same language — other languages do not count
        $latest = $this->revisions()
            ->where('created_at', '>', now()->subMinutes($minutes))
            ->get(['id', 'user_id', 'created_at', 'data'])
            ->first(fn (Revision $revision) => ($revision->data['_locale'] ?? null) === $locale);

        return $latest !== null && (int) $latest->user_id === (int) auth()->id();
    }

    /**
     * Restore the model from a revision snapshot. The current state is
     * always saved first — even in the middle of an editing session.
     */
    public function restoreFromRevision(Revision $revision): void
    {
        $data = $revision->data ?? [];
        $locale = $data['_locale'] ?? null;

        $this->revisionsPaused = true;
        try {
            if (is_string($locale) && $locale !== '' && method_exists($this, 'saveTranslations')) {
                if (auth()->check()) {
                    $this->createTranslationRevision($locale, $this->translationsFor($locale), __('translation.pages.revisions.before_restore'));
                }

                // Clear fields that did not exist back then (= fall back to the original)
                $values = array_fill_keys($this->translatableFields(), null);
                $this->saveTranslations($locale, array_intersect_key($data, $values) + $values);

                return;
            }

            if (auth()->check()) {
                $this->createRevision(__('translation.pages.revisions.before_restore'));
            }

            $this->update(array_intersect_key($data, array_flip($this->getRevisionableFields())));
        } finally {
            $this->revisionsPaused = false;
        }
    }

    private function storeRevision(array $data, ?string $note): Revision
    {
        $revision = $this->revisions()->create([
            'user_id' => auth()->id(),
            'data' => $data,
            'note' => $note,
            'created_at' => now(),
        ]);

        $this->pruneRevisions();

        return $revision;
    }

    /**
     * Enforce the maximum revision limit.
     */
    protected function pruneRevisions(): void
    {
        $max = (int) setting('revisions.max_per_model', 50);
        if ($max <= 0) {
            return;
        }

        $count = $this->revisions()->count();
        if ($count > $max) {
            // Get IDs of oldest revisions to delete (LIMIT+DELETE is unreliable on SQLite).
            // reorder() clears the relation's default orderByDesc, so the oldest are selected.
            $idsToDelete = $this->revisions()
                ->reorder('created_at')
                ->orderBy('id')
                ->limit($count - $max)
                ->pluck('id');

            Revision::whereIn('id', $idsToDelete)->delete();
        }
    }
}
