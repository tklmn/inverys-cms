<?php

namespace Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Translations in the ml_translations table — one row per model,
 * language and field.
 *
 * Reading is designed for menus: on first access, all small fields of a
 * language are loaded for a model type in ONE query
 * (titles, slugs, menu titles …). Large fields (content, blocks, form fields)
 * only come when a specific model really needs them — otherwise a menu
 * with 50 pages would drag along 50 page contents.
 */
class DatabaseContentTranslator implements ContentTranslator
{
    public const TABLE = 'ml_translations';

    /**
     * Marker "copied from another language" — a separate row per model
     * and language, value = source language. Not a translatable field,
     * so translationsFor() filters it out.
     */
    public const DRAFT_FIELD = '_copied_from';

    /** Never copied: the address is created from the translated title */
    private const NOT_COPIED = ['slug', self::DRAFT_FIELD];

    /** Fields that are only loaded individually */
    private const HEAVY = ['content', 'blocks', 'fields', 'field_texts', 'description', 'captions', 'regions'];

    /** @var array<string, array<string, array<int, array<string, mixed>>>> type → locale → id → field → value */
    private array $light = [];

    /** @var array<string, mixed> "type|locale|id|field" → value */
    private array $heavy = [];

    private ?bool $available = null;

    /** @var array<string, array<int, list<string>>> type → id → languages with content (hreflang, sitemap) */
    private array $localeMaps = [];

    /** @var array<string, array<int, string>> "type|locale" → id → last saved translation */
    private array $savedAt = [];

    public function enabled(): bool
    {
        return $this->available ??= Schema::hasTable(self::TABLE);
    }

    public function get(Model $model, string $field, string $locale): mixed
    {
        if (! $this->enabled()) {
            return null;
        }

        $type = $model::class;
        $id = (int) $model->getKey();

        if (in_array($field, self::HEAVY, true)) {
            $key = "{$type}|{$locale}|{$id}|{$field}";

            if (! array_key_exists($key, $this->heavy)) {
                $this->heavy[$key] = DB::table(self::TABLE)
                    ->where(['translatable_type' => $type, 'translatable_id' => $id, 'locale' => $locale, 'field' => $field])
                    ->value('value');
            }

            return $this->heavy[$key];
        }

        if (! isset($this->light[$type][$locale])) {
            $this->light[$type][$locale] = [];

            DB::table(self::TABLE)
                ->where('translatable_type', $type)
                ->where('locale', $locale)
                ->whereNotIn('field', self::HEAVY)
                ->get(['translatable_id', 'field', 'value'])
                ->each(function ($row) use ($type, $locale) {
                    $this->light[$type][$locale][(int) $row->translatable_id][$row->field] = $row->value;
                });
        }

        return $this->light[$type][$locale][$id][$field] ?? null;
    }

    public function all(Model $model, string $locale): array
    {
        if (! $this->enabled() || ! $model->exists) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey(), 'locale' => $locale])
            ->pluck('value', 'field')
            ->all();
    }

    public function save(Model $model, string $locale, array $values): void
    {
        if (! $this->enabled() || ! $model->exists) {
            return;
        }

        $base = [
            'translatable_type' => $model::class,
            'translatable_id' => $model->getKey(),
            'locale' => $locale,
        ];
        $now = now();

        foreach ($values as $field => $value) {
            $value = is_array($value) ? json_encode($value) : $value;
            $where = $base + ['field' => $field];

            // Empty means: no translation of its own, fall back to the default language
            if ($value === null || trim((string) $value) === '' || $value === '[]') {
                DB::table(self::TABLE)->where($where)->delete();

                continue;
            }

            // upsert instead of "exists already? then update, otherwise insert": two
            // concurrent requests (autosave + apply) would otherwise
            // collide on the unique index
            DB::table(self::TABLE)->upsert(
                [$where + ['value' => $value, 'created_at' => $now, 'updated_at' => $now]],
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                ['value', 'updated_at'],
            );
        }

        // Discard this request's cache
        unset($this->light[$model::class][$locale]);
        $this->heavy = array_filter($this->heavy, fn ($k) => ! str_starts_with($k, $model::class.'|'.$locale.'|'.$model->getKey().'|'), ARRAY_FILTER_USE_KEY);
        $this->forgetMaps();
    }

    /**
     * Languages in which a model has a translation of its own — for all
     * entries of its type in one query (sitemap, hreflang).
     *
     * @return list<string>
     */
    public function availableLocales(Model $model): array
    {
        if (! $this->enabled() || ! $model->exists) {
            return [];
        }

        $type = $model::class;
        $this->localeMaps[$type] ??= $this->localesFor($type);

        return array_values(array_unique($this->localeMaps[$type][(int) $model->getKey()] ?? []));
    }

    /** When the model's translation in this language was last saved — null without one */
    public function lastTranslated(Model $model, string $locale): ?string
    {
        if (! $this->enabled() || ! $model->exists) {
            return null;
        }

        $key = $model::class.'|'.$locale;

        if (! isset($this->savedAt[$key])) {
            $this->savedAt[$key] = DB::table(self::TABLE)
                ->where('translatable_type', $model::class)
                ->where('locale', $locale)
                ->where('field', '!=', self::DRAFT_FIELD)
                ->groupBy('translatable_id')
                ->selectRaw('translatable_id, MAX(updated_at) as saved_at')
                ->pluck('saved_at', 'translatable_id')
                ->mapWithKeys(fn ($date, $id) => [(int) $id => (string) $date])
                ->all();
        }

        return $this->savedAt[$key][(int) $model->getKey()] ?? null;
    }

    private function forgetMaps(): void
    {
        $this->localeMaps = [];
        $this->savedAt = [];
    }

    public function findBySlug(string $modelClass, string $slug, string $locale): ?int
    {
        if (! $this->enabled()) {
            return null;
        }

        $id = DB::table(self::TABLE)
            ->where(['translatable_type' => $modelClass, 'locale' => $locale, 'field' => 'slug', 'value' => $slug])
            ->value('translatable_id');

        return $id !== null ? (int) $id : null;
    }

    public function locales(Model $model): array
    {
        if (! $this->enabled() || ! $model->exists) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey()])
            ->distinct()
            ->pluck('locale')
            ->all();
    }

    public function localesFor(string $modelClass, ?array $ids = null): array
    {
        if (! $this->enabled() || $ids === []) {
            return [];
        }

        $map = [];
        DB::table(self::TABLE)
            ->where('translatable_type', $modelClass)
            ->when($ids !== null, fn ($q) => $q->whereIn('translatable_id', $ids))
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->select('translatable_id', 'locale')
            ->distinct()
            ->get()
            ->each(function ($row) use (&$map) {
                $map[(int) $row->translatable_id][] = $row->locale;
            });

        return $map;
    }

    public function search(string $modelClass, array $fields, string $pattern, array $locales): array
    {
        if (! $this->enabled() || $fields === [] || $locales === []) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where('translatable_type', $modelClass)
            ->whereIn('locale', $locales)
            ->whereIn('field', $fields)
            ->where('value', 'LIKE', $pattern)
            ->distinct()
            ->pluck('translatable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function forget(Model $model): void
    {
        if (! $this->enabled()) {
            return;
        }

        DB::table(self::TABLE)
            ->where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey()])
            ->delete();
        $this->forgetMaps();
    }

    public function copy(Model $model, string $from, string $to): int
    {
        if (! $this->enabled() || ! $model->exists || $from === $to) {
            return 0;
        }

        $copied = $this->copyRows($model::class, (int) $model->getKey(), $from, $to);
        $this->light = [];
        $this->heavy = [];
        $this->forgetMaps();

        return $copied;
    }

    /**
     * Take over a whole language as a template — all content translated
     * in $from. Fields already translated in $to stay unchanged.
     *
     * @return array{items: int, fields: int}
     */
    public function copyLocale(string $from, string $to): array
    {
        if (! $this->enabled() || $from === $to) {
            return ['items' => 0, 'fields' => 0];
        }

        $items = 0;
        $fields = 0;

        $rows = DB::table(self::TABLE)
            ->where('locale', $from)
            ->whereNotIn('field', self::NOT_COPIED)
            ->select('translatable_type', 'translatable_id')
            ->distinct()
            ->orderBy('translatable_type')
            ->orderBy('translatable_id')
            ->get()
            ->groupBy('translatable_type');

        foreach ($rows as $type => $group) {
            // Only entries that still exist (outside the trash)
            foreach (self::liveIds($type, $group->pluck('translatable_id')->map(fn ($id) => (int) $id)->all()) as $id) {
                $copied = $this->copyRows($type, $id, $from, $to);
                if ($copied > 0) {
                    $items++;
                    $fields += $copied;
                }
            }
        }

        $this->light = [];
        $this->heavy = [];
        $this->forgetMaps();

        return ['items' => $items, 'fields' => $fields];
    }

    public function draftOf(Model $model, string $locale): ?string
    {
        if (! $this->enabled() || ! $model->exists) {
            return null;
        }

        $value = DB::table(self::TABLE)
            ->where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey(), 'locale' => $locale, 'field' => self::DRAFT_FIELD])
            ->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function draftsOf(Model $model): array
    {
        if (! $this->enabled() || ! $model->exists) {
            return [];
        }

        return DB::table(self::TABLE)
            ->where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey(), 'field' => self::DRAFT_FIELD])
            ->pluck('value', 'locale')
            ->all();
    }

    public function draftsFor(string $modelClass): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $map = [];
        DB::table(self::TABLE)
            ->where(['translatable_type' => $modelClass, 'field' => self::DRAFT_FIELD])
            ->get(['translatable_id', 'locale'])
            ->each(function ($row) use (&$map) {
                $map[(int) $row->translatable_id][] = $row->locale;
            });

        return $map;
    }

    public function clearDraft(Model $model, string $locale): void
    {
        if (! $this->enabled() || ! $model->exists) {
            return;
        }

        DB::table(self::TABLE)
            ->where(['translatable_type' => $model::class, 'translatable_id' => $model->getKey(), 'locale' => $locale, 'field' => self::DRAFT_FIELD])
            ->delete();
    }

    /** Copied versions not yet saved, per language. @return array<string, int> */
    public function draftCounts(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        // Only entries that can be opened — nothing in the trash counts
        $counts = [];
        DB::table(self::TABLE)
            ->where('field', self::DRAFT_FIELD)
            ->get(['translatable_type', 'translatable_id', 'locale'])
            ->groupBy('translatable_type')
            ->each(function ($group, $type) use (&$counts) {
                $live = array_flip(self::liveIds($type, $group->pluck('translatable_id')->map(fn ($id) => (int) $id)->all()));
                foreach ($group as $row) {
                    if (isset($live[(int) $row->translatable_id])) {
                        $counts[$row->locale] = ($counts[$row->locale] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    /**
     * Which of these IDs still exist as a model (trash excluded)?
     * Unknown model types (plugin removed) return nothing.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function liveIds(string $type, array $ids): array
    {
        if ($ids === [] || ! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return [];
        }

        return $type::query()->whereKey($ids)->pluck((new $type)->getKeyName())->map(fn ($id) => (int) $id)->all();
    }

    private static function hasEditor(string $type): bool
    {
        return class_exists($type) && in_array(HasTranslations::class, class_uses_recursive($type), true);
    }

    /** Copy an entry's fields from $from to $to where $to is still empty; marks the draft. */
    private function copyRows(string $type, int $id, string $from, string $to): int
    {
        $where = ['translatable_type' => $type, 'translatable_id' => $id];

        $source = DB::table(self::TABLE)
            ->where($where + ['locale' => $from])
            ->whereNotIn('field', self::NOT_COPIED)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('value', 'field');

        $existing = DB::table(self::TABLE)
            ->where($where + ['locale' => $to])
            ->pluck('field')
            ->flip();

        $now = now();
        $rows = [];
        foreach ($source as $field => $value) {
            if (! $existing->has($field)) {
                $rows[] = $where + ['locale' => $to, 'field' => $field, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if ($rows === []) {
            return 0;
        }

        // insertOrIgnore: if someone has translated the field in the meantime
        // (concurrent request), their version stays — no failure on the index
        return DB::transaction(function () use ($rows, $where, $type, $from, $to, $now) {
            $inserted = DB::table(self::TABLE)->insertOrIgnore($rows);

            // Only content with an editor (HasTranslations) is marked —
            // settings texts have no list and no "save = reviewed"
            if ($inserted > 0 && self::hasEditor($type)) {
                DB::table(self::TABLE)->insertOrIgnore($where + ['locale' => $to, 'field' => self::DRAFT_FIELD, 'value' => $from, 'created_at' => $now, 'updated_at' => $now]);
            }

            return $inserted;
        });
    }
}
